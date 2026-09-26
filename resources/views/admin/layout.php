<!DOCTYPE html>
<html lang="<?php echo $e($uiLocale ?? 'de') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $e($title ?? 'Nexis Admin') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $e(\Nexis\Kernel\Nexis::brandUrl((string) ($basePath ?? ''), \Nexis\Kernel\Nexis::BRAND_ICON)) ?>">
    <style>
        :root { color-scheme: light; --bg:#f4f1ea; --ink:#1c1917; --muted:#57534e; --line:#d6d3d1; --accent:#1b4d3e; --card:#fff; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.5 "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--ink); }
        a { color: var(--accent); }
        header.admin-top {
            display: flex; justify-content: space-between; align-items: center; gap: .85rem 1.25rem;
            padding: .55rem 1.25rem; border-bottom: 1px solid var(--line); background: var(--card); flex-wrap: wrap;
        }
        header.admin-top .brand { font-weight: 700; font-size: 1.05rem; }
        .admin-nav {
            display: flex; flex-wrap: wrap; align-items: center; gap: .15rem; flex: 1; min-width: 0;
        }
        .admin-nav__primary,
        .admin-nav__plugins {
            display: flex; flex-wrap: wrap; align-items: center; gap: .1rem;
        }
        .admin-nav__sep {
            width: 1px; height: 1.15rem; background: var(--line); margin: 0 .4rem; flex: 0 0 auto;
        }
        .admin-nav a,
        .admin-nav-drop > summary {
            display: inline-flex; align-items: center; gap: .25rem;
            padding: .35rem .55rem; border-radius: 6px; text-decoration: none;
            color: var(--ink); line-height: 1.2; border: 0; background: transparent;
            cursor: pointer; font: inherit; font-size: .875rem; font-weight: 600;
        }
        .admin-nav a:hover,
        .admin-nav-drop > summary:hover { background: #f5f5f4; color: var(--accent); text-decoration: none; }
        .admin-nav a.is-active,
        .admin-nav-drop > summary.is-active { background: #ecfdf5; color: var(--accent); }
        .admin-nav-drop { position: relative; }
        .admin-nav-drop > summary { list-style: none; }
        .admin-nav-drop > summary::-webkit-details-marker { display: none; }
        .admin-nav-drop > summary::after {
            content: ""; width: .35rem; height: .35rem; border-right: 1.5px solid currentColor;
            border-bottom: 1.5px solid currentColor; transform: rotate(45deg); margin-top: -.15rem; opacity: .7;
        }
        .admin-nav-drop[open] > summary { background: #f5f5f4; color: var(--accent); }
        .admin-nav-drop__panel {
            position: absolute; top: calc(100% + .3rem); left: 0; z-index: 50;
            min-width: 11.5rem; padding: .35rem;
            background: var(--card); border: 1px solid var(--line); border-radius: 8px;
            box-shadow: 0 10px 28px rgba(28, 25, 23, .1);
            display: flex; flex-direction: column; gap: .1rem;
        }
        .admin-nav-drop__panel a { display: block; width: 100%; padding: .45rem .65rem; border-radius: 5px; }
        .admin-user { display: flex; gap: .55rem; align-items: center; margin-left: auto; flex-wrap: wrap; }
        .admin-locale {
            display: inline-flex; align-items: center; gap: .15rem;
            padding: .15rem; border: 1px solid var(--line); border-radius: 7px; background: #fafaf9;
        }
        .admin-locale a,
        .admin-locale strong {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2.1rem; padding: .25rem .45rem; border-radius: 5px;
            text-decoration: none; font-size: .75rem; font-weight: 700; letter-spacing: .02em;
            color: var(--muted); text-transform: uppercase;
        }
        .admin-locale a:hover { color: var(--accent); background: #fff; text-decoration: none; }
        .admin-locale strong {
            color: var(--accent); background: #fff; box-shadow: 0 0 0 1px var(--line);
        }
        .admin-user__name {
            font-size: .875rem; color: var(--muted); max-width: 8rem;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .admin-nav-shell {
            flex: 1; min-width: 0; display: flex; align-items: center; gap: .5rem;
        }
        .admin-nav-burger {
            display: none; appearance: none; border: 1px solid var(--line); border-radius: 6px;
            background: #fafaf9; color: var(--ink); font: inherit; font-size: .875rem; font-weight: 700;
            padding: .4rem .7rem; cursor: pointer;
        }
        .admin-nav-burger:hover { background: #f5f5f4; color: var(--accent); }
        main { width: 100%; max-width: none; margin: 0; padding: 1rem 1.75rem 1.5rem; }
        main > h1 {
            margin: 0 0 .45rem;
            font-size: 1.55rem;
            line-height: 1.25;
        }
        main > h2 {
            margin: 1.5rem 0 .4rem;
            font-size: 1.25rem;
            line-height: 1.3;
        }
        main > h3 {
            margin: 1.25rem 0 .35rem;
            font-size: 1.1rem;
            line-height: 1.3;
        }
        main > h1 + .muted,
        main > h2 + .muted,
        main > h3 + .muted { margin-top: 0; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
        .card > h2,
        .card > h3 {
            margin: 0 0 .65rem;
            font-size: 1.15rem;
            line-height: 1.3;
        }
        .card > h3 { font-size: 1.05rem; }
        .card > h2:not(:first-child),
        .card > h3:not(:first-child) { margin-top: 1rem; }
        .card > h2 + .muted,
        .card > h3 + .muted { margin-top: 0; }
        .nx-pages-locales {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            align-items: center;
        }
        .nx-pages-locale {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            text-decoration: none;
            color: inherit;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: .2rem .35rem .2rem .45rem;
            background: #fff;
        }
        .nx-pages-locale:hover { border-color: var(--accent); background: #f0fdf4; text-decoration: none; }
        .nx-pages-locale--missing {
            opacity: .55;
            border-style: dashed;
        }
        .nx-pages-locale__code {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .nx-seo-summary-score {
            display: inline-block;
            font-size: .75rem;
            font-weight: 700;
            color: #1b4d3e;
            background: #ecfdf5;
            border-radius: 999px;
            padding: .1rem .4rem;
            text-decoration: none;
            white-space: nowrap;
            min-width: 1.75rem;
            text-align: center;
        }
        .nx-seo-summary-score.is-mid { color: #92400e; background: #fef3c7; }
        .nx-seo-summary-score.is-low { color: #9f1239; background: #ffe4e6; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .5rem .4rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        input, textarea, select { width: 100%; padding: .5rem .6rem; border: 1px solid var(--line); border-radius: 6px; font: inherit; }
        label { display: block; font-weight: 600; margin: .75rem 0 .3rem; }
        label.check-label { display: flex; align-items: center; gap: .5rem; font-weight: 600; margin: 0 0 1rem; cursor: pointer; }
        label.check-label input[type="checkbox"] { width: auto; margin: 0; flex: 0 0 auto; }
        input[type="checkbox"] { width: auto; }
        button, .btn { display: inline-block; background: var(--accent); color: #fff; border: 0; border-radius: 6px; padding: .5rem .9rem; text-decoration: none; cursor: pointer; font: inherit; }
        .muted { color: var(--muted); }
        .error { color: #9f1239; margin: 0 0 1rem; }
        .flash {
            margin: 0 0 1.1rem;
            padding: .75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--line);
            font-weight: 600;
        }
        .flash--success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }
        .flash--error {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #9f1239;
        }
        .flash--info {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e3a8a;
        }
        .row { display: flex; gap: .75rem; flex-wrap: wrap; }
        .row > * { flex: 1; min-width: 12rem; }
        .badge { display: inline-block; font-size: .75rem; font-weight: 600; padding: .15rem .45rem; border-radius: 4px; background: #e7e5e4; color: #44403c; }
        .badge-admin { background: #dcfce7; color: #166534; }
        .badge-editor { background: #fef3c7; color: #92400e; }
        .totp-qr { display: inline-block; margin: .75rem 0; padding: .5rem; background: #fff; border: 1px solid var(--line); border-radius: 8px; }
        .totp-qr svg { display: block; width: 200px; height: 200px; }
        .btn-small { padding: .3rem .55rem; font-size: .85rem; }
        .btn-danger { background: #9f1239; }
        .btn-muted { background: #78716c; }
        .btn-muted:hover { background: #57534e; }
        table form { margin: 0; }
        table .btn, table button { margin: .1rem .15rem .1rem 0; }
        .row-actions {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: .35rem;
            white-space: nowrap;
        }
        .row-actions form { display: inline; margin: 0; }
        .row-actions .btn,
        .row-actions button { margin: 0; flex-shrink: 0; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: 1rem; }
        .media-card { background: var(--card); border: 1px solid var(--line); border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; }
        .media-thumb { aspect-ratio: 4/3; background: #e7e5e4; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: .5rem; }
        .media-thumb img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; }
        .media-thumb--focus { position: relative; padding: 0; cursor: crosshair; }
        .media-thumb--focus img { width: 100%; height: 100%; max-width: none; max-height: none; object-fit: cover; }
        .media-focus-marker {
            position: absolute; width: 12px; height: 12px; margin: -6px 0 0 -6px;
            border: 2px solid #fff; border-radius: 50%; background: #0c0a09;
            box-shadow: 0 0 0 1px rgba(0,0,0,.35); pointer-events: none;
        }
        .media-focus-hint { margin: 0; font-size: .8rem; }
        .nx-media-folders { display: flex; flex-direction: column; gap: .25rem; }
        .nx-media-folder-row {
            display: flex; align-items: center; gap: .35rem; min-width: 0;
        }
        .nx-media-folder-link {
            display: block; flex: 1; min-width: 0;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            padding: .3rem .4rem; border-radius: 6px; text-decoration: none; color: inherit;
        }
        .nx-media-folder-link:hover { background: #f5f5f4; text-decoration: none; }
        .nx-media-folder-link[aria-current="page"] { background: #ecfdf5; color: var(--accent); font-weight: 650; }
        .nx-media-folder-delete { margin: 0; flex: 0 0 auto; }
        .nx-media-folder-delete button {
            min-width: 1.75rem; padding: .2rem .4rem; line-height: 1.1;
        }
        .media-variants {
            margin: .45rem 0 0; padding: .55rem .7rem; border: 1px solid var(--line);
            border-radius: 8px; background: #fafaf9;
        }
        .media-variants > summary {
            cursor: pointer; font-weight: 650; font-size: .9rem; list-style: none;
        }
        .media-variants > summary::-webkit-details-marker { display: none; }
        .media-variants[open] > summary { margin-bottom: .45rem; }
        .media-variants__head {
            display: flex; align-items: center; justify-content: flex-end; gap: .5rem; flex-wrap: wrap;
            margin-bottom: .45rem;
        }
        .media-variants__regen { margin: 0; }
        .media-edit-form { display: flex; flex-direction: column; gap: .35rem; margin: 0; }
        .media-edit-form label { margin-top: .15rem; }
        .media-edit-actions { margin: .35rem 0 0; }
        .media-variants__list {
            list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .55rem;
        }
        .media-variants__list li { display: flex; flex-direction: column; gap: .2rem; }
        .media-variants__meta { display: flex; flex-wrap: wrap; gap: .25rem .5rem; align-items: baseline; }
        .media-variants__meta strong { font-size: .85rem; }
        .media-file-badge { font-weight: 700; color: var(--muted); letter-spacing: .04em; }
        .media-body { padding: .85rem 1rem 1rem; display: flex; flex-direction: column; gap: .35rem; flex: 1; }
        .media-name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .media-meta { margin: 0; font-size: .85rem; }
        .media-url-row { display: flex; gap: .35rem; }
        .media-url-row input { margin: 0; font-size: .85rem; }
        .media-actions { display: flex; gap: .35rem; align-items: center; margin-top: .35rem; flex-wrap: wrap; }
        .media-actions form { margin: 0; }
        .admin-modal {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 0;
            max-width: 26rem;
            width: calc(100% - 2rem);
            box-shadow: 0 18px 48px rgba(28, 25, 23, .22);
        }
        .admin-modal--wide { max-width: 36rem; }
        .admin-modal::backdrop { background: rgba(28, 25, 23, .45); }
        .admin-modal form { margin: 0; padding: 1.25rem 1.35rem 1.35rem; }
        .admin-modal h2 { margin: 0 0 .85rem; font-size: 1.15rem; }
        .admin-modal p { margin: 0 0 .75rem; }
        .admin-modal .row { margin-bottom: .75rem; }
        .admin-modal__actions {
            display: flex; justify-content: flex-end; gap: .5rem; flex-wrap: wrap;
            margin-top: 1.15rem;
        }
        code { font-size: .85em; background: #f5f5f4; padding: .1rem .3rem; border-radius: 4px; }
        .dash-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1rem; }
        .dash-card h2 { margin: 0 0 .35rem; font-size: 1.1rem; }
        .dash-links { display: flex; flex-wrap: wrap; gap: .4rem; margin: .75rem 0 0; }
        .about-version { font-size: 1.15rem; margin: .35rem 0 .5rem; }
        .about-update { margin: 1rem 0 1.25rem; padding-top: .85rem; border-top: 1px solid var(--line); }
        .about-update h3 { margin: 0 0 .55rem; font-size: 1rem; }
        .about-meta { display: grid; grid-template-columns: 9rem 1fr; gap: .45rem .85rem; margin: 1rem 0 0; }
        .about-meta dt { margin: 0; color: var(--muted); font-weight: 600; }
        .about-meta dd { margin: 0; }
        .about-disclaimer { margin: 1rem 0 0; }
        .about-logo { height: 2.75rem; width: auto; }
        .license-text {
            margin: 0; white-space: pre-wrap; word-break: break-word;
            font: .8rem/1.45 ui-monospace, Consolas, monospace;
            max-height: 70vh; overflow: auto;
        }
        .admin-footer {
            width: 100%; max-width: none; margin: 0; padding: 0 1.75rem 1.5rem;
            font-size: .85rem; color: var(--muted);
        }
        .admin-footer a { color: var(--muted); }
        .brand-stack { display: flex; flex-direction: column; gap: .15rem; flex: 0 0 auto; }
        .nexis-brand { display: inline-flex; align-items: center; text-decoration: none; color: inherit; }
        .nexis-brand__img { display: block; }
        .nexis-brand__img--logo { height: 2rem; width: auto; }
        .nexis-brand__img--icon { width: 2rem; height: 2rem; }
        .brand-vendor { font-size: .65rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); padding-left: .1rem; }
        .forms-table td { vertical-align: middle; }
        .forms-row-unread td { font-weight: 600; }
        .forms-row-unread td.muted, .forms-row-unread .muted { font-weight: 500; }
        .forms-row-active { background: #f0fdf4; }
        .forms-dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; background: var(--accent); }
        .forms-message { white-space: pre-wrap; background: #f5f5f4; border: 1px solid var(--line); border-radius: 6px; padding: .85rem 1rem; margin-top: .5rem; }
        .locale-tabs__list {
            display: flex; flex-wrap: wrap; gap: .35rem; margin: 0 0 1rem;
            border-bottom: 1px solid var(--line); padding-bottom: .5rem;
        }
        .locale-tabs__tab {
            appearance: none; border: 1px solid transparent; background: transparent;
            color: var(--muted); font: inherit; font-weight: 600; font-size: .9rem;
            padding: .4rem .75rem; border-radius: 6px 6px 0 0; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center;
        }
        .locale-tabs__tab:hover { color: var(--ink); background: #f5f5f4; text-decoration: none; }
        .locale-tabs__tab[aria-selected="true"],
        .locale-tabs__tab[aria-current="page"] {
            color: var(--accent); background: #fff; border-color: var(--line); border-bottom-color: #fff;
            margin-bottom: -1px; box-shadow: 0 1px 0 #fff;
        }
        .locale-tabs__tab--missing { opacity: .75; border-style: dashed; border-color: var(--line); border-radius: 6px; }
        .locale-tabs__panel[hidden] { display: none !important; }
        .locale-tabs__panel > h3 { margin: 0 0 .5rem; font-size: 1rem; }
        .locale-tabs[data-active="locales"] .settings-main-actions { display: none; }
        .locale-tabs[data-active="theme"] .theme-design-actions { display: none; }
        @media (max-width: 960px) {
            .admin-nav-shell { flex: 0 0 auto; position: relative; }
            .admin-nav-burger { display: inline-flex; align-items: center; }
            .admin-nav-shell > .admin-nav {
                display: none; position: absolute; top: calc(100% + .4rem); left: 0; z-index: 60;
                width: min(22rem, calc(100vw - 2rem)); padding: .5rem;
                background: var(--card); border: 1px solid var(--line); border-radius: 8px;
                box-shadow: 0 10px 28px rgba(28, 25, 23, .12);
                flex-direction: column; align-items: stretch; gap: .35rem;
            }
            .admin-nav-shell.is-open > .admin-nav { display: flex; }
            .admin-nav__primary,
            .admin-nav__plugins { flex-direction: column; align-items: stretch; }
            .admin-nav__sep { display: none; }
            .admin-nav a,
            .admin-nav-drop > summary { width: 100%; }
            .admin-nav-drop__panel {
                position: static; box-shadow: none; border: 0; padding: .15rem 0 .15rem .5rem; min-width: 0;
            }
            .admin-user { width: 100%; margin-left: 0; justify-content: flex-end; }
            .locale-tabs__tab[aria-selected="true"] { margin-bottom: 0; box-shadow: none; }
        }
    </style>
    <?php foreach ($pluginHead ?? [] as $headHtml): ?>
        <?php echo $headHtml ?>
    <?php endforeach; ?>
</head>
<body>
<header class="admin-top">
    <div class="brand-stack">
        <?php
        $variant = 'logo';
        $href = ($basePath ?? '') . '/admin';
        require dirname(__DIR__) . '/partials/nexis-brand.php';
        ?>
        <span class="brand-vendor">by Vayvolt</span>
    </div>
    <?php if (isset($user)): ?>
        <?php
        /** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
        $t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
        $uiLocale = (string) ($uiLocale ?? $user->uiLocale ?? 'de');
        $uiReturn = (string) ($_SERVER['REQUEST_URI'] ?? (($basePath ?? '') . '/admin'));
        $uiLocales = \Nexis\I18n\Translator::supportedUiLocales();
        $activeAdminNav = (string) ($activeAdminNav ?? '');
        $navClass = static function (string $key) use ($activeAdminNav): string {
            return $activeAdminNav === $key ? ' class="is-active"' : '';
        };
        $systemOpen = in_array($activeAdminNav, ['users', 'plugins', 'audit', 'mail', 'logs', 'health', 'webhooks', 'export', 'security', 'about'], true);
        ?>
        <div class="admin-nav-shell" data-admin-nav>
            <button type="button" class="admin-nav-burger" data-admin-nav-toggle aria-expanded="false" aria-controls="admin-main-nav">
                <?php echo $e($t('admin.nav.menu')) ?>
            </button>
            <nav id="admin-main-nav" class="admin-nav" data-active-nav="<?php echo $e($activeAdminNav) ?>" aria-label="<?php echo $e($t('admin.nav.aria')) ?>">
                <div class="admin-nav__primary">
                    <a href="<?php echo $e($basePath) ?>/admin/pages"<?php echo $navClass('pages') ?>><?php echo $e($t('admin.nav.pages')) ?></a>
                    <a href="<?php echo $e($basePath) ?>/admin/menus"<?php echo $navClass('menus') ?>><?php echo $e($t('admin.nav.menus')) ?></a>
                    <a href="<?php echo $e($basePath) ?>/admin/media"<?php echo $navClass('media') ?>><?php echo $e($t('admin.nav.media')) ?></a>
                    <a href="<?php echo $e($basePath) ?>/admin/patterns"<?php echo $navClass('patterns') ?>><?php echo $e($t('admin.nav.patterns')) ?></a>
                    <a href="<?php echo $e($basePath) ?>/admin/globals"<?php echo $navClass('globals') ?>><?php echo $e($t('admin.nav.globals')) ?></a>
                    <?php if (($pluginNav ?? []) !== []): ?>
                        <span class="admin-nav__plugins">
                            <?php foreach ($pluginNav as $navHtml): ?>
                                <?php echo $navHtml ?>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span class="admin-nav__sep" aria-hidden="true"></span>
                <div class="admin-nav__primary">
                    <a href="<?php echo $e($basePath) ?>/admin/theme"<?php echo $navClass('theme') ?>><?php echo $e($t('admin.nav.theme')) ?></a>
                    <a href="<?php echo $e($basePath) ?>/admin/settings"<?php echo $navClass('settings') ?>><?php echo $e($t('admin.nav.settings')) ?></a>
                </div>
                <span class="admin-nav__sep" aria-hidden="true"></span>
                <details class="admin-nav-drop">
                    <summary<?php echo $systemOpen ? ' class="is-active"' : '' ?>><?php echo $e($t('admin.nav.system')) ?></summary>
                    <div class="admin-nav-drop__panel">
                        <a href="<?php echo $e($basePath) ?>/admin/users"<?php echo $navClass('users') ?>><?php echo $e($t('admin.nav.users')) ?></a>
                        <a href="<?php echo $e($basePath) ?>/admin/plugins"<?php echo $navClass('plugins') ?>><?php echo $e($t('admin.nav.plugins')) ?></a>
                        <a href="<?php echo $e($basePath) ?>/admin/audit"<?php echo $navClass('audit') ?>><?php echo $e($t('admin.nav.audit')) ?></a>
                        <a href="<?php echo $e($basePath) ?>/admin/mail"<?php echo $navClass('mail') ?>><?php echo $e($t('admin.nav.mail')) ?></a>
                        <a href="<?php echo $e($basePath) ?>/admin/logs"<?php echo $navClass('logs') ?>><?php echo $e($t('admin.nav.logs')) ?></a>
                        <a href="<?php echo $e($basePath) ?>/admin/health"<?php echo $navClass('health') ?>><?php echo $e($t('admin.nav.health')) ?></a>
                        <a href="<?php echo $e($basePath) ?>/admin/webhooks"<?php echo $navClass('webhooks') ?>><?php echo $e($t('admin.nav.webhooks')) ?></a>
                        <a href="<?php echo $e($basePath) ?>/admin/export"<?php echo $navClass('export') ?>><?php echo $e($t('admin.nav.export')) ?></a>
                        <a href="<?php echo $e($basePath) ?>/admin/security"<?php echo $navClass('security') ?>><?php echo $e($t('admin.nav.security')) ?></a>
                        <a href="<?php echo $e($basePath) ?>/admin/about"<?php echo $navClass('about') ?>><?php echo $e($t('admin.nav.about')) ?></a>
                    </div>
                </details>
            </nav>
        </div>
        <div class="admin-user">
            <div class="admin-locale" role="navigation" aria-label="<?php echo $e($t('admin.ui_locale')) ?>">
                <?php foreach ($uiLocales as $code): ?>
                    <?php if ($code === $uiLocale): ?>
                        <strong><?php echo $e($code) ?></strong>
                    <?php else: ?>
                        <a href="<?php echo $e($basePath) ?>/admin/ui-locale?locale=<?php echo $e($code) ?>&amp;return=<?php echo $e(rawurlencode($uiReturn)) ?>"><?php echo $e($code) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <span class="admin-user__name" title="<?php echo $e($user->displayName) ?>"><?php echo $e($user->displayName) ?></span>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/logout" style="margin:0">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <button type="submit" class="btn-small"><?php echo $e($t('admin.logout')) ?></button>
            </form>
        </div>
    <?php endif; ?>
</header>
<main>
    <?php echo $content ?>
</main>
<footer class="admin-footer">
    <a href="<?php echo $e($basePath ?? '') ?>/admin/about">Nexis <?php echo $e(\Nexis\Kernel\Nexis::VERSION) ?></a>
    ·
    <a href="<?php echo $e($basePath ?? '') ?>/admin/about/license"><?php echo $e(\Nexis\Kernel\Nexis::LICENSE) ?></a>
    ·
    <a href="<?php echo $e(\Nexis\Kernel\Nexis::VENDOR_URL) ?>" target="_blank" rel="noopener"><?php echo $e(\Nexis\Kernel\Nexis::ATTRIBUTION) ?></a>
</footer>
<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<dialog class="admin-modal" id="admin-confirm-modal" aria-labelledby="admin-confirm-title">
    <form method="dialog">
        <h2 id="admin-confirm-title"></h2>
        <p id="admin-confirm-detail" class="muted" hidden></p>
        <p id="admin-confirm-message" hidden></p>
        <div class="admin-modal__actions">
            <button type="button" class="btn" data-admin-confirm-cancel style="background:#78716c"><?php echo $e($t('admin.common.cancel')) ?></button>
            <button type="button" class="btn-danger" data-admin-confirm-ok><?php echo $e($t('admin.common.delete')) ?></button>
        </div>
    </form>
</dialog>
<script src="<?php echo $e($basePath ?? '') ?>/assets/admin/locale-tabs.js" defer></script>
<script src="<?php echo $e($basePath ?? '') ?>/assets/admin/admin-nav.js" defer></script>
<script src="<?php echo $e($basePath ?? '') ?>/assets/admin/confirm.js" defer></script>
</body>
</html>
