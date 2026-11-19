<?php

declare(strict_types=1);

namespace Nexis\Site;

use Nexis\Support\Uuid;
use PDO;

final class PdoSiteRepository implements SiteRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function findById(SiteId $id): ?Site
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sites WHERE id = :id AND deleted_at IS NULL LIMIT 1',
        );
        $stmt->execute(['id' => $id->value]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByHost(string $host): ?Site
    {
        $host = strtolower($host);
        $stmt = $this->pdo->prepare(
            'SELECT s.*
             FROM sites s
             LEFT JOIN site_domains d ON d.site_id = s.id
             WHERE s.deleted_at IS NULL AND s.status = :status
               AND (LOWER(s.primary_domain) = :host_primary OR LOWER(d.host) = :host_domain)
             LIMIT 1',
        );
        $stmt->execute(['status' => 'active', 'host_primary' => $host, 'host_domain' => $host]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function installed(): ?Site
    {
        $sites = $this->all();

        return $sites[0] ?? null;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM sites WHERE deleted_at IS NULL AND status = 'active' ORDER BY name ASC",
        );
        if ($stmt === false) {
            return [];
        }

        $sites = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $sites[] = $this->hydrate($row);
            }
        }

        return $sites;
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = 'id' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id->value;
        }

        $sql = 'SELECT * FROM sites WHERE deleted_at IS NULL AND id IN (' . implode(',', $placeholders) . ') ORDER BY name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $sites = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $sites[] = $this->hydrate($row);
            }
        }

        return $sites;
    }

    public function updateBasics(
        SiteId $id,
        string $name,
        string $primaryDomain,
        string $defaultLocale,
        LocaleUrlStrategy $strategy,
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE sites
             SET name = :name,
                 primary_domain = :primary_domain,
                 default_locale = :default_locale,
                 locale_url_strategy = :locale_url_strategy,
                 updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL',
        );
        $stmt->execute([
            'name' => $name,
            'primary_domain' => $primaryDomain,
            'default_locale' => $defaultLocale,
            'locale_url_strategy' => $strategy->value,
            'updated_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $id->value,
        ]);
        $this->markDefaultLocale($id, $defaultLocale);
    }

    public function addLocale(
        SiteId $siteId,
        string $locale,
        string $label,
        ?string $urlPrefix,
        string $hreflang,
        bool $enabled = true,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO site_locales (id, site_id, locale, label, url_prefix, hreflang, is_default, enabled)
             VALUES (:id, :site_id, :locale, :label, :url_prefix, :hreflang, 0, :enabled)',
        );
        $stmt->execute([
            'id' => Uuid::v7(),
            'site_id' => $siteId->value,
            'locale' => $locale,
            'label' => $label,
            'url_prefix' => $urlPrefix,
            'hreflang' => $hreflang,
            'enabled' => $enabled ? 1 : 0,
        ]);
    }

    public function updateLocale(
        SiteId $siteId,
        string $locale,
        string $label,
        ?string $urlPrefix,
        string $hreflang,
        bool $enabled,
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE site_locales
             SET label = :label,
                 url_prefix = :url_prefix,
                 hreflang = :hreflang,
                 enabled = :enabled
             WHERE site_id = :site_id AND locale = :locale',
        );
        $stmt->execute([
            'label' => $label,
            'url_prefix' => $urlPrefix,
            'hreflang' => $hreflang,
            'enabled' => $enabled ? 1 : 0,
            'site_id' => $siteId->value,
            'locale' => $locale,
        ]);
    }

    public function deleteLocale(SiteId $siteId, string $locale): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM site_locales WHERE site_id = :site_id AND locale = :locale AND is_default = 0',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function countPagesForLocale(SiteId $siteId, string $locale): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM pages WHERE site_id = :site_id AND locale = :locale AND deleted_at IS NULL',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function replaceLocaleDomains(SiteId $siteId, array $localeToHost): void
    {
        $del = $this->pdo->prepare(
            'DELETE FROM site_domains WHERE site_id = :site_id AND locale IS NOT NULL',
        );
        $del->execute(['site_id' => $siteId->value]);
        $ins = $this->pdo->prepare(
            'INSERT INTO site_domains (id, site_id, host, locale, is_primary, created_at)
             VALUES (:id, :site_id, :host, :locale, 0, :created_at)',
        );
        $now = gmdate('Y-m-d H:i:s.v');
        foreach ($localeToHost as $locale => $host) {
            $host = strtolower(trim($host));
            $locale = strtolower(trim($locale));
            if ($host === '' || $locale === '') {
                continue;
            }
            $ins->execute([
                'id' => Uuid::v7(),
                'site_id' => $siteId->value,
                'host' => $host,
                'locale' => $locale,
                'created_at' => $now,
            ]);
        }
    }

    public function localeDomains(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT host, locale FROM site_domains
             WHERE site_id = :site_id AND locale IS NOT NULL AND locale <> \'\'
             ORDER BY host ASC',
        );
        $stmt->execute(['site_id' => $siteId->value]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $locale = (string) ($row['locale'] ?? '');
            $host = (string) ($row['host'] ?? '');
            if ($locale !== '' && $host !== '') {
                $out[$locale] = $host;
            }
        }

        return $out;
    }

    public function localeForHost(SiteId $siteId, string $host): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT locale FROM site_domains
             WHERE site_id = :site_id AND LOWER(host) = :host AND locale IS NOT NULL
             LIMIT 1',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'host' => $host,
        ]);
        $locale = $stmt->fetchColumn();

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    private function markDefaultLocale(SiteId $siteId, string $locale): void
    {
        $clear = $this->pdo->prepare(
            'UPDATE site_locales SET is_default = 0 WHERE site_id = :site_id',
        );
        $clear->execute(['site_id' => $siteId->value]);
        $set = $this->pdo->prepare(
            'UPDATE site_locales
             SET is_default = 1, enabled = 1
             WHERE site_id = :site_id AND locale = :locale',
        );
        $set->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Site
    {
        $id = new SiteId((string) $row['id']);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM site_locales WHERE site_id = :site_id ORDER BY is_default DESC, locale ASC',
        );
        $stmt->execute(['site_id' => $id->value]);
        $locales = [];
        foreach ($stmt->fetchAll() as $localeRow) {
            if (!is_array($localeRow)) {
                continue;
            }
            $prefix = $localeRow['url_prefix'];
            $locales[] = new SiteLocale(
                (string) $localeRow['locale'],
                (string) $localeRow['label'],
                is_string($prefix) && $prefix !== '' ? $prefix : null,
                (string) $localeRow['hreflang'],
                (int) $localeRow['is_default'] === 1,
                (int) $localeRow['enabled'] === 1,
            );
        }

        $strategy = LocaleUrlStrategy::tryFrom((string) $row['locale_url_strategy']) ?? LocaleUrlStrategy::Prefix;

        $domainStmt = $this->pdo->prepare(
            'SELECT host, locale FROM site_domains WHERE site_id = :site_id ORDER BY is_primary DESC, host ASC',
        );
        $domainStmt->execute(['site_id' => $id->value]);
        $domains = [];
        foreach ($domainStmt->fetchAll(PDO::FETCH_ASSOC) as $domainRow) {
            if (!is_array($domainRow)) {
                continue;
            }
            $domains[] = [
                'host' => (string) $domainRow['host'],
                'locale' => is_string($domainRow['locale'] ?? null) && $domainRow['locale'] !== ''
                    ? (string) $domainRow['locale']
                    : null,
            ];
        }

        return new Site(
            $id,
            new TenantId((string) $row['tenant_id']),
            (string) $row['name'],
            (string) $row['primary_domain'],
            (string) $row['default_locale'],
            $strategy,
            $locales,
            $domains,
        );
    }
}
