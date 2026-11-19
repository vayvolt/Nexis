<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Plugin\PluginAssetRegistry;

final class FormsAssets
{
    public function __construct(
        private PluginAssetRegistry $assets,
    ) {
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function headHtml(array $ctx): string
    {
        $basePath = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $css = $basePath . $this->assets->url('nexis/forms', 'forms.css');
        $js = $basePath . $this->assets->url('nexis/forms', 'forms.js');

        return '<link rel="stylesheet" href="' . $css . '">' . "\n"
            . '<script src="' . $js . '" defer></script>';
    }
}
