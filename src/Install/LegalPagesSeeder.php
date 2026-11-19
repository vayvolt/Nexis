<?php

declare(strict_types=1);

namespace Nexis\Install;

use Nexis\Auth\UserId;
use Nexis\Builder\DocumentService;
use Nexis\Builder\PublishService;
use Nexis\Content\MenuRepository;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageRepository;
use Nexis\Content\PageStatus;
use Nexis\Site\Site;
use Nexis\Support\Uuid;

/**
 * Idempotent legal-page + footer ensure for existing installs (Impressum, Datenschutz, AGB).
 * Does not overwrite pages that already exist at the canonical paths.
 */
final class LegalPagesSeeder
{
    /** @var list<array{key: string, locales: array<string, array{path: string, slug: string, title: string, metaTitle: string, metaDescription: string, footerLabel: string}>}> */
    private const SPECS = [
        [
            'key' => 'imprint',
            'locales' => [
                'de' => [
                    'path' => '/impressum',
                    'slug' => 'impressum',
                    'title' => 'Impressum',
                    'metaTitle' => 'Impressum',
                    'metaDescription' => 'Anbieterkennzeichnung und Kontaktdaten.',
                    'footerLabel' => 'Impressum',
                ],
                'en' => [
                    'path' => '/imprint',
                    'slug' => 'imprint',
                    'title' => 'Legal notice',
                    'metaTitle' => 'Legal notice',
                    'metaDescription' => 'Provider information and contact details.',
                    'footerLabel' => 'Legal notice',
                ],
            ],
        ],
        [
            'key' => 'privacy',
            'locales' => [
                'de' => [
                    'path' => '/datenschutz',
                    'slug' => 'datenschutz',
                    'title' => 'Datenschutz',
                    'metaTitle' => 'Datenschutzerklärung',
                    'metaDescription' => 'Informationen zur Verarbeitung personenbezogener Daten.',
                    'footerLabel' => 'Datenschutz',
                ],
                'en' => [
                    'path' => '/privacy',
                    'slug' => 'privacy',
                    'title' => 'Privacy',
                    'metaTitle' => 'Privacy policy',
                    'metaDescription' => 'Information about processing of personal data.',
                    'footerLabel' => 'Privacy',
                ],
            ],
        ],
        [
            'key' => 'terms',
            'locales' => [
                'de' => [
                    'path' => '/agb',
                    'slug' => 'agb',
                    'title' => 'AGB',
                    'metaTitle' => 'Allgemeine Geschäftsbedingungen',
                    'metaDescription' => 'Nutzungsbedingungen dieser Website.',
                    'footerLabel' => 'AGB',
                ],
                'en' => [
                    'path' => '/terms',
                    'slug' => 'terms',
                    'title' => 'Terms of service',
                    'metaTitle' => 'Terms of service',
                    'metaDescription' => 'Terms of use for this website.',
                    'footerLabel' => 'Terms',
                ],
            ],
        ],
    ];

    public function __construct(
        private PageRepository $pages,
        private DocumentService $documents,
        private PublishService $publish,
        private MenuRepository $menus,
    ) {
    }

    /**
     * @return list<array{key: string, locale: string, path: string, title: string}>
     */
    public function gaps(Site $site): array
    {
        $enabled = $this->enabledLegalLocales($site);
        $gaps = [];
        foreach (self::SPECS as $spec) {
            foreach ($enabled as $locale) {
                $meta = $spec['locales'][$locale] ?? null;
                if ($meta === null) {
                    continue;
                }
                if ($this->pages->findByPath($site->id, $locale, $meta['path']) !== null) {
                    continue;
                }
                $gaps[] = [
                    'key' => $spec['key'],
                    'locale' => $locale,
                    'path' => $meta['path'],
                    'title' => $meta['title'],
                ];
            }
        }

        return $gaps;
    }

