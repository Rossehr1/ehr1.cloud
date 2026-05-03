<?php
/**
 * Create ep_paid_column_manifest (EP PAID Data explorer column list). Idempotent.
 * Run on server: php tools/run-migration-07.php
 */
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
$db = $config['db'];
$schema = (string) $db['name'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    (int) $db['port'],
    $db['name'],
    $db['charset']
);
$pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$exists = (int) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '
    . $pdo->quote($schema)
    . " AND TABLE_NAME = 'ep_paid_column_manifest'"
)->fetchColumn();
if ($exists === 0) {
    $pdo->exec(
        'CREATE TABLE ep_paid_column_manifest ('
        . ' ordinal INT UNSIGNED NOT NULL,'
        . ' header_name VARCHAR(768) NOT NULL,'
        . ' PRIMARY KEY (ordinal)'
        . ' ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        . " COMMENT='EP PAID explorer columns; DELETE+INSERT on each load'"
    );
    echo "Created table ep_paid_column_manifest.\n";
} else {
    echo "Table ep_paid_column_manifest already present.\n";
}

echo "Done.\n";
