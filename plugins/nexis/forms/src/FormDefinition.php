<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

final class FormDefinition
{
    public const CONTACT_SLUG = 'contact';

    /**
     * @param list<FormField> $fields
     */
    public function __construct(
        public string $id,
        public string $siteId,
        public string $slug,
        public string $name,
        public array $fields,
        public ?string $successMessage = null,
        public ?string $submitLabel = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public bool $persisted = false,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $decoded = json_decode((string) ($row['fields_json'] ?? '[]'), true);

        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['site_id'] ?? ''),
            (string) ($row['slug'] ?? ''),
            (string) ($row['name'] ?? ''),
            FormField::listFromArray($decoded),
            self::nullableString($row['success_message'] ?? null),
            self::nullableString($row['submit_label'] ?? null),
            self::nullableString($row['created_at'] ?? null),
            self::nullableString($row['updated_at'] ?? null),
            true,
        );
    }

    /**
     * In-memory fallback so pages keep working before the first form was saved.
     */
    public static function defaultContact(string $siteId = ''): self
    {
        return new self('', $siteId, self::CONTACT_SLUG, 'Kontakt', self::contactFields());
    }

    /**
     * The classic hardcoded contact fields (name/email/message).
     *
     * @return list<FormField>
     */
    public static function contactFields(): array
    {
        return [
            new FormField('name', 'Name', 'text', true),
            new FormField('email', 'E-Mail', 'email', true),
            new FormField('message', 'Nachricht', 'textarea', true),
        ];
    }

    public function fieldsJson(): string
    {
        return (string) json_encode(FormField::listToArray($this->fields), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function field(string $key): ?FormField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function fieldKeys(): array
    {
        return array_map(static fn (FormField $field): string => $field->key, $this->fields);
    }

    public function isContactShaped(): bool
    {
        return $this->field('name') !== null
            && $this->field('email') !== null
            && $this->field('message') !== null;
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = (string) preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return mb_substr($slug, 0, 80);
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        return $value === '' ? null : $value;
    }
}
