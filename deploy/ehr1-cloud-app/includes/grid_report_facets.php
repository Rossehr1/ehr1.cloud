<?php
/**
 * Parse multi-value location filters and load distinct facet options for grid report UI.
 */
declare(strict_types=1);

/** @var int Max IN-list size per facet */
const EHR1_GRID_FACET_MAX = 80;

/**
 * @param array<string, mixed> $get
 * @return list<string>
 */
function ehr1_grid_report_parse_states(array $get): array
{
    if (!isset($get['states']) || !is_array($get['states'])) {
        return [];
    }
    $out = [];
    foreach ($get['states'] as $s) {
        $t = trim((string) $s);
        if ($t !== '') {
            $out[] = $t;
        }
    }
    $out = array_values(array_unique($out));
    if (count($out) > EHR1_GRID_FACET_MAX) {
        $out = array_slice($out, 0, EHR1_GRID_FACET_MAX);
    }
    return $out;
}

/**
 * @param array<string, mixed> $get
 * @return list<string>
 */
function ehr1_grid_report_parse_cities(array $get): array
{
    if (!isset($get['cities']) || !is_array($get['cities'])) {
        return [];
    }
    $out = [];
    foreach ($get['cities'] as $c) {
        $t = trim((string) $c);
        if ($t !== '') {
            $out[] = $t;
        }
    }
    $out = array_values(array_unique($out));
    if (count($out) > EHR1_GRID_FACET_MAX) {
        $out = array_slice($out, 0, EHR1_GRID_FACET_MAX);
    }
    return $out;
}

/**
 * Normalize to 5-digit ZIP prefix for matching stored postal codes.
 *
 * @param array<string, mixed> $get
 * @return list<string>
 */
function ehr1_grid_report_parse_zips(array $get): array
{
    if (!isset($get['zips']) || !is_array($get['zips'])) {
        return [];
    }
    $out = [];
    foreach ($get['zips'] as $z) {
        $d = preg_replace('/\D/', '', (string) $z);
        if ($d === null || $d === '') {
            continue;
        }
        $five = strlen($d) >= 5 ? substr($d, 0, 5) : $d;
        if ($five !== '') {
            $out[] = $five;
        }
    }
    $out = array_values(array_unique($out));
    if (count($out) > EHR1_GRID_FACET_MAX) {
        $out = array_slice($out, 0, EHR1_GRID_FACET_MAX);
    }
    return $out;
}

/**
 * @param array<string, mixed> $get
 * @return list<int>
 */
function ehr1_grid_report_parse_entity_types(array $get): array
{
    if (!isset($get['entity_types']) || !is_array($get['entity_types'])) {
        return [];
    }
    $out = [];
    foreach ($get['entity_types'] as $v) {
        $n = (int) $v;
        if ($n === 1 || $n === 2) {
            $out[] = $n;
        }
    }
    $out = array_values(array_unique($out));
    if (count($out) > EHR1_GRID_FACET_MAX) {
        $out = array_slice($out, 0, EHR1_GRID_FACET_MAX);
    }
    return $out;
}

/**
 * @param array<string, mixed> $get
 * @return list<string>
 */
function ehr1_grid_report_parse_taxonomy_codes(array $get): array
{
    if (!isset($get['taxonomy_codes']) || !is_array($get['taxonomy_codes'])) {
        return [];
    }
    $out = [];
    foreach ($get['taxonomy_codes'] as $c) {
        $t = trim((string) $c);
        if ($t !== '' && strlen($t) <= 20) {
            $out[] = $t;
        }
    }
    $out = array_values(array_unique($out));
    if (count($out) > EHR1_GRID_FACET_MAX) {
        $out = array_slice($out, 0, EHR1_GRID_FACET_MAX);
    }
    return $out;
}

/**
 * Three-digit NANP area codes from multi-select (values must be [0-9]{3}).
 *
 * @param array<string, mixed> $get
 * @return list<string>
 */
function ehr1_grid_report_parse_area_codes(array $get): array
{
    if (!isset($get['area_codes']) || !is_array($get['area_codes'])) {
        return [];
    }
    $out = [];
    foreach ($get['area_codes'] as $a) {
        $d = preg_replace('/\D/', '', (string) $a);
        if ($d !== null && strlen($d) === 3) {
            $out[] = $d;
        }
    }
    $out = array_values(array_unique($out));
    if (count($out) > EHR1_GRID_FACET_MAX) {
        $out = array_slice($out, 0, EHR1_GRID_FACET_MAX);
    }
    return $out;
}

/**
 * @return array{
 *   states:list<string>,
 *   cities:list<string>,
 *   zips:list<string>,
 *   entity_types:list<int|string>,
 *   taxonomy_codes:list<string>,
 *   area_codes:list<string>
 * }
 */
