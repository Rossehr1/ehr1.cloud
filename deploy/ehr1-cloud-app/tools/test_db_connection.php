<?php
/** One-off: run from ehr1-data dir — php tools/test_db_connection.php */
declare(strict_types=1);
chdir(dirname(__DIR__));
$c = require 'includes/config.local.php';
$d = $c['db'];
try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $d['host'],
            (int) $d['port'],
            $d['name'],
            $d['charset']
        ),
        $d['user'],
        $d['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $r = $pdo->query('SELECT 1 AS x')->fetch(PDO::FETCH_ASSOC);
    echo 'OK ' . json_encode($r) . PHP_EOL;
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo 'Tables: ' . count($tables) . ' — ' . implode(', ', $tables) . PHP_EOL;
    if (in_array('core_npi_provider', $tables, true)) {
        echo 'core_npi_provider rows: ' . $pdo->query('SELECT COUNT(*) FROM core_npi_provider')->fetchColumn() . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
