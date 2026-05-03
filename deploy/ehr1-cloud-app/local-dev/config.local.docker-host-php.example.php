<?php
/**
 * Use this when MySQL runs in Docker but you run PHP on your PC (not in the web container).
 * Copy to deploy/ehr1-cloud-app/includes/config.local.php
 * Port 3307 is docker-compose.yml db publish port (-> 3306 inside the db container).
 */
return [
    'environment' => 'local',
    'show_errors' => true,
    'http_base_path' => '/ehr1-data',
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3307,
        'name'    => 'ehr1_local',
        'user'    => 'ehr1',
        'pass'    => 'ehr1local',
        'charset' => 'utf8mb4',
    ],
];
