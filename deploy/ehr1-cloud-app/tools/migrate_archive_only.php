<?php
/**
 * Apply archival supplemental DDL only (safe on existing DB with data).
 * CLI from ehr1-cloud-app: php tools/migrate_archive_only.php
 *
 * Resolves sql/mysql from repo layout (EHR1 Data/sql/mysql) or ./sql/mysql under this app.
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
    if (is_dir($dir) && is_readable($dir . '/07_archive_supplemental.sql')) {
        $sqlDir = $dir;
        break;
    }
}
if ($sqlDir === null) {
    fwrite(STDERR, "Cannot find sql/mysql/07_archive_supplemental.sql. Tried:\n  " . implode("\n  ", $candidates) . PHP_EOL);
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

$path = $sqlDir . '/07_archive_supplemental.sql';
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
                . "sql/mysql/07_archive_supplemental.sql (DDL-capable user).\n");
        }
        exit(1);
    }
}
if ($mysqli->errno) {
    fwrite(STDERR, "{$mysqli->error}\n");
    if (stripos($mysqli->error, 'CREATE') !== false && stripos($mysqli->error, 'denied') !== false) {
        fwrite(STDERR, "\nThis MySQL user cannot CREATE TABLE. In hPanel: phpMyAdmin → your database → Import → "
            . "choose sql/mysql/07_archive_supplemental.sql (as a user with DDL rights).\n");
    }
    exit(1);
}
echo "OK 07_archive_supplemental.sql (from $sqlDir)\nDone.\n";
