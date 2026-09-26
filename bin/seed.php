<?php

declare(strict_types=1);

use Nexis\Auth\PasswordHasher;
use Nexis\Auth\UserId;
use Nexis\Builder\BlockDocument;
use Nexis\Builder\DocumentService;
use Nexis\Builder\PublishService;
use Nexis\Content\PageId;
use Nexis\Content\PageStatus;
use Nexis\Kernel\Bootstrap;
use Nexis\Media\MediaLibrary;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = Bootstrap::boot(dirname(__DIR__));
$pdo = $app->container->get(PDO::class);
$hasher = $app->container->get(PasswordHasher::class);
$documents = $app->container->get(DocumentService::class);
$publish = $app->container->get(PublishService::class);
$basePath = $app->container->get(\Nexis\Kernel\Config::class)->publicBasePath();
$now = gmdate('Y-m-d H:i:s.v');

$tenantId = Uuid::v7();
$siteId = Uuid::v7();
$alice = Uuid::v7();
$admin = Uuid::v7();
$groupAbout = Uuid::v7();
$groupHome = Uuid::v7();
$homeId = Uuid::v7();
$homeEn = Uuid::v7();
$aboutDe = Uuid::v7();
$aboutEn = Uuid::v7();

foreach (['plugin_baukasten_forms_submissions', 'plugin_baukasten_redirects'] as $legacy) {
    try {
        $pdo->exec('DROP TABLE IF EXISTS `' . $legacy . '`');
    } catch (Throwable) {
    }
}

$pdo->beginTransaction();

$pdo->exec('UPDATE pages SET published_revision_id = NULL, published_snapshot_id = NULL');
$pdo->exec('DELETE FROM page_snapshots');
$pdo->exec('DELETE FROM page_revisions');
foreach ([
    'DELETE FROM plugin_nexis_forms_submissions',
    'DELETE FROM plugin_nexis_redirects',
    'DELETE FROM plugin_nexis_workshop_order_events',
    'DELETE FROM plugin_nexis_workshop_orders',
    'DELETE FROM plugin_nexis_workshop_customer_profiles',
    'DELETE FROM plugin_nexis_workshop_services',
    'DELETE FROM plugin_installations',
    'DELETE FROM plugins',
] as $sql) {
    try {
        $pdo->exec($sql);
    } catch (Throwable) {
        // Tabellen entstehen erst mit Plugin-Migrationen
    }
}
$pdo->exec('DELETE FROM media_variants');
$pdo->exec('DELETE FROM media_assets');
$pdo->exec('DELETE FROM menu_items');
$pdo->exec('DELETE FROM menus');
$pdo->exec('DELETE FROM site_memberships');
$pdo->exec('DELETE FROM site_settings');
$pdo->exec('DELETE FROM pages');
$pdo->exec('DELETE FROM role_permissions');
$pdo->exec('DELETE FROM permissions');
$pdo->exec('DELETE FROM site_locales');
$pdo->exec('DELETE FROM site_domains');
$pdo->exec('DELETE FROM roles');
$pdo->exec('DELETE FROM users');
$pdo->exec('DELETE FROM sites');
$pdo->exec('DELETE FROM tenants');

insert($pdo, 'tenants', [
    'id' => $tenantId,
    'name' => 'Demo',
    'created_at' => $now,
    'updated_at' => $now,
]);

insert($pdo, 'sites', [
    'id' => $siteId,
    'tenant_id' => $tenantId,
    'name' => 'Demo Website',
    'primary_domain' => 'localhost',
    'default_locale' => 'de',
    'locale_url_strategy' => 'prefix',
    'status' => 'active',
    'created_at' => $now,
    'updated_at' => $now,
]);

insert($pdo, 'site_domains', [
    'id' => Uuid::v7(),
    'site_id' => $siteId,
    'host' => 'localhost',
    'is_primary' => 1,
    'created_at' => $now,
]);
insert($pdo, 'site_domains', [
    'id' => Uuid::v7(),
    'site_id' => $siteId,
    'host' => '127.0.0.1',
    'is_primary' => 0,
    'created_at' => $now,
]);

insert($pdo, 'site_locales', [
    'id' => Uuid::v7(),
    'site_id' => $siteId,
    'locale' => 'de',
    'label' => 'Deutsch',
    'url_prefix' => 'de',
    'hreflang' => 'de',
    'is_default' => 1,
    'enabled' => 1,
]);
insert($pdo, 'site_locales', [
    'id' => Uuid::v7(),
    'site_id' => $siteId,
    'locale' => 'en',
    'label' => 'English',
    'url_prefix' => 'en',
    'hreflang' => 'en',
    'is_default' => 0,
    'enabled' => 1,
]);

