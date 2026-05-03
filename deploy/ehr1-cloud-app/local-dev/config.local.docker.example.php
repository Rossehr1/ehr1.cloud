<?php
/**
 * Local Docker: copy to deploy/ehr1-cloud-app/includes/config.local.php (gitignored).
 * Do not use these credentials in production.
 */
return [
    'environment' => 'local',
    'show_errors' => true,
    'http_base_path' => '/ehr1-data',
    'db' => [
        'host'    => 'db',
        'port'    => 3306,
        'name'    => 'ehr1_local',
        'user'    => 'ehr1',
        'pass'    => 'ehr1local',
        'charset' => 'utf8mb4',
    ],
];
