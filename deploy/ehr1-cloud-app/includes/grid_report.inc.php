<?php
/**
 * Single-page NPI data explorer (grid): filters + columns + results + CSV.
 */
declare(strict_types=1);

$config = require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/grid_report_catalog.php';
require_once __DIR__ . '/grid_report_query.php';
require_once __DIR__ . '/grid_report_facets.php';

$GLOBALS['ehr1_http_base'] = $config['http_base_path'] ?? '/ehr1-data';
require_once __DIR__ . '/layout_reports.php';

const EHR1_GRID_PAGE = '/reports/index.php';

function ehr1_grid_report_format_query_error(Throwable $e, array $config): string
{
    if ($config['show_errors'] ?? false) {
        return $e->getMessage();
    }
    $m = $e->getMessage();
    if (preg_match('/1038|Out of sort memory|sort buffer|Sorting memory/i', $m)) {
        return 'Report failed: the combined columns made the result too wide for the database to sort. '
            . 'Try fewer columns (uncheck some EP PAID fields) or run without “All”.';
    }
    if (stripos($m, 'max_allowed_packet') !== false || stripos($m, 'Packet too large') !== false) {
        return 'Report failed: the SQL exceeded database packet size. Select fewer columns.';
    }

    return 'Report failed.';
}

$pdo = null;
$dbConnectMsg = '';
try {
    $pdo = ehr1_pdo($config);
} catch (Throwable $e) {
    $dbConnectMsg = ($config['show_errors'] ?? false) ? $e->getMessage() : 'Database unavailable.';
}

$catalog = ehr1_grid_report_field_catalog($pdo);
$defaultCols = ehr1_grid_report_default_columns();

$get = ehr1_grid_report_request_params();
$colsIn = isset($get['cols']) && is_array($get['cols']) ? array_map('strval', $get['cols']) : null;
$selectedCols = [];
if ($colsIn !== null) {
    foreach ($colsIn as $c) {
        if (isset($catalog[$c])) {
            $selectedCols[] = $c;
        }
    }
}
if ($selectedCols === []) {
    $selectedCols = $defaultCols;
}

$facetRefresh = isset($get['facet_refresh']) && (string) $get['facet_refresh'] === '1';
$run = isset($get['run']) && (string) $get['run'] === '1' && !$facetRefresh;
$format = isset($get['format']) ? (string) $get['format'] : '';

$rows = [];
$error = '';
$validation = '';
$facets = [
    'states' => [], 'cities' => [], 'zips' => [],
    'entity_types' => [], 'taxonomy_codes' => [], 'area_codes' => [],
];
$selStates = ehr1_grid_report_parse_states($get);
$selCities = ehr1_grid_report_parse_cities($get);
$selZips = ehr1_grid_report_parse_zips($get);
$selEntityTypes = ehr1_grid_report_parse_entity_types($get);
$selTaxonomy = ehr1_grid_report_parse_taxonomy_codes($get);
$selAreaCodes = ehr1_grid_report_parse_area_codes($get);
$currentSort = ehr1_grid_report_parse_sort($get);