insert($pdo, 'users', [
    'id' => $alice,
    'email' => 'alice@nexis.test',
    'password_hash' => $hasher->hash('alice-dev'),
    'display_name' => 'Alice',
    'is_platform_admin' => 0,
    'ui_locale' => 'de',
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'users', [
    'id' => $admin,
    'email' => 'admin@nexis.test',
    'password_hash' => $hasher->hash('admin-dev'),
    'display_name' => 'Admin',
    'is_platform_admin' => 1,
    'ui_locale' => 'de',
    'created_at' => $now,
    'updated_at' => $now,
]);

$seeder = $app->container->get(Nexis\Auth\SystemRoleSeeder::class);
$seeder->ensureForSite(new SiteId($siteId));
$roleId = $app->container->get(Nexis\Auth\UserRepository::class)->findRoleIdBySlug(new SiteId($siteId), 'editor');
$adminRoleId = $app->container->get(Nexis\Auth\UserRepository::class)->findRoleIdBySlug(new SiteId($siteId), 'admin');
if ($roleId === null || $adminRoleId === null) {
    throw new RuntimeException('System roles missing after seed.');
}

insert($pdo, 'site_memberships', [
    'id' => Uuid::v7(),
    'site_id' => $siteId,
    'user_id' => $alice,
    'role_id' => $roleId,
    'created_at' => $now,
]);
insert($pdo, 'site_memberships', [
    'id' => Uuid::v7(),
    'site_id' => $siteId,
    'user_id' => $admin,
    'role_id' => $adminRoleId,
    'created_at' => $now,
]);

insert($pdo, 'pages', [
    'id' => $homeId,
    'site_id' => $siteId,
    'translation_group_id' => $groupHome,
    'type' => 'page',
    'slug' => 'home',
    'path' => '/',
    'locale' => 'de',
    'title' => 'Willkommen',
    'meta_title' => 'Nexis Demo – Willkommen',
    'meta_description' => 'Demo-Homepage gebaut mit dem Nexis Block-Builder.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'pages', [
    'id' => $homeEn,
    'site_id' => $siteId,
    'translation_group_id' => $groupHome,
    'type' => 'page',
    'slug' => 'home',
    'path' => '/',
    'locale' => 'en',
    'title' => 'Welcome',
    'meta_title' => 'Nexis Demo – Welcome',
    'meta_description' => 'Demo homepage built with the Nexis block builder.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'pages', [
    'id' => $aboutDe,
    'site_id' => $siteId,
    'translation_group_id' => $groupAbout,
    'type' => 'page',
    'slug' => 'ueber-uns',
    'path' => '/ueber-uns',
    'locale' => 'de',
    'title' => 'Über uns',
    'meta_title' => 'Über uns – Nexis Demo',
    'meta_description' => 'Wer wir sind und was Nexis kann.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'pages', [
    'id' => $aboutEn,
    'site_id' => $siteId,
    'translation_group_id' => $groupAbout,
    'type' => 'page',
    'slug' => 'about-us',
    'path' => '/about-us',
    'locale' => 'en',
    'title' => 'About us',
    'meta_title' => 'About us – Nexis Demo',
    'meta_description' => 'Who we are and what Nexis can do.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);

$pdo->commit();

$png = tempnam(sys_get_temp_dir(), 'bkpng');
if ($png === false) {
    throw new RuntimeException('temp file');
}
$image = imagecreatetruecolor(320, 180);
$fill = imagecolorallocate($image, 27, 77, 62);
$accent = imagecolorallocate($image, 244, 241, 234);
if ($fill === false || $accent === false) {
    throw new RuntimeException('png color');
}
imagefilledrectangle($image, 0, 0, 319, 179, $fill);
imagefilledrectangle($image, 24, 24, 295, 155, $accent);
imagestring($image, 5, 90, 80, 'Nexis', $fill);
imagepng($image, $png);
imagedestroy($image);
$handle = fopen($png, 'rb');
if ($handle === false) {
    throw new RuntimeException('png read');
}
$bytes = filesize($png);
$upload = new UploadedFile(
    Stream::create($handle),
    is_int($bytes) ? $bytes : 0,
    UPLOAD_ERR_OK,
    'marke.png',
    'image/png',
);
$asset = $app->container->get(MediaLibrary::class)->store(new SiteId($siteId), $upload, 'Nexis Demo-Marke');
unlink($png);

$discovery = $app->container->get(Nexis\Plugin\PluginDiscovery::class);
$catalog = $app->container->get(Nexis\Plugin\PluginCatalog::class);
$catalog->sync($discovery->discover());
$siteIdObj = new SiteId($siteId);
$discoveredKeys = [];
foreach ($discovery->discover() as $manifest) {
    $discoveredKeys[$manifest->id] = true;
}
foreach (['nexis/forms', 'nexis/redirects', 'nexis/consent', 'nexis/blog', 'nexis/catalog', 'nexis/workshop'] as $pluginKey) {
    if (!isset($discoveredKeys[$pluginKey])) {
        continue;
    }
    $catalog->ensureInstallation($siteIdObj, $pluginKey, Nexis\Plugin\PluginInstallStatus::Installed);
    $catalog->setStatus($siteIdObj, $pluginKey, Nexis\Plugin\PluginInstallStatus::Enabled);
}
$app->container->get(Nexis\Plugin\PluginRuntime::class)->bootOnce();

$withWorkshop = isset($discoveredKeys['nexis/workshop']);
if ($withWorkshop) {
$workshopClass = 'Nexis\\Plugins\\Workshop\\WorkshopRepository';
if (!class_exists($workshopClass)) {
    throw new RuntimeException('Workshop plugin discovered but class missing: ' . $workshopClass);
}
$workshop = $app->container->get($workshopClass);
if (!is_object($workshop) || !method_exists($workshop, 'saveService')) {
    throw new RuntimeException('WorkshopRepository::saveService unavailable.');
}
$seedService = static function (
    object $workshop,
    SiteId $siteId,
    string $titleDe,
    string $titleEn,
    string $slug,
    string $descDe,
    string $descEn,
    ?int $price,
    string $priceLabelDe,
    string $priceLabelEn,
    int $sort,
) : void {
    if (!method_exists($workshop, 'saveService')) {
        throw new RuntimeException('WorkshopRepository::saveService unavailable.');
    }
    $workshop->saveService(
        $siteId,
        null,
        $slug,
        ['de' => $titleDe, 'en' => $titleEn],
        ['de' => $descDe, 'en' => $descEn],
        $price,
        ['de' => $priceLabelDe, 'en' => $priceLabelEn],
        $sort,
        true,
    );
};
$seedService($workshop, $siteIdObj, 'HU / AU', 'MOT / emissions', 'hu-au', 'Hauptuntersuchung und Abgasuntersuchung.', 'General inspection and emissions test.', 11900, 'ab', 'from', 10);
$seedService($workshop, $siteIdObj, 'Ölwechsel', 'Oil change', 'oelwechsel', 'Motoröl und Filter nach Herstellervorgabe.', 'Engine oil and filter per manufacturer spec.', 8900, 'ab', 'from', 20);
$seedService($workshop, $siteIdObj, 'Diagnose', 'Diagnostics', 'diagnose', 'Fehlerauslese und Erstbewertung – Preis nach Aufwand.', 'Fault readout and initial assessment — price on effort.', null, '', '', 30);
}

$themeService = $app->container->get(Nexis\Theme\ThemeService::class);
$themeService->sync();
$app->container->get(Nexis\Theme\ThemeCatalog::class)->assignTheme(new SiteId($siteId), 'nexis/nexis');

insert($pdo, 'plugin_nexis_redirects', [
    'id' => Uuid::v7(),
    'site_id' => $siteId,
    'locale' => null,
    'from_path' => '/alt',
    'to_url' => $basePath . '/de',
    'status_code' => 301,
    'created_at' => $now,
]);

$actor = new UserId($admin);
$pages = $app->container->get(Nexis\Content\PageRepository::class);

$postDeId = Uuid::v7();
$postEnId = Uuid::v7();
$groupPost = Uuid::v7();
insert($pdo, 'pages', [
    'id' => $postDeId,
    'site_id' => $siteId,
    'translation_group_id' => $groupPost,
    'type' => 'post',
    'slug' => 'willkommen-im-blog',
    'path' => '/blog/willkommen-im-blog',
    'locale' => 'de',
    'title' => 'Willkommen im Blog',
    'meta_title' => 'Willkommen im Blog',
    'meta_description' => 'Erster Demo-Beitrag im Nexis Blog.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'pages', [
    'id' => $postEnId,
    'site_id' => $siteId,
    'translation_group_id' => $groupPost,
    'type' => 'post',
    'slug' => 'welcome-to-the-blog',
    'path' => '/blog/welcome-to-the-blog',
    'locale' => 'en',
    'title' => 'Welcome to the blog',
    'meta_title' => 'Welcome to the blog',
    'meta_description' => 'First demo post in the Nexis blog.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
$documents->save(new PageId($postDeId), documentWith(
    heading('Willkommen im Blog', 1),
    text('Beiträge sind Seiten mit Typ „post“ unter /blog/…. Das Archiv finden Sie unter /de/blog.'),
), null, $actor, 'Seed');
$postDe = $pages->findById(new PageId($postDeId));
if ($postDe === null) {
    throw new RuntimeException('blog post de missing');
}
$publish->publish($postDe, null, $actor, $basePath);
$documents->save(new PageId($postEnId), documentWith(
    heading('Welcome to the blog', 1),
    text('Posts are pages with type “post” under /blog/…. The archive lives at /en/blog.'),
), null, $actor, 'Seed');
$postEn = $pages->findById(new PageId($postEnId));
if ($postEn === null) {
    throw new RuntimeException('blog post en missing');
}
$publish->publish($postEn, null, $actor, $basePath);

$productDeId = Uuid::v7();
$productEnId = Uuid::v7();
$groupProduct = Uuid::v7();
insert($pdo, 'pages', [
    'id' => $productDeId,
    'site_id' => $siteId,
    'translation_group_id' => $groupProduct,
    'type' => 'product',
    'slug' => 'atelier-lampe',
    'path' => '/catalog/atelier-lampe',
    'locale' => 'de',
    'title' => 'Atelier-Lampe',
    'meta_title' => 'Atelier-Lampe',
    'meta_description' => 'Demoprodukt im Nexis-Katalog.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'pages', [
    'id' => $productEnId,
    'site_id' => $siteId,
    'translation_group_id' => $groupProduct,
    'type' => 'product',
    'slug' => 'atelier-lamp',
    'path' => '/catalog/atelier-lamp',
    'locale' => 'en',
    'title' => 'Atelier lamp',
    'meta_title' => 'Atelier lamp',
    'meta_description' => 'Demo product in the Nexis catalog.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
$documents->save(new PageId($productDeId), documentWith(
    heading('Atelier-Lampe', 1),
    text('Produkte sind Seiten mit Typ „product“ unter /catalog/…. Das Archiv finden Sie unter /de/catalog.'),
), null, $actor, 'Seed');
$productDe = $pages->findById(new PageId($productDeId));
if ($productDe === null) {
    throw new RuntimeException('catalog product de missing');
}
$publish->publish($productDe, null, $actor, $basePath);
$documents->save(new PageId($productEnId), documentWith(
    heading('Atelier lamp', 1),
    text('Products are pages with type “product” under /catalog/…. The archive lives at /en/catalog.'),
), null, $actor, 'Seed');
$productEn = $pages->findById(new PageId($productEnId));
if ($productEn === null) {
    throw new RuntimeException('catalog product en missing');
}
$publish->publish($productEn, null, $actor, $basePath);

$leistungenDeId = null;
$leistungenEnId = null;
$auftragDeId = null;
$auftragEnId = null;
if ($withWorkshop) {
$leistungenDeId = Uuid::v7();
$leistungenEnId = Uuid::v7();
$groupLeistungen = Uuid::v7();
insert($pdo, 'pages', [
    'id' => $leistungenDeId,
    'site_id' => $siteId,
    'translation_group_id' => $groupLeistungen,
    'type' => 'page',
    'slug' => 'leistungen',
    'path' => '/leistungen',
    'locale' => 'de',
    'title' => 'Leistungen',
    'meta_title' => 'Leistungen',
    'meta_description' => 'Werkstatt-Leistungen mit optionalen Preisen.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'pages', [
    'id' => $leistungenEnId,
    'site_id' => $siteId,
    'translation_group_id' => $groupLeistungen,
    'type' => 'page',
    'slug' => 'services',
    'path' => '/services',
    'locale' => 'en',
    'title' => 'Services',
    'meta_title' => 'Services',
    'meta_description' => 'Workshop services with optional prices.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
$documents->save(new PageId($leistungenDeId), documentWith(
    heading('Leistungen', 1),
    text('Transparente Services für Ihr Fahrzeug – Preise optional ausgewiesen.'),
    workshopServices('Leistungen'),
), null, $actor, 'Seed');
$leistungenDe = $pages->findById(new PageId($leistungenDeId));
if ($leistungenDe === null) {
    throw new RuntimeException('leistungen de missing');
}
$publish->publish($leistungenDe, null, $actor, $basePath);
$documents->save(new PageId($leistungenEnId), documentWith(
    heading('Services', 1),
    text('Transparent workshop services — prices shown when available.'),
    workshopServices('Services'),
), null, $actor, 'Seed');
$leistungenEn = $pages->findById(new PageId($leistungenEnId));
if ($leistungenEn === null) {
    throw new RuntimeException('leistungen en missing');
}
$publish->publish($leistungenEn, null, $actor, $basePath);

$auftragDeId = Uuid::v7();
$auftragEnId = Uuid::v7();
$groupAuftrag = Uuid::v7();
insert($pdo, 'pages', [
    'id' => $auftragDeId,
    'site_id' => $siteId,
    'translation_group_id' => $groupAuftrag,
    'type' => 'page',
    'slug' => 'auftrag',
    'path' => '/auftrag',
    'locale' => 'de',
    'title' => 'Auftrag anmelden',
    'meta_title' => 'Auftrag anmelden',
    'meta_description' => 'Werkstattauftrag über Ihr Kundenkonto anmelden.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'pages', [
    'id' => $auftragEnId,
    'site_id' => $siteId,
    'translation_group_id' => $groupAuftrag,
    'type' => 'page',
    'slug' => 'request',
    'path' => '/request',
    'locale' => 'en',
    'title' => 'Book a job',
    'meta_title' => 'Book a job',
    'meta_description' => 'Submit a workshop job via your customer account.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
$documents->save(new PageId($auftragDeId), documentWith(
    heading('Auftrag anmelden', 1),
    text('Nach dem Absenden sehen Sie den Fortschritt jederzeit in Ihrem Kundenkonto.'),
    workshopRequest(),
), null, $actor, 'Seed');
$auftragDe = $pages->findById(new PageId($auftragDeId));
if ($auftragDe === null) {
    throw new RuntimeException('auftrag de missing');
}
$publish->publish($auftragDe, null, $actor, $basePath);
$documents->save(new PageId($auftragEnId), documentWith(
    heading('Book a job', 1),
    text('After submitting, track progress anytime in your customer account.'),
    workshopRequest(),
), null, $actor, 'Seed');
$auftragEn = $pages->findById(new PageId($auftragEnId));
if ($auftragEn === null) {
    throw new RuntimeException('auftrag en missing');
}
$publish->publish($auftragEn, null, $actor, $basePath);
}

$homeDoc = documentWith(
    heading('Willkommen bei Nexis', 1),
    text('Bauen Sie Seiten aus Blöcken, veröffentlichen Sie Revisionen und erweitern Sie die Seite mit Plugins.'),
    columns(
        text('Visueller Builder mit Abschnitten, Text, Bildern und Formularen.'),
        text('Themes und Tokens steuern das Erscheinungsbild ohne Fork.'),
    ),
    image($asset->id->value, 'Nexis Demo'),
    blogPosts('Aktuelles aus dem Blog'),
    catalogProducts('Aus dem Katalog'),
    button('Über uns', $basePath . '/de/ueber-uns'),
);
$documents->save(new PageId($homeId), $homeDoc, null, $actor, 'Seed');
$home = $pages->findById(new PageId($homeId));
if ($home === null) {
    throw new RuntimeException('home missing');
}
$publish->publish($home, null, $actor, $basePath);

$homeEnDoc = documentWith(
    heading('Welcome to Nexis', 1),
    text('Build pages from blocks, publish revisions, and extend the site with plugins.'),
    columns(
        text('Visual builder with sections, text, images and forms.'),
        text('Themes and tokens control appearance without forking.'),
    ),
    image($asset->id->value, 'Nexis Demo'),
    button('About us', $basePath . '/en/about-us'),
);
$documents->save(new PageId($homeEn), $homeEnDoc, null, $actor, 'Seed');
$homeEnPage = $pages->findById(new PageId($homeEn));
if ($homeEnPage === null) {
    throw new RuntimeException('home en missing');
}
$publish->publish($homeEnPage, null, $actor, $basePath);

$aboutDeDoc = documentWith(
    heading('Über uns', 1),
    text('Wir bauen individuelle Homepages.'),
    image($asset->id->value, 'Marke'),
    contactForm(),
);
$documents->save(new PageId($aboutDe), $aboutDeDoc, null, $actor, 'Seed');
$aboutDePage = $pages->findById(new PageId($aboutDe));
if ($aboutDePage === null) {
    throw new RuntimeException('about de missing');
}
$publish->publish($aboutDePage, null, $actor, $basePath);

$aboutEnDoc = documentWith(
    heading('About us', 1),
    text('We build tailored homepages.'),
    image($asset->id->value, 'Brand'),
    contactFormEn(),
);
$documents->save(new PageId($aboutEn), $aboutEnDoc, null, $actor, 'Seed');
$aboutEnPage = $pages->findById(new PageId($aboutEn));
if ($aboutEnPage === null) {
    throw new RuntimeException('about en missing');
}
$publish->publish($aboutEnPage, null, $actor, $basePath);

$menuDe = Uuid::v7();
$menuEn = Uuid::v7();
insert($pdo, 'menus', [
    'id' => $menuDe,
    'site_id' => $siteId,
    'handle' => 'primary',
    'name' => 'Hauptnavigation',
    'locale' => 'de',
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'menus', [
    'id' => $menuEn,
    'site_id' => $siteId,
    'handle' => 'primary',
    'name' => 'Primary navigation',
    'locale' => 'en',
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $menuDe,
    'parent_id' => null,
    'page_id' => $homeId,
    'label' => 'Start',
    'url' => null,
    'sort_order' => 0,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $menuDe,
    'parent_id' => null,
    'page_id' => $aboutDe,
    'label' => 'Über uns',
    'url' => null,
    'sort_order' => 1,
    'created_at' => $now,
    'updated_at' => $now,
]);
if ($leistungenDeId !== null) {
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $menuDe,
    'parent_id' => null,
    'page_id' => $leistungenDeId,
    'label' => 'Leistungen',
    'url' => null,
    'sort_order' => 2,
    'created_at' => $now,
    'updated_at' => $now,
]);
}
if ($auftragDeId !== null) {
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $menuDe,
    'parent_id' => null,
    'page_id' => $auftragDeId,
    'label' => 'Auftrag',
    'url' => null,
    'sort_order' => 3,
    'created_at' => $now,
    'updated_at' => $now,
]);
}
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $menuEn,
    'parent_id' => null,
    'page_id' => $homeEn,
    'label' => 'Home',
    'url' => null,
    'sort_order' => 0,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $menuEn,
    'parent_id' => null,
    'page_id' => $aboutEn,
    'label' => 'About',
    'url' => null,
    'sort_order' => 1,
    'created_at' => $now,
    'updated_at' => $now,
]);
if ($leistungenEnId !== null) {
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $menuEn,
    'parent_id' => null,
    'page_id' => $leistungenEnId,
    'label' => 'Services',
    'url' => null,
    'sort_order' => 2,
    'created_at' => $now,
    'updated_at' => $now,
]);
}
if ($auftragEnId !== null) {
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $menuEn,
    'parent_id' => null,
    'page_id' => $auftragEnId,
    'label' => 'Book a job',
    'url' => null,
    'sort_order' => 3,
    'created_at' => $now,
    'updated_at' => $now,
]);
}
$imprintDe = Uuid::v7();
$imprintEn = Uuid::v7();
$groupImprint = Uuid::v7();
insert($pdo, 'pages', [
    'id' => $imprintDe,
    'site_id' => $siteId,
    'translation_group_id' => $groupImprint,
    'type' => 'page',
    'slug' => 'impressum',
    'path' => '/impressum',
    'locale' => 'de',
    'title' => 'Impressum',
    'meta_title' => 'Impressum',
    'meta_description' => 'Rechtliche Angaben.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'pages', [
    'id' => $imprintEn,
    'site_id' => $siteId,
    'translation_group_id' => $groupImprint,
    'type' => 'page',
    'slug' => 'legal-notice',
    'path' => '/legal-notice',
    'locale' => 'en',
    'title' => 'Legal notice',
    'meta_title' => 'Legal notice',
    'meta_description' => 'Legal information.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);

$documents->save(new PageId($imprintDe), documentWith(
    heading('Impressum', 1),
    text("Demo-Impressum.\nFirma: Nexis Demo\nOrt: Beispielstadt"),
), null, $actor, 'Seed');
$imprintDePage = $pages->findById(new PageId($imprintDe));
if ($imprintDePage === null) {
    throw new RuntimeException('imprint de missing');
}
$publish->publish($imprintDePage, null, $actor, $basePath);

$documents->save(new PageId($imprintEn), documentWith(
    heading('Legal notice', 1),
    text("Demo legal notice.\nCompany: Nexis Demo\nCity: Example City"),
), null, $actor, 'Seed');
$imprintEnPage = $pages->findById(new PageId($imprintEn));
if ($imprintEnPage === null) {
    throw new RuntimeException('imprint en missing');
}
$publish->publish($imprintEnPage, null, $actor, $basePath);

$privacyDe = Uuid::v7();
$privacyEn = Uuid::v7();
$groupPrivacy = Uuid::v7();
insert($pdo, 'pages', [
    'id' => $privacyDe,
    'site_id' => $siteId,
    'translation_group_id' => $groupPrivacy,
    'type' => 'page',
    'slug' => 'datenschutz',
    'path' => '/datenschutz',
    'locale' => 'de',
    'title' => 'Datenschutzerklärung',
    'meta_title' => 'Datenschutzerklärung',
    'meta_description' => 'Informationen zur Verarbeitung personenbezogener Daten.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'pages', [
    'id' => $privacyEn,
    'site_id' => $siteId,
    'translation_group_id' => $groupPrivacy,
    'type' => 'page',
    'slug' => 'privacy',
    'path' => '/privacy',
    'locale' => 'en',
    'title' => 'Privacy policy',
    'meta_title' => 'Privacy policy',
    'meta_description' => 'Information on how we process personal data.',
    'body_text' => null,
    'status' => PageStatus::Draft->value,
    'created_at' => $now,
    'updated_at' => $now,
]);

$privacyDeText = "Stand: [Datum]\n\n"
    . "1. Verantwortlicher\n"
    . "Nexis Demo\n"
    . "[Straße und Hausnummer]\n"
    . "[PLZ Ort]\n"
    . "E-Mail: admin@nexis.test\n\n"
    . "2. Technisch notwendige Cookies\n"
    . "Für den Betrieb der Website setzen wir technisch notwendige Cookies ein (z. B. Session, CSRF-Schutz). "
    . "Diese sind für die Funktion erforderlich und werden ohne Einwilligung gesetzt.\n\n"
    . "3. Kontaktformular\n"
    . "Wenn Sie uns über das Kontaktformular schreiben, verarbeiten wir Ihre Angaben zur Bearbeitung der Anfrage.\n\n"
    . "4. Analyse und Marketing (Google)\n"
    . "Sofern Google Analytics, Google Tag Manager oder Google Ads eingebunden sind, erfolgt dies nur nach Ihrer Einwilligung über das Cookie-Banner.\n\n"
    . "5. Ihre Rechte\n"
    . "Sie haben Rechte auf Auskunft, Berichtigung, Löschung, Einschränkung, Widerspruch und Datenübertragbarkeit gemäß DSGVO.\n\n"
    . "Bitte passen Sie diese Vorlage an Ihre tatsächliche Datenverarbeitung an.";
$privacyEnText = "Last updated: [date]\n\n"
    . "1. Controller\n"
    . "Nexis Demo\n"
    . "[Street and number]\n"
    . "[Postal code City]\n"
    . "Email: admin@nexis.test\n\n"
    . "2. Strictly necessary cookies\n"
    . "We use strictly necessary cookies to operate this website (e.g. session, CSRF protection). "
    . "They are required for functionality and are set without consent.\n\n"
    . "3. Contact form\n"
    . "If you contact us via the form, we process your details to handle your request.\n\n"
    . "4. Analytics and marketing (Google)\n"
    . "If Google Analytics, Google Tag Manager or Google Ads are enabled, this happens only after your consent via the cookie banner.\n\n"
    . "5. Your rights\n"
    . "You have rights of access, rectification, erasure, restriction, objection and data portability under the GDPR.\n\n"
    . "Please adapt this template to your actual data processing.";

$documents->save(new PageId($privacyDe), documentWith(
    heading('Datenschutzerklärung', 1),
    text($privacyDeText),
), null, $actor, 'Seed');
$privacyDePage = $pages->findById(new PageId($privacyDe));
if ($privacyDePage === null) {
    throw new RuntimeException('privacy de missing');
}
$publish->publish($privacyDePage, null, $actor, $basePath);

$documents->save(new PageId($privacyEn), documentWith(
    heading('Privacy policy', 1),
    text($privacyEnText),
), null, $actor, 'Seed');
$privacyEnPage = $pages->findById(new PageId($privacyEn));
if ($privacyEnPage === null) {
    throw new RuntimeException('privacy en missing');
}
$publish->publish($privacyEnPage, null, $actor, $basePath);

$footerDe = Uuid::v7();
$footerEn = Uuid::v7();
insert($pdo, 'menus', [
    'id' => $footerDe,
    'site_id' => $siteId,
    'handle' => 'footer',
    'name' => 'Footer',
    'locale' => 'de',
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'menus', [
    'id' => $footerEn,
    'site_id' => $siteId,
    'handle' => 'footer',
    'name' => 'Footer',
    'locale' => 'en',
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $footerDe,
    'parent_id' => null,
    'page_id' => $imprintDe,
    'label' => 'Impressum',
    'url' => null,
    'sort_order' => 0,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $footerDe,
    'parent_id' => null,
    'page_id' => $privacyDe,
    'label' => 'Datenschutz',
    'url' => null,
    'sort_order' => 1,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $footerEn,
    'parent_id' => null,
    'page_id' => $imprintEn,
    'label' => 'Legal notice',
    'url' => null,
    'sort_order' => 0,
    'created_at' => $now,
    'updated_at' => $now,
]);
insert($pdo, 'menu_items', [
    'id' => Uuid::v7(),
    'menu_id' => $footerEn,
    'parent_id' => null,
    'page_id' => $privacyEn,
    'label' => 'Privacy',
    'url' => null,
    'sort_order' => 1,
    'created_at' => $now,
    'updated_at' => $now,
]);

$app->container->get(Nexis\Site\PdoSiteSettingsRepository::class)
    ->set(new SiteId($siteId), 'plugin:nexis/forms.notify_email', 'admin@nexis.test');
$settings = $app->container->get(Nexis\Site\PdoSiteSettingsRepository::class);
if ($withWorkshop) {
    $settings->set(new SiteId($siteId), 'plugin:nexis/workshop.notify_email', 'admin@nexis.test');
}
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.enabled', true);
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.privacy_url', $basePath . '/de/datenschutz');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.ga_measurement_id', '');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.gtm_id', '');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.google_ads_id', '');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.text.de', 'Wir verwenden Cookies und ähnliche Technologien für den Betrieb dieser Website. Mit Ihrer Einwilligung nutzen wir auch Analyse- und Marketing-Dienste von Google.');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.text.en', 'We use cookies and similar technologies to operate this website. With your consent we also use Google analytics and marketing services.');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.accept_label.de', 'Alle akzeptieren');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.accept_label.en', 'Accept all');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.reject_label.de', 'Nur notwendige');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.reject_label.en', 'Essential only');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.save_label.de', 'Auswahl speichern');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.save_label.en', 'Save selection');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.privacy_label.de', 'Datenschutz');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.privacy_label.en', 'Privacy');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.analytics_label.de', 'Analyse (Google Analytics)');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.analytics_label.en', 'Analytics (Google Analytics)');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.marketing_label.de', 'Marketing (Google Ads)');
$settings->set(new SiteId($siteId), 'plugin:nexis/consent.marketing_label.en', 'Marketing (Google Ads)');
$settings->set(new SiteId($siteId), 'auth.registration_enabled', true);
$settings->set(new SiteId($siteId), 'auth.password_reset_enabled', true);

