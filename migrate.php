<?php

require_once __DIR__ . '/core/Database.php';

$config = Database::config();
$server = Database::pdo(false);
$databaseName = str_replace('`', '``', $config['database']);

$server->exec('CREATE DATABASE IF NOT EXISTS `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$server->exec('USE `' . $databaseName . '`');
$server->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(255) NOT NULL,
    migrated_at DATETIME NOT NULL,
    PRIMARY KEY (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $migration = basename($file);
    $check = $server->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = ?');
    $check->execute(array($migration));
    if ((int) $check->fetchColumn() > 0) {
        echo 'Skipped: ' . $migration . PHP_EOL;
        continue;
    }

    $sql = file_get_contents($file);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $server->exec($statement);
        }
    }
    $mark = $server->prepare('INSERT INTO schema_migrations (migration, migrated_at) VALUES (?, NOW())');
    $mark->execute(array($migration));
    echo 'Migrated: ' . $migration . PHP_EOL;
}

echo 'Database ready: ' . $config['database'] . PHP_EOL;
