<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

/**
 * A single field of a form definition. Conditions are intentionally flat:
 * one `visibleWhen` rule per field, no AND/OR trees.
 */
final class FormField
{
    public const TYPES = ['text', 'email', 'textarea', 'select', 'checkbox', 'number'];

    public const OPS = ['eq', 'neq', 'empty', 'not_empty'];

    /** Names already used by the submit endpoint itself. */
    private const RESERVED_KEYS = ['_csrf', '_idempotency_key', 'locale', 'form_id', 'form_slug'];

    public const MAX_VALUE_LENGTH = 500;

    public const MAX_TEXTAREA_LENGTH = 5000;

    /**
     * @param list<array{value: string, label: string}> $options
     * @param array{field: string, op: string, value: string}|null $visibleWhen
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required,
        public array $options = [],
        public string $placeholder = '',
        public ?array $visibleWhen = null,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): ?self
    {
        $key = self::normalizeKey((string) ($raw['key'] ?? ''));
        if ($key === '') {
            return null;
        }
        $type = strtolower(trim((string) ($raw['type'] ?? 'text')));
        if (!in_array($type, self::TYPES, true)) {
            $type = 'text';
        }
        $label = trim((string) ($raw['label'] ?? ''));
        if ($label === '') {
            $label = $key;
        }

        return new self(
            $key,
            mb_substr($label, 0, 190),
            $type,
            self::truthy($raw['required'] ?? false),
            $type === 'select' ? self::normalizeOptions($raw['options'] ?? []) : [],
            mb_substr(trim((string) ($raw['placeholder'] ?? '')), 0, 190),
            self::normalizeCondition($raw['visibleWhen'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
        ];
        if ($this->options !== []) {
            $out['options'] = $this->options;
        }
        if ($this->placeholder !== '') {
            $out['placeholder'] = $this->placeholder;
        }
        if ($this->visibleWhen !== null) {
            $out['visibleWhen'] = $this->visibleWhen;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_map(static fn (array $option): string => $option['value'], $this->options);
    }

    public function maxLength(): int
    {
        return $this->type === 'textarea' ? self::MAX_TEXTAREA_LENGTH : self::MAX_VALUE_LENGTH;
    }

    /**
     * Lowercase snake_case identifier; empty when unusable or reserved.
     */
    public static function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = (string) preg_replace('/[^a-z0-9_]+/', '_', $key);
        $key = trim($key, '_');
        if ($key === '' || preg_match('/^[a-z]/', $key) !== 1) {
            return '';
        }
        $key = mb_substr($key, 0, 40);
        if (in_array($key, self::RESERVED_KEYS, true)) {
            return '';
        }

        return $key;
    }

    /**
     * Normalize a raw field list, dropping unusable entries and duplicate keys.
     *
     * @param mixed $raw
     * @return list<self>
     */
    public static function listFromArray(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $fields = [];
        $seen = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $field = self::fromArray($entry);
            if ($field === null || isset($seen[$field->key])) {
                continue;
            }
            $seen[$field->key] = true;
            $fields[] = $field;
        }

        return self::dropDanglingConditions($fields);
    }

    /**
     * @param list<self> $fields
     * @return list<array<string, mixed>>
     */
    public static function listToArray(array $fields): array
    {
        return array_map(static fn (self $field): array => $field->toArray(), $fields);
    }

    /**
     * Conditions may only reference a field declared before this one.
     *
     * @param list<self> $fields
     * @return list<self>
     */
    private static function dropDanglingConditions(array $fields): array
    {
        $before = [];
        foreach ($fields as $field) {
            if ($field->visibleWhen !== null && !isset($before[$field->visibleWhen['field']])) {
                $field->visibleWhen = null;
            }
            $before[$field->key] = true;
        }

        return $fields;
    }

    /**
     * @return array{field: string, op: string, value: string}|null
     */
    private static function normalizeCondition(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $field = self::normalizeKey((string) ($raw['field'] ?? ''));
        if ($field === '') {
            return null;
        }
        $op = strtolower(trim((string) ($raw['op'] ?? 'eq')));
        if (!in_array($op, self::OPS, true)) {
            return null;
        }
        $value = ($op === 'empty' || $op === 'not_empty')
            ? ''
            : mb_substr(trim((string) ($raw['value'] ?? '')), 0, self::MAX_VALUE_LENGTH);

        return ['field' => $field, 'op' => $op, 'value' => $value];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function normalizeOptions(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $options = [];
        $seen = [];
        foreach ($raw as $entry) {
            if (is_string($entry)) {
                $value = trim($entry);
                $label = $value;
            } elseif (is_array($entry)) {
                $value = trim((string) ($entry['value'] ?? ''));
                $label = trim((string) ($entry['label'] ?? ''));
                if ($value === '') {
                    $value = $label;
                }
                if ($label === '') {
                    $label = $value;
                }
            } else {
                continue;
            }
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $options[] = [
                'value' => mb_substr($value, 0, self::MAX_VALUE_LENGTH),
                'label' => mb_substr($label, 0, 190),
            ];
        }

        return $options;
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }
}