$app->container->get(Nexis\Plugin\BlogMenuSync::class)->ensure(new SiteId($siteId));
$app->container->get(Nexis\Plugin\CatalogMenuSync::class)->ensure(new SiteId($siteId));

fwrite(STDOUT, "seed ok\n");
fwrite(STDOUT, "alice@nexis.test / alice-dev  (Redaktion — ohne plugin/users/settings)\n");
fwrite(STDOUT, "admin@nexis.test / admin-dev  (Plattform-Admin + Website-Admin)\n");
fwrite(STDOUT, "forms notify: admin@nexis.test (MAIL_TRANSPORT=log → storage/logs/mail.log)\n");
fwrite(STDOUT, "plugins: forms + redirects + consent + blog + catalog"
    . ($withWorkshop ? ' + workshop' : '') . " enabled\n");
fwrite(STDOUT, "consent google: IDs unter /admin/consent setzen (GA4/GTM/Ads)\n");
fwrite(STDOUT, "blog: {$basePath}/de/blog · Admin /admin/blog\n");
fwrite(STDOUT, "catalog: {$basePath}/de/catalog · Admin /admin/catalog\n");
if ($withWorkshop) {
    fwrite(STDOUT, "workshop: Admin /admin/workshop · Account /account/orders · /de/leistungen · /de/auftrag\n");
}
fwrite(STDOUT, "theme: nexis/nexis\n");
fwrite(STDOUT, 'demo png: /media/' . $asset->id->value . PHP_EOL);

