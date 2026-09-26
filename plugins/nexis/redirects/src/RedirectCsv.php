<?php

declare(strict_types=1);

namespace Nexis\Plugins\Redirects;

/**
 * CSV format: from_path,to_url[,locale[,status_code]]
 * Header row optional (detected when first cell looks like "from").
 *
 * @phpstan-type RedirectRow array{from_path: string, to_url: string, locale: ?string, status_code: int}
 */
final class RedirectCsv
{
    public const MAX_ROWS = 2000;

    /**
     * @return list<RedirectRow>
     */
    public static function parse(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return [];
        }
        // Strip UTF-8 BOM
        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            $csv = substr($csv, 3);
        }

        $lines = preg_split('/\R/u', $csv) ?: [];
        $out = [];
        $started = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $cells = str_getcsv($line, ',', '"', '');
            if ($cells === [null] || $cells === false) {
                continue;
            }
            $cells = array_map(static fn ($c): string => trim((string) $c), $cells);
            if (!$started) {
                $started = true;
                $head = mb_strtolower($cells[0] ?? '');
                if (in_array($head, ['from_path', 'from', 'von', 'source'], true)) {
                    continue;
                }
            }
            if (count($cells) < 2) {
                continue;
            }
            $from = self::normalizeFrom($cells[0]);
            $to = trim($cells[1]);
            if ($from === '' || $to === '') {
                continue;
            }
            $locale = isset($cells[2]) && trim($cells[2]) !== '' ? trim($cells[2]) : null;
            $code = isset($cells[3]) && trim($cells[3]) !== '' ? (int) $cells[3] : 301;
            if (!in_array($code, [301, 302, 307, 308], true)) {
                $code = 301;
            }
            $out[] = [
                'from_path' => $from,
                'to_url' => $to,
                'locale' => $locale,
                'status_code' => $code,
            ];
            if (count($out) >= self::MAX_ROWS) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function export(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return "from_path,to_url,locale,status_code\n";
        }
        fputcsv($fh, ['from_path', 'to_url', 'locale', 'status_code'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($fh, [
                (string) ($row['from_path'] ?? ''),
                (string) ($row['to_url'] ?? ''),
                (string) ($row['locale'] ?? ''),
                (string) ($row['status_code'] ?? 301),
            ], ',', '"', '');
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $csv;
    }

    public static function normalizeFrom(string $from): string
    {
        $from = trim($from);
        if ($from === '') {
            return '';
        }
        if (!str_starts_with($from, '/')) {
            $from = '/' . $from;
        }
        if ($from === '/') {
            return '';
        }

        return $from;
    }
}
