<?php
/**
 * Apply sql/mysql/06_alter_supplemental_ep_paid_payload.sql logic (idempotent).
 * Run on server: php tools/run-migration-06.php
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

$hasCol = (int) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '
    . $pdo->quote($schema)
    . " AND TABLE_NAME = 'supplemental_ep_paid' AND COLUMN_NAME = 'payload_json'"
)->fetchColumn();
if ($hasCol === 0) {
    $pdo->exec(
        "ALTER TABLE supplemental_ep_paid ADD COLUMN payload_json JSON NULL "
        . "COMMENT 'EP PAID xlsx row as object keyed by column header' AFTER raw_note"
    );
    echo "Added column payload_json.\n";
} else {
    echo "Column payload_json already present.\n";
}

$hasUk = (int) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '
    . $pdo->quote($schema)
    . " AND TABLE_NAME = 'supplemental_ep_paid' AND INDEX_NAME = 'uk_supp_ep_paid_npi'"
)->fetchColumn();
if ($hasUk === 0) {
    $pdo->exec('ALTER TABLE supplemental_ep_paid ADD UNIQUE KEY uk_supp_ep_paid_npi (npi)');
    echo "Added UNIQUE KEY uk_supp_ep_paid_npi (npi).\n";
} else {
    echo "UNIQUE KEY uk_supp_ep_paid_npi already present.\n";
}

echo "Done.\n";
