<?php
/**
 * Build SELECT/WHERE for grid report from GET-like params.
 */
declare(strict_types=1);

require_once __DIR__ . '/npi.php';
require_once __DIR__ . '/grid_report_catalog.php';
require_once __DIR__ . '/grid_report_facets.php';

/** @var list<string> */
const EHR1_GRID_SORT_KEYS = ['entity_npi', 'npi', 'city', 'state', 'zip', 'last_name', 'phone'];

/**
 * EP-only UNION branch: cap how many supplemental rows get heavy JSON projections before merge.
 * Without this, the branch scans the full EP PAID table (hundreds of thousands of rows) and appears hung.
 */
const EHR1_GRID_EP_ONLY_BRANCH_PRELIMIT = 25000;

/**
 * Scale EP-only prelimit down when many columns are selected (wide rows exhaust MySQL sort buffer on ORDER BY).
 */
function ehr1_grid_report_ep_only_prelimit(int $selectColumnCount): int
{
    if ($selectColumnCount < 1) {
        $selectColumnCount = 1;
    }
    $scaled = (int) (3000000 / $selectColumnCount);

    return max(300, min(EHR1_GRID_EP_ONLY_BRANCH_PRELIMIT, $scaled));
}

/**
 * Whitelist sort key from GET (default: individuals/groups then NPI).
 */
function ehr1_grid_report_parse_sort(array $get): string
{
    $s = isset($get['sort']) ? (string) $get['sort'] : 'entity_npi';
    return in_array($s, EHR1_GRID_SORT_KEYS, true) ? $s : 'entity_npi';
}

/**
 * SQL ORDER BY fragment (fixed strings only).
 */
function ehr1_grid_report_sort_clause(string $sortKey): string
{
    $zip5 = 'LEFT(REPLACE(REPLACE(TRIM(IFNULL(p.practice_postal_code, "")), "-", ""), " ", ""), 5)';
    switch ($sortKey) {
        case 'npi':
            return 'p.npi ASC';
        case 'city':
            return 'p.practice_city ASC, p.npi ASC';
        case 'state':
            return 'p.practice_state ASC, p.npi ASC';
        case 'zip':
            return $zip5 . ' ASC, p.npi ASC';
        case 'last_name':
            return 'p.provider_last_name ASC, p.npi ASC';
        case 'phone':
            return 'p.practice_phone ASC, p.npi ASC';
        case 'entity_npi':
        default:
            return 'p.entity_type_code ASC, p.npi ASC';
    }
}

/**
 * True when filters reference NPPES (core) fields — EP-PAY-only rows cannot satisfy these.
 */
function ehr1_grid_report_filters_require_core_row(array $get): bool
{
    if (ehr1_grid_report_parse_states($get) !== []) {
        return true;
    }
    if (ehr1_grid_report_parse_cities($get) !== []) {
        return true;
    }
    if (ehr1_grid_report_parse_zips($get) !== []) {
        return true;
    }
    if (ehr1_grid_report_parse_entity_types($get) !== []) {
        return true;
    }
    if (ehr1_grid_report_parse_taxonomy_codes($get) !== []) {
        return true;
    }
    if (ehr1_grid_report_parse_area_codes($get) !== []) {
        return true;
    }
    $area = isset($get['area_code']) ? preg_replace('/\D/', '', (string) $get['area_code']) : '';
    if (strlen($area) === 3) {
        return true;
    }
    if (isset($get['last_name']) && trim((string) $get['last_name']) !== '') {
        return true;
    }
    if (isset($get['first_name']) && trim((string) $get['first_name']) !== '') {
        return true;
    }
    if (isset($get['practice_name']) && trim((string) $get['practice_name']) !== '') {
        return true;
    }

    return false;
}

/**
 * ORDER BY for wrapped UNION result (alias u = outer select).
 */
function ehr1_grid_report_sort_clause_union(string $sortKey): string
{
    switch ($sortKey) {
        case 'npi':
            return 'u.`npi` ASC';
        case 'city':
            return 'u.`practice_city` ASC, u.`npi` ASC';
        case 'state':
            return 'u.`practice_state` ASC, u.`npi` ASC';
        case 'zip':
            return 'LEFT(REPLACE(REPLACE(TRIM(IFNULL(u.`practice_postal_code`, "")), "-", ""), " ", ""), 5) ASC, u.`npi` ASC';
        case 'last_name':
            return 'u.`provider_last_name` ASC, u.`npi` ASC';
        case 'phone':
            return 'u.`practice_phone` ASC, u.`npi` ASC';
        case 'entity_npi':
        default:
            return '(u.`entity_type_code` IS NULL) ASC, u.`entity_type_code` ASC, u.`npi` ASC';
    }
}

