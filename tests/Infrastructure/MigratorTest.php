<?php

declare(strict_types=1);

namespace Nexis\Tests\Infrastructure;

use Nexis\Infrastructure\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testSplitStatementsDropsComments(): void
    {
        $sql = <<<'SQL'
-- header
CREATE TABLE demo (id INT);
-- trailing
INSERT INTO demo (id) VALUES (1);
SQL;

        self::assertSame(
            [
                'CREATE TABLE demo (id INT)',
                'INSERT INTO demo (id) VALUES (1)',
            ],
            Migrator::splitStatements($sql),
        );
    }

    public function testFreshInstallAppliesCoreSchemaOnce(): void
    {
        $schema = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis_core_' . bin2hex(random_bytes(4)) . '.sql';
        file_put_contents($schema, "CREATE TABLE core (id INTEGER PRIMARY KEY);\n");

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $migrator = new Migrator($pdo, $schema);

        self::assertSame(['core_schema.sql'], $migrator->migrate());
        self::assertSame([], $migrator->migrate());
        self::assertNotFalse($pdo->query('SELECT 1 FROM core'));

        unlink($schema);
    }

    public function testMigrateRequiresSchemaFileOnFreshDb(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $migrator = new Migrator($pdo, sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'missing-core-schema.sql');

        $this->expectException(\RuntimeException::class);
        $migrator->migrate();
    }
}
