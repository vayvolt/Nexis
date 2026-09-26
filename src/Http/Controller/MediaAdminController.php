<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Media\MediaFolder;
use Nexis\Media\MediaFolderId;
use Nexis\Media\MediaId;
use Nexis\Media\MediaLibrary;
use Nexis\Media\MediaRepository;
use Nexis\Site\SiteRepository;
use Nexis\Support\Uuid;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class MediaAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private MediaRepository $media,
        private MediaLibrary $library,
        private SitePolicy $policy,
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
        $query = $request->getQueryParams();
        [$folderFilter, $activeFolderId, $folderError] = $this->resolveFolderFilter($query, $site->id, $user);
        if ($folderError !== '') {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($folderError));
        }

        $assets = $this->media->listBySite(
            $site->id,
            $activeFolderId,
            $folderFilter === 'none',
        );
        $folders = $this->media->listFolders($site->id);
        $thumbUrls = [];
        /** @var array<string, list<\Nexis\Media\MediaVariant>> $variantsByAsset */
        $variantsByAsset = [];
        foreach ($assets as $asset) {
            if (!$asset->isImage()) {
                continue;
            }
            if ($this->media->hasVariant($asset->id, 'thumb')) {
                $thumbUrls[$asset->id->value] = $basePath . '/media/' . $asset->id->value . '/thumb';
            }
            $variantsByAsset[$asset->id->value] = $this->media->variantsFor($asset->id);
        }

        $folderQuery = '';
        if ($folderFilter === 'none') {
            $folderQuery = 'none';
        } elseif ($activeFolderId !== null) {
            $folderQuery = $activeFolderId->value;
        }

        return $this->responses->html($this->views->render('admin.media.index', [
            'user' => $user,
            'site' => $site,
            'assets' => $assets,
            'folders' => $folders,
            'folderFilter' => $folderFilter,
            'folderQuery' => $folderQuery,
            'activeFolderId' => $activeFolderId?->value,
            'thumbUrls' => $thumbUrls,
            'variantsByAsset' => $variantsByAsset,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => is_string($query['error'] ?? null) ? (string) $query['error'] : '',
            'deleted' => ($query['deleted'] ?? '') === '1',
            'uploaded' => ($query['uploaded'] ?? '') === '1',
            'saved' => ($query['saved'] ?? '') === '1',
            'variantsRegenerated' => ($query['variants'] ?? '') === '1',
            'folderCreated' => ($query['folder_created'] ?? '') === '1',
            'folderDeleted' => ($query['folder_deleted'] ?? '') === '1',
        ], 'admin.layout'));
    }

    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_choose_file')));
        }

        $folderId = $this->optionalFolderId($request, $site->id, $user);
        if ($folderId === false) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.media.folder.error_not_found')));
        }

        try {
            $alt = trim(RequestInput::string($request, 'alt_text'));
            $asset = $this->library->store($site->id, $file, $alt, $folderId);
            $this->audit->log('media.upload', $site->id, $user->id, 'media', $asset->id->value, [
                'name' => $asset->originalName,
                'mime' => $asset->mime,
            ]);
        } catch (\InvalidArgumentException $e) {
            $msg = match ($e->getMessage()) {
                'media.alt_required' => $this->ui->get($user, 'admin.error.media_alt_required'),
                'media.too_large' => $this->ui->get($user, 'admin.error.media_too_large'),
                'media.too_many_pixels' => $this->ui->get($user, 'admin.error.media_too_many_pixels'),
                default => $this->ui->get($user, 'admin.error.media_upload_rejected'),
            };
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($msg));
        } catch (\Throwable) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_upload_rejected')));
        }

        $suffix = $this->folderRedirectSuffix($request);

        return $this->responses->redirect($basePath . '/admin/media?uploaded=1' . $suffix);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        try {
            $id = new MediaId(RequestInput::string($request, 'media_id'));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_invalid')));
        }
        $asset = $this->media->findById($id, $site->id);
        if ($asset === null) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_not_found')));
        }

        try {
            $asset = $this->library->updateAlt($asset, RequestInput::string($request, 'alt_text'));
        } catch (\InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_alt_required')));
        }
        $this->audit->log('media.alt.update', $site->id, $user->id, 'media', $asset->id->value);

        if ($asset->isImage()) {
            $focusX = (float) RequestInput::string($request, 'focus_x', '50');
            $focusY = (float) RequestInput::string($request, 'focus_y', '50');
            try {
                $asset = $this->library->updateFocus($asset, $focusX, $focusY);
            } catch (\InvalidArgumentException) {
                return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_focus_images_only')));
            }
            $this->audit->log('media.focus.update', $site->id, $user->id, 'media', $asset->id->value, [
                'focus_x' => $focusX,
                'focus_y' => $focusY,
            ]);
        }

        $folderRaw = RequestInput::string($request, 'target_folder_id');
        $folderId = null;
        if ($folderRaw !== '' && $folderRaw !== 'none') {
            try {
                $folderId = new MediaFolderId($folderRaw);
            } catch (InvalidArgumentException) {
                return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.media.folder.error_invalid')));
            }
            if ($this->media->findFolder($folderId, $site->id) === null) {
                return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.media.folder.error_not_found')));
            }
        }
        $currentFolder = $asset->folderId?->value;
        $nextFolder = $folderId?->value;
        if ($currentFolder !== $nextFolder) {
            if (!$this->media->moveToFolder($id, $site->id, $folderId)) {
                return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_not_found')));
            }
            $this->audit->log('media.move', $site->id, $user->id, 'media', $asset->id->value, [
                'folder_id' => $folderId?->value,
            ]);
        }

        $suffix = $this->folderRedirectSuffix($request);

        return $this->responses->redirect($basePath . '/admin/media?saved=1' . $suffix);
    }

    public function regenerateVariants(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        try {
            $id = new MediaId(RequestInput::string($request, 'media_id'));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_invalid')));
        }
        $asset = $this->media->findById($id, $site->id);
        if ($asset === null) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_not_found')));
        }
        try {
            $this->library->regenerateVariants($asset);
        } catch (\InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_variants_images_only')));
        } catch (\Throwable) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_variants_failed')));
        }
        $this->audit->log('media.variants.regenerate', $site->id, $user->id, 'media', $asset->id->value);
        $suffix = $this->folderRedirectSuffix($request);

        return $this->responses->redirect($basePath . '/admin/media?variants=1' . $suffix);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        try {
            $id = new MediaId(RequestInput::string($request, 'media_id'));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_invalid')));
        }

        $asset = $this->media->findById($id, $site->id);
        if ($asset === null) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_not_found')));
        }

        if (!$this->library->delete($asset)) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.error.media_delete_failed')));
        }

        $this->audit->log('media.delete', $site->id, $user->id, 'media', $asset->id->value, [
            'name' => $asset->originalName,
        ]);

        $suffix = $this->folderRedirectSuffix($request);

        return $this->responses->redirect($basePath . '/admin/media?deleted=1' . $suffix);
    }

    public function createFolder(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $name = trim(RequestInput::string($request, 'name'));
        if ($name === '' || strlen($name) > 190) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.media.folder.error_name')));
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $folder = new MediaFolder(
            new MediaFolderId(Uuid::v7()),
            $site->id,
            $name,
            count($this->media->listFolders($site->id)),
            $now,
        );
        $this->media->saveFolder($folder);
        $this->audit->log('media.folder.create', $site->id, $user->id, 'media_folder', $folder->id->value, ['name' => $name]);

        return $this->responses->redirect($basePath . '/admin/media?folder=' . rawurlencode($folder->id->value) . '&folder_created=1');
    }

    public function deleteFolder(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        try {
            $id = new MediaFolderId(RequestInput::string($request, 'folder_id'));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.media.folder.error_invalid')));
        }

        if (!$this->media->deleteFolder($id, $site->id)) {
            return $this->responses->redirect($basePath . '/admin/media?error=' . rawurlencode($this->ui->get($user, 'admin.media.folder.error_not_found')));
        }

        $this->audit->log('media.folder.delete', $site->id, $user->id, 'media_folder', $id->value);

        return $this->responses->redirect($basePath . '/admin/media?folder_deleted=1');
    }

    /**
     * @param array<string, mixed> $query
     * @return array{string, ?MediaFolderId, string}
     */
    private function resolveFolderFilter(array $query, \Nexis\Site\SiteId $siteId, User $user): array
    {
        if (!array_key_exists('folder', $query)) {
            return ['all', null, ''];
        }
        $raw = $query['folder'];
        if (!is_string($raw) || $raw === '' || $raw === 'all') {
            return ['all', null, ''];
        }
        if ($raw === 'none') {
            return ['none', null, ''];
        }
        try {
            $folderId = new MediaFolderId($raw);
        } catch (InvalidArgumentException) {
            return ['all', null, $this->ui->get($user, 'admin.media.folder.error_invalid')];
        }
        if ($this->media->findFolder($folderId, $siteId) === null) {
            return ['all', null, $this->ui->get($user, 'admin.media.folder.error_not_found')];
        }

        return ['folder', $folderId, ''];
    }

    /**
     * @return MediaFolderId|null|false null = unfiled/none, false = invalid folder
     */
    private function optionalFolderId(ServerRequestInterface $request, \Nexis\Site\SiteId $siteId, User $user): MediaFolderId|null|false
    {
        $raw = RequestInput::string($request, 'folder_id');
        if ($raw === '') {
            return null;
        }
        try {
            $folderId = new MediaFolderId($raw);
        } catch (InvalidArgumentException) {
            return false;
        }
        if ($this->media->findFolder($folderId, $siteId) === null) {
            return false;
        }

        return $folderId;
    }

    private function folderRedirectSuffix(ServerRequestInterface $request): string
    {
        $raw = RequestInput::string($request, 'folder_id');
        if ($raw === '') {
            return '';
        }

        return '&folder=' . rawurlencode($raw);
    }

    /**
     * @return array{User, \Nexis\Site\Site, string}|ResponseInterface
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
        if (!$this->policy->can($user, $site, Permission::CONTENT_MEDIA_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.media.manage']), 403);
        }

        return [$user, $site, $basePath];
    }
}