/**
 * @param array<string, mixed> $data
 */
function insert(PDO $pdo, string $table, array $data): void
{
    $cols = array_keys($data);
    $placeholders = [];
    foreach ($cols as $col) {
        $placeholders[] = ':' . $col;
    }
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

/**
 * @param array<string, mixed> ...$children
 * @return array<string, mixed>
 */
function documentWith(array ...$children): array
{
    return [
        'schemaVersion' => BlockDocument::SCHEMA_VERSION,
        'root' => [
            'id' => Uuid::v7(),
            'type' => 'core/section',
            'props' => ['width' => 'wide', 'padding' => 'lg'],
            'children' => $children,
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function heading(string $text, int $level): array
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
function text(string $text): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'core/text',
        'props' => ['text' => $text],
    ];
}

/**
 * @return array<string, mixed>
 */
function button(string $label, string $href): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'core/button',
        'props' => ['label' => $label, 'href' => $href, 'style' => 'primary'],
    ];
}

/**
 * @return array<string, mixed>
 */
function image(string $assetId, string $alt): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'core/image',
        'props' => ['assetId' => $assetId, 'alt' => $alt],
    ];
}

/**
 * @param array<string, mixed> ...$children
 * @return array<string, mixed>
 */
function columns(array ...$children): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'core/columns',
        'props' => ['columns' => max(2, min(4, count($children) ?: 2))],
        'children' => $children,
    ];
}

