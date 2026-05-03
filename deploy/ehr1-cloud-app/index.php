<?php
/**
 * EHR1 Data — production status page (Hostinger PHP + MySQL).
 * Deploy under e.g. https://ehr1.cloud/ehr1-data/ (unlisted path + hPanel directory password).
 */
declare(strict_types=1);

$config = require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/brand.php';

$GLOBALS['ehr1_http_base'] = $config['http_base_path'] ?? '/ehr1-data';

header('Content-Type: text/html; charset=UTF-8');

$pageTitle = 'EHR1 Data';
$dbOk = false;
$error = '';
$counts = [];
$sample = [];

try {
    $pdo = ehr1_pdo($config);

    $tables = [
        'ref_source_batch',
        'core_npi_provider',
        'core_npi_endpoint',
        'core_npi_practice_location',
        'core_npi_other_name',
        'supplemental_ep_paid',
        'supplemental_dncs_ndfile',
    ];
    foreach ($tables as $t) {
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $t) . '`');
        $row = $stmt->fetch();
        $counts[$t] = (int) ($row['c'] ?? 0);
    }

    $stmt = $pdo->query(
        'SELECT npi, provider_organization_name, provider_last_name, provider_first_name, practice_city, practice_state
         FROM core_npi_provider ORDER BY npi LIMIT 25'
    );
    $sample = $stmt->fetchAll();
    $dbOk = true;
} catch (Throwable $e) {
    $error = $config['show_errors'] ?? false
        ? $e->getMessage()
        : 'Database unavailable. Check config.local.php and that schema is imported.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0d47a1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= ehr1_h(ehr1_url('/assets/app.css')) ?>">
</head>
<body class="ehr1-body">
  <?php ehr1_brand_topbar(); ?>
  <div class="ehr1-page">
    <nav class="main ehr1-subnav" aria-label="Main">
      <span class="brand"><a href="<?= ehr1_h(ehr1_url('/index.php')) ?>">EHR1 Data</a></span>
      <a href="<?= ehr1_h(ehr1_url('/index.php')) ?>" class="is-active" aria-current="page">Status</a>
      <a href="<?= ehr1_h(ehr1_url('/reports/index.php')) ?>">Data explorer</a>
    </nav>
    <main class="ehr1-main">
  <div class="report-hero">
    <h1 class="report-hero-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="report-hero-lead">Consolidated NPPES-style data (test / rollout). Open the <a href="<?= ehr1_h(ehr1_url('/reports/index.php')) ?>">Data explorer</a> to filter and export on one page.</p>
  </div>

  <?php if ($dbOk) : ?>
    <p class="ok"><strong>Database:</strong> connected.</p>
    <p class="muted"><strong>EP PAID (supplemental):</strong> <?= (int) ($counts['supplemental_ep_paid'] ?? 0) ?> row(s) keyed by NPI and joined to master provider rows in the <a href="<?= ehr1_h(ehr1_url('/reports/index.php')) ?>">Data explorer</a> (select EP PAID fields under section 2).</p>
    <h2>Row counts</h2>
    <table class="data">
      <thead><tr><th>Table</th><th>Rows</th></tr></thead>
      <tbody>
        <?php foreach ($counts as $t => $c) : ?>
          <tr><td><code><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></code></td><td><?= $c ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h2>Sample providers <span class="muted">(up to 25)</span></h2>
    <?php if ($sample === []) : ?>
      <p class="muted">No rows in <code>core_npi_provider</code>. Import schema and seed, or load data.</p>
    <?php else : ?>
      <table class="data">
        <thead>
          <tr>
            <th>NPI</th><th>Organization</th><th>Name</th><th>City</th><th>State</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sample as $r) : ?>
            <tr>
              <td><?= htmlspecialchars((string) $r['npi'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($r['provider_organization_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars(trim(($r['provider_last_name'] ?? '') . ', ' . ($r['provider_first_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($r['practice_city'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($r['practice_state'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  <?php else : ?>
    <p class="bad"><strong>Database:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

    </main>
    <footer class="ehr1-footer muted">EHR1 Data · <?= htmlspecialchars(date('Y-m-d H:i'), ENT_QUOTES, 'UTF-8') ?> (server)</footer>
  </div>
</body>
</html>
