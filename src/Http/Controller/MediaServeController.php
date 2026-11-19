<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Http\ResponseFactory;
use Nexis\I18n\PublicUi;
use Nexis\Media\MediaId;
use Nexis\Media\MediaLibrary;
use Nexis\Media\MediaRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDOException;
use RuntimeException;

final class MediaServeController
{
    public function __construct(
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private MediaRepository $media,
        private MediaLibrary $library,
        private PublicUi $ui,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $site = $this->sites->installed();
        } catch (PDOException) {
            $site = null;
        }
        if (!$site instanceof Site) {
            return $this->notFound($request);
        }

        try {
            $id = new MediaId((string) $request->getAttribute('id', ''));
        } catch (InvalidArgumentException) {
            return $this->notFound($request);
        }

        $asset = $this->media->findById($id, $site->id);
        if ($asset === null) {
            return $this->notFound($request);
        }

        $handle = trim((string) $request->getAttribute('handle', ''));
        if ($handle !== '') {
            if (!in_array($handle, ['thumb', 'webp'], true)) {
                return $this->notFound($request);
            }
            $variant = $this->media->findVariant($asset->id, $handle);
            if ($variant === null) {
                return $this->notFound($request);
            }
            try {
                $binary = $this->library->readVariant($variant);
            } catch (RuntimeException) {
                return $this->notFound($request);
            }

            return $this->responses->file($binary, $variant->mime)
                ->withHeader('Cache-Control', 'public, max-age=86400');
        }

        try {
            $binary = $this->library->read($asset);
        } catch (RuntimeException) {
            return $this->notFound($request);
        }

        return $this->responses->file($binary, $asset->mime)
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }

    private function notFound(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responses->html($this->ui->getRequest($request, 'http.not_found'), 404);
    }
}
