<?php
/**
 * Cross-reference search when NPI is unknown or not in the database.
 */
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/report_service.php';
require_once __DIR__ . '/../includes/ui.php';

$GLOBALS['ehr1_http_base'] = $config['http_base_path'] ?? '/ehr1-data';
require_once __DIR__ . '/../includes/layout_reports.php';

$ln = isset($_GET['last_name']) ? trim((string) $_GET['last_name']) : '';
$fn = isset($_GET['first_name']) ? trim((string) $_GET['first_name']) : '';
$org = isset($_GET['org']) ? trim((string) $_GET['org']) : '';
$city = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
$st = isset($_GET['state']) ? trim((string) $_GET['state']) : '';
$zip = isset($_GET['zip']) ? trim((string) $_GET['zip']) : '';

$rows = [];
$error = '';
$ran = ($_SERVER['REQUEST_METHOD'] === 'GET') && (isset($_GET['run']) || $ln !== '' || $fn !== '' || $org !== '' || $city !== '' || $st !== '' || $zip !== '');

try {
    $pdo = ehr1_pdo($config);
    if ($ran) {
        $rows = ehr1_crossref_search($pdo, $ln ?: null, $fn ?: null, $org ?: null, $city ?: null, $st ?: null, $zip ?: null);
    }
} catch (Throwable $e) {
    $error = ($config['show_errors'] ?? false) ? $e->getMessage() : 'Search failed.';
}

ehr1_layout_start(['title' => 'Cross-reference search · EHR1 Data', 'active' => 'xref']);
?>
<h1>Cross-reference search</h1>
<p class="muted">Uses OR logic across fields you fill in, then ranks by how many fields line up. Always confirm with the official <strong>NPI</strong> before relying on a match.</p>

<form method="get" class="stacked" action="">
  <input type="hidden" name="run" value="1">
  <label>Last name <input type="text" name="last_name" value="<?= ehr1_h($ln) ?>"></label>
  <label>First name <input type="text" name="first_name" value="<?= ehr1_h($fn) ?>"></label>
  <label>Organization name <input type="text" name="org" value="<?= ehr1_h($org) ?>"></label>
  <label>Practice city <input type="text" name="city" value="<?= ehr1_h($city) ?>"></label>
  <label>State (2 letters) <input type="text" name="state" value="<?= ehr1_h($st) ?>" maxlength="2"></label>
  <label>ZIP (first 5) <input type="text" name="zip" value="<?= ehr1_h($zip) ?>" maxlength="10"></label>
  <button type="submit">Search</button>
</form>

<?php if ($error !== '') : ?>
  <p class="bad"><?= ehr1_h($error) ?></p>
<?php elseif ($ran) : ?>
  <h2>Results (<?= count($rows) ?>)</h2>
  <?php if ($rows === []) : ?>
    <p class="muted">No rows matched. Broaden the search or use a different field.</p>
  <?php else : ?>
    <table class="data">
      <thead>
        <tr>
          <th>NPI</th><th>Match</th><th>Organization</th><th>Name</th><th>City</th><th>ST</th><th>ZIP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r) : ?>
          <tr>
            <td><a href="<?= ehr1_h(ehr1_url('/reports/by_npi.php?npi=' . urlencode((string) $r['npi']))) ?>"><?= ehr1_h((string) $r['npi']) ?></a></td>
            <td><?= ehr1_h((string) ($r['match_reason'] ?? '')) ?></td>
            <td><?= ehr1_h((string) ($r['provider_organization_name'] ?? '')) ?></td>
            <td><?= ehr1_h(trim(($r['provider_last_name'] ?? '') . ', ' . ($r['provider_first_name'] ?? ''))) ?></td>
            <td><?= ehr1_h((string) ($r['practice_city'] ?? '')) ?></td>
            <td><?= ehr1_h((string) ($r['practice_state'] ?? '')) ?></td>
            <td><?= ehr1_h((string) ($r['practice_postal_code'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endif; ?>

<?php
ehr1_layout_end();
