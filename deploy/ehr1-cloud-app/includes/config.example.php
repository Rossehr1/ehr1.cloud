<?php
/**
 * Copy this file to config.local.php and fill in Hostinger MySQL credentials from hPanel.
 * Never commit config.local.php.
 */
return [
    'environment' => 'production',
    'show_errors' => false,
    /** Web path to this app (no trailing slash), e.g. /ehr1-data */
    'http_base_path' => '/ehr1-data',
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'YOUR_DATABASE_NAME',
        'user'    => 'YOUR_MYSQL_USER',
        'pass'    => 'YOUR_MYSQL_PASSWORD',
        'charset' => 'utf8mb4',
    ],
];
