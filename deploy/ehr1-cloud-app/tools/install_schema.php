<?php
/**
 * Apply sql/mysql/*.sql in order (CLI only). Run from ehr1-data root:
 *   php tools/install_schema.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

$base = dirname(__DIR__);
$sqlDir = $base . '/sql/mysql';
$files = [
    '00_meta.sql',
    '01_core_npi_provider.sql',
    '02_core_endpoint.sql',
    '03_core_practice_location.sql',
    '04_core_other_name.sql',
    '05_core_supplemental.sql',
    '06_core_npi_relationship.sql',
    '99_seed_test_data.sql',
    '100_relationship_seed.sql',
];

$c = require $base . '/includes/config.local.php';
$d = $c['db'];

$mysqli = mysqli_init();
if (!$mysqli->real_connect($d['host'], $d['user'], $d['pass'], $d['name'], (int) $d['port'])) {
    fwrite(STDERR, 'Connect failed: ' . mysqli_connect_error() . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

foreach ($files as $f) {
    $path = $sqlDir . '/' . $f;
    if (!is_readable($path)) {
        fwrite(STDERR, "Missing file: $path\n");
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
        fwrite(STDERR, "Error in $f: {$mysqli->error}\n");
        exit(1);
    }
    echo "OK $f\n";
}

echo "Schema install complete.\n";
