<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

use PDO;
use RuntimeException;

/**
 * Logical MariaDB dump/restore via PDO only (no mysqldump/mysql client).
 * Suitable for shared hosting where exec/proc_open or CLI binaries are unavailable.
 */
final class PhpSqlDump
{
    private const INSERT_BATCH = 50;

    /**
     * @param array{host: string, port: string, database: string, username: string, password: string} $db
     */
    public function dumpToFile(array $db, string $sqlFile): void
    {
        $pdo = $this->connect($db);
        $handle = fopen($sqlFile, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Cannot write database.sql.');
        }

        try {
            $this->write($handle, "-- Nexis PHP SQL dump\n");
            $this->write($handle, '-- Database: `' . str_replace('`', '``', $db['database']) . "`\n");
            $this->write($handle, '-- Generated: ' . gmdate('c') . "\n\n");
            $this->write($handle, "SET NAMES utf8mb4;\n");
            $this->write($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            $this->write($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = [];
            $views = [];
            $list = $pdo->query('SHOW FULL TABLES');
            if ($list === false) {
                throw new RuntimeException('SHOW FULL TABLES failed.');
            }
            while ($row = $list->fetch(PDO::FETCH_NUM)) {
                $name = (string) ($row[0] ?? '');
                $type = strtoupper((string) ($row[1] ?? 'BASE TABLE'));
                if ($name === '') {
                    continue;
                }
                if ($type === 'VIEW') {
                    $views[] = $name;
                } else {
                    $tables[] = $name;
                }
            }

            foreach ($views as $view) {
                $this->write($handle, 'DROP VIEW IF EXISTS `' . str_replace('`', '``', $view) . "`;\n");
            }
            foreach ($tables as $table) {
                $this->dumpTable($pdo, $handle, $table);
            }
            foreach ($views as $view) {
                $this->dumpView($pdo, $handle, $view);
            }

            $this->write($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }

        if (!is_file($sqlFile) || filesize($sqlFile) <= 0) {
            throw new RuntimeException('PHP SQL dump produced an empty file.');
        }
    }

    /**
     * @param array{host: string, port: string, database: string, username: string, password: string} $db
     */
    public function restoreFromFile(array $db, string $sqlFile): void
    {
        if (!is_file($sqlFile) || filesize($sqlFile) <= 0) {
            throw new RuntimeException('database.sql missing or empty.');
        }

        $sql = file_get_contents($sqlFile);
        if (!is_string($sql) || $sql === '') {
            throw new RuntimeException('database.sql could not be read.');
        }

        $pdo = $this->connect($db);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET NAMES utf8mb4');

        try {
            foreach (self::splitStatements($sql) as $statement) {
                $pdo->exec($statement);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Split SQL into statements; respects quotes and comments (not DELIMITER blocks).
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buf = '';
        $len = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($ch === "\n") {
                    $inLineComment = false;
                    $buf .= $ch;
                }
                continue;
            }
            if ($inBlockComment) {
                if ($ch === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($ch === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($ch === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($ch === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
                if ($ch === ';') {
                    $trimmed = trim($buf);
                    if ($trimmed !== '') {
                        $statements[] = $trimmed;
                    }
                    $buf = '';
                    continue;
                }
            }

            if ($inSingle) {
                $buf .= $ch;
                if ($ch === '\\' && $next !== '') {
                    $buf .= $next;
                    $i++;
                    continue;
                }
                if ($ch === "'") {
                    if ($next === "'") {
                        $buf .= $next;
                        $i++;
                        continue;
                    }
                    $inSingle = false;
                }
                continue;
            }
            if ($inDouble) {
                $buf .= $ch;
                if ($ch === '\\' && $next !== '') {
                    $buf .= $next;
                    $i++;
                    continue;
                }
                if ($ch === '"') {
                    if ($next === '"') {
                        $buf .= $next;
                        $i++;
                        continue;
                    }
                    $inDouble = false;
                }
                continue;
            }
            if ($inBacktick) {
                $buf .= $ch;
                if ($ch === '`') {
                    if ($next === '`') {
                        $buf .= $next;
                        $i++;
                        continue;
                    }
                    $inBacktick = false;
                }
                continue;
            }

            if ($ch === "'") {
                $inSingle = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === '"') {
                $inDouble = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === '`') {
                $inBacktick = true;
                $buf .= $ch;
                continue;
            }

            $buf .= $ch;
        }

        $trimmed = trim($buf);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    /**
     * @param resource $handle
     */
    private function dumpTable(PDO $pdo, $handle, string $table): void
    {
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        $this->write($handle, "\nDROP TABLE IF EXISTS {$quoted};\n");

        $create = $pdo->query('SHOW CREATE TABLE ' . $quoted);
        if ($create === false) {
            throw new RuntimeException('SHOW CREATE TABLE failed for ' . $table);
        }
        $row = $create->fetch(PDO::FETCH_ASSOC);
        $create->closeCursor();
        $ddl = is_array($row) ? (string) ($row['Create Table'] ?? '') : '';
        if ($ddl === '') {
            throw new RuntimeException('Empty CREATE TABLE for ' . $table);
        }
        $this->write($handle, $ddl . ";\n\n");

        $data = $pdo->query('SELECT * FROM ' . $quoted);
        if ($data === false) {
            throw new RuntimeException('SELECT failed for ' . $table);
        }

        $batch = [];
        $columnCount = 0;
        while ($row = $data->fetch(PDO::FETCH_NUM)) {
            if ($columnCount === 0) {
                $columnCount = count($row);
            }
            $values = [];
            foreach ($row as $value) {
                $values[] = $this->sqlLiteral($pdo, $value);
            }
            $batch[] = '(' . implode(',', $values) . ')';
            if (count($batch) >= self::INSERT_BATCH) {
                $this->flushInsert($handle, $quoted, $batch);
                $batch = [];
            }
        }
        $data->closeCursor();
        if ($batch !== []) {
            $this->flushInsert($handle, $quoted, $batch);
        }
    }

    /**
     * @param resource $handle
     */
    private function dumpView(PDO $pdo, $handle, string $view): void
    {
        $quoted = '`' . str_replace('`', '``', $view) . '`';
        $create = $pdo->query('SHOW CREATE VIEW ' . $quoted);
        if ($create === false) {
            throw new RuntimeException('SHOW CREATE VIEW failed for ' . $view);
        }
        $row = $create->fetch(PDO::FETCH_ASSOC);
        $create->closeCursor();
        $ddl = is_array($row) ? (string) ($row['Create View'] ?? '') : '';
        if ($ddl === '') {
            throw new RuntimeException('Empty CREATE VIEW for ' . $view);
        }
        // Strip DEFINER for restore under restricted accounts.
        $ddl = (string) preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $ddl);
        $this->write($handle, "\n" . $ddl . ";\n");
    }

    /**
     * @param resource $handle
     * @param list<string> $batch
     */
    private function flushInsert($handle, string $quotedTable, array $batch): void
    {
        $this->write(
            $handle,
            'INSERT INTO ' . $quotedTable . ' VALUES ' . implode(",\n", $batch) . ";\n",
        );
    }

    private function sqlLiteral(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_resource($value)) {
            $bin = stream_get_contents($value);
            $value = is_string($bin) ? $bin : '';
        }
        $str = (string) $value;
        if ($str === '') {
            return $pdo->quote($str) ?: "''";
        }
        if (str_contains($str, "\0") || preg_match('//u', $str) !== 1) {
            return "X'" . bin2hex($str) . "'";
        }
        $quoted = $pdo->quote($str);
        if ($quoted === false) {
            return "X'" . bin2hex($str) . "'";
        }

        return $quoted;
    }

    /**
     * @param array{host: string, port: string, database: string, username: string, password: string} $db
     */
    private function connect(array $db): PDO
    {
        $port = $db['port'] !== '' ? $db['port'] : '3306';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $db['host'],
            $port,
            $db['database'],
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (\PHP_VERSION_ID >= 80500) {
            $options[\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY] = true;
        } else {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }

        return new PDO($dsn, $db['username'], $db['password'], $options);
    }

    /**
     * @param resource $handle
     */
    private function write($handle, string $chunk): void
    {
        if (fwrite($handle, $chunk) === false) {
            throw new RuntimeException('Failed writing database.sql.');
        }
    }
}
