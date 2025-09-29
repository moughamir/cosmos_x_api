<?php

namespace App\Factory;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    public static function create(string $name = 'app', string $logFile = 'php://stderr'): LoggerInterface
    {
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
        return $logger;
    }
}
