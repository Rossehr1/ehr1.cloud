<?php
/**
 * CLI: report supplemental vs merged EP PAID counts; optionally rebuild merged_ep_paid_npi
 * (same rule as tools/merge_ep_paid_to_npi.py — latest supplemental row per NPI).
 *
 * From ehr1-cloud-app:  php tools/ep_paid_merge_cli.php
 *                        php tools/ep_paid_merge_cli.php --rebuild
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$rebuild = in_array('--rebuild', $argv, true);

$root = dirname(__DIR__);
$config = require $root . '/includes/bootstrap.php';
require_once $root . '/includes/db.php';
$pdo = ehr1_pdo($config);

$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$st = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables '
    . 'WHERE table_schema = ? AND table_name = ?'
);
$st->execute([$dbName, 'merged_ep_paid_npi']);
$mergedExists = ((int) $st->fetchColumn() > 0);

$sup = (int) $pdo->query('SELECT COUNT(*) FROM supplemental_ep_paid')->fetchColumn();
echo 'supplemental_ep_paid rows: ' . $sup . "\n";

if (!$mergedExists) {
    echo "merged_ep_paid_npi: TABLE MISSING — apply sql/mysql/11_merged_ep_paid.sql (or migrate_merged_ep_paid_only.php)\n";
    exit(2);
}

$mer = (int) $pdo->query('SELECT COUNT(*) FROM merged_ep_paid_npi')->fetchColumn();
echo 'merged_ep_paid_npi rows: ' . $mer . "\n";

if (!$rebuild) {
    exit(0);
}

if ($sup === 0) {
    echo "skip rebuild: supplemental_ep_paid is empty\n";
    exit(0);
}

$sqlInsert = <<<'SQL'
INSERT INTO merged_ep_paid_npi (npi, payload_json, source_ep_paid_id, source_batch_id)
SELECT s.npi, s.payload_json, s.ep_paid_id, s.source_batch_id
FROM supplemental_ep_paid s
INNER JOIN (
  SELECT npi, MAX(ep_paid_id) AS max_id
  FROM supplemental_ep_paid
  WHERE npi IS NOT NULL AND npi != ''
  GROUP BY npi
) x ON s.npi = x.npi AND s.ep_paid_id = x.max_id
SQL;

$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM merged_ep_paid_npi');
    $pdo->exec($sqlInsert);
    $mer2 = (int) $pdo->query('SELECT COUNT(*) FROM merged_ep_paid_npi')->fetchColumn();
    $pdo->commit();
    echo 'rebuild OK: merged_ep_paid_npi now has ' . $mer2 . " row(s) (latest supplemental per NPI)\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'rebuild FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
