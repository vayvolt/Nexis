<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Database;

use PDO;
use Throwable;

final class DatabaseHealth
{
    /**
     * @param callable(): PDO $connector
     */
    public function __construct(
        private mixed $connector,
    ) {
    }

    public function status(): string
    {
        try {
            if (!is_callable($this->connector)) {
                return 'down';
            }
            $pdo = ($this->connector)();
            if (!$pdo instanceof PDO) {
                return 'down';
            }
            $pdo->query('SELECT 1');

            return 'ok';
        } catch (Throwable) {
            return 'down';
        }
    }
}
