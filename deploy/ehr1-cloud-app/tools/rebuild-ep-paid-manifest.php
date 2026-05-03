<?php
/**
 * Fill ep_paid_column_manifest from ep_paid_headers.generated.json (if any) plus
 * top-level keys seen across supplemental_ep_paid.payload_json.
 * Run on server when you cannot run tools/ep_paid_sync.py load (e.g. no xlsx on hand).
 *
 * Usage: php tools/rebuild-ep-paid-manifest.php
 */
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ep_paid_helpers.php';

$pdo = ehr1_pdo($config);

if (!ehr1_ep_paid_manifest_table_exists($pdo)) {
    fwrite(STDERR, "Run tools/run-migration-07.php first.\n");
    exit(1);
}

$fileHeaders = ehr1_ep_paid_manifest_headers_from_json_file();
$seen = [];
$ordered = [];
foreach ($fileHeaders as $h) {
    if ($h === '' || isset($seen[$h])) {
        continue;
    }
    $seen[$h] = true;
    $ordered[] = $h;
}

$stmt = $pdo->query('SELECT payload_json FROM supplemental_ep_paid WHERE payload_json IS NOT NULL');
if ($stmt !== false) {
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $decoded = ehr1_ep_paid_normalize_payload_to_array($row['payload_json'] ?? null);
        if ($decoded === null) {
            continue;
        }
        foreach (array_keys($decoded) as $k) {
            if (!is_string($k)) {
                $k = (string) $k;
            }
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $ordered[] = $k;
        }
    }
}

$pdo->exec('DELETE FROM ep_paid_column_manifest');
$ins = $pdo->prepare('INSERT INTO ep_paid_column_manifest (ordinal, header_name) VALUES (?, ?)');
foreach ($ordered as $i => $h) {
    $ins->execute([$i, mb_substr($h, 0, 768)]);
}

echo 'Inserted ' . count($ordered) . " row(s) into ep_paid_column_manifest.\n";
