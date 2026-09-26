<?php

declare(strict_types=1);

namespace Nexis\Builder;

use RuntimeException;

/**
 * Leichte JSON-Schema-ähnliche Validierung für Block-Props (Draft-4-Subset).
 * Vermeidet justinrainbow/json-schema wegen PHP-8.4-Deprecations.
 */
final class DocumentValidator
{
    public function __construct(
        private BlockRegistry $registry,
    ) {
    }

    /**
     * @param array<string, mixed> $document
     * @return list<string>
     */
    public function validate(array $document): array
    {
        $errors = [];
        try {
            $doc = BlockDocument::fromArray($document);
        } catch (\InvalidArgumentException $e) {
            return [$e->getMessage()];
        }

        if ($doc->schemaVersion !== BlockDocument::SCHEMA_VERSION) {
            $errors[] = 'Unsupported schemaVersion: ' . $doc->schemaVersion;
        }

        $this->validateNode($doc->root, 'root', $errors, true);

        return $errors;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $errors
     */
    private function validateNode(array $node, string $path, array &$errors, bool $requireKnown = false): void
    {
        if (!isset($node['id'], $node['type']) || !is_string($node['id']) || !is_string($node['type'])) {
            $errors[] = $path . ': Block braucht id und type.';

            return;
        }

        $type = $node['type'];
        $blockType = $this->registry->get($type);
        if ($blockType === null) {
            if ($requireKnown) {
                $errors[] = $path . ': Unbekannter Blocktyp ' . $type . '.';
            }
        } else {
            $props = $node['props'] ?? [];
            if (!is_array($props)) {
                $errors[] = $path . ': props muss Objekt sein.';
            } else {
                $schema = $blockType->propsSchema();
                if (is_object($schema)) {
                    /** @var array<string, mixed> $schemaArray */
                    $schemaArray = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                } else {
                    $schemaArray = $schema;
                }
                foreach ($this->validateProps($props, $schemaArray, $path . '.props') as $error) {
                    $errors[] = $error;
                }
            }
        }

        if (!array_key_exists('children', $node)) {
            return;
        }
        $children = $node['children'];
        if (!is_array($children)) {
            $errors[] = $path . ': children muss Array sein.';

            return;
        }
        foreach ($children as $i => $child) {
            if (!is_array($child)) {
                $errors[] = $path . '.children[' . $i . ']: ungültig.';
                continue;
            }
            $this->validateNode($child, $path . '.children[' . $i . ']', $errors, false);
        }
    }

    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $schema
     * @return list<string>
     */
    private function validateProps(array $props, array $schema, string $path): array
    {
        $errors = [];
        $required = $schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $key) {
                if (!is_string($key)) {
                    continue;
                }
                if (!array_key_exists($key, $props)) {
                    $errors[] = $path . '.' . $key . ': fehlt.';
                }
            }
        }

        $properties = $schema['properties'] ?? [];
        if (!is_array($properties)) {
            return $errors;
        }

        $additional = $schema['additionalProperties'] ?? true;
        foreach ($props as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (!isset($properties[$key])) {
                if ($additional === false) {
                    $errors[] = $path . '.' . $key . ': unbekannt.';
                }
                continue;
            }
            $propSchema = $properties[$key];
            if (!is_array($propSchema)) {
                continue;
            }
            $errors = [...$errors, ...$this->validateValue($value, $propSchema, $path . '.' . $key)];
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $schema
     * @return list<string>
     */
    private function validateValue(mixed $value, array $schema, string $path): array
    {
        $type = $schema['type'] ?? null;
        if ($type === 'string') {
            if (!is_string($value)) {
                return [$path . ': muss string sein.'];
            }
            if (isset($schema['minLength']) && is_int($schema['minLength']) && strlen($value) < $schema['minLength']) {
                return [$path . ': zu kurz.'];
            }
            if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
                return [$path . ': ungültiger Enum-Wert.'];
            }

            return [];
        }
        if ($type === 'integer') {
            if (!is_int($value)) {
                return [$path . ': muss integer sein.'];
            }
            if (isset($schema['minimum']) && is_int($schema['minimum']) && $value < $schema['minimum']) {
                return [$path . ': unter Minimum.'];
            }
            if (isset($schema['maximum']) && is_int($schema['maximum']) && $value > $schema['maximum']) {
                return [$path . ': über Maximum.'];
            }

            return [];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $document
     */
    public function assertValid(array $document): void
    {
        $errors = $this->validate($document);
        if ($errors !== []) {
            throw new RuntimeException('Dokument ungültig: ' . implode('; ', $errors));
        }
    }
}
