<?php
declare(strict_types=1);
$config = require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
$pdo = ehr1_pdo($config);
$n = (int) $pdo->query('SELECT COUNT(*) FROM supplemental_ep_paid')->fetchColumn();
$j = (int) $pdo->query(
    'SELECT COUNT(*) FROM supplemental_ep_paid WHERE payload_json IS NOT NULL'
)->fetchColumn();
echo "supplemental_ep_paid rows: {$n}\n";
echo "with payload_json set: {$j}\n";
try {
    $m = (int) $pdo->query('SELECT COUNT(*) FROM ep_paid_column_manifest')->fetchColumn();
    echo "ep_paid_column_manifest rows: {$m}\n";
} catch (Throwable $e) {
    echo "ep_paid_column_manifest: (table missing?)\n";
}
