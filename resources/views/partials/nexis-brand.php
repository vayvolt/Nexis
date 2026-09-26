<?php

declare(strict_types=1);

use Nexis\Kernel\Nexis;

/** @var string $basePath */
/** @var 'logo'|'icon' $variant */
/** @var string|null $href */
/** @var string|null $class */
/** @var string|null $imgClass */

$variant = $variant ?? 'logo';
$href = $href ?? null;
$class = trim('nexis-brand ' . ($class ?? ''));
$imgClass = trim('nexis-brand__img ' . ($imgClass ?? '') . ($variant === 'logo' ? ' nexis-brand__img--logo' : ' nexis-brand__img--icon'));
$asset = $variant === 'icon' ? Nexis::BRAND_ICON : Nexis::BRAND_LOGO;
$src = Nexis::brandUrl((string) ($basePath ?? ''), $asset);
$alt = Nexis::NAME;
$tag = $href !== null && $href !== ''
    ? '<a class="' . htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    : '<span class="' . htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
$close = $href !== null && $href !== '' ? '</a>' : '</span>';
?>
<?php echo $tag ?>
    <img class="<?php echo htmlspecialchars($imgClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" src="<?php echo htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?php echo htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" width="<?php echo $variant === 'logo' ? '130' : '32' ?>" height="<?php echo $variant === 'logo' ? '48' : '32' ?>" decoding="async">
<?php echo $close ?>
