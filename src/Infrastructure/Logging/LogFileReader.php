<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Logging;

use Nexis\Kernel\Config;

/**
 * Read-only tail of Monolog JSON lines in storage/logs.
 */
final class LogFileReader
{
    private const MAX_BYTES = 2_000_000;

    public function __construct(
        private Config $config,
    ) {
    }

    public function appLogPath(): string
    {
        return $this->config->rootPath
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'logs'
            . DIRECTORY_SEPARATOR . 'app.log';
    }

    /**
     * @return list<array{time: string, level: string, message: string, context: string, channel: string}>
     */
    public function recent(int $limit = 200, string $level = ''): array
    {
        $limit = max(1, min(500, $limit));
        $path = $this->appLogPath();
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $size = filesize($path);
        if ($size === false || $size === 0) {
            return [];
        }

        $read = min(self::MAX_BYTES, $size);
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }
        try {
            if ($size > $read) {
                fseek($fh, -$read, SEEK_END);
                // Discard possibly partial first line.
                fgets($fh);
            }
            $chunk = stream_get_contents($fh);
        } finally {
            fclose($fh);
        }
        if (!is_string($chunk) || $chunk === '') {
            return [];
        }

        $level = strtoupper(trim($level));
        $lines = preg_split("/\r\n|\n|\r/", $chunk) ?: [];
        $entries = [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim((string) $lines[$i]);
            if ($line === '') {
                continue;
            }
            $entry = $this->parseLine($line);
            if ($entry === null) {
                continue;
            }
            if ($level !== '' && $entry['level'] !== $level) {
                continue;
            }
            $entries[] = $entry;
            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    public function fileSizeBytes(): int
    {
        $path = $this->appLogPath();
        if (!is_file($path)) {
            return 0;
        }
        $size = filesize($path);

        return $size === false ? 0 : (int) $size;
    }

    /**
     * @return ?array{time: string, level: string, message: string, context: string, channel: string}
     */
    private function parseLine(string $line): ?array
    {
        try {
            $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [
                'time' => '',
                'level' => 'INFO',
                'message' => mb_substr($line, 0, 500),
                'context' => '',
                'channel' => 'raw',
            ];
        }
        if (!is_array($data)) {
            return null;
        }

        $level = strtoupper((string) ($data['level_name'] ?? $data['level'] ?? 'INFO'));
        $message = (string) ($data['message'] ?? '');
        $message = $this->redact($message);
        $context = $data['context'] ?? [];
        if (!is_array($context)) {
            $context = [];
        }
        unset($context['exception']);
        $contextJson = $context === []
            ? ''
            : $this->redact((string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'time' => (string) ($data['datetime'] ?? ''),
            'level' => $level,
            'message' => mb_substr($message, 0, 2000),
            'context' => mb_substr($contextJson, 0, 2000),
            'channel' => (string) ($data['channel'] ?? 'nexis'),
        ];
    }

    private function redact(string $value): string
    {
        $value = preg_replace('/(password|secret|token|api[_-]?key)\s*[:=]\s*\S+/i', '$1=***', $value) ?? $value;
        $value = preg_replace('/("?(?:password|secret|token|api[_-]?key)"?\s*:\s*")[^"]*(")/i', '$1***$2', $value) ?? $value;

        return $value;
    }
}
