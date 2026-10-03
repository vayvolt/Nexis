<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;
use RuntimeException;
use Throwable;

/**
 * CRUD for form definitions (typed FormDefinition / FormField models).
 */
final class FormDefinitionStore
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<FormDefinition>
     */
    public function all(SiteId $siteId): array
    {
        $this->ensureDefaultContactForm($siteId);
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, site_id, slug, name, fields_json, success_message, submit_label, created_at, updated_at
                 FROM plugin_nexis_forms_definitions
                 WHERE site_id = :site_id
                 ORDER BY name ASC',
            );
            $stmt->execute(['site_id' => $siteId->value]);
        } catch (Throwable) {
            return [FormDefinition::defaultContact($siteId->value)];
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $out[] = FormDefinition::fromRow($row);
            }
        }
        if ($out === []) {
            return [FormDefinition::defaultContact($siteId->value)];
        }

        return $out;
    }

    public function find(SiteId $siteId, string $idOrSlug): ?FormDefinition
    {
        $idOrSlug = trim($idOrSlug);
        if ($idOrSlug === '') {
            return null;
        }
        $this->ensureDefaultContactForm($siteId);
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, site_id, slug, name, fields_json, success_message, submit_label, created_at, updated_at
                 FROM plugin_nexis_forms_definitions
                 WHERE site_id = :site_id AND (id = :id OR slug = :slug)
                 LIMIT 1',
            );
            $stmt->execute([
                'site_id' => $siteId->value,
                'id' => $idOrSlug,
                'slug' => $idOrSlug,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            if ($idOrSlug === FormDefinition::CONTACT_SLUG) {
                return FormDefinition::defaultContact($siteId->value);
            }

            return null;
        }

        return is_array($row) ? FormDefinition::fromRow($row) : null;
    }

    public function create(SiteId $siteId, string $name, string $slug = ''): FormDefinition
    {
        $name = mb_substr(trim($name), 0, 190);
        if ($name === '') {
            throw new RuntimeException('forms.invalid_definition');
        }
        $slug = FormDefinition::normalizeSlug($slug !== '' ? $slug : $name);
        if ($slug === '') {
            $slug = 'form-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }
        if ($this->slugTaken($siteId, $slug)) {
            $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }
        $definition = new FormDefinition(
            Uuid::v7(),
            $siteId->value,
            $slug,
            $name,
            FormDefinition::contactFields(),
            'Vielen Dank!',
            'Senden',
        );
        $this->insert($definition);

        return $definition;
    }

    public function save(FormDefinition $definition): void
    {
        if (!$definition->persisted || $definition->id === '') {
            $this->insert($definition);

            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE plugin_nexis_forms_definitions
             SET slug = :slug, name = :name, fields_json = :fields_json,
                 success_message = :success_message, submit_label = :submit_label,
                 updated_at = :updated_at
             WHERE id = :id AND site_id = :site_id',
        );
        $stmt->execute([
            'id' => $definition->id,
            'site_id' => $definition->siteId,
            'slug' => $definition->slug,
            'name' => $definition->name,
            'fields_json' => $definition->fieldsJson(),
            'success_message' => $definition->successMessage,
            'submit_label' => $definition->submitLabel,
            'updated_at' => gmdate('Y-m-d H:i:s.v'),
        ]);
        $definition->updatedAt = gmdate('Y-m-d H:i:s.v');
    }

    public function delete(SiteId $siteId, string $id): bool
    {
        $existing = $this->find($siteId, $id);
        if ($existing === null || !$existing->persisted) {
            return false;
        }
        if ($existing->slug === FormDefinition::CONTACT_SLUG) {
            throw new RuntimeException('forms.cannot_delete_default');
        }
        $stmt = $this->pdo->prepare(
            'DELETE FROM plugin_nexis_forms_definitions WHERE id = :id AND site_id = :site_id',
        );
        $stmt->execute(['id' => $existing->id, 'site_id' => $siteId->value]);

        return $stmt->rowCount() > 0;
    }

    public function slugTaken(SiteId $siteId, string $slug, ?string $exceptId = null): bool
    {
        try {
            $sql = 'SELECT id FROM plugin_nexis_forms_definitions
                    WHERE site_id = :site_id AND slug = :slug';
            $params = ['site_id' => $siteId->value, 'slug' => $slug];
            if ($exceptId !== null && $exceptId !== '') {
                $sql .= ' AND id <> :except';
                $params['except'] = $exceptId;
            }
            $sql .= ' LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function ensureDefaultContactForm(SiteId $siteId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM plugin_nexis_forms_definitions
                 WHERE site_id = :site_id AND slug = :slug LIMIT 1',
            );
            $stmt->execute(['site_id' => $siteId->value, 'slug' => FormDefinition::CONTACT_SLUG]);
            if ($stmt->fetchColumn() !== false) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        try {
            $definition = new FormDefinition(
                Uuid::v7(),
                $siteId->value,
                FormDefinition::CONTACT_SLUG,
                'Kontakt',
                FormDefinition::contactFields(),
                'Vielen Dank!',
                'Senden',
            );
            $this->insert($definition);
        } catch (Throwable) {
            // race / missing table
        }
    }

    private function insert(FormDefinition $definition): void
    {
        if ($definition->id === '') {
            $definition->id = Uuid::v7();
        }
        $now = gmdate('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'INSERT INTO plugin_nexis_forms_definitions
                (id, site_id, slug, name, fields_json, success_message, submit_label, created_at, updated_at)
             VALUES
                (:id, :site_id, :slug, :name, :fields_json, :success_message, :submit_label, :created_at, :updated_at)',
        );
        $stmt->execute([
            'id' => $definition->id,
            'site_id' => $definition->siteId,
            'slug' => $definition->slug,
            'name' => $definition->name,
            'fields_json' => $definition->fieldsJson(),
            'success_message' => $definition->successMessage,
            'submit_label' => $definition->submitLabel,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $definition->createdAt = $now;
        $definition->updatedAt = $now;
        $definition->persisted = true;
    }
}