/**
 * Merge GET/POST for grid: POST carries column checkboxes so URLs stay short (many EP PAID columns
 * exceed browser/proxy limits when sent as GET). CSV uses short GET ?run=1&format=csv and restores
 * the last successful report from session.
 *
 * @return array<string, mixed>
 */
function ehr1_grid_report_request_params(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        return $_POST;
    }
    if (!empty($_SESSION['ehr1_grid_report_params'])
        && is_array($_SESSION['ehr1_grid_report_params'])
        && isset($_GET['format']) && (string) $_GET['format'] === 'csv'
        && isset($_GET['run']) && (string) $_GET['run'] === '1') {
        $g = $_SESSION['ehr1_grid_report_params'];
        $g['format'] = 'csv';
        $g['run'] = '1';
        unset($g['facet_refresh']);

        return $g;
    }

    return $_GET;
}

/**
 * @param array<string, string|array<int, string>> $get
 * @param list<string> $columnIds
 * @param array<string, array{label:string, sql:string}> $catalog
 * @return array{sql:string, params:array<string,mixed>, whereParts:list<string>}
 */
function ehr1_grid_report_build_query(array $get, array $columnIds, array $catalog): array
{
    $selectParts = [];
    foreach ($columnIds as $id) {
        if (!isset($catalog[$id])) {
            continue;
        }
        $sel = $catalog[$id]['sql'];
        $selectParts[] = $sel . ' AS `' . str_replace('`', '``', $id) . '`';
    }
    if ($selectParts === []) {
        foreach (ehr1_grid_report_default_columns() as $id) {
            if (isset($catalog[$id])) {
                $sel = $catalog[$id]['sql'];
                $selectParts[] = $sel . ' AS `' . str_replace('`', '``', $id) . '`';
            }
        }
    }

    $params = [];
    $where = [];
    $npiWhereP = [];

    $npi = isset($get['npi']) ? ehr1_normalize_npi((string) $get['npi']) : null;
    if ($npi !== null) {
        $npiWhereP[] = 'p.npi = :npi';
        $params['npi'] = $npi;
    } elseif (isset($get['npi']) && trim((string) $get['npi']) !== '') {
        $npiWhereP[] = 'p.npi LIKE :npi_like';
        $params['npi_like'] = '%' . preg_replace('/\D/', '', (string) $get['npi']) . '%';
    }

    $states = ehr1_grid_report_parse_states($get);
    if ($states !== []) {
        $ph = [];
        foreach ($states as $i => $st) {
            $k = 'gst' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $st;
        }
        $where[] = 'TRIM(p.practice_state) IN (' . implode(',', $ph) . ')';
    }

    $cities = ehr1_grid_report_parse_cities($get);
    if ($cities !== []) {
        $ph = [];
        foreach ($cities as $i => $ct) {
            $k = 'gct' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $ct;
        }
        $where[] = 'TRIM(p.practice_city) IN (' . implode(',', $ph) . ')';
    }

    $zips = ehr1_grid_report_parse_zips($get);
    if ($zips !== []) {
        $normZip = 'LEFT(REPLACE(REPLACE(TRIM(p.practice_postal_code), "-", ""), " ", ""), 5)';
        $ph = [];
        foreach ($zips as $i => $z) {
            $k = 'gz' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $z;
        }
        $where[] = $normZip . ' IN (' . implode(',', $ph) . ')';
    }

    $entityTypes = ehr1_grid_report_parse_entity_types($get);
    if ($entityTypes !== []) {
        $ph = [];
        foreach ($entityTypes as $i => $n) {
            $k = 'et' . $i;
            $ph[] = ':' . $k;
            $params[$k] = $n;
        }
        $where[] = 'p.entity_type_code IN (' . implode(',', $ph) . ')';
    }

    $taxonomies = ehr1_grid_report_parse_taxonomy_codes($get);
    if ($taxonomies !== []) {
        $ph1 = [];
        $ph2 = [];
        foreach ($taxonomies as $i => $t) {
            $k1 = 'txa' . $i;
            $k2 = 'txb' . $i;
            $ph1[] = ':' . $k1;
            $ph2[] = ':' . $k2;
            $params[$k1] = $t;
            $params[$k2] = $t;
        }
        $where[] = '(p.healthcare_provider_taxonomy_code_1 IN (' . implode(',', $ph1) . ') OR p.healthcare_provider_taxonomy_code_2 IN (' . implode(',', $ph2) . '))';
    }

    $areaCodes = ehr1_grid_report_parse_area_codes($get);
    if ($areaCodes !== []) {
        $orParts = [];
        foreach ($areaCodes as $i => $ac) {
            $like = '%' . $ac . '%';
            $k1 = 'ana' . $i;
            $k2 = 'anb' . $i;
            $k3 = 'anc' . $i;
            $orParts[] = '(p.practice_phone LIKE :' . $k1 . ' OR p.mailing_phone LIKE :' . $k2 . ' OR p.authorized_official_phone LIKE :' . $k3 . ')';
            $params[$k1] = $like;
            $params[$k2] = $like;
            $params[$k3] = $like;
        }
        $where[] = '(' . implode(' OR ', $orParts) . ')';
    } else {
        $area = isset($get['area_code']) ? preg_replace('/\D/', '', (string) $get['area_code']) : '';
        if (strlen($area) === 3) {
            $where[] = '(p.practice_phone LIKE :aca OR p.mailing_phone LIKE :acb OR p.authorized_official_phone LIKE :acc)';
            $like = '%' . $area . '%';
            $params['aca'] = $like;
            $params['acb'] = $like;
            $params['acc'] = $like;
        }
    }

    $ln = isset($get['last_name']) ? trim((string) $get['last_name']) : '';
    if ($ln !== '') {
        $where[] = 'p.provider_last_name LIKE :ln';
        $params['ln'] = '%' . $ln . '%';
    }

    $fn = isset($get['first_name']) ? trim((string) $get['first_name']) : '';
    if ($fn !== '') {
        $where[] = 'p.provider_first_name LIKE :fn';
        $params['fn'] = '%' . $fn . '%';
    }

    $pn = isset($get['practice_name']) ? trim((string) $get['practice_name']) : '';
    if ($pn !== '') {
        $where[] = '(p.provider_organization_name LIKE :pn OR EXISTS (
            SELECT 1 FROM core_npi_other_name x WHERE x.npi = p.npi AND x.provider_other_organization_name LIKE :pn2
        ))';
        $params['pn'] = '%' . $pn . '%';
        $params['pn2'] = '%' . $pn . '%';
    }

    $whereCore = array_merge($npiWhereP, $where);
    $whereSqlCore = $whereCore === [] ? '1=1' : implode(' AND ', $whereCore);

    $sortKey = ehr1_grid_report_parse_sort($get);
    $orderSql = ehr1_grid_report_sort_clause($sortKey);

    $selectSql = implode(', ', $selectParts);
    $fromCore = ' FROM core_npi_provider p LEFT JOIN supplemental_ep_paid ep ON ep.npi = p.npi WHERE ';

    $useUnion = !ehr1_grid_report_filters_require_core_row($get);
    if ($useUnion) {
        // NPI filter for ep-only branch (subquery only — avoids duplicate :npi binds in outer SELECT).
        $npiEp2Conds = [];
        if ($npi !== null) {
            $npiEp2Conds[] = 'ep2.npi = :npi';
        } elseif (isset($get['npi']) && trim((string) $get['npi']) !== '') {
            $npiEp2Conds[] = 'ep2.npi LIKE :npi_like';
        }
        $npiEp2Sql = $npiEp2Conds === [] ? '1=1' : implode(' AND ', $npiEp2Conds);
        $lim = ehr1_grid_report_ep_only_prelimit(count($selectParts));
        $fromEpOnly = ' FROM supplemental_ep_paid ep '
            . 'INNER JOIN ('
            . 'SELECT ep2.npi FROM supplemental_ep_paid ep2 '
            . 'LEFT JOIN core_npi_provider p2 ON p2.npi = ep2.npi '
            . 'WHERE p2.npi IS NULL AND ep2.npi IS NOT NULL AND (' . $npiEp2Sql . ') '
            . 'ORDER BY ep2.npi LIMIT ' . $lim
            . ') ep_lim ON ep_lim.npi = ep.npi '
            . 'LEFT JOIN core_npi_provider p ON p.npi = ep.npi WHERE p.npi IS NULL';

        // Very wide SELECTs + multi-column ORDER BY blow MySQL sort buffer (error 1038). Fall back to NPI-only order.
        $orderUnion = count($selectParts) >= 85
            ? 'u.`npi` ASC'
            : ehr1_grid_report_sort_clause_union($sortKey);
        $sql = 'SELECT * FROM ('
            . 'SELECT ' . $selectSql . $fromCore . $whereSqlCore
            . ' UNION ALL '
            . 'SELECT ' . $selectSql . $fromEpOnly
            . ') u ORDER BY ' . $orderUnion;
    } else {
        $sql = 'SELECT ' . $selectSql . $fromCore . $whereSqlCore . ' ORDER BY ' . $orderSql;
    }

    return ['sql' => $sql, 'params' => $params, 'whereParts' => $where];
}
