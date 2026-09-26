<?php
/** @var list<\Nexis\Media\MediaAsset> $assets */
/** @var list<\Nexis\Media\MediaFolder> $folders */
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$folderFilter = $folderFilter ?? 'all';
$folderQuery = $folderQuery ?? '';
?>
<h1><?php echo $e($t('admin.media.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.media.intro', ['site' => $site->name])) ?></p>
<?php if (!empty($uploaded)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.media.uploaded')) ?></p><?php endif; ?>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.media.saved')) ?></p><?php endif; ?>
<?php if (!empty($variantsRegenerated)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.media.variants_regenerated')) ?></p><?php endif; ?>
<?php if (!empty($deleted)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.media.deleted')) ?></p><?php endif; ?>
<?php if (!empty($folderCreated)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.media.folder.created')) ?></p><?php endif; ?>
<?php if (!empty($folderDeleted)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.media.folder.deleted')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<div class="nx-media-layout" style="display:flex;gap:1.5rem;align-items:flex-start;flex-wrap:wrap">
    <aside class="card" style="flex:0 0 220px;min-width:200px">
        <h2><?php echo $e($t('admin.media.folder.sidebar')) ?></h2>
        <nav class="nx-media-folders" aria-label="<?php echo $e($t('admin.media.folder.sidebar')) ?>">
            <a class="nx-media-folder-link" href="<?php echo $e($basePath) ?>/admin/media"<?php echo $folderFilter === 'all' ? ' aria-current="page"' : '' ?>>
                <?php echo $e($t('admin.media.folder.all')) ?>
            </a>
            <a class="nx-media-folder-link" href="<?php echo $e($basePath) ?>/admin/media?folder=none"<?php echo $folderFilter === 'none' ? ' aria-current="page"' : '' ?>>
                <?php echo $e($t('admin.media.folder.unfiled')) ?>
            </a>
            <?php foreach ($folders as $folder): ?>
                <div class="nx-media-folder-row">
                    <a class="nx-media-folder-link" href="<?php echo $e($basePath) ?>/admin/media?folder=<?php echo $e(rawurlencode($folder->id->value)) ?>"
                       <?php echo ($activeFolderId ?? '') === $folder->id->value ? ' aria-current="page"' : '' ?>
                       title="<?php echo $e($folder->name) ?>">
                        <?php echo $e($folder->name) ?>
                    </a>
                    <form
                        method="post"
                        action="<?php echo $e($basePath) ?>/admin/media/folders/delete"
                        class="nx-media-folder-delete"
                        data-confirm="<?php echo $e($t('admin.media.folder.delete_confirm', ['name' => $folder->name])) ?>"
                        data-confirm-title="<?php echo $e($t('admin.media.folder.delete_title')) ?>"
                        data-confirm-detail="<?php echo $e($t('admin.media.folder.delete_detail')) ?>"
                        data-confirm-ok="<?php echo $e($t('admin.common.delete')) ?>"
                    >
                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                        <input type="hidden" name="folder_id" value="<?php echo $e($folder->id->value) ?>">
                        <button type="submit" class="btn-danger btn-small" title="<?php echo $e($t('admin.common.delete')) ?>" aria-label="<?php echo $e($t('admin.common.delete')) ?>">×</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </nav>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/media/folders" style="margin-top:1rem">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <label for="folder-name"><?php echo $e($t('admin.media.folder.new_label')) ?></label>
            <input id="folder-name" name="name" type="text" maxlength="190" required>
            <p style="margin-top:.5rem"><button type="submit" class="btn-small"><?php echo $e($t('admin.media.folder.create')) ?></button></p>
        </form>
    </aside>

    <div style="flex:1;min-width:280px">
        <div class="card">
            <h2><?php echo $e($t('admin.media.upload_heading')) ?></h2>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/media" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <?php if ($folderQuery !== '' && $folderQuery !== 'none'): ?>
                    <input type="hidden" name="folder_id" value="<?php echo $e($folderQuery) ?>">
                <?php endif; ?>
                <label for="file"><?php echo $e($t('admin.media.file_label')) ?></label>
                <input id="file" name="file" type="file" accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,image/png,image/jpeg,image/gif,image/webp,application/pdf" required>
                <label for="alt_text"><?php echo $e($t('admin.media.alt_label')) ?></label>
                <input id="alt_text" name="alt_text" type="text" maxlength="500" placeholder="<?php echo $e($t('admin.media.alt_placeholder')) ?>">
                <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('admin.common.upload')) ?></button></p>
            </form>
        </div>

        <?php if ($assets === []): ?>
            <p class="muted"><?php echo $e($t('admin.media.empty')) ?></p>
        <?php else: ?>
            <div class="media-grid">
                <?php foreach ($assets as $asset): ?>
                    <?php
                    $publicPath = $basePath . '/media/' . $asset->id->value;
                    $thumbPath = $thumbUrls[$asset->id->value] ?? $publicPath;
                    $meta = [$asset->humanSize()];
                    if ($asset->width !== null && $asset->height !== null) {
                        $meta[] = $asset->width . '×' . $asset->height;
                    }
                    $meta[] = $asset->mime;
                    if ($asset->createdAt !== null) {
                        $meta[] = $asset->createdAt->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i');
                    }
                    ?>
                    <article class="media-card">
                        <div class="media-thumb<?php echo $asset->isImage() ? ' media-thumb--focus' : '' ?>">
                            <?php if ($asset->isImage()): ?>
                                <img src="<?php echo $e($thumbPath) ?>" alt="<?php echo $e($asset->displayAlt()) ?>"
                                     style="object-position: <?php echo $e($asset->objectPositionCss()) ?>">
                                <span class="media-focus-marker" style="left:<?php echo $e((string) $asset->focusX) ?>%;top:<?php echo $e((string) $asset->focusY) ?>%" aria-hidden="true"></span>
                            <?php else: ?>
                                <span class="media-file-badge">PDF</span>
                            <?php endif; ?>
                        </div>
                        <div class="media-body">
                            <strong class="media-name" title="<?php echo $e($asset->originalName) ?>"><?php echo $e($asset->originalName) ?></strong>
                            <p class="muted media-meta"><?php echo $e(implode(' · ', $meta)) ?></p>
                            <form method="post" action="<?php echo $e($basePath) ?>/admin/media/update" class="media-edit-form">
                                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                <input type="hidden" name="media_id" value="<?php echo $e($asset->id->value) ?>">
                                <?php if ($folderQuery !== ''): ?>
                                    <input type="hidden" name="folder_id" value="<?php echo $e($folderQuery) ?>">
                                <?php endif; ?>
                                <label class="muted" for="alt-<?php echo $e($asset->id->value) ?>"><?php echo $e($t('admin.media.alt_short')) ?></label>
                                <input id="alt-<?php echo $e($asset->id->value) ?>" name="alt_text" type="text" maxlength="500" value="<?php echo $e((string) ($asset->altText ?? '')) ?>" <?php echo $asset->isImage() ? 'required' : '' ?>>
                                <label class="muted" for="folder-<?php echo $e($asset->id->value) ?>"><?php echo $e($t('admin.media.folder.move_label')) ?></label>
                                <select id="folder-<?php echo $e($asset->id->value) ?>" name="target_folder_id">
                                    <option value="none"<?php echo $asset->folderId === null ? ' selected' : '' ?>><?php echo $e($t('admin.media.folder.unfiled')) ?></option>
                                    <?php foreach ($folders as $folder): ?>
                                        <option value="<?php echo $e($folder->id->value) ?>"<?php echo ($asset->folderId?->value ?? '') === $folder->id->value ? ' selected' : '' ?>>
                                            <?php echo $e($folder->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($asset->isImage()): ?>
                                    <p class="muted media-focus-hint"><?php echo $e($t('admin.media.focus_hint')) ?></p>
                                    <div class="media-url-row media-focus-inputs">
                                        <label class="muted" for="fx-<?php echo $e($asset->id->value) ?>">X%</label>
                                        <input id="fx-<?php echo $e($asset->id->value) ?>" name="focus_x" type="number" min="0" max="100" step="0.1" value="<?php echo $e((string) $asset->focusX) ?>">
                                        <label class="muted" for="fy-<?php echo $e($asset->id->value) ?>">Y%</label>
                                        <input id="fy-<?php echo $e($asset->id->value) ?>" name="focus_y" type="number" min="0" max="100" step="0.1" value="<?php echo $e((string) $asset->focusY) ?>">
                                    </div>
                                <?php endif; ?>
                                <p class="media-edit-actions">
                                    <button type="submit" class="btn-small"><?php echo $e($t('admin.common.save')) ?></button>
                                </p>
                            </form>
                            <label class="muted" for="url-<?php echo $e($asset->id->value) ?>"><?php echo $e($t('admin.media.public_url')) ?></label>
                            <div class="media-url-row">
                                <input id="url-<?php echo $e($asset->id->value) ?>" type="text" readonly value="<?php echo $e($publicPath) ?>" data-select-on-focus>
                                <button type="button" class="btn-small" data-copy="<?php echo $e($publicPath) ?>"><?php echo $e($t('admin.media.copy')) ?></button>
                            </div>
                            <?php if ($asset->isImage()): ?>
                                <?php
                                /** @var list<\Nexis\Media\MediaVariant> $assetVariants */
                                $assetVariants = $variantsByAsset[$asset->id->value] ?? [];
                                ?>
                                <details class="media-variants">
                                    <summary><?php echo $e($t('admin.media.variants_heading')) ?></summary>
                                    <div class="media-variants__head">
                                        <form method="post" action="<?php echo $e($basePath) ?>/admin/media/variants/regenerate" class="media-variants__regen">
                                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                            <input type="hidden" name="media_id" value="<?php echo $e($asset->id->value) ?>">
                                            <?php if ($folderQuery !== ''): ?>
                                                <input type="hidden" name="folder_id" value="<?php echo $e($folderQuery) ?>">
                                            <?php endif; ?>
                                            <button type="submit" class="btn-small"><?php echo $e($t('admin.media.variants_regenerate')) ?></button>
                                        </form>
                                    </div>
                                    <ul class="media-variants__list">
                                        <li>
                                            <div class="media-variants__meta">
                                                <strong><?php echo $e($t('admin.media.variant.original')) ?></strong>
                                                <span class="muted">
                                                    <?php
                                                    $origMeta = [$asset->humanSize()];
                                                    if ($asset->width !== null && $asset->height !== null) {
                                                        $origMeta[] = $asset->width . '×' . $asset->height;
                                                    }
                                                    $origMeta[] = $asset->mime;
                                                    echo $e(implode(' · ', $origMeta));
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="media-url-row">
                                                <input type="text" readonly value="<?php echo $e($publicPath) ?>" data-select-on-focus>
                                                <button type="button" class="btn-small" data-copy="<?php echo $e($publicPath) ?>"><?php echo $e($t('admin.media.copy')) ?></button>
                                            </div>
                                        </li>
                                        <?php foreach ($assetVariants as $variant): ?>
                                            <?php
                                            $variantUrl = $basePath . '/media/' . $asset->id->value . '/' . rawurlencode($variant->handle);
                                            $variantMeta = [$variant->humanSize()];
                                            $dims = $variant->dimensionsLabel();
                                            if ($dims !== '') {
                                                $variantMeta[] = $dims;
                                            }
                                            $variantMeta[] = $variant->mime;
                                            $handleLabel = match ($variant->handle) {
                                                'thumb' => $t('admin.media.variant.thumb'),
                                                'webp' => $t('admin.media.variant.webp'),
                                                default => $variant->handle,
                                            };
                                            ?>
                                            <li>
                                                <div class="media-variants__meta">
                                                    <strong><?php echo $e($handleLabel) ?></strong>
                                                    <span class="muted"><?php echo $e(implode(' · ', $variantMeta)) ?></span>
                                                </div>
                                                <div class="media-url-row">
                                                    <input type="text" readonly value="<?php echo $e($variantUrl) ?>" data-select-on-focus>
                                                    <button type="button" class="btn-small" data-copy="<?php echo $e($variantUrl) ?>"><?php echo $e($t('admin.media.copy')) ?></button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if ($assetVariants === []): ?>
                                            <li class="muted"><?php echo $e($t('admin.media.variants_empty')) ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                            <div class="media-actions">
                                <a class="btn btn-small" href="<?php echo $e($publicPath) ?>" target="_blank" rel="noopener"><?php echo $e($t('admin.common.open')) ?></a>
                                <form
                                    method="post"
                                    action="<?php echo $e($basePath) ?>/admin/media/delete"
                                    data-confirm="<?php echo $e($t('admin.media.delete_confirm', ['name' => $asset->originalName])) ?>"
                                    data-confirm-title="<?php echo $e($t('admin.media.delete_title')) ?>"
                                    data-confirm-detail="<?php echo $e($t('admin.media.delete_irreversible')) ?>"
                                    data-confirm-ok="<?php echo $e($t('admin.common.delete')) ?>"
                                >
                                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                    <input type="hidden" name="media_id" value="<?php echo $e($asset->id->value) ?>">
                                    <?php if ($folderQuery !== ''): ?>
                                        <input type="hidden" name="folder_id" value="<?php echo $e($folderQuery) ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.common.delete')) ?></button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="<?php echo $e($basePath) ?>/assets/admin/media.js"></script>
