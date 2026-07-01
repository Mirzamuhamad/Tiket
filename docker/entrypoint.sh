#!/bin/sh
set -e

echo "Waiting for database ${DB_HOST:-db}:${DB_PORT:-3306}..."
php -r '
$host = getenv("DB_HOST") ?: "db";
$port = getenv("DB_PORT") ?: "3306";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "";
$deadline = time() + 60;
do {
    try {
        new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass);
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDOUT, ".");
        sleep(2);
    }
} while (time() < $deadline);
fwrite(STDERR, PHP_EOL . "Database is not ready." . PHP_EOL);
exit(1);
'
echo
mkdir -p /var/www/html/uploads/tickets
chown -R www-data:www-data /var/www/html/uploads

echo "Running database migrations..."
php /var/www/html/migrate.php

exec "$@"
