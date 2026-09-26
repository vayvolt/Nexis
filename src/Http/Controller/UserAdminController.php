<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\AdminMembershipGuard;
use Nexis\Auth\PasswordHasher;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\SystemRoleSeeder;
use Nexis\Auth\User;
use Nexis\Auth\UserId;
use Nexis\Auth\UserRepository;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\SiteRepository;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private UserRepository $users,
        private SitePolicy $policy,
        private PasswordHasher $passwords,
        private AdminUi $ui,
        private SystemRoleSeeder $roleSeeder,
        private AdminMembershipGuard $adminGuard,
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

        return $this->responses->html($this->views->render('admin.users.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'members' => $this->users->listMembers($site->id),
            'roles' => $this->users->listRoles($site->id),
            'error' => RequestInput::query($request, 'error'),
            'saved' => RequestInput::query($request, 'saved') === '1',
            'canManage' => $this->policy->can($user, $site, Permission::USERS_MANAGE),
        ], 'admin.layout'));
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request, true);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $email = trim(RequestInput::string($request, 'email'));
        $name = trim(RequestInput::string($request, 'display_name'));
        $password = RequestInput::string($request, 'password');
        $roleId = RequestInput::string($request, 'role_id');
        $platform = RequestInput::string($request, 'is_platform_admin') === '1';
        if ($email === '' || $name === '' || strlen($password) < 8 || $roleId === '') {
            return $this->responses->redirect($basePath . '/admin/users?new=1&error=' . rawurlencode($this->ui->get($user, 'admin.error.users_required')) . '#new-user');
        }
        if ($this->users->findByEmail($email) !== null) {
            return $this->responses->redirect($basePath . '/admin/users?new=1&error=' . rawurlencode($this->ui->get($user, 'admin.error.users_email_taken')) . '#new-user');
        }

        $created = $this->users->createUser(
            $email,
            $this->passwords->hash($password),
            $name,
            $platform,
            $site->defaultLocale,
        );
        $this->users->setMembership($site->id, $created->id, $roleId);

        return $this->responses->redirect($basePath . '/admin/users?saved=1');
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request, true);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$actor, $site, $basePath] = $ctx;

        try {
            $id = new UserId(RequestInput::string($request, 'user_id'));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/users?error=' . rawurlencode($this->ui->get($actor, 'admin.error.users_invalid')));
        }

        $target = $this->users->findById($id);
        if ($target === null) {
            return $this->responses->redirect($basePath . '/admin/users?error=' . rawurlencode($this->ui->get($actor, 'admin.error.users_not_found')));
        }

        $email = trim(RequestInput::string($request, 'email'));
        $name = trim(RequestInput::string($request, 'display_name'));
        $roleId = RequestInput::string($request, 'role_id');
        $platform = RequestInput::string($request, 'is_platform_admin') === '1';
        if ($email === '' || $name === '') {
            return $this->responses->redirect($basePath . '/admin/users?error=' . rawurlencode($this->ui->get($actor, 'admin.error.users_name_email')));
        }
        if ($roleId === '' && !$platform) {
            return $this->responses->redirect($basePath . '/admin/users?error=' . rawurlencode($this->ui->get($actor, 'admin.error.users_role_required')));
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null && !$existing->id->equals($id)) {
            return $this->responses->redirect($basePath . '/admin/users?error=' . rawurlencode($this->ui->get($actor, 'admin.error.users_email_taken')));
        }

        $guardError = $this->adminGuard->assertUpdateAllowed($actor, $target, $site->id, $roleId, $platform);
        if ($guardError !== null) {
            return $this->responses->redirect(
                $basePath . '/admin/users?edit=' . rawurlencode($id->value)
                . '&error=' . rawurlencode($this->ui->get($actor, 'admin.error.' . $guardError))
                . '#edit-user',
            );
        }

        $this->users->updateProfile($id, $name, $email, $platform);
        if ($roleId !== '') {
            $this->users->setMembership($site->id, $id, $roleId);
        }
        $password = RequestInput::string($request, 'password');
        if ($password !== '') {
            if (strlen($password) < 8) {
                return $this->responses->redirect($basePath . '/admin/users?error=' . rawurlencode($this->ui->get($actor, 'admin.error.users_password_min')));
            }
            $this->users->updatePassword($id, $this->passwords->hash($password));
        }

        return $this->responses->redirect($basePath . '/admin/users?saved=1');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request, true);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$actor, $site, $basePath] = $ctx;

        try {
            $id = new UserId(RequestInput::string($request, 'user_id'));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/users?error=' . rawurlencode($this->ui->get($actor, 'admin.error.users_invalid')));
        }

        $target = $this->users->findById($id);
        if ($target === null) {
            return $this->responses->redirect($basePath . '/admin/users?error=' . rawurlencode($this->ui->get($actor, 'admin.error.users_not_found')));
        }

        $guardError = $this->adminGuard->assertDeleteAllowed($actor, $target, $site->id);
        if ($guardError !== null) {
            return $this->responses->redirect($basePath . '/admin/users?error=' . rawurlencode($this->ui->get($actor, 'admin.error.' . $guardError)));
        }

        $this->users->softDelete($id);

        return $this->responses->redirect($basePath . '/admin/users?saved=1');
    }

    /**
     * @return array{User, \Nexis\Site\Site, string}|ResponseInterface
     */
    private function context(ServerRequestInterface $request, bool $requireManage = false): array|ResponseInterface
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
        if (!$this->policy->view($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }
        if ($requireManage && !$this->policy->can($user, $site, Permission::USERS_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'users.manage']), 403);
        }

        return [$user, $site, $basePath];
    }
}
