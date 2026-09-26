<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$localeDomains = $localeDomains ?? [];
$swRaw = $switcherUnpublished ?? 'hide';
if (is_bool($swRaw)) {
    $sw = $swRaw ? 'label' : 'hide';
} else {
    $sw = (string) $swRaw;
    if ($sw === '1') {
        $sw = 'label';
    } elseif ($sw === '0' || $sw === '') {
        $sw = 'hide';
    }
}
$settingsTabs = [
    'website' => $t('admin.settings.website'),
    'i18n' => $t('admin.settings.i18n'),
    'locales' => $t('admin.settings.locales'),
    'members' => $t('admin.settings.members'),
    'operations' => $t('admin.settings.operations'),
];
?>
<h1><?php echo $e($t('admin.settings.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.settings.intro')) ?></p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.common.saved')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<?php if (empty($canManage)): ?>
    <p class="muted"><?php echo $e($t('admin.settings.readonly')) ?></p>
<?php endif; ?>

<div class="card locale-tabs" data-locale-tabs data-tabs-hash>
    <div class="locale-tabs__list" role="tablist" aria-label="<?php echo $e($t('admin.settings.title')) ?>">
        <?php $tabIndex = 0; foreach ($settingsTabs as $tabId => $tabLabel): ?>
            <button
                type="button"
                class="locale-tabs__tab"
                role="tab"
                id="settings-tab-<?php echo $e($tabId) ?>"
                data-locale-tab="<?php echo $e($tabId) ?>"
                aria-controls="settings-panel-<?php echo $e($tabId) ?>"
                aria-selected="<?php echo $tabIndex === 0 ? 'true' : 'false' ?>"
                tabindex="<?php echo $tabIndex === 0 ? '0' : '-1' ?>"
            ><?php echo $e($tabLabel) ?></button>
        <?php $tabIndex++; endforeach; ?>
    </div>

    <form method="post" action="<?php echo $e($basePath) ?>/admin/settings" <?php echo empty($canManage) ? 'onsubmit="return false"' : '' ?>>
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="return_tab" value="website" data-tabs-return="website,i18n,members,operations">

        <div
            class="locale-tabs__panel"
            role="tabpanel"
            id="settings-panel-website"
            data-locale-panel="website"
            aria-labelledby="settings-tab-website"
        >
            <div class="row">
                <div>
                    <label for="name"><?php echo $e($t('admin.common.name')) ?></label>
                    <input id="name" name="name" required value="<?php echo $e($site->name) ?>" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                </div>
                <div>
                    <label for="primary_domain"><?php echo $e($t('admin.settings.primary_domain')) ?></label>
                    <input id="primary_domain" name="primary_domain" required value="<?php echo $e($site->primaryDomain) ?>" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                </div>
            </div>
            <div class="row">
                <div>
                    <label for="default_locale"><?php echo $e($t('admin.settings.default_locale')) ?></label>
                    <select id="default_locale" name="default_locale" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                        <?php foreach ($site->enabledLocales() as $loc): ?>
                            <option value="<?php echo $e($loc->locale) ?>" <?php echo $site->defaultLocale === $loc->locale ? 'selected' : '' ?>><?php echo $e($t('admin.locale.' . $loc->locale, [], $loc->label)) ?> (<?php echo $e($loc->locale) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="locale_url_strategy"><?php echo $e($t('admin.settings.url_strategy')) ?></label>
                    <select id="locale_url_strategy" name="locale_url_strategy" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                        <?php foreach ([
                            'prefix' => $t('admin.settings.strategy.prefix'),
                            'domain' => $t('admin.settings.strategy.domain'),
                            'none' => $t('admin.settings.strategy.none'),
                        ] as $value => $label): ?>
                            <option value="<?php echo $e($value) ?>" <?php echo $site->localeUrlStrategy->value === $value ? 'selected' : '' ?>><?php echo $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="locale-domains" style="margin-top:1rem">
                <p class="muted"><?php echo $e($t('admin.settings.domain_hint')) ?></p>
                <div class="row">
                    <?php foreach ($site->enabledLocales() as $loc): ?>
                        <div>
                            <label for="locale_host_<?php echo $e($loc->locale) ?>"><?php echo $e($t('admin.settings.host_for', ['label' => $t('admin.locale.' . $loc->locale, [], $loc->label)])) ?></label>
                            <input id="locale_host_<?php echo $e($loc->locale) ?>" name="locale_host[<?php echo $e($loc->locale) ?>]" value="<?php echo $e($localeDomains[$loc->locale] ?? '') ?>" placeholder="<?php echo $e($loc->locale) ?>.example.test" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div
            class="locale-tabs__panel"
            role="tabpanel"
            id="settings-panel-i18n"
            data-locale-panel="i18n"
            aria-labelledby="settings-tab-i18n"
            hidden
        >
            <label><input type="checkbox" name="detect_on_root" value="1" <?php echo !empty($detectOnRoot) ? 'checked' : '' ?> <?php echo empty($canManage) ? 'disabled' : '' ?> style="width:auto"> <?php echo $e($t('admin.settings.detect_on_root')) ?></label>
            <label for="missing_policy"><?php echo $e($t('admin.settings.missing_policy')) ?></label>
            <select id="missing_policy" name="missing_policy" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                <option value="fallback" <?php echo ($missingPolicy ?? '') === 'fallback' ? 'selected' : '' ?>><?php echo $e($t('admin.settings.missing.fallback')) ?></option>
                <option value="404" <?php echo ($missingPolicy ?? '') === '404' ? 'selected' : '' ?>><?php echo $e($t('admin.settings.missing.404')) ?></option>
            </select>
            <label for="switcher_unpublished"><?php echo $e($t('admin.settings.switcher_unpublished')) ?></label>
            <select id="switcher_unpublished" name="switcher_unpublished" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                <option value="hide" <?php echo $sw === 'hide' ? 'selected' : '' ?>><?php echo $e($t('admin.settings.switcher.hide')) ?></option>
                <option value="label" <?php echo $sw === 'label' ? 'selected' : '' ?>><?php echo $e($t('admin.settings.switcher.label')) ?></option>
            </select>
        </div>

        <div
            class="locale-tabs__panel"
            role="tabpanel"
            id="settings-panel-members"
            data-locale-panel="members"
            aria-labelledby="settings-tab-members"
            hidden
        >
            <p class="muted"><?php echo $e($t('admin.settings.members_intro')) ?></p>
            <label class="check-label">
                <input type="checkbox" name="registration_enabled" value="1" <?php echo !empty($registrationEnabled) ? 'checked' : '' ?> <?php echo empty($canManage) ? 'disabled' : '' ?>>
                <?php echo $e($t('admin.settings.registration')) ?>
            </label>
            <label class="check-label">
                <input type="checkbox" name="password_reset_enabled" value="1" <?php echo !empty($passwordResetEnabled) ? 'checked' : '' ?> <?php echo empty($canManage) ? 'disabled' : '' ?>>
                <?php echo $e($t('admin.settings.password_reset')) ?>
            </label>
        </div>

        <div
            class="locale-tabs__panel"
            role="tabpanel"
            id="settings-panel-operations"
            data-locale-panel="operations"
            aria-labelledby="settings-tab-operations"
            hidden
        >
            <p class="muted"><?php echo $e($t('admin.settings.operations_intro')) ?></p>
            <label class="check-label">
                <input type="checkbox" name="maintenance_enabled" value="1" <?php echo !empty($maintenanceEnabled) ? 'checked' : '' ?> <?php echo empty($canManage) ? 'disabled' : '' ?>>
                <?php echo $e($t('admin.settings.maintenance_enabled')) ?>
            </label>
            <p class="muted" style="margin:.35rem 0 .75rem;font-size:.85rem"><?php echo $e($t('admin.settings.maintenance_hint')) ?></p>
            <label for="maintenance_message"><?php echo $e($t('admin.settings.maintenance_message')) ?></label>
            <textarea id="maintenance_message" name="maintenance_message" rows="3" maxlength="2000" <?php echo empty($canManage) ? 'disabled' : '' ?> placeholder="<?php echo $e($t('admin.settings.maintenance_message_placeholder')) ?>"><?php echo $e((string) ($maintenanceMessage ?? '')) ?></textarea>
        </div>

        <?php if (!empty($canManage)): ?>
            <p class="settings-main-actions" style="margin:1rem 0 0"><button type="submit"><?php echo $e($t('admin.common.save')) ?></button></p>
        <?php endif; ?>
    </form>

    <div
        class="locale-tabs__panel"
        role="tabpanel"
        id="settings-panel-locales"
        data-locale-panel="locales"
        aria-labelledby="settings-tab-locales"
        hidden
    >
        <p class="muted"><?php echo $e($t('admin.settings.locales_intro')) ?></p>
        <table>
            <thead>
                <tr>
                    <th><?php echo $e($t('admin.settings.code')) ?></th>
                    <th><?php echo $e($t('admin.settings.label')) ?></th>
                    <th><?php echo $e($t('admin.settings.url_prefix')) ?></th>
                    <th>hreflang</th>
                    <th><?php echo $e($t('admin.common.status')) ?></th>
                    <?php if (!empty($canManage)): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($site->locales as $loc): ?>
                    <tr>
                        <?php if (!empty($canManage)): ?>
                            <td colspan="6">
                                <form method="post" action="<?php echo $e($basePath) ?>/admin/settings/locales/update" class="row" style="align-items:flex-end;margin:0">
                                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                    <input type="hidden" name="locale" value="<?php echo $e($loc->locale) ?>">
                                    <div style="flex:0 0 5rem">
                                        <label><?php echo $e($t('admin.settings.code')) ?></label>
                                        <input value="<?php echo $e($loc->locale) ?>" disabled>
                                    </div>
                                    <div>
                                        <label><?php echo $e($t('admin.settings.label')) ?></label>
                                        <input name="label" value="<?php echo $e($loc->label) ?>" required>
                                    </div>
                                    <div style="flex:0 0 6rem">
                                        <label><?php echo $e($t('admin.settings.prefix')) ?></label>
                                        <input name="url_prefix" value="<?php echo $e($loc->urlPrefix ?? '') ?>" required>
                                    </div>
                                    <div style="flex:0 0 6rem">
                                        <label>hreflang</label>
                                        <input name="hreflang" value="<?php echo $e($loc->hreflang) ?>" required>
                                    </div>
                                    <div style="flex:0 0 8rem">
                                        <label><input type="checkbox" name="enabled" value="1" <?php echo $loc->enabled ? 'checked' : '' ?> <?php echo ($loc->isDefault || $loc->locale === $site->defaultLocale) ? 'disabled' : '' ?> style="width:auto"> <?php echo $e($t('admin.settings.enabled')) ?></label>
                                        <?php if ($loc->isDefault || $loc->locale === $site->defaultLocale): ?>
                                            <input type="hidden" name="enabled" value="1">
                                            <span class="badge badge-admin"><?php echo $e($t('admin.settings.default')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex:0">
                                        <button type="submit" class="btn-small"><?php echo $e($t('admin.common.save')) ?></button>
                                    </div>
                                </form>
                                <?php if (!$loc->isDefault && $loc->locale !== $site->defaultLocale): ?>
                                    <form method="post" action="<?php echo $e($basePath) ?>/admin/settings/locales/default" style="margin:.35rem 0 0;display:inline-block">
                                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                        <input type="hidden" name="locale" value="<?php echo $e($loc->locale) ?>">
                                        <button type="submit" class="btn-small"><?php echo $e($t('admin.settings.set_default')) ?></button>
                                    </form>
                                    <form method="post" action="<?php echo $e($basePath) ?>/admin/settings/locales/delete" style="margin:.35rem 0 0;display:inline-block" onsubmit="return confirm(<?php echo json_encode($t('admin.settings.confirm_delete_locale', ['locale' => $loc->locale]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);">
                                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                        <input type="hidden" name="locale" value="<?php echo $e($loc->locale) ?>">
                                        <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.common.delete')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php else: ?>
                            <td><code><?php echo $e($loc->locale) ?></code></td>
                            <td><?php echo $e($loc->label) ?></td>
                            <td><?php echo $e($loc->urlPrefix ?? $t('admin.common.empty_dash')) ?></td>
                            <td><?php echo $e($loc->hreflang) ?></td>
                            <td>
                                <?php if ($loc->enabled): ?><span class="badge badge-admin"><?php echo $e($t('admin.settings.active')) ?></span><?php else: ?><span class="badge"><?php echo $e($t('admin.settings.inactive')) ?></span><?php endif; ?>
                                <?php if ($loc->isDefault || $loc->locale === $site->defaultLocale): ?> · <?php echo $e($t('admin.settings.default')) ?><?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($canManage)): ?>
            <h3><?php echo $e($t('admin.settings.add_locale')) ?></h3>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/settings/locales">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <div class="row">
                    <div style="flex:0 0 6rem">
                        <label for="new_locale"><?php echo $e($t('admin.settings.code')) ?></label>
                        <input id="new_locale" name="locale" placeholder="fr" required pattern="[a-z]{2,3}(-[a-z0-9]{2,8})*">
                    </div>
                    <div>
                        <label for="new_label"><?php echo $e($t('admin.settings.label')) ?></label>
                        <input id="new_label" name="label" placeholder="Français" required>
                    </div>
                    <div style="flex:0 0 6rem">
                        <label for="new_prefix"><?php echo $e($t('admin.settings.url_prefix')) ?></label>
                        <input id="new_prefix" name="url_prefix" placeholder="fr">
                    </div>
                    <div style="flex:0 0 6rem">
                        <label for="new_hreflang">hreflang</label>
                        <input id="new_hreflang" name="hreflang" placeholder="fr">
                    </div>
                </div>
                <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('admin.settings.create_locale')) ?></button></p>
            </form>
        <?php endif; ?>
    </div>
</div>

<p class="muted"><?php echo $e($t('admin.settings.theme_link')) ?> <a href="<?php echo $e($basePath) ?>/admin/theme"><?php echo $e($t('admin.nav.theme')) ?></a></p>
