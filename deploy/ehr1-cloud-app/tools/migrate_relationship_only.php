<?php
/**
 * Apply only NPI relationship DDL + seed (safe on existing DB with data).
 * CLI: php tools/migrate_relationship_only.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}

$base = dirname(__DIR__);
$files = ['06_core_npi_relationship.sql', '100_relationship_seed.sql'];
$sqlDir = $base . '/sql/mysql';

$c = require $base . '/includes/config.local.php';
$d = $c['db'];

$mysqli = mysqli_init();
if (!$mysqli->real_connect($d['host'], $d['user'], $d['pass'], $d['name'], (int) $d['port'])) {
    fwrite(STDERR, mysqli_connect_error() . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

foreach ($files as $f) {
    $path = $sqlDir . '/' . $f;
    if (!is_readable($path)) {
        fwrite(STDERR, "Missing: $path\n");
        exit(1);
    }
    $sql = file_get_contents($path);
    if ($mysqli->multi_query($sql)) {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    }
    if ($mysqli->errno) {
        fwrite(STDERR, "$f: {$mysqli->error}\n");
        exit(1);
    }
    echo "OK $f\n";
}
echo "Done.\n";