/**
 * @return array<string, mixed>
 */
function contactForm(): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'nexis/forms/contact',
        'props' => [
            'heading' => 'Schreiben Sie uns',
            'submitLabel' => 'Absenden',
            'successMessage' => 'Danke! Wir melden uns bald.',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function contactFormEn(): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'nexis/forms/contact',
        'props' => [
            'heading' => 'Get in touch',
            'submitLabel' => 'Send',
            'successMessage' => 'Thanks! We will get back to you.',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function blogPosts(string $heading = 'Aktuelles'): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'nexis/blog/posts',
        'props' => [
            'heading' => $heading,
            'limit' => 5,
            'emptyText' => 'Noch keine Beiträge.',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function catalogProducts(string $heading = 'Produkte'): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'nexis/catalog/products',
        'props' => [
            'heading' => $heading,
            'limit' => 6,
            'emptyText' => 'Noch keine Produkte.',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function workshopServices(string $heading = 'Leistungen'): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'nexis/workshop/services',
        'props' => [
            'heading' => $heading,
            'showPrice' => true,
            'emptyText' => 'Aktuell keine Leistungen.',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function workshopRequest(): array
{
    return [
        'id' => Uuid::v7(),
        'type' => 'nexis/workshop/request',
        'props' => [
            'heading' => 'Auftrag anmelden',
            'intro' => 'Melden Sie sich an und beschreiben Sie Ihr Anliegen.',
            'submitLabel' => 'Auftrag absenden',
        ],
    ];
}
