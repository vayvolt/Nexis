<?php

declare(strict_types=1);

namespace Nexis\Api;

use DateTimeInterface;
use Nexis\Builder\PageRevision;
use Nexis\Content\Page;
use Nexis\Media\MediaAsset;
use Nexis\Site\Site;
use Nexis\Site\SiteLocale;

/**
 * Shared JSON shapes for the public and admin API. IDs are UUID v7 strings,
 * timestamps are RFC 3339.
 */
final class ApiSerializer
{
    /**
     * @return array{id: string, name: string, defaultLocale: string, localeUrlStrategy: string, primaryDomain: string, locales: list<array{code: string, name: string, enabled: bool}>}
     */
    public static function site(Site $site): array
    {
        return [
            'id' => $site->id->value,
            'name' => $site->name,
            'defaultLocale' => $site->defaultLocale,
            'localeUrlStrategy' => $site->localeUrlStrategy->value,
            'primaryDomain' => $site->primaryDomain,
            'locales' => array_map(
                static fn (SiteLocale $locale): array => [
                    'code' => $locale->locale,
                    'name' => $locale->label,
                    'enabled' => $locale->enabled,
                ],
                $site->locales,
            ),
        ];
    }

    /**
     * @return array{id: string, path: string, slug: string, title: string, locale: string, type: string, publishedAt: ?string, updatedAt: ?string}
     */
    public static function page(Page $page): array
    {
        return [
            'id' => $page->id->value,
            'path' => $page->path,
            'slug' => $page->slug,
            'title' => $page->title,
            'locale' => $page->locale,
            'type' => $page->type,
            'publishedAt' => self::date($page->publishedAt),
            'updatedAt' => self::date($page->updatedAt),
        ];
    }

    /**
     * Admin list/detail row: adds workflow state the public API must not expose.
     *
     * @return array<string, mixed>
     */
    public static function adminPage(Page $page): array
    {
        return self::page($page) + [
            'status' => $page->status->value,
            'translationGroupId' => $page->translationGroupId,
            'scheduledAt' => self::date($page->scheduledAt),
            'unpublishAt' => self::date($page->unpublishAt),
            'publishedRevisionId' => $page->publishedRevisionId?->value,
            'publishedSnapshotId' => $page->publishedSnapshotId?->value,
            'metadata' => self::metadata($page),
        ];
    }

    /**
     * @return array{metaTitle: ?string, metaDescription: ?string, robots: string, focusKeyword: ?string}
     */
    public static function metadata(Page $page): array
    {
        return [
            'metaTitle' => $page->metaTitle,
            'metaDescription' => $page->metaDescription,
            'robots' => $page->robots,
            'focusKeyword' => $page->focusKeyword,
        ];
    }

    /**
     * `documentHash` doubles as the `If-Match` value of `PUT /pages/{id}/document`.
     *
     * @return array<string, mixed>
     */
    public static function revision(PageRevision $revision, bool $withDocument = true): array
    {
        $payload = [
            'pageId' => $revision->pageId->value,
            'revisionId' => $revision->id->value,
            'schemaVersion' => $revision->schemaVersion,
            'documentHash' => $revision->documentHash,
            'message' => $revision->message,
            'createdAt' => self::date($revision->createdAt),
        ];
        if ($withDocument) {
            $payload['document'] = $revision->document;
        }

        return $payload;
    }

    /**
     * @return array{locale: string, path: string, id: string}
     */
    public static function alternate(Page $page): array
    {
        return [
            'locale' => $page->locale,
            'path' => $page->path,
            'id' => $page->id->value,
        ];
    }

    /**
     * Catalog row of PluginCatalog::listForSite. `id` and `key` are both the
     * `vendor/name` key; `status` is `discovered`, `installed`, `enabled` or
     * `disabled`.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function plugin(array $row): array
    {
        $key = (string) ($row['key'] ?? '');

        return [
            'id' => $key,
            'key' => $key,
            'name' => (string) ($row['name'] ?? ''),
            'version' => (string) ($row['version'] ?? ''),
            'author' => (string) ($row['author'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'license' => (string) ($row['license'] ?? ''),
            'licenseUri' => (string) ($row['licenseUri'] ?? ''),
            'compatibleCore' => (string) ($row['compatibleCore'] ?? ''),
            'php' => (string) ($row['php'] ?? ''),
            'available' => [
                'blocks' => is_array($row['blocks'] ?? null) ? array_values($row['blocks']) : [],
                'permissions' => is_array($row['permissions'] ?? null) ? array_values($row['permissions']) : [],
                'slots' => is_array($row['slots'] ?? null) ? array_values($row['slots']) : [],
            ],
        ];
    }

    /**
     * Entry of BlockRegistry::catalog().
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public static function block(array $entry): array
    {
        return [
            'type' => (string) ($entry['type'] ?? ''),
            'label' => (string) ($entry['label'] ?? ''),
            'allowsChildren' => (bool) ($entry['allowsChildren'] ?? false),
            'propsSchema' => $entry['propsSchema'] ?? [],
            'defaultProps' => $entry['defaultProps'] ?? [],
        ];
    }

    /**
     * Theme overrides of a site: only the tokens that differ from the theme
     * defaults, plus the stored custom CSS.
     *
     * @param array<string, string> $tokens
     * @return array<string, mixed>
     */
    public static function themeTokens(array $tokens, string $customCss): array
    {
        return [
            // Empty overrides must stay a JSON object, not an array.
            'tokens' => $tokens === [] ? new \stdClass() : $tokens,
            'customCss' => $customCss !== '' ? $customCss : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function media(MediaAsset $asset, string $basePath): array
    {
        return [
            'id' => $asset->id->value,
            'originalName' => $asset->originalName,
            'altText' => $asset->altText,
            'mime' => $asset->mime,
            'extension' => $asset->extension,
            'byteSize' => $asset->byteSize,
            'width' => $asset->width,
            'height' => $asset->height,
            'checksum' => $asset->checksum,
            'folderId' => $asset->folderId?->value,
            'focus' => ['x' => $asset->focusX, 'y' => $asset->focusY],
            'url' => $basePath . '/media/' . $asset->id->value,
            'createdAt' => self::date($asset->createdAt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function token(ApiToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'tokenPrefix' => $token->tokenPrefix,
            'userId' => $token->userId->value,
            'scopes' => $token->scopes,
            'lastUsedAt' => self::date($token->lastUsedAt),
            'expiresAt' => self::date($token->expiresAt),
            'revokedAt' => self::date($token->revokedAt),
            'createdAt' => self::date($token->createdAt),
        ];
    }

    public static function date(?DateTimeInterface $value): ?string
    {
        return $value?->format(DateTimeInterface::RFC3339);
    }
}
