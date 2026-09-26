<?php

declare(strict_types=1);

namespace Nexis\Install;

use Nexis\Auth\UserId;
use Nexis\Builder\BlockDocument;
use Nexis\Builder\DocumentService;
use Nexis\Builder\PublishService;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageRepository;
use Nexis\Content\PageStatus;
use Nexis\Content\PageType;
use Nexis\Kernel\Bootstrap;
use Nexis\Plugin\PluginRuntime;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use Nexis\Theme\ThemeService;
use PDO;

/**
 * Publishes default pages/menus after a fresh web install.
 */
final class InstallDefaultContent
{
    /**
     * @param array{
     *   siteId: string,
     *   adminId: string,
     *   homeId: string,
     *   imprintId: string,
     *   privacyId: string,
     *   homeEnId: string,
     *   imprintEnId: string,
     *   privacyEnId: string
     * } $ids
     */
    public function seed(
        string $rootPath,
        array $ids,
        string $siteName,
        string $adminEmail,
        string $basePath,
        bool $withPlugins,
    ): void {
        $app = Bootstrap::boot($rootPath);
        $pdo = $app->container->get(PDO::class);
        $documents = $app->container->get(DocumentService::class);
        $publish = $app->container->get(PublishService::class);
        $pages = $app->container->get(PageRepository::class);
        $themes = $app->container->get(ThemeService::class);
        $settings = $app->container->get(PdoSiteSettingsRepository::class);

        $themes->sync();
        $siteId = new SiteId($ids['siteId']);
        $app->container->get(\Nexis\Theme\ThemeCatalog::class)->assignTheme($siteId, 'nexis/nexis');

        if ($withPlugins) {
            $app->container->get(PluginRuntime::class)->bootOnce();
        }

        $actor = new UserId($ids['adminId']);
        $now = gmdate('Y-m-d H:i:s.v');
        $kontaktId = null;
        $kontaktEnId = null;
        $leistungenId = null;
        $auftragId = null;
        $leistungenEnId = null;
        $auftragEnId = null;
        $withWorkshop = $withPlugins && is_file(
            $rootPath . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis'
            . DIRECTORY_SEPARATOR . 'workshop' . DIRECTORY_SEPARATOR . 'plugin.json',
        );

        $content = new DefaultSiteContent($siteName, $adminEmail);

        $documents->save(
            new PageId($ids['homeId']),
            $content->homeDocument(
                $basePath,
                $withWorkshop,
                $withPlugins,
                $withPlugins ? '/de/kontakt' : '/de/impressum',
                'de',
            ),
            null,
            $actor,
            'Installation',
        );
        $home = $pages->findById(new PageId($ids['homeId']));
        if ($home === null) {
            throw new \RuntimeException('Startseite fehlt nach Installation.');
        }
        $publish->publish($home, null, $actor, $basePath);

        $documents->save(
            new PageId($ids['homeEnId']),
            $content->homeDocument(
                $basePath,
                $withWorkshop,
                $withPlugins,
                $withPlugins ? '/en/contact' : '/en/imprint',
                'en',
            ),
            null,
            $actor,
            'Installation',
        );
        $homeEn = $pages->findById(new PageId($ids['homeEnId']));
        if ($homeEn === null) {
            throw new \RuntimeException('English homepage missing after install.');
        }
        $publish->publish($homeEn, null, $actor, $basePath);

        $documents->save(
            new PageId($ids['imprintId']),
            $content->imprintDocument('de'),
            null,
            $actor,
            'Installation',
        );
        $imprint = $pages->findById(new PageId($ids['imprintId']));
        if ($imprint === null) {
            throw new \RuntimeException('Impressum fehlt nach Installation.');
        }
        $publish->publish($imprint, null, $actor, $basePath);

        $documents->save(
            new PageId($ids['imprintEnId']),
            $content->imprintDocument('en'),
            null,
            $actor,
            'Installation',
        );
        $imprintEn = $pages->findById(new PageId($ids['imprintEnId']));
        if ($imprintEn === null) {
            throw new \RuntimeException('English legal notice missing after install.');
        }
        $publish->publish($imprintEn, null, $actor, $basePath);

        $documents->save(
            new PageId($ids['privacyId']),
            $content->privacyDocument('de'),
            null,
            $actor,
            'Installation',
        );
        $privacy = $pages->findById(new PageId($ids['privacyId']));
        if ($privacy === null) {
            throw new \RuntimeException('Datenschutzerklärung fehlt nach Installation.');
        }
        $publish->publish($privacy, null, $actor, $basePath);

        $documents->save(
            new PageId($ids['privacyEnId']),
            $content->privacyDocument('en'),
            null,
            $actor,
            'Installation',
        );
        $privacyEn = $pages->findById(new PageId($ids['privacyEnId']));
        if ($privacyEn === null) {
            throw new \RuntimeException('English privacy policy missing after install.');
        }
        $publish->publish($privacyEn, null, $actor, $basePath);

        if ($withPlugins) {
            $kontaktGroup = Uuid::v7();
            $kontaktId = Uuid::v7();
            $kontakt = new Page(
                new PageId($kontaktId),
                $siteId,
                $kontaktGroup,
                'de',
                'kontakt',
                '/kontakt',
                'Über uns & Kontakt',
                null,
                PageStatus::Draft,
                metaTitle: 'Über uns & Kontakt',
                metaDescription: 'Kontakt und Informationen zu ' . $siteName . '.',
                robots: 'index,follow',
            );
            $pages->save($kontakt);
            $documents->save(
                new PageId($kontaktId),
                $content->aboutDocument(true, 'de'),
                null,
                $actor,
                'Installation',
            );
            $kontaktPage = $pages->findById(new PageId($kontaktId));
            if ($kontaktPage === null) {
                throw new \RuntimeException('Kontaktseite fehlt nach Installation.');
            }
            $publish->publish($kontaktPage, null, $actor, $basePath);

            $kontaktEnId = Uuid::v7();
            $kontaktEn = new Page(
                new PageId($kontaktEnId),
                $siteId,
                $kontaktGroup,
                'en',
                'contact',
                '/contact',
                'About & contact',
                null,
                PageStatus::Draft,
                metaTitle: 'About & contact',
                metaDescription: 'Contact and information about ' . $siteName . '.',
                robots: 'index,follow',
            );
            $pages->save($kontaktEn);
            $documents->save(
                new PageId($kontaktEnId),
                $content->aboutDocument(true, 'en'),
                null,
                $actor,
                'Installation',
            );
            $kontaktEnPage = $pages->findById(new PageId($kontaktEnId));
            if ($kontaktEnPage === null) {
                throw new \RuntimeException('English contact page missing after install.');
            }
            $publish->publish($kontaktEnPage, null, $actor, $basePath);

            $settings->set($siteId, 'plugin:nexis/forms.notify_email', $adminEmail);
            if ($withWorkshop) {
                $settings->set($siteId, 'plugin:nexis/workshop.notify_email', $adminEmail);
            }
            $settings->set($siteId, 'plugin:nexis/consent.enabled', true);
            $settings->set($siteId, 'plugin:nexis/consent.privacy_url', rtrim($basePath, '/') . '/de/datenschutz');
            $settings->set(
                $siteId,
                'plugin:nexis/consent.text.de',
                'Wir verwenden Cookies und ähnliche Technologien für den Betrieb dieser Webseite.',
            );
            $settings->set(
                $siteId,
                'plugin:nexis/consent.text.en',
                'We use cookies and similar technologies to operate this website.',
            );

            if ($withWorkshop) {
                $this->seedWorkshopDemoServices($app->container, $siteId);

                $leistungenGroup = Uuid::v7();
                $leistungenId = Uuid::v7();
                $leistungen = new Page(
                    new PageId($leistungenId),
                    $siteId,
                    $leistungenGroup,
                    'de',
                    'leistungen',
                    '/leistungen',
                    'Leistungen',
                    null,
                    PageStatus::Draft,
                    metaTitle: 'Leistungen',
                    metaDescription: 'Unsere Werkstatt-Leistungen.',
                    robots: 'index,follow',
                );
                $pages->save($leistungen);
                $documents->save(
                    new PageId($leistungenId),
                    $content->servicesPageDocument(true, 'de'),
                    null,
                    $actor,
                    'Installation',
                );
                $leistungenPage = $pages->findById(new PageId($leistungenId));
                if ($leistungenPage === null) {
                    throw new \RuntimeException('Leistungsseite fehlt nach Installation.');
                }
                $publish->publish($leistungenPage, null, $actor, $basePath);

                $leistungenEnId = Uuid::v7();
                $leistungenEn = new Page(
                    new PageId($leistungenEnId),
                    $siteId,
                    $leistungenGroup,
                    'en',
                    'services',
                    '/services',
                    'Services',
                    null,
                    PageStatus::Draft,
                    metaTitle: 'Services',
                    metaDescription: 'Our workshop services.',
                    robots: 'index,follow',
                );
                $pages->save($leistungenEn);
                $documents->save(
                    new PageId($leistungenEnId),
                    $content->servicesPageDocument(true, 'en'),
                    null,
                    $actor,
                    'Installation',
                );
                $leistungenEnPage = $pages->findById(new PageId($leistungenEnId));
                if ($leistungenEnPage === null) {
                    throw new \RuntimeException('English services page missing after install.');
                }
                $publish->publish($leistungenEnPage, null, $actor, $basePath);

                $auftragGroup = Uuid::v7();
                $auftragId = Uuid::v7();
                $auftrag = new Page(
                    new PageId($auftragId),
                    $siteId,
                    $auftragGroup,
                    'de',
                    'auftrag',
                    '/auftrag',
                    'Auftrag anmelden',
                    null,
                    PageStatus::Draft,
                    metaTitle: 'Auftrag anmelden',
                    metaDescription: 'Werkstattauftrag über Ihr Kundenkonto anmelden.',
                    robots: 'index,follow',
                );
                $pages->save($auftrag);
                $documents->save(
                    new PageId($auftragId),
                    $content->orderDocument('de'),
                    null,
                    $actor,
                    'Installation',
                );
                $auftragPage = $pages->findById(new PageId($auftragId));
                if ($auftragPage === null) {
                    throw new \RuntimeException('Auftragsseite fehlt nach Installation.');
                }
                $publish->publish($auftragPage, null, $actor, $basePath);

                $auftragEnId = Uuid::v7();
                $auftragEn = new Page(
                    new PageId($auftragEnId),
                    $siteId,
                    $auftragGroup,
                    'en',
                    'request',
                    '/request',
                    'Book a job',
                    null,
                    PageStatus::Draft,
                    metaTitle: 'Book a job',
                    metaDescription: 'Submit a workshop job via your customer account.',
                    robots: 'index,follow',
                );
                $pages->save($auftragEn);
                $documents->save(
                    new PageId($auftragEnId),
                    $content->orderDocument('en'),
                    null,
                    $actor,
                    'Installation',
                );
                $auftragEnPage = $pages->findById(new PageId($auftragEnId));
                if ($auftragEnPage === null) {
                    throw new \RuntimeException('English request page missing after install.');
                }
                $publish->publish($auftragEnPage, null, $actor, $basePath);
            }

            $postId = Uuid::v7();
            $post = new Page(
                new PageId($postId),
                $siteId,
                Uuid::v7(),
                'de',
                'willkommen-im-blog',
                '/blog/willkommen-im-blog',
                'Willkommen im Blog',
                null,
                PageStatus::Draft,
                metaTitle: 'Willkommen im Blog',
                metaDescription: 'Erster Beitrag auf Ihrer neuen Webseite.',
                robots: 'index,follow',
                type: PageType::POST,
            );
            $pages->save($post);
            $documents->save(
                new PageId($postId),
                $this->document(
                    $this->heading('Willkommen im Blog', 1),
                    $this->text('Neue Beiträge legen Sie unter Admin → Blog an. Das Archiv erreichen Sie unter /de/blog.'),
                ),
                null,
                $actor,
                'Installation',
            );
            $postPage = $pages->findById(new PageId($postId));
            if ($postPage === null) {
                throw new \RuntimeException('Blog-Beitrag fehlt nach Installation.');
            }
            $publish->publish($postPage, null, $actor, $basePath);

            $productId = Uuid::v7();
            $product = new Page(
                new PageId($productId),
                $siteId,
                Uuid::v7(),
                'de',
                'beispielprodukt',
                '/catalog/beispielprodukt',
                'Beispielprodukt',
                null,
                PageStatus::Draft,
                metaTitle: 'Beispielprodukt',
                metaDescription: 'Demoprodukt im Katalog.',
                robots: 'index,follow',
                type: PageType::PRODUCT,
            );
            $pages->save($product);
            $documents->save(
                new PageId($productId),
                $this->document(
                    $this->heading('Beispielprodukt', 1),
                    $this->text('Neue Produkte legen Sie unter Admin → Katalog an. Das Archiv erreichen Sie unter /de/catalog.'),
                ),
                null,
                $actor,
                'Installation',
            );
            $productPage = $pages->findById(new PageId($productId));
            if ($productPage === null) {
                throw new \RuntimeException('Katalog-Produkt fehlt nach Installation.');
            }
            $publish->publish($productPage, null, $actor, $basePath);
        }

        $this->createMenus(
            $pdo,
            $ids['siteId'],
            $ids['homeId'],
            $ids['imprintId'],
            $ids['privacyId'],
            $ids['homeEnId'],
            $ids['imprintEnId'],
            $ids['privacyEnId'],
            $kontaktId,
            $kontaktEnId,
            $leistungenId,
            $auftragId,
            $leistungenEnId,
            $auftragEnId,
            $now,
        );

        if ($withPlugins) {
            $app->container->get(\Nexis\Plugin\BlogMenuSync::class)->ensure($siteId);
            $app->container->get(\Nexis\Plugin\CatalogMenuSync::class)->ensure($siteId);
        }
    }

