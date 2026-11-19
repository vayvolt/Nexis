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
    'sso' => $t('admin.settings.sso'),
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
        <input type="hidden" name="return_tab" value="website" data-tabs-return="website,i18n,members,sso,operations">

        <div
            class="locale-tabs__panel"
            role="tabpanel"
            id="settings-panel-website"
            data-locale-panel="website"
            aria-labelledby="settings-tab-website"
        >
            <div class="form-grid">
                <div>
                    <label for="name"><?php echo $e($t('admin.common.name')) ?></label>
                    <input id="name" name="name" required value="<?php echo $e($site->name) ?>" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                </div>
                <div>
                    <label for="primary_domain"><?php echo $e($t('admin.settings.primary_domain')) ?></label>
                    <input id="primary_domain" name="primary_domain" required value="<?php echo $e($site->primaryDomain) ?>" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                </div>
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
            <div id="locale-domains" class="settings-hosts">
                <p class="muted"><?php echo $e($t('admin.settings.domain_hint')) ?></p>
                <div class="form-grid">
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
            <label class="check-label">
                <input type="checkbox" name="detect_on_root" value="1" <?php echo !empty($detectOnRoot) ? 'checked' : '' ?> <?php echo empty($canManage) ? 'disabled' : '' ?>>
                <?php echo $e($t('admin.settings.detect_on_root')) ?>
            </label>
            <div class="form-grid">
                <div>
                    <label for="missing_policy"><?php echo $e($t('admin.settings.missing_policy')) ?></label>
                    <select id="missing_policy" name="missing_policy" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                        <option value="fallback" <?php echo ($missingPolicy ?? '') === 'fallback' ? 'selected' : '' ?>><?php echo $e($t('admin.settings.missing.fallback')) ?></option>
                        <option value="404" <?php echo ($missingPolicy ?? '') === '404' ? 'selected' : '' ?>><?php echo $e($t('admin.settings.missing.404')) ?></option>
                    </select>
                </div>
                <div>
                    <label for="switcher_unpublished"><?php echo $e($t('admin.settings.switcher_unpublished')) ?></label>
                    <select id="switcher_unpublished" name="switcher_unpublished" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                        <option value="hide" <?php echo $sw === 'hide' ? 'selected' : '' ?>><?php echo $e($t('admin.settings.switcher.hide')) ?></option>
                        <option value="label" <?php echo $sw === 'label' ? 'selected' : '' ?>><?php echo $e($t('admin.settings.switcher.label')) ?></option>
                    </select>
                </div>
            </div>
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
            id="settings-panel-sso"
            data-locale-panel="sso"
            aria-labelledby="settings-tab-sso"
            hidden
        >
            <p class="muted"><?php echo $e($t('admin.settings.sso_intro')) ?></p>
            <label class="check-label">
                <input type="checkbox" name="oidc_enabled" value="1" <?php echo !empty($oidcEnabled) ? 'checked' : '' ?> <?php echo empty($canManage) ? 'disabled' : '' ?>>
                <?php echo $e($t('admin.settings.oidc_enabled')) ?>
            </label>
            <div class="form-grid">
                <div class="form-grid__full">
                    <label for="oidc_issuer"><?php echo $e($t('admin.settings.oidc_issuer')) ?></label>
                    <input id="oidc_issuer" name="oidc_issuer" type="url" placeholder="https://login.microsoftonline.com/{tenant}/v2.0" value="<?php echo $e((string) ($oidcIssuer ?? '')) ?>" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                    <p class="form-hint"><?php echo $e($t('admin.settings.oidc_issuer_hint')) ?></p>
                </div>
                <div>
                    <label for="oidc_client_id"><?php echo $e($t('admin.settings.oidc_client_id')) ?></label>
                    <input id="oidc_client_id" name="oidc_client_id" value="<?php echo $e((string) ($oidcClientId ?? '')) ?>" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                </div>
                <div>
                    <label for="oidc_client_secret"><?php echo $e($t('admin.settings.oidc_client_secret')) ?></label>
                    <input id="oidc_client_secret" name="oidc_client_secret" type="password" autocomplete="new-password" placeholder="<?php echo !empty($oidcHasSecret) ? $e($t('admin.settings.oidc_secret_kept')) : '' ?>" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                </div>
                <div>
                    <label for="oidc_button_label"><?php echo $e($t('admin.settings.oidc_button_label')) ?></label>
                    <input id="oidc_button_label" name="oidc_button_label" value="<?php echo $e((string) ($oidcButtonLabel ?? '')) ?>" placeholder="<?php echo $e($t('admin.login.oidc_continue')) ?>" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                </div>
                <div>
                    <label for="oidc_default_role"><?php echo $e($t('admin.settings.oidc_default_role')) ?></label>
                    <select id="oidc_default_role" name="oidc_default_role" <?php echo empty($canManage) ? 'disabled' : '' ?>>
                        <?php
                        $oidcRole = (string) ($oidcDefaultRole ?? 'member');
                        foreach (['admin' => 'admin.role.admin', 'editor' => 'admin.role.editor', 'seo' => 'admin.role.seo', 'member' => 'admin.role.member'] as $slug => $labelKey):
                        ?>
                            <option value="<?php echo $e($slug) ?>" <?php echo $oidcRole === $slug ? 'selected' : '' ?>><?php echo $e($t($labelKey, [], $slug)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid__full">
                    <label class="check-label" style="margin-top:.25rem">
                        <input type="checkbox" name="oidc_auto_provision" value="1" <?php echo !empty($oidcAutoProvision) ? 'checked' : '' ?> <?php echo empty($canManage) ? 'disabled' : '' ?>>
                        <?php echo $e($t('admin.settings.oidc_auto_provision')) ?>
                    </label>
                    <p class="form-hint"><?php echo $e($t('admin.settings.oidc_auto_provision_hint')) ?></p>
                </div>
            </div>
            <p class="form-hint" style="margin-top:1rem">
                <?php echo $e($t('admin.settings.oidc_callback')) ?>:
                <code><?php echo $e((string) ($oidcCallbackUrl ?? '')) ?></code>
            </p>
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
            <p class="form-hint" style="margin-bottom:.75rem"><?php echo $e($t('admin.settings.maintenance_hint')) ?></p>
            <label for="maintenance_message"><?php echo $e($t('admin.settings.maintenance_message')) ?></label>
            <textarea id="maintenance_message" name="maintenance_message" rows="3" maxlength="2000" <?php echo empty($canManage) ? 'disabled' : '' ?> placeholder="<?php echo $e($t('admin.settings.maintenance_message_placeholder')) ?>"><?php echo $e((string) ($maintenanceMessage ?? '')) ?></textarea>
        </div>

        <?php if (!empty($canManage)): ?>
            <p class="settings-main-actions"><button type="submit"><?php echo $e($t('admin.common.save')) ?></button></p>
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
        <?php if (!empty($canManage)): ?>
            <div class="locale-edit-list">
                <div class="locale-edit-row locale-edit-row--head" aria-hidden="true">
                    <div class="locale-edit-row__fields">
                        <div class="locale-edit-row__code"><?php echo $e($t('admin.settings.code')) ?></div>
                        <div class="locale-edit-row__label"><?php echo $e($t('admin.settings.label')) ?></div>
                        <div class="locale-edit-row__prefix"><?php echo $e($t('admin.settings.prefix')) ?></div>
                        <div class="locale-edit-row__hreflang">hreflang</div>
                        <div class="locale-edit-row__status"><?php echo $e($t('admin.common.status')) ?></div>
                    </div>
                    <div class="locale-edit-row__save"><?php echo $e($t('admin.common.save')) ?></div>
                    <div class="locale-edit-row__more"><?php echo $e($t('admin.common.actions')) ?></div>
                </div>
                <?php foreach ($site->locales as $loc): ?>
                    <?php $localeFormId = 'locale-update-' . preg_replace('/[^a-z0-9_-]+/i', '-', $loc->locale); ?>
                    <div class="locale-edit-row">
                        <form
                            id="<?php echo $e($localeFormId) ?>"
                            method="post"
                            action="<?php echo $e($basePath) ?>/admin/settings/locales/update"
                            class="locale-edit-row__fields"
                        >
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                            <input type="hidden" name="locale" value="<?php echo $e($loc->locale) ?>">
                            <div class="locale-edit-row__code">
                                <input value="<?php echo $e($loc->locale) ?>" disabled aria-label="<?php echo $e($t('admin.settings.code')) ?>">
                            </div>
                            <div class="locale-edit-row__label">
                                <input name="label" value="<?php echo $e($loc->label) ?>" required aria-label="<?php echo $e($t('admin.settings.label')) ?>">
                            </div>
                            <div class="locale-edit-row__prefix">
                                <input name="url_prefix" value="<?php echo $e($loc->urlPrefix ?? '') ?>" required aria-label="<?php echo $e($t('admin.settings.prefix')) ?>">
                            </div>
                            <div class="locale-edit-row__hreflang">
                                <input name="hreflang" value="<?php echo $e($loc->hreflang) ?>" required aria-label="hreflang">
                            </div>
                            <div class="locale-edit-row__status">
                                <label class="check-label" style="margin:0">
                                    <input type="checkbox" name="enabled" value="1" <?php echo $loc->enabled ? 'checked' : '' ?> <?php echo ($loc->isDefault || $loc->locale === $site->defaultLocale) ? 'disabled' : '' ?>>
                                    <?php echo $e($t('admin.settings.enabled')) ?>
                                </label>
                                <?php if ($loc->isDefault || $loc->locale === $site->defaultLocale): ?>
                                    <input type="hidden" name="enabled" value="1">
                                    <span class="badge badge-admin"><?php echo $e($t('admin.settings.default')) ?></span>
                                <?php endif; ?>
                            </div>
                        </form>
                        <div class="locale-edit-row__save">
                            <button type="submit" form="<?php echo $e($localeFormId) ?>" class="btn-small"><?php echo $e($t('admin.common.save')) ?></button>
                        </div>
                        <div class="row-actions locale-edit-row__more">
                            <?php if (!$loc->isDefault && $loc->locale !== $site->defaultLocale): ?>
                                <form method="post" action="<?php echo $e($basePath) ?>/admin/settings/locales/default">
                                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                    <input type="hidden" name="locale" value="<?php echo $e($loc->locale) ?>">
                                    <button type="submit" class="btn-small"><?php echo $e($t('admin.settings.set_default')) ?></button>
                                </form>
                                <form method="post" action="<?php echo $e($basePath) ?>/admin/settings/locales/delete" data-confirm="<?php echo $e($t('admin.settings.confirm_delete_locale', ['locale' => $loc->locale])) ?>">
                                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                    <input type="hidden" name="locale" value="<?php echo $e($loc->locale) ?>">
                                    <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.common.delete')) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3><?php echo $e($t('admin.settings.add_locale')) ?></h3>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/settings/locales" class="locale-edit-row locale-edit-row--add">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <div class="locale-edit-row__fields">
                    <div class="locale-edit-row__code">
                        <label for="new_locale"><?php echo $e($t('admin.settings.code')) ?></label>
                        <input id="new_locale" name="locale" placeholder="fr" required pattern="[a-z]{2,3}(-[a-z0-9]{2,8})*">
                    </div>
                    <div class="locale-edit-row__label">
                        <label for="new_label"><?php echo $e($t('admin.settings.label')) ?></label>
                        <input id="new_label" name="label" placeholder="Français" required>
                    </div>
                    <div class="locale-edit-row__prefix">
                        <label for="new_prefix"><?php echo $e($t('admin.settings.prefix')) ?></label>
                        <input id="new_prefix" name="url_prefix" placeholder="fr">
                    </div>
                    <div class="locale-edit-row__hreflang">
                        <label for="new_hreflang">hreflang</label>
                        <input id="new_hreflang" name="hreflang" placeholder="fr">
                    </div>
                </div>
                <div class="locale-edit-row__save">
                    <button type="submit" class="btn-small"><?php echo $e($t('admin.settings.create_locale')) ?></button>
                </div>
            </form>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?php echo $e($t('admin.settings.code')) ?></th>
                        <th><?php echo $e($t('admin.settings.label')) ?></th>
                        <th><?php echo $e($t('admin.settings.url_prefix')) ?></th>
                        <th>hreflang</th>
                        <th><?php echo $e($t('admin.common.status')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($site->locales as $loc): ?>
                        <tr>
                            <td><code><?php echo $e($loc->locale) ?></code></td>
                            <td><?php echo $e($loc->label) ?></td>
                            <td><?php echo $e($loc->urlPrefix ?? $t('admin.common.empty_dash')) ?></td>
                            <td><?php echo $e($loc->hreflang) ?></td>
                            <td>
                                <?php if ($loc->enabled): ?><span class="badge badge-admin"><?php echo $e($t('admin.settings.active')) ?></span><?php else: ?><span class="badge"><?php echo $e($t('admin.settings.inactive')) ?></span><?php endif; ?>
                                <?php if ($loc->isDefault || $loc->locale === $site->defaultLocale): ?> | <?php echo $e($t('admin.settings.default')) ?><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<p class="muted"><?php echo $e($t('admin.settings.theme_link')) ?> <a href="<?php echo $e($basePath) ?>/admin/theme"><?php echo $e($t('admin.nav.theme')) ?></a></p>
