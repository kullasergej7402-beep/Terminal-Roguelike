<?php

declare(strict_types=1);

namespace TerminalRoguelike\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin factory around a single PDO/SQLite connection.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function connection(string $dbPath): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $directory = dirname($dbPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        try {
            $pdo = new PDO('sqlite:' . $dbPath);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to SQLite database: ' . $exception->getMessage(), 0, $exception);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        self::$connection = $pdo;

        return $pdo;
    }
}
