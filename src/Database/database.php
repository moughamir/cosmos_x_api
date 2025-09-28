<?php

namespace App\Database;

use PDO;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dbFile = __DIR__ . '/../../config/products.sqlite';
            self::$instance = new PDO("sqlite:" . $dbFile);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$instance;
    }
}
