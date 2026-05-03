<?php
/**
 * Shared HTML escaping and base path for links.
 * Set $GLOBALS['ehr1_http_base'] from config['http_base_path'] before including layout (e.g. '/ehr1-data').
 */
declare(strict_types=1);

function ehr1_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function ehr1_base_path(): string
{
    $b = $GLOBALS['ehr1_http_base'] ?? '';
    return is_string($b) ? rtrim($b, '/') : '';
}

function ehr1_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $base = ehr1_base_path();
    return ($base === '' ? '' : $base) . $path;
}
