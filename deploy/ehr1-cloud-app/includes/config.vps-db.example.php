<?php
/**
 * Production example: PHP and MySQL both on the same VPS (`ehr1.cloud` in DNS).
 *
 * Copy to includes/config.local.php on the server (or merge these `db` keys).
 * Prefer MySQL bound to localhost only — use 127.0.0.1 below, not 0.0.0.0:3306 on the public internet.
 *
 * Never commit the real config.local.php.
 */
return [
    'environment' => 'production',
    'show_errors' => false,
    'http_base_path' => '/ehr1-data',
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        /** Database you created on the VPS (example: ehr1_data). */
        'name'    => 'ehr1_data',
        'user'    => 'YOUR_VPS_MYSQL_USER',
        'pass'    => 'YOUR_VPS_MYSQL_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];