function ehr1_grid_report_load_facets(PDO $pdo, array $get): array
{
    $selectedStates = ehr1_grid_report_parse_states($get);
    $selectedCities = ehr1_grid_report_parse_cities($get);

    $stmt = $pdo->query(
        'SELECT DISTINCT TRIM(p.practice_state) AS s FROM core_npi_provider p
         WHERE p.practice_state IS NOT NULL AND TRIM(p.practice_state) <> ""
         ORDER BY s'
    );
    $allStates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $allStates = array_values(array_filter(array_map('strval', $allStates)));

    $cities = [];
    if ($selectedStates !== []) {
        $ph = [];
        $params = [];
        foreach ($selectedStates as $i => $st) {
            $k = 'fst' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $st;
        }
        $sql = 'SELECT DISTINCT TRIM(p.practice_city) AS c FROM core_npi_provider p
                WHERE p.practice_city IS NOT NULL AND TRIM(p.practice_city) <> ""
                AND TRIM(p.practice_state) IN (' . implode(',', $ph) . ')
                ORDER BY c';
        $st2 = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st2->bindValue(':' . $k, $v);
        }
        $st2->execute();
        $cities = $st2->fetchAll(PDO::FETCH_COLUMN);
        $cities = array_values(array_filter(array_map('strval', $cities)));
    }

    $zips = [];
    if ($selectedStates !== []) {
        $phS = [];
        $params = [];
        foreach ($selectedStates as $i => $st) {
            $k = 'zst' . $i;
            $phS[] = ':' . $k;
            $params[$k] = $st;
        }
        $zipExpr = 'LEFT(REPLACE(REPLACE(TRIM(p.practice_postal_code), "-", ""), " ", ""), 5)';
        $sql = 'SELECT DISTINCT ' . $zipExpr . ' AS z
                FROM core_npi_provider p
                WHERE p.practice_postal_code IS NOT NULL AND TRIM(p.practice_postal_code) <> ""
                AND LENGTH(REPLACE(REPLACE(TRIM(p.practice_postal_code), "-", ""), " ", "")) >= 3
                AND TRIM(p.practice_state) IN (' . implode(',', $phS) . ')';
        if ($selectedCities !== []) {
            $phC = [];
            foreach ($selectedCities as $j => $ct) {
                $k = 'zct' . $j;
                $phC[] = ':' . $k;
                $params[$k] = $ct;
            }
            $sql .= ' AND TRIM(p.practice_city) IN (' . implode(',', $phC) . ')';
        }
        $sql .= ' ORDER BY z';
        $st3 = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st3->bindValue(':' . $k, $v);
        }
        $st3->execute();
        $zips = $st3->fetchAll(PDO::FETCH_COLUMN);
        $zips = array_values(array_filter(array_map('strval', $zips)));
    }

    $entityTypes = $pdo->query(
        'SELECT DISTINCT p.entity_type_code FROM core_npi_provider p
         WHERE p.entity_type_code IN (1, 2)
         ORDER BY p.entity_type_code'
    )->fetchAll(PDO::FETCH_COLUMN);
    $entityTypes = array_values(array_map('intval', array_map('strval', $entityTypes)));

    $taxStmt = $pdo->query(
        'SELECT DISTINCT TRIM(p.healthcare_provider_taxonomy_code_1) AS t FROM core_npi_provider p
         WHERE p.healthcare_provider_taxonomy_code_1 IS NOT NULL AND TRIM(p.healthcare_provider_taxonomy_code_1) <> ""
         ORDER BY t
         LIMIT 3000'
    );
    $taxonomyCodes = $taxStmt->fetchAll(PDO::FETCH_COLUMN);
    $taxonomyCodes = array_values(array_filter(array_map('strval', $taxonomyCodes)));

    $areaSql = 'SELECT DISTINCT ac FROM (
        SELECT CASE
            WHEN LENGTH(d) >= 11 AND LEFT(d, 1) = \'1\' THEN SUBSTRING(d, 2, 3)
            WHEN LENGTH(d) >= 10 THEN SUBSTRING(d, 1, 3)
            ELSE NULL
        END AS ac
        FROM (
            SELECT REGEXP_REPLACE(IFNULL(p.practice_phone, \'\'), \'[^0-9]\', \'\') AS d FROM core_npi_provider p
            UNION ALL
            SELECT REGEXP_REPLACE(IFNULL(p.mailing_phone, \'\'), \'[^0-9]\', \'\') FROM core_npi_provider p
            UNION ALL
            SELECT REGEXP_REPLACE(IFNULL(p.authorized_official_phone, \'\'), \'[^0-9]\', \'\') FROM core_npi_provider p
        ) u
    ) t
    WHERE ac IS NOT NULL AND CHAR_LENGTH(ac) = 3
    ORDER BY ac';
    $areaCodes = [];
    try {
        $areaCodes = $pdo->query($areaSql)->fetchAll(PDO::FETCH_COLUMN);
        $areaCodes = array_values(array_filter(array_map('strval', $areaCodes)));
    } catch (Throwable $e) {
        $areaCodes = [];
    }

    return [
        'states' => $allStates,
        'cities' => $cities,
        'zips' => $zips,
        'entity_types' => $entityTypes,
        'taxonomy_codes' => $taxonomyCodes,
        'area_codes' => $areaCodes,
    ];
}