    private function createMenus(
        PDO $pdo,
        string $siteId,
        string $homeId,
        string $imprintId,
        string $privacyId,
        string $homeEnId,
        string $imprintEnId,
        string $privacyEnId,
        ?string $kontaktId,
        ?string $kontaktEnId,
        ?string $leistungenId,
        ?string $auftragId,
        ?string $leistungenEnId,
        ?string $auftragEnId,
        string $now,
    ): void {
        $primaryId = Uuid::v7();
        $footerId = Uuid::v7();
        $this->insert($pdo, 'menus', [
            'id' => $primaryId,
            'site_id' => $siteId,
            'handle' => 'primary',
            'name' => 'Hauptnavigation',
            'locale' => 'de',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert($pdo, 'menus', [
            'id' => $footerId,
            'site_id' => $siteId,
            'handle' => 'footer',
            'name' => 'Footer',
            'locale' => 'de',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sort = 0;
        $this->insert($pdo, 'menu_items', [
            'id' => Uuid::v7(),
            'menu_id' => $primaryId,
            'parent_id' => null,
            'page_id' => $homeId,
            'label' => 'Start',
            'url' => null,
            'sort_order' => $sort++,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($leistungenId !== null) {
            $this->insert($pdo, 'menu_items', [
                'id' => Uuid::v7(),
                'menu_id' => $primaryId,
                'parent_id' => null,
                'page_id' => $leistungenId,
                'label' => 'Leistungen',
                'url' => null,
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($auftragId !== null) {
            $this->insert($pdo, 'menu_items', [
                'id' => Uuid::v7(),
                'menu_id' => $primaryId,
                'parent_id' => null,
                'page_id' => $auftragId,
                'label' => 'Auftrag',
                'url' => null,
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($kontaktId !== null) {
            $this->insert($pdo, 'menu_items', [
                'id' => Uuid::v7(),
                'menu_id' => $primaryId,
                'parent_id' => null,
                'page_id' => $kontaktId,
                'label' => 'Kontakt',
                'url' => null,
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->insert($pdo, 'menu_items', [
            'id' => Uuid::v7(),
            'menu_id' => $footerId,
            'parent_id' => null,
            'page_id' => $imprintId,
            'label' => 'Impressum',
            'url' => null,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert($pdo, 'menu_items', [
            'id' => Uuid::v7(),
            'menu_id' => $footerId,
            'parent_id' => null,
            'page_id' => $privacyId,
            'label' => 'Datenschutz',
            'url' => null,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $primaryEnId = Uuid::v7();
        $footerEnId = Uuid::v7();
        $this->insert($pdo, 'menus', [
            'id' => $primaryEnId,
            'site_id' => $siteId,
            'handle' => 'primary',
            'name' => 'Primary navigation',
            'locale' => 'en',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert($pdo, 'menus', [
            'id' => $footerEnId,
            'site_id' => $siteId,
            'handle' => 'footer',
            'name' => 'Footer',
            'locale' => 'en',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $enSort = 0;
        $this->insert($pdo, 'menu_items', [
            'id' => Uuid::v7(),
            'menu_id' => $primaryEnId,
            'parent_id' => null,
            'page_id' => $homeEnId,
            'label' => 'Home',
            'url' => null,
            'sort_order' => $enSort++,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($leistungenEnId !== null) {
            $this->insert($pdo, 'menu_items', [
                'id' => Uuid::v7(),
                'menu_id' => $primaryEnId,
                'parent_id' => null,
                'page_id' => $leistungenEnId,
                'label' => 'Services',
                'url' => null,
                'sort_order' => $enSort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($auftragEnId !== null) {
            $this->insert($pdo, 'menu_items', [
                'id' => Uuid::v7(),
                'menu_id' => $primaryEnId,
                'parent_id' => null,
                'page_id' => $auftragEnId,
                'label' => 'Book a job',
                'url' => null,
                'sort_order' => $enSort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($kontaktEnId !== null) {
            $this->insert($pdo, 'menu_items', [
                'id' => Uuid::v7(),
                'menu_id' => $primaryEnId,
                'parent_id' => null,
                'page_id' => $kontaktEnId,
                'label' => 'Contact',
                'url' => null,
                'sort_order' => $enSort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->insert($pdo, 'menu_items', [
            'id' => Uuid::v7(),
            'menu_id' => $footerEnId,
            'parent_id' => null,
            'page_id' => $imprintEnId,
            'label' => 'Legal notice',
            'url' => null,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insert($pdo, 'menu_items', [
            'id' => Uuid::v7(),
            'menu_id' => $footerEnId,
            'parent_id' => null,
            'page_id' => $privacyEnId,
            'label' => 'Privacy',
            'url' => null,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Optional local plugin (not in the public repo). Resolve by string so
     * static analysis does not require the Workshop classes to exist.
     *
     * @param \Psr\Container\ContainerInterface $container
     */
    private function seedWorkshopDemoServices(object $container, SiteId $siteId): void
    {
        $class = 'Nexis\\Plugins\\Workshop\\WorkshopRepository';
        if (!class_exists($class) || !method_exists($container, 'get')) {
            return;
        }
        $workshop = $container->get($class);
        if (!is_object($workshop) || !method_exists($workshop, 'saveService')) {
            return;
        }
        $sort = 10;
        foreach (DefaultSiteContent::demoServices() as $service) {
            $workshop->saveService(
                $siteId,
                null,
                $service['slug'],
                $service['titles'],
                $service['descriptions'],
                null,
                ['de' => 'auf Anfrage', 'en' => 'on request'],
                $sort,
                true,
            );
            $sort += 10;
        }
    }

    /**
     * @param array<string, mixed> ...$children
     * @return array<string, mixed>
     */
    private function document(array ...$children): array
    {
        return [
            'schemaVersion' => BlockDocument::SCHEMA_VERSION,
            'root' => [
                'id' => Uuid::v7(),
                'type' => 'core/section',
                'props' => ['width' => 'wide', 'padding' => 'lg'],
                'children' => array_values($children),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function heading(string $text, int $level): array
    {
        return [
            'id' => Uuid::v7(),
            'type' => 'core/heading',
            'props' => ['text' => $text, 'level' => $level],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function text(string $text): array
    {
        return [
            'id' => Uuid::v7(),
            'type' => 'core/text',
            'props' => ['text' => $text],
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(PDO $pdo, string $table, array $row): void
    {
        $cols = array_keys($row);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        foreach ($row as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
    }
}
