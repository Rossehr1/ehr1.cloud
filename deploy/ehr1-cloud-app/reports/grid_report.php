<?php
/**
 * Legacy URL: redirect to single-page explorer at reports/index.php.
 */
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/ui.php';

$GLOBALS['ehr1_http_base'] = $config['http_base_path'] ?? '/ehr1-data';

$q = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
$target = ehr1_url('/reports/index.php' . ($q !== '' ? '?' . $q : ''));

header('Location: ' . $target, true, 301);
exit;
