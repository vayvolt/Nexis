<?php

declare(strict_types=1);

namespace Nexis\Plugin;

/**
 * Declared plugin setting field (storage key = plugin:{id}.{field}).
 */
final readonly class SettingsField
{
    public function __construct(
        public string $name,
        /** string|bool|int|email|url|secret */
        public string $type = 'string',
        public mixed $default = null,
        public string $label = '',
    ) {
    }

    public function isSecret(): bool
    {
        return $this->type === 'secret';
    }
}