    /**
     * @return array{created: list<string>, linked: list<string>, skipped: list<string>}
     */
    public function ensure(Site $site, UserId $actor, string $basePath, string $adminEmail = ''): array
    {
        $content = new DefaultSiteContent($site->name, $adminEmail);
        $enabled = $this->enabledLegalLocales($site);
        $created = [];
        $linked = [];
        $skipped = [];

        foreach (self::SPECS as $spec) {
            $groupId = null;
            $pageIds = [];

            foreach ($enabled as $locale) {
                $meta = $spec['locales'][$locale] ?? null;
                if ($meta === null) {
                    continue;
                }

                $existing = $this->pages->findByPath($site->id, $locale, $meta['path']);
                if ($existing !== null) {
                    $skipped[] = $locale . ':' . $meta['path'];
                    $pageIds[$locale] = $existing;
                    if ($groupId === null) {
                        $groupId = $existing->translationGroupId;
                    }
                    continue;
                }

                if ($groupId === null) {
                    foreach ($enabled as $other) {
                        if ($other === $locale) {
                            continue;
                        }
                        $otherMeta = $spec['locales'][$other] ?? null;
                        if ($otherMeta === null) {
                            continue;
                        }
                        $sibling = $this->pages->findByPath($site->id, $other, $otherMeta['path']);
                        if ($sibling !== null) {
                            $groupId = $sibling->translationGroupId;
                            break;
                        }
                    }
                }
                if ($groupId === null) {
                    $groupId = Uuid::v7();
                }

                $page = new Page(
                    new PageId(Uuid::v7()),
                    $site->id,
                    $groupId,
                    $locale,
                    $meta['slug'],
                    $meta['path'],
                    $meta['title'],
                    null,
                    PageStatus::Draft,
                    metaTitle: $meta['metaTitle'],
                    metaDescription: $meta['metaDescription'],
                    robots: 'index,follow',
                );
                $this->pages->save($page);
                $saved = $this->pages->findById($page->id);
                if ($saved === null) {
                    throw new \RuntimeException('Legal page missing after insert: ' . $meta['path']);
                }

                $this->documents->save(
                    $saved->id,
                    $this->documentFor($content, (string) $spec['key'], $locale),
                    null,
                    $actor,
                    'Legal-Seiten anlegen',
                );
                $this->publish->publish($saved, null, $actor, $basePath);
                $pageIds[$locale] = $saved;
                $created[] = $locale . ':' . $meta['path'];
            }

            foreach ($pageIds as $locale => $page) {
                $meta = $spec['locales'][$locale];
                if ($this->ensureFooterLink($site, $locale, $page, $meta['footerLabel'])) {
                    $linked[] = $locale . ':' . $meta['path'];
                }
            }
        }

        return [
            'created' => $created,
            'linked' => $linked,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<string>
     */
    private function enabledLegalLocales(Site $site): array
    {
        $out = [];
        foreach ($site->enabledLocales() as $loc) {
            if (isset(self::SPECS[0]['locales'][$loc->locale])) {
                $out[] = $loc->locale;
            }
        }
        if ($out === [] && isset(self::SPECS[0]['locales'][$site->defaultLocale])) {
            $out[] = $site->defaultLocale;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentFor(DefaultSiteContent $content, string $key, string $locale): array
    {
        return match ($key) {
            'imprint' => $content->imprintDocument($locale),
            'privacy' => $content->privacyDocument($locale),
            'terms' => $content->termsDocument($locale),
            default => throw new \LogicException('Unknown legal key: ' . $key),
        };
    }

    private function ensureFooterLink(Site $site, string $locale, Page $page, string $label): bool
    {
        $menu = $this->menus->ensure($site->id, 'footer', 'Footer', $locale);
        foreach ($this->menus->itemsFor($menu->id) as $item) {
            if ($item->pageId !== null && $item->pageId->value === $page->id->value) {
                return false;
            }
            if (strcasecmp(trim($item->label), $label) === 0) {
                return false;
            }
        }
        $this->menus->appendRootItem($menu->id, $label, $page->id->value, null);

        return true;
    }
}
