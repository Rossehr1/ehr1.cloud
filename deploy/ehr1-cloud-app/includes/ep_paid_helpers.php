<?php
/**
 * EP PAID supplemental columns: JSON extract expressions for payload_json (keys = Excel headers).
 */
declare(strict_types=1);

/**
 * Human-readable slug (used only for debugging); grid ids are hash-based for uniqueness.
 */
function ehr1_ep_paid_slug(string $header): string
{
    $s = preg_replace('/[^a-zA-Z0-9]+/', '_', trim($header));
    $s = strtolower(trim((string) $s, '_'));
    if ($s === '') {
        $s = 'col';
    }

    return 'ep_paid__' . $s;
}

/**
 * Walk JSON-decoded payload objects and emit one entry per scalar / list leaf.
 * Segments preserve real object keys (including keys that contain "."); do not split on ".".
 *
 * @return list<array{segments: list<string>, label: string}>
 */
function ehr1_ep_paid_json_path_entries(array $data, array $prefixSegs = []): array
{
    $out = [];
    foreach ($data as $k => $v) {
        if (is_int($k) || is_float($k)) {
            $k = (string) $k;
        }
        if (!is_string($k) || $k === '') {
            continue;
        }
        $segments = array_merge($prefixSegs, [$k]);
        $label = implode('.', $segments);
        if (is_array($v) && $v !== []) {
            if (function_exists('array_is_list') && array_is_list($v)) {
                $out[] = ['segments' => $segments, 'label' => $label];

                continue;
            }
            foreach (ehr1_ep_paid_json_path_entries($v, $segments) as $sub) {
                $out[] = $sub;
            }
        } else {
            $out[] = ['segments' => $segments, 'label' => $label];
        }
    }

    return $out;
}

/**
 * Stable checkbox / column id from path segments (avoids slug collisions and "." ambiguity).
 */
function ehr1_ep_paid_column_id(array $segments): string
{
    $raw = json_encode($segments, JSON_UNESCAPED_UNICODE);
    $h = hash('sha256', $raw === false ? '[]' : $raw);

    return 'ep_paid__' . substr($h, 0, 18);
}

/**
 * SQL fragment: scalar (or JSON text for array values) from ep.payload_json.
 *
 * @param list<string> $segments
 */
function ehr1_ep_paid_extract_sql_for_segments(array $segments): string
{
    if ($segments === []) {
        return 'NULL';
    }
    $path = '$';
    foreach ($segments as $part) {
        $esc = str_replace(['\\', '"'], ['\\\\', '\\"'], $part);
        $path .= '."' . $esc . '"';
    }

    return 'JSON_UNQUOTE(JSON_EXTRACT(ep.payload_json, ' . var_export($path, true) . '))';
}

/**
 * PDO may return JSON columns as string, array, or (less often) object — normalize to associative array or null.
 */
function ehr1_ep_paid_normalize_payload_to_array(mixed $raw): ?array
{
    if ($raw === null || $raw === '') {
        return null;
    }
    if (is_array($raw)) {
        if ($raw === []) {
            return null;
        }
        if (function_exists('array_is_list') && array_is_list($raw)) {
            return null;
        }

        return $raw;
    }
    if (is_object($raw)) {
        $enc = json_encode($raw, JSON_UNESCAPED_UNICODE);
        if ($enc === false) {
            return null;
        }
        $decoded = json_decode($enc, true, 65536);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }
        if ($decoded === [] || (function_exists('array_is_list') && array_is_list($decoded))) {
            return null;
        }

        return $decoded;
    }
    if (is_string($raw)) {
        $decoded = json_decode($raw, true, 65536);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        if (!is_array($decoded) || $decoded === []) {
            return null;
        }
        if (function_exists('array_is_list') && array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    return null;
}

/**
 * Walk payload_json rows to discover nested JSON paths. Use a cap — full-table scans
 * (500k+ rows) on every Data explorer request will time out the site.
 *
 * @return list<array{segments: list<string>, label: string}>
 */
function ehr1_ep_paid_paths_from_database(PDO $pdo, ?int $maxRows = 8000): array
{
    if (!ehr1_supplemental_ep_paid_has_payload_json($pdo)) {
        return [];
    }
    try {
        $sql = 'SELECT payload_json FROM supplemental_ep_paid WHERE payload_json IS NOT NULL';
        if ($maxRows !== null && $maxRows > 0) {
            $sql .= ' LIMIT ' . (int) $maxRows;
        }
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return [];
        }
        $seenSig = [];
        $ordered = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $decoded = ehr1_ep_paid_normalize_payload_to_array($row['payload_json'] ?? null);
            if ($decoded === null) {
                continue;
            }
            foreach (ehr1_ep_paid_json_path_entries($decoded) as $entry) {
                $sig = json_encode($entry['segments'], JSON_UNESCAPED_UNICODE);
                if ($sig === false || isset($seenSig[$sig])) {
                    continue;
                }
                $seenSig[$sig] = true;
                $ordered[] = $entry;
            }
        }

        return $ordered;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * True when ep_paid_column_manifest exists (migration 07).
 */
function ehr1_ep_paid_manifest_table_exists(PDO $pdo): bool
{
    static $yes = null;
    if ($yes !== null) {
        return $yes;
    }
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($db) || $db === '') {
            $yes = false;

            return false;
        }
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $st->execute([$db, 'ep_paid_column_manifest']);
        $yes = ((int) $st->fetchColumn() > 0);
    } catch (Throwable $e) {
        $yes = false;
    }

    return $yes;
}

