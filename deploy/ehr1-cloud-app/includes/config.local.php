<?php
/**
 * Production: PHP app and MySQL both on the VPS. Public hostname: ehr1.cloud (DNS → VPS).
 *
 * Replace YOUR_VPS_* with the real DB user/password. Do not commit secrets.
 * See: includes/config.vps-db.example.php, README-DEPLOY.txt.
 */
return [
    'environment' => 'production',
    'show_errors'   => false,
    'http_base_path' => '/ehr1-data',
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'ehr1_data',
        'user'    => 'YOUR_VPS_MYSQL_USER',
        'pass'    => 'YOUR_VPS_MYSQL_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];
