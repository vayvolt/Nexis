<?php

declare(strict_types=1);

namespace Nexis\Site;

use RuntimeException;
use ZipArchive;

final class SiteExportArchive
{
    /**
     * @return array<string, mixed>
     */
    public static function read(string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ext-zip fehlt für Site-Import.');
        }
        if (!is_file($zipPath)) {
            throw new RuntimeException('Import-Datei fehlt.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ZIP konnte nicht geöffnet werden.');
        }
        try {
            $json = $zip->getFromName('site.json');
            if (!is_string($json) || $json === '') {
                throw new RuntimeException('site.json fehlt im Archiv.');
            }
            try {
                $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new RuntimeException('site.json ungültig: ' . $e->getMessage(), 0, $e);
            }
            if (!is_array($payload) || ($payload['format'] ?? '') !== 'nexis.site.export') {
                throw new RuntimeException('Unbekanntes Export-Format.');
            }

            return $payload;
        } finally {
            $zip->close();
        }
    }
}
