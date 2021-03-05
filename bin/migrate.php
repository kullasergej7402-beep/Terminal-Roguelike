<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use TerminalRoguelike\Database\Database;

$root = dirname(__DIR__);

$dotenv = Dotenv::createImmutable($root);
$dotenv->safeLoad();

$dbPath = $root . '/' . ($_ENV['DB_PATH'] ?? 'database/game.sqlite');
$schemaPath = $root . '/database/schema.sql';

$pdo = Database::connection($dbPath);
$schema = file_get_contents($schemaPath);

if ($schema === false) {
    fwrite(STDERR, "Could not read schema file: {$schemaPath}\n");
    exit(1);
}

$pdo->exec($schema);

echo "Database migrated: {$dbPath}\n";
