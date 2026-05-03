<?php
/**
 * Apply EP PAID-related DDL in order: archive (07), supplemental table (10), merged table (11).
 * CLI from ehr1-cloud-app: php tools/migrate_ep_paid_schema_bundle.php
 *
 * Use when phpMyAdmin is inconvenient: still requires a MySQL user with CREATE (often not the web app user).
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
    fwrite(STDERR, "Cannot find sql/mysql (need 11_merged_ep_paid.sql). Tried:\n  " . implode("\n  ", $candidates) . PHP_EOL);
    exit(1);
}

$files = [
    '07_archive_supplemental.sql',
    '10_supplemental_ep_paid.sql',
    '11_merged_ep_paid.sql',
];

$c = require $appRoot . '/includes/config.local.php';
$d = $c['db'];

$mysqli = mysqli_init();
if (!$mysqli->real_connect($d['host'], $d['user'], $d['pass'], $d['name'], (int) $d['port'])) {
    fwrite(STDERR, mysqli_connect_error() . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

/**
 * @return bool true on success
 */
$apply = static function (mysqli $mysqli, string $path, string $label): bool {
    if (!is_readable($path)) {
        fwrite(STDERR, "Missing: $path\n");
        return false;
    }
    $sql = file_get_contents($path);
    if ($sql === false || $sql === '') {
        fwrite(STDERR, "Unreadable: $path\n");
        return false;
    }
    try {
        if (!$mysqli->multi_query($sql)) {
            fwrite(STDERR, "$label: {$mysqli->error}\n");
            return false;
        }
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    } catch (Throwable $e) {
        fwrite(STDERR, "$label: " . $e->getMessage() . "\n");
        return false;
    }
    if ($mysqli->errno) {
        fwrite(STDERR, "$label: {$mysqli->error}\n");
        return false;
    }
    return true;
};

foreach ($files as $f) {
    $path = $sqlDir . '/' . $f;
    if (! $apply($mysqli, $path, $f)) {
        fwrite(STDERR, "\nFailed on $f. Import these files in order in phpMyAdmin (DDL-capable user):\n");
        foreach ($files as $x) {
            fwrite(STDERR, "  - sql/mysql/$x\n");
        }
        exit(1);
    }
    echo "OK $f\n";
}

echo "All EP PAID schema files applied (from $sqlDir)\nDone.\n";
