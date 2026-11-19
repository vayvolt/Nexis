<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Logging;

use Nexis\Kernel\Config;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

final class LoggerFactory
{
    public static function create(string $rootPath, Config $config): Logger
    {
        $logFile = $rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log';
        $level = $config->debug() ? Level::Debug : Level::Info;

        $logDir = dirname($logFile);
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            throw new \RuntimeException('Log-Verzeichnis konnte nicht erstellt werden: ' . $logDir);
        }

        $handler = new StreamHandler($logFile, $level);
        $handler->setFormatter(new JsonFormatter());

        $logger = new Logger('nexis');
        $logger->pushHandler($handler);
        $logger->pushProcessor(new PsrLogMessageProcessor());

        return $logger;
    }
}
