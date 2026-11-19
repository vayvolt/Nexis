<?php

declare(strict_types=1);

/**
 * Ensure Impressum / Datenschutz / AGB (and EN counterparts) exist for the installed site.
 * Idempotent: existing pages at the canonical paths are left untouched; missing footer links are added.
 *
 * Usage: php bin/seed-legal-pages.php [--email=admin@example.com]
 *
 * Exit codes: 0 = ok, 1 = no site / failure.
 */

use Nexis\Auth\UserId;
use Nexis\Auth\UserRepository;
use Nexis\Install\LegalPagesSeeder;
use Nexis\Kernel\Bootstrap;
use Nexis\Kernel\Config;
use Nexis\Site\SiteRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$app = Bootstrap::boot($root);
$sites = $app->container->get(SiteRepository::class);
$site = $sites->installed();
if ($site === null) {
    fwrite(STDERR, "Keine installierte Site gefunden.\n");
    exit(1);
}

$email = '';
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--email=')) {
        $email = trim(substr($arg, 8));
    }
}

$users = $app->container->get(UserRepository::class);
$actorId = null;
foreach ($users->listMembers($site->id) as $row) {
    /** @var \Nexis\Auth\User $member */
    $member = $row['user'];
    $isAdmin = ($row['role_slug'] ?? '') === 'admin';
    if ($actorId === null || $isAdmin) {
        $actorId = $member->id;
        if ($email === '' && $member->email !== '') {
            $email = $member->email;
        }
        if ($isAdmin) {
            break;
        }
    }
}

if (!$actorId instanceof UserId) {
    fwrite(STDERR, "Kein Benutzer für die Site gefunden (Publish-Actor).\n");
    exit(1);
}

$basePath = $app->container->get(Config::class)->publicBasePath();
$result = $app->container->get(LegalPagesSeeder::class)->ensure($site, $actorId, $basePath, $email);

fwrite(STDOUT, 'Site: ' . $site->name . "\n");
fwrite(STDOUT, 'Angelegt: ' . (count($result['created']) === 0 ? '—' : implode(', ', $result['created'])) . "\n");
fwrite(STDOUT, 'Footer ergänzt: ' . (count($result['linked']) === 0 ? '—' : implode(', ', $result['linked'])) . "\n");
fwrite(STDOUT, 'Bereits vorhanden: ' . (count($result['skipped']) === 0 ? '—' : implode(', ', $result['skipped'])) . "\n");
exit(0);
