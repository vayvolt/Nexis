<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\Permission;
use Nexis\Auth\PdoPermissionLookup;
use Nexis\Auth\RoleSlug;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\SystemRoleSeeder;
use Nexis\Auth\User;
use Nexis\Auth\UserRepository;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Role × permission matrix under /admin/roles (requires users.manage).
 */
final class RoleAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private UserRepository $users,
        private SitePolicy $policy,
        private PdoPermissionLookup $permissions,
        private SystemRoleSeeder $roleSeeder,
        private AuditLogger $audit,
        private AdminUi $ui,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $this->roleSeeder->ensureForSite($site->id);

        $roles = $this->orderedRoles($this->users->listRoles($site->id));
        $grants = [];
        foreach ($roles as $role) {
            $grants[$role['id']] = array_fill_keys($this->permissions->keysForRole($role['id']), true);
        }

        return $this->responses->html($this->views->render('admin.roles.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'roles' => $roles,
            'permissions' => $this->catalogKeys(),
            'grants' => $grants,
            'saved' => RequestInput::query($request, 'saved') === '1',
            'reset' => RequestInput::query($request, 'reset') === '1',
            'error' => RequestInput::query($request, 'error'),
        ], 'admin.layout'));
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $this->roleSeeder->ensureForSite($site->id);

        $action = RequestInput::string($request, 'action', 'save');
        if ($action === 'reset') {
            $this->roleSeeder->resetToTemplates($site->id);
            $this->audit->log('roles.reset', $site->id, $user->id, 'site', $site->id->value, []);

            return $this->responses->redirect($basePath . '/admin/roles?reset=1');
        }

        $roles = $this->orderedRoles($this->users->listRoles($site->id));
        $rolesById = [];
        foreach ($roles as $role) {
            $rolesById[$role['id']] = $role;
        }
        $catalog = array_fill_keys($this->catalogKeys(), true);
        $body = $request->getParsedBody();
        $posted = is_array($body) && is_array($body['perm'] ?? null) ? $body['perm'] : [];

        $next = [];
        foreach ($rolesById as $roleId => $role) {
            if ($role['slug'] === RoleSlug::ADMIN) {
                $next[$roleId] = array_keys($catalog);
                continue;
            }
            $selected = [];
            $row = is_array($posted[$roleId] ?? null) ? $posted[$roleId] : [];
            foreach ($row as $key => $on) {
                if (!is_string($key) || !isset($catalog[$key])) {
                    continue;
                }
                if ((string) $on === '1') {
                    $selected[] = $key;
                }
            }
            $next[$roleId] = $selected;
        }

        $error = $this->assertSafe($user, $site, $rolesById, $next);
        if ($error !== null) {
            return $this->responses->redirect(
                $basePath . '/admin/roles?error=' . rawurlencode($this->ui->get($user, $error)),
            );
        }

        foreach ($next as $roleId => $keys) {
            $this->permissions->setRolePermissions($roleId, $keys);
        }
        $this->audit->log('roles.update', $site->id, $user->id, 'site', $site->id->value, [
            'roles' => array_map(
                static fn (array $role): string => $role['slug'],
                array_values($rolesById),
            ),
        ]);

        return $this->responses->redirect($basePath . '/admin/roles?saved=1');
    }

    /**
     * @param list<array{id: string, name: string, slug: string}> $roles
     * @return list<array{id: string, name: string, slug: string}>
     */
    private function orderedRoles(array $roles): array
    {
        $rank = array_flip(RoleSlug::system());
        usort($roles, static function (array $a, array $b) use ($rank): int {
            $ra = $rank[$a['slug']] ?? 100;
            $rb = $rank[$b['slug']] ?? 100;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp($a['name'], $b['name']);
        });

        return $roles;
    }

    /**
     * @return list<string>
     */
    private function catalogKeys(): array
    {
        $this->permissions->ensurePermissions(Permission::core());
        $all = $this->permissions->allKeys();
        $core = Permission::core();
        $coreSet = array_fill_keys($core, true);
        $rest = [];
        foreach ($all as $key) {
            if (!isset($coreSet[$key])) {
                $rest[] = $key;
            }
        }
        sort($rest, SORT_STRING);

        return array_merge($core, $rest);
    }

    /**
     * @param array<string, array{id: string, name: string, slug: string}> $rolesById
     * @param array<string, list<string>> $next
     */
    private function assertSafe(User $user, Site $site, array $rolesById, array $next): ?string
    {
        $hasUsersManage = false;
        foreach ($next as $keys) {
            if (in_array(Permission::USERS_MANAGE, $keys, true)) {
                $hasUsersManage = true;
                break;
            }
        }
        if (!$hasUsersManage) {
            return 'admin.error.roles_users_manage_required';
        }

        foreach ($rolesById as $roleId => $role) {
            if ($role['slug'] === RoleSlug::ADMIN
                && !in_array(Permission::USERS_MANAGE, $next[$roleId] ?? [], true)
            ) {
                return 'admin.error.roles_admin_locked';
            }
        }

        if ($user->isPlatformAdmin) {
            return null;
        }

        $actorSlug = $this->users->roleSlugFor($user->id, $site->id);
        if ($actorSlug === null) {
            return 'admin.error.roles_self_lockout';
        }
        foreach ($rolesById as $roleId => $role) {
            if ($role['slug'] !== $actorSlug) {
                continue;
            }
            if (!in_array(Permission::USERS_MANAGE, $next[$roleId] ?? [], true)) {
                return 'admin.error.roles_self_lockout';
            }
        }

        return null;
    }

    /**
     * @return array{User, Site, string}|ResponseInterface
     */
    private function context(ServerRequestInterface $request): array|ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }
        $site = $this->sites->installed();
        if ($site === null) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_site'), 503);
        }
        if (!$this->policy->can($user, $site, Permission::USERS_MANAGE)) {
            return $this->responses->html(
                $this->ui->get($user, 'admin.error.no_access_permission', ['permission' => Permission::USERS_MANAGE]),
                403,
            );
        }

        return [$user, $site, $basePath];
    }
}
