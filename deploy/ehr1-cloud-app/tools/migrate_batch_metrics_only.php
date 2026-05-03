<?php
/**
 * Apply ref_source_batch metric columns (09_ref_source_batch_metrics.sql).
 * CLI: php tools/migrate_batch_metrics_only.php
 *
 * Resolves sql/mysql from repo layout or ./sql/mysql under this app.
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
    if (is_dir($dir) && is_readable($dir . '/09_ref_source_batch_metrics.sql')) {
        $sqlDir = $dir;
        break;
    }
}
if ($sqlDir === null) {
    fwrite(STDERR, "Cannot find sql/mysql/09_ref_source_batch_metrics.sql. Tried:\n  " . implode("\n  ", $candidates) . PHP_EOL);
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

$path = $sqlDir . '/09_ref_source_batch_metrics.sql';
$sql = file_get_contents($path);
if ($sql === false || $sql === '') {
    fwrite(STDERR, "Unreadable: $path\n");
    exit(1);
}

if ($mysqli->multi_query($sql)) {
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
}
if ($mysqli->errno) {
    if ((int) $mysqli->errno === 1060 || stripos($mysqli->error, 'Duplicate column') !== false) {
        echo "OK (columns already present)\nDone.\n";
        exit(0);
    }
    fwrite(STDERR, "{$mysqli->error}\n");
    if (stripos($mysqli->error, 'ALTER') !== false && stripos($mysqli->error, 'denied') !== false) {
        fwrite(STDERR, "\nImport sql/mysql/09_ref_source_batch_metrics.sql in phpMyAdmin as a user with ALTER rights.\n");
    }
    exit(1);
}
echo "OK 09_ref_source_batch_metrics.sql (from $sqlDir)\nDone.\n";