try {
    if ($pdo === null) {
        throw new RuntimeException($dbConnectMsg !== '' ? $dbConnectMsg : 'Database unavailable.');
    }
    $facets = ehr1_grid_report_load_facets($pdo, $get);
    if ($run && $validation === '') {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
        try {
            $pdo->exec('SET SESSION sort_buffer_size = 67108864');
            $pdo->exec('SET SESSION read_rnd_buffer_size = 16777216');
        } catch (Throwable $e) {
            // Shared hosts may disallow SESSION variables; query still attempts without.
        }
        $built = ehr1_grid_report_build_query($get, $selectedCols, $catalog);
        $sql = $built['sql'] . ' LIMIT 500';
        $stmt = $pdo->prepare($sql);
        foreach ($built['params'] as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $error = ehr1_grid_report_format_query_error($e, $config);
}

if ($run && $error === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['ehr1_grid_report_params'] = $get;
}

// Short URL: full column list lives in session (see ehr1_grid_report_request_params).
$csvHref = '?run=1&format=csv';

if ($format === 'csv' && $run && $validation === '' && $error === '') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ehr1-data-explorer.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($rows !== []) {
        $headerKeys = array_keys($rows[0]);
        $headerLabels = [];
        foreach ($headerKeys as $hk) {
            $headerLabels[] = $catalog[$hk]['label'] ?? $hk;
        }
        fputcsv($out, $headerLabels);
        foreach ($rows as $r) {
            fputcsv($out, $r);
        }
    } else {
        $labels = [];
        foreach ($selectedCols as $id) {
            $labels[] = $catalog[$id]['label'] ?? $id;
        }
        fputcsv($out, $labels);
    }
    fclose($out);
    exit;
}

$formActionPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (!is_string($formActionPath) || $formActionPath === '') {
    $formActionPath = ehr1_url(EHR1_GRID_PAGE);
}

ehr1_layout_start(['title' => 'Data explorer · EHR1 Data', 'active' => 'explorer']);
?>
<div class="explorer-page">
  <header class="explorer-hero">
    <h1 class="explorer-title">Data explorer</h1>
    <p class="explorer-lead">Set filters, columns, and sort, then <strong>Run report</strong> &mdash; the table appears <strong>on this page</strong> below (up to <strong>500</strong> rows). When filters do <strong>not</strong> use NPPES-only fields (state, city, name, etc.), results also include <strong>EP PAID-only NPIs</strong> (supplemental rows with no NPPES match); those are drawn in NPI order up to a large internal cap so the report finishes in reasonable time. Click column headers to re-sort. <strong>Update lists</strong> refreshes city/ZIP dropdowns.</p>
  </header>

  <form class="explorer-form" method="post" action="<?= ehr1_h($formActionPath) ?>" id="grid-form">
    <section class="ehr1-surface explorer-panel" aria-labelledby="filters-heading">
      <h2 id="filters-heading" class="explorer-panel-title">1. Filters</h2>
      <p class="explorer-panel-hint muted">Use the lists to narrow providers. After choosing states, click <strong>Update lists</strong> to load cities and ZIPs. Ctrl+click (Windows) or &#8984;+click (Mac) to pick several options. Leave filters empty to browse the first rows (sorted as below), up to 500.</p>

      <div class="facet-row">
        <div class="facet-field">
          <span class="facet-label">State (practice)</span>
          <select name="states[]" multiple class="facet-multiselect" size="7" aria-label="State">
            <?php foreach ($facets['states'] as $s) : ?>
              <?php $sv = (string) $s; ?>
              <option value="<?= ehr1_h($sv) ?>"<?= in_array($sv, $selStates, true) ? ' selected' : '' ?>><?= ehr1_h($sv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="facet-field">
          <span class="facet-label">City (practice)</span>
          <?php if ($facets['cities'] === []) : ?>
            <?php foreach ($selCities as $sc) : ?>
              <input type="hidden" name="cities[]" value="<?= ehr1_h($sc) ?>">
            <?php endforeach; ?>
            <p class="muted facet-empty">Pick state(s), then <strong>Update lists</strong>.</p>
          <?php else : ?>
            <select name="cities[]" multiple class="facet-multiselect" size="7" aria-label="City">
              <?php foreach ($facets['cities'] as $c) : ?>
                <?php $cv = (string) $c; ?>
                <option value="<?= ehr1_h($cv) ?>"<?= in_array($cv, $selCities, true) ? ' selected' : '' ?>><?= ehr1_h($cv) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="facet-field">
          <span class="facet-label">ZIP (first 5 digits)</span>
          <?php if ($facets['zips'] === []) : ?>
            <?php foreach ($selZips as $sz) : ?>
              <input type="hidden" name="zips[]" value="<?= ehr1_h($sz) ?>">
            <?php endforeach; ?>
            <p class="muted facet-empty">Pick state(s); use <strong>Update lists</strong> to load ZIPs.</p>
          <?php else : ?>
            <select name="zips[]" multiple class="facet-multiselect" size="7" aria-label="ZIP">
              <?php foreach ($facets['zips'] as $z) : ?>
                <?php $zv = (string) $z; ?>
                <option value="<?= ehr1_h($zv) ?>"<?= in_array($zv, $selZips, true) ? ' selected' : '' ?>><?= ehr1_h($zv) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
      </div>

      <div class="facet-row facet-row-data">
        <div class="facet-field">
          <span class="facet-label">Entity type</span>
          <?php if ($facets['entity_types'] === []) : ?>
            <p class="muted facet-empty">No values in data.</p>
          <?php else : ?>
            <select name="entity_types[]" multiple class="facet-multiselect facet-multiselect-sm" size="3" aria-label="Entity type">
              <?php foreach ($facets['entity_types'] as $et) : ?>
                <?php
                $e = (int) $et;
                if ($e !== 1 && $e !== 2) {
                    continue;
                }
                $el = $e === 1 ? 'Individual' : 'Organization';
                ?>
                <option value="<?= ehr1_h((string) $e) ?>"<?= in_array($e, $selEntityTypes, true) ? ' selected' : '' ?>><?= ehr1_h($el) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="facet-field facet-field-wide">
          <span class="facet-label">Taxonomy (primary list)</span>
          <?php if ($facets['taxonomy_codes'] === []) : ?>
            <p class="muted facet-empty">No taxonomy codes loaded yet.</p>
          <?php else : ?>
            <select name="taxonomy_codes[]" multiple class="facet-multiselect" size="7" aria-label="Taxonomy">
              <?php foreach ($facets['taxonomy_codes'] as $t) : ?>
                <?php $tv = (string) $t; ?>
                <option value="<?= ehr1_h($tv) ?>"<?= in_array($tv, $selTaxonomy, true) ? ' selected' : '' ?>><?= ehr1_h($tv) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="facet-field">
          <span class="facet-label">Area code (phones)</span>
          <?php if ($facets['area_codes'] === []) : ?>
            <label class="facet-fallback-label">Manual <input type="text" name="area_code" value="<?= ehr1_h((string) ($get['area_code'] ?? '')) ?>" maxlength="3" inputmode="numeric" autocomplete="off" placeholder="e.g. 801"></label>
          <?php else : ?>
            <select name="area_codes[]" multiple class="facet-multiselect" size="7" aria-label="Area code">
              <?php foreach ($facets['area_codes'] as $a) : ?>
                <?php $av = (string) $a; ?>
                <option value="<?= ehr1_h($av) ?>"<?= in_array($av, $selAreaCodes, true) ? ' selected' : '' ?>><?= ehr1_h($av) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
      </div>

      <div class="toolbar-grid explorer-text-filters">
        <label>NPI <input type="text" name="npi" value="<?= ehr1_h((string) ($get['npi'] ?? '')) ?>" maxlength="10" autocomplete="off" placeholder="10 digits"></label>
        <label>Last name <input type="text" name="last_name" value="<?= ehr1_h((string) ($get['last_name'] ?? '')) ?>" placeholder="Contains"></label>
        <label>First name <input type="text" name="first_name" value="<?= ehr1_h((string) ($get['first_name'] ?? '')) ?>" placeholder="Contains"></label>
        <label class="explorer-span-2">Organization / practice name <input type="text" name="practice_name" value="<?= ehr1_h((string) ($get['practice_name'] ?? '')) ?>" placeholder="Contains"></label>
      </div>

      <div class="explorer-sort-row">
        <label class="explorer-sort-label">
          <span class="explorer-sort-text">Sort (when you run)</span>
          <select name="sort" class="explorer-sort-select">
            <option value="entity_npi"<?= $currentSort === 'entity_npi' ? ' selected' : '' ?>>Individual NPI, then group NPI (by type, then NPI)</option>
            <option value="npi"<?= $currentSort === 'npi' ? ' selected' : '' ?>>NPI only</option>
            <option value="city"<?= $currentSort === 'city' ? ' selected' : '' ?>>Practice city, then NPI</option>
            <option value="state"<?= $currentSort === 'state' ? ' selected' : '' ?>>Practice state, then NPI</option>
            <option value="zip"<?= $currentSort === 'zip' ? ' selected' : '' ?>>Practice ZIP (first 5), then NPI</option>
            <option value="last_name"<?= $currentSort === 'last_name' ? ' selected' : '' ?>>Last name, then NPI</option>
            <option value="phone"<?= $currentSort === 'phone' ? ' selected' : '' ?>>Practice phone, then NPI</option>
          </select>
        </label>
      </div>

      <div class="explorer-actions">
        <button type="submit" name="run" value="1" class="btn btn-primary btn-lg">Run report</button>
        <button type="submit" name="facet_refresh" value="1" class="btn btn-secondary btn-lg">Update lists</button>
        <?php if ($run && $validation === '' && $error === '') : ?>
          <a class="btn btn-primary btn-lg" href="<?= ehr1_h($csvHref) ?>">Download CSV</a>
        <?php endif; ?>
      </div>
    </section>

    <section class="ehr1-surface explorer-panel" aria-labelledby="cols-heading">
      <details class="explorer-details" open>
        <summary class="explorer-details-summary" id="cols-heading"><span class="explorer-step">2.</span> Data columns (checkboxes)</summary>
        <p class="muted explorer-details-hint">Check each field you want as a column in the table and in the CSV export. EP PAID can add many fields — scroll the list below to see them all.</p>
        <p class="explorer-col-tools">
          <button type="button" class="linkish" id="col-all">All</button>
          <button type="button" class="linkish" id="col-none">None</button>
          <button type="button" class="linkish" id="col-default">Defaults</button>
        </p>
        <div class="column-picker-grid">
          <?php foreach ($catalog as $id => $meta) : ?>
            <label class="col-check">
              <input type="checkbox" name="cols[]" value="<?= ehr1_h($id) ?>" <?= in_array($id, $selectedCols, true) ? ' checked' : '' ?>>
              <?= ehr1_h($meta['label']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </details>
    </section>
  </form>

  <?php if (!$run) : ?>
    <p class="muted explorer-placeholder">Set filters and columns above, then click <strong>Run report</strong>.</p>
  <?php else : ?>
    <section class="ehr1-surface explorer-panel explorer-results" id="grid-results" aria-labelledby="grid-results-heading">
      <div class="explorer-results-head">
        <h2 id="grid-results-heading" class="explorer-results-title"><span class="explorer-step">3.</span> Results<?php if ($validation === '' && $error === '') : ?> <span class="muted">(<?= count($rows) ?>)</span><?php endif; ?></h2>
        <?php if ($validation === '' && $error === '') : ?>
          <a class="btn btn-primary" href="<?= ehr1_h($csvHref) ?>">Download CSV</a>
        <?php endif; ?>
      </div>

      <?php if ($error !== '') : ?>
        <div class="ehr1-alert ehr1-alert-error" role="alert"><?= ehr1_h($error) ?></div>
      <?php else : ?>
        <p class="muted explorer-results-note">Click a column header to sort (newest sort wins). CSV uses the <strong>Sort (when you run)</strong> order from this run.</p>
        <?php if ($rows === []) : ?>
          <p class="muted"><strong>No rows matched</strong> your filters. Try widening the search or different criteria.</p>
        <?php else : ?>
          <div class="grid-scroll">
            <table class="data grid-table" id="explorer-results-table">
              <thead>
                <tr>
                  <?php foreach (array_keys($rows[0]) as $col) : ?>
                    <th class="grid-th-sortable" scope="col" title="Click to sort"><?= ehr1_h($catalog[$col]['label'] ?? $col) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r) : ?>
                  <tr>
                    <?php foreach ($r as $cell) : ?>
                      <td><?= ehr1_h((string) $cell) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>

<script>
(function () {
  var form = document.getElementById('grid-form');
  if (!form) return;
  var checks = form.querySelectorAll('input[name="cols[]"]');
  var defaults = <?= json_encode($defaultCols) ?>;
  var all = document.getElementById('col-all');
  var none = document.getElementById('col-none');
  var def = document.getElementById('col-default');
  if (all) all.onclick = function () { checks.forEach(function (c) { c.checked = true; }); };
  if (none) none.onclick = function () { checks.forEach(function (c) { c.checked = false; }); };
  if (def) def.onclick = function () {
    checks.forEach(function (c) { c.checked = defaults.indexOf(c.value) >= 0; });
  };
})();

(function () {
  var table = document.getElementById('explorer-results-table');
  if (!table || !table.tBodies.length) return;
  var tbody = table.tBodies[0];
  var ths = table.tHead && table.tHead.rows[0] ? table.tHead.rows[0].cells : [];
  if (!ths.length) return;
  var activeCol = null;
  var activeDir = 'asc';

  function cmp(a, b) {
    var na = parseFloat(a);
    var nb = parseFloat(b);
    if (a !== '' && b !== '' && !isNaN(na) && !isNaN(nb) && /^[\d.\-\s]+$/.test(a) && /^[\d.\-\s]+$/.test(b)) {
      return na - nb;
    }
    return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
  }

  for (var i = 0; i < ths.length; i++) {
    (function (col) {
      ths[col].addEventListener('click', function () {
        if (activeCol === col) {
          activeDir = activeDir === 'asc' ? 'desc' : 'asc';
        } else {
          activeCol = col;
          activeDir = 'asc';
        }
        var rows = Array.prototype.slice.call(tbody.rows);
        rows.sort(function (r1, r2) {
          var a = r1.cells[col].textContent.trim();
          var b = r2.cells[col].textContent.trim();
          var n = cmp(a, b);
          return activeDir === 'asc' ? n : -n;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
      });
    })(i);
  }
})();

(function () {
  var el = document.getElementById('grid-results');
  if (el) {
    requestAnimationFrame(function () {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
})();
</script>
<?php
ehr1_layout_end();
