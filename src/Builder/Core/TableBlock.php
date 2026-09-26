<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class TableBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'core/table';
    }

    public function label(): string
    {
        return 'Tabelle';
    }

    public function defaultProps(): array
    {
        return [
            'csv' => "Spalte A,Spalte B\nWert 1,Wert 2",
            'header' => 'yes',
            'caption' => '',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'csv' => (object) ['type' => 'string'],
                'header' => (object) ['type' => 'string', 'enum' => ['yes', 'no']],
                'caption' => (object) ['type' => 'string'],
            ],
            'required' => ['csv', 'header'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $rows = self::parseCsv((string) ($props['csv'] ?? ''));
        if ($rows === []) {
            return '<div class="bk-table bk-table--empty"><p>Leere Tabelle.</p></div>';
        }
        $withHeader = ((string) ($props['header'] ?? 'yes')) === 'yes';
        $caption = trim((string) ($props['caption'] ?? ''));

        $html = '<div class="bk-table"><table>';
        if ($caption !== '') {
            $html .= '<caption>' . $this->e($caption) . '</caption>';
        }
        if ($withHeader) {
            $head = array_shift($rows);
            if (is_array($head)) {
                $html .= '<thead><tr>';
                foreach ($head as $cell) {
                    $html .= '<th scope="col">' . $this->e($cell) . '</th>';
                }
                $html .= '</tr></thead>';
            }
        }
        $html .= '<tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $this->e($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @return list<list<string>>
     */
    public static function parseCsv(string $csv): array
    {
        $csv = str_replace(["\r\n", "\r"], "\n", $csv);
        $lines = explode("\n", $csv);
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cells = str_getcsv($line, ',', '"', '\\');
            $row = [];
            foreach ($cells as $cell) {
                $row[] = trim((string) $cell);
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
