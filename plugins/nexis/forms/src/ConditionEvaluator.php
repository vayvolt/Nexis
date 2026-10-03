<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

/**
 * Field visibility for form builder conditions (single rule per field).
 */
final class ConditionEvaluator
{
    /**
     * @param array<string, string> $values
     */
    public static function isVisible(FormField $field, array $values): bool
    {
        $rule = $field->visibleWhen;
        if ($rule === null) {
            return true;
        }
        $dep = $rule['field'];
        $op = $rule['op'];
        $expected = $rule['value'];
        $actual = (string) ($values[$dep] ?? '');

        return match ($op) {
            'empty' => trim($actual) === '',
            'not_empty' => trim($actual) !== '',
            'neq' => $actual !== $expected,
            default => $actual === $expected,
        };
    }

    /**
     * @param list<FormField> $fields
     * @param array<string, string> $values
     * @return list<FormField>
     */
    public static function filterVisibleFields(array $fields, array $values): array
    {
        $out = [];
        foreach ($fields as $field) {
            if (self::isVisible($field, $values)) {
                $out[] = $field;
            }
        }

        return $out;
    }

    /**
     * @param list<FormField> $fields
     * @param array<string, string> $values
     * @return list<string> error field keys
     */
    public static function validate(array $fields, array $values): array
    {
        $errors = [];
        foreach (self::filterVisibleFields($fields, $values) as $field) {
            $value = (string) ($values[$field->key] ?? '');
            if ($field->type === 'checkbox') {
                $checked = $value === '1' || $value === 'on' || $value === 'true';
                if ($field->required && !$checked) {
                    $errors[] = $field->key;
                }
                continue;
            }
            if ($field->required && trim($value) === '') {
                $errors[] = $field->key;
                continue;
            }
            if ($field->type === 'email' && trim($value) !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $field->key;
                continue;
            }
            if ($field->type === 'number' && trim($value) !== '' && !is_numeric($value)) {
                $errors[] = $field->key;
                continue;
            }
            if ($field->type === 'select' && trim($value) !== '' && $field->options !== []) {
                if (!in_array($value, $field->optionValues(), true)) {
                    $errors[] = $field->key;
                }
            }
            if (mb_strlen($value) > $field->maxLength()) {
                $errors[] = $field->key;
            }
        }

        return $errors;
    }
}