/**
 * Ordered header names written by ep_paid_sync.py load (preferred source for section 2).
 *
 * @return list<string>
 */
function ehr1_ep_paid_manifest_headers_from_table(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            'SELECT header_name FROM ep_paid_column_manifest ORDER BY ordinal ASC'
        );
        if ($stmt === false) {
            return [];
        }
        $out = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $h = $row['header_name'] ?? '';
            if (is_string($h) && $h !== '') {
                $out[] = $h;
            }
        }

        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Fallback when DB manifest table is empty: ep_paid_headers.generated.json next to this file.
 *
 * @return list<string>
 */
function ehr1_ep_paid_manifest_headers_from_json_file(): array
{
    $path = __DIR__ . '/ep_paid_headers.generated.json';
    if (!is_readable($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true, 65536);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    $out = [];
    foreach ($decoded as $h) {
        if (is_string($h) && $h !== '') {
            $out[] = $h;
        }
    }

    return $out;
}

/**
 * Merge manifest (MySQL table preferred, then JSON file) with paths discovered from payload_json.
 * When the manifest table or JSON file has any columns, skip scanning every supplemental row
 * (avoids timeouts with large EP PAID loads).
 *
 * @return list<array{segments: list<string>, label: string}>
 */
function ehr1_ep_paid_resolve_paths(?PDO $pdo): array
{
    $manifestStrs = [];
    if ($pdo instanceof PDO && ehr1_ep_paid_manifest_table_exists($pdo)) {
        $manifestStrs = ehr1_ep_paid_manifest_headers_from_table($pdo);
    }
    if ($manifestStrs === []) {
        $manifestStrs = ehr1_ep_paid_manifest_headers_from_json_file();
    }

    $fromManifest = [];
    foreach ($manifestStrs as $h) {
        $fromManifest[] = ['segments' => [$h], 'label' => $h];
    }

    $fromDb = [];
    if ($pdo instanceof PDO) {
        // Only full walk when we have no manifest — e.g. dev DB before migration 07 / load.
        if ($fromManifest === []) {
            $fromDb = ehr1_ep_paid_paths_from_database($pdo, 8000);
        }
    }

    if ($fromManifest === []) {
        return $fromDb;
    }
    if ($fromDb === []) {
        return $fromManifest;
    }

    $seenSig = [];
    $merged = [];
    foreach ($fromManifest as $entry) {
        $sig = json_encode($entry['segments'], JSON_UNESCAPED_UNICODE);
        if ($sig === false || isset($seenSig[$sig])) {
            continue;
        }
        $seenSig[$sig] = true;
        $merged[] = $entry;
    }
    foreach ($fromDb as $entry) {
        $sig = json_encode($entry['segments'], JSON_UNESCAPED_UNICODE);
        if ($sig === false || isset($seenSig[$sig])) {
            continue;
        }
        $seenSig[$sig] = true;
        $merged[] = $entry;
    }

    return $merged;
}

/**
 * @return array<string, array{label:string, sql:string}>
 */
function ehr1_ep_paid_grid_columns_from_manifest(?PDO $pdo = null): array
{
    require_once __DIR__ . '/db.php';
    if (!ehr1_supplemental_ep_paid_has_payload_json($pdo)) {
        return [];
    }
    $paths = ehr1_ep_paid_resolve_paths($pdo);
    $out = [];
    foreach ($paths as $entry) {
        $id = ehr1_ep_paid_column_id($entry['segments']);
        $out[$id] = [
            'label' => 'EP PAID · ' . $entry['label'],
            'sql' => ehr1_ep_paid_extract_sql_for_segments($entry['segments']),
        ];
    }

    return $out;
}
