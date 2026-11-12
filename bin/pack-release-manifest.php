<?php

declare(strict_types=1);

/**
 * What ships in the CMS release ZIP vs. what stays repo-only.
 *
 * New first-party plugins/themes must be added to `plugins` / `themes` explicitly
 * before they appear in a pack. Repo samples and marketplace-only packages belong
 * in `forbidden` so pack-release fails hard if they leak into staging.
 *
 * @return array{
 *   plugins: list<string>,
 *   themes: list<string>,
 *   forbidden: list<string>
 * }
 */
return [
    // Relative to plugins/
    'plugins' => [
        'nexis/blog',
        'nexis/catalog',
        'nexis/consent',
        'nexis/forms',
        'nexis/redirects',
    ],
    // Relative to themes/
    'themes' => [
        'nexis/atelier',
        'nexis/atelier-warm',
        'nexis/editorial',
        'nexis/nexis',
    ],
    // Relative to app root — must never appear in the ZIP
    'forbidden' => [
        'themes/nexis/nord',
        'plugins/acme',
        'plugins/nexis/workshop',
        'plugins/nexis/experiments',
    ],
];
