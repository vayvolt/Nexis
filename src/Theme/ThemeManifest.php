<?php

declare(strict_types=1);

namespace Nexis\Theme;

final class ThemeManifest
{
    /**
     * @param array<string, mixed> $raw
     * @param list<string> $assets
     * @param array<string, string> $templates
     * @param list<string> $slots
     */
    public function __construct(
        public private(set) string $id,
        public private(set) string $name,
        public private(set) string $version,
        public private(set) string $compatibleCore,
        public private(set) ?string $extends,
        public private(set) string $directory,
        public private(set) string $tokensFile,
        public private(set) array $assets,
        public private(set) array $templates,
        public private(set) array $slots,
        public private(set) array $raw,
    ) {
    }
}
