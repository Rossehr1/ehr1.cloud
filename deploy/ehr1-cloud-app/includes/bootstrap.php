<?php
/**
 * Loads config.local.php (required for the app to run).
 */
declare(strict_types=1);

$configPath = __DIR__ . '/config.local.php';
if (!is_readable($configPath)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Configuration missing. Copy includes/config.example.php to includes/config.local.php on the server.\n";
    exit;
}

/** @var array<string,mixed> $config */
$config = require $configPath;

if (empty($config['show_errors'])) {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
}

return $config;
