<?php

declare(strict_types=1);

namespace Nexis\Tests\Infrastructure\Backup;

use Nexis\Infrastructure\Backup\PhpSqlDump;
use Nexis\Kernel\Bootstrap;
use Nexis\Kernel\Config;
use PDO;
use PHPUnit\Framework\TestCase;

final class PhpSqlDumpTest extends TestCase
{
    public function testSplitStatementsRespectsQuotesAndComments(): void
    {
        $sql = <<<'SQL'
SET NAMES utf8mb4;
-- line comment with ;
INSERT INTO t VALUES ('a;b', "c;d", `e`);
/* block ; comment */
INSERT INTO t VALUES ('it''s ok');
SQL;

        $parts = PhpSqlDump::splitStatements($sql);
        self::assertCount(3, $parts);
        self::assertSame('SET NAMES utf8mb4', $parts[0]);
        self::assertStringContainsString("'a;b'", $parts[1]);
        self::assertStringContainsString("it''s ok", $parts[2]);
    }

    public function testDumpAndRestoreRoundTrip(): void
    {
        [$pdo, $db] = $this->pdoOrSkip();
        $suffix = bin2hex(random_bytes(3));
        $table = 'nexis_php_dump_' . $suffix;

        $pdo->exec(
            "CREATE TABLE `{$table}` (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(100) NOT NULL,
                note TEXT NULL,
                blob_col BLOB NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        );
        $ins = $pdo->prepare("INSERT INTO `{$table}` (label, note, blob_col) VALUES (?, ?, ?)");
        $ins->execute(['hello;world', "it's fine", "\0bin"]);
        $ins->execute(['second', null, null]);

        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-dump-' . $suffix . '.sql';
        try {
            (new PhpSqlDump())->dumpToFile($db, $file);
            self::assertFileExists($file);
            $sql = (string) file_get_contents($file);
            self::assertStringContainsString("CREATE TABLE `{$table}`", $sql);
            self::assertStringContainsString('INSERT INTO', $sql);

            $pdo->exec("DROP TABLE `{$table}`");
            (new PhpSqlDump())->restoreFromFile($db, $file);

            $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            self::assertInstanceOf(\PDOStatement::class, $countStmt);
            $count = (int) $countStmt->fetchColumn();
            self::assertSame(2, $count);
            $rowStmt = $pdo->query("SELECT label, note, blob_col FROM `{$table}` WHERE id = 1");
            self::assertInstanceOf(\PDOStatement::class, $rowStmt);
            $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
            self::assertIsArray($row);
            self::assertSame('hello;world', $row['label']);
            self::assertSame("it's fine", $row['note']);
            self::assertSame("\0bin", $row['blob_col']);
        } finally {
            @unlink($file);
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    /**
     * @return array{0: PDO, 1: array{host: string, port: string, database: string, username: string, password: string}}
     */
    private function pdoOrSkip(): array
    {
        $root = dirname(__DIR__, 3);
        if (!is_file($root . DIRECTORY_SEPARATOR . '.env')) {
            self::markTestSkipped('No .env — MariaDB dump round-trip skipped');
        }
        try {
            $app = Bootstrap::boot($root);
            /** @var PDO $pdo */
            $pdo = $app->container->get(PDO::class);
            $pdo->query('SELECT 1');
            /** @var Config $config */
            $config = $app->container->get(Config::class);
            $db = [
                'host' => (string) $config->get('db.host', '127.0.0.1'),
                'port' => (string) $config->get('db.port', '3306'),
                'database' => (string) $config->get('db.database', 'nexis'),
                'username' => (string) $config->get('db.username', 'root'),
                'password' => (string) $config->get('db.password', ''),
            ];

            return [$pdo, $db];
        } catch (\Throwable $e) {
            self::markTestSkipped('MariaDB not available: ' . $e->getMessage());
        }
    }
}
