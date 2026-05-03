<?php
/**
 * Apply merged_ep_paid_npi DDL only (11_merged_ep_paid.sql). Safe on existing DB.
 * CLI from ehr1-cloud-app: php tools/migrate_merged_ep_paid_only.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}

$appRoot = dirname(__DIR__);
$candidates = [
    dirname($appRoot, 2) . '/sql/mysql',
    $appRoot . '/sql/mysql',
];
$sqlDir = null;
foreach ($candidates as $dir) {
    if (is_dir($dir) && is_readable($dir . '/11_merged_ep_paid.sql')) {
        $sqlDir = $dir;
        break;
    }
}
if ($sqlDir === null) {
    fwrite(STDERR, "Cannot find sql/mysql/11_merged_ep_paid.sql. Tried:\n  " . implode("\n  ", $candidates) . PHP_EOL);
    exit(1);
}

$c = require $appRoot . '/includes/config.local.php';
$d = $c['db'];

$mysqli = mysqli_init();
if (!$mysqli->real_connect($d['host'], $d['user'], $d['pass'], $d['name'], (int) $d['port'])) {
    fwrite(STDERR, mysqli_connect_error() . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$path = $sqlDir . '/11_merged_ep_paid.sql';
$sql = file_get_contents($path);
if ($sql === false || $sql === '') {
    fwrite(STDERR, "Unreadable: $path\n");
    exit(1);
}

if ($mysqli->multi_query($sql)) {
    try {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    } catch (Throwable $e) {
        fwrite(STDERR, "{$e->getMessage()}\n");
        if (stripos($e->getMessage(), 'denied') !== false || stripos($e->getMessage(), 'CREATE') !== false) {
            fwrite(STDERR, "\nThis MySQL user cannot CREATE TABLE. In hPanel: phpMyAdmin → Import → "
                . "sql/mysql/11_merged_ep_paid.sql (DDL-capable user). Or run: php tools/migrate_ep_paid_schema_bundle.php\n");
        }
        exit(1);
    }
}
if ($mysqli->errno) {
    fwrite(STDERR, "{$mysqli->error}\n");
    if (stripos($mysqli->error, 'denied') !== false || (int) $mysqli->errno === 1142) {
        fwrite(STDERR, "\nImport sql/mysql/11_merged_ep_paid.sql in phpMyAdmin as a user with DDL rights.\n");
    }
    exit(1);
}
echo "OK 11_merged_ep_paid.sql (from $sqlDir)\nDone.\n";
