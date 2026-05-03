<?php
/**
 * Practice / group NPI roster: list individual NPIs attached to an organization NPI.
 */
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/npi.php';
require_once __DIR__ . '/../includes/report_service.php';
require_once __DIR__ . '/../includes/ui.php';

$GLOBALS['ehr1_http_base'] = $config['http_base_path'] ?? '/ehr1-data';
require_once __DIR__ . '/../includes/layout_reports.php';

$npiRaw = isset($_GET['parent_npi']) ? (string) $_GET['parent_npi'] : '';
$parentNpi = ehr1_normalize_npi($npiRaw);
$error = '';
$org = null;
$children = [];

try {
    $pdo = ehr1_pdo($config);
    if ($parentNpi !== null) {
        $org = ehr1_fetch_provider($pdo, $parentNpi);
        if ($org === null) {
            $error = 'No provider record for this NPI.';
        } else {
            try {
                $children = ehr1_children_for_parent($pdo, $parentNpi);
            } catch (Throwable $e) {
                $error = 'Relationship table missing — run migration 06 on the database.';
            }
        }
    }
} catch (Throwable $e) {
    $error = ($config['show_errors'] ?? false) ? $e->getMessage() : 'Query failed.';
}

ehr1_layout_start(['title' => 'Practice / group roster · EHR1 Data', 'active' => 'hier']);
?>
<h1>Practice / group NPI roster</h1>
<p class="muted">Enter the <strong>organization (practice or group) NPI</strong>. Individual providers (doctors) linked in <code>core_npi_relationship</code> appear below.</p>

<form method="get" class="stacked" action="">
  <label>Parent (group / practice) NPI
    <input type="text" name="parent_npi" value="<?= ehr1_h($npiRaw) ?>" maxlength="10" inputmode="numeric">
  </label>
  <button type="submit">Show roster</button>
</form>

<?php if ($parentNpi === null && $npiRaw !== '') : ?>
  <p class="bad">Invalid NPI.</p>
<?php elseif ($error !== '') : ?>
  <p class="bad"><?= ehr1_h($error) ?></p>
<?php elseif ($org !== null) : ?>
  <?php $et = ehr1_entity_type(isset($org['entity_type_code']) ? (int) $org['entity_type_code'] : null); ?>
  <h2><?= ehr1_h((string) ($org['provider_organization_name'] ?: 'NPI ' . $org['npi'])) ?></h2>
  <p class="muted">NPI <code><?= ehr1_h((string) $org['npi']) ?></code> · <?= ehr1_h($et['label']) ?> · <?= ehr1_h((string) ($org['practice_city'] ?? '')) ?>, <?= ehr1_h((string) ($org['practice_state'] ?? '')) ?></p>

  <h2>Attached individual NPIs</h2>
  <?php if ($children === []) : ?>
    <p class="muted">No child NPIs linked. Populate <code>core_npi_relationship</code> (parent = group, child = individual).</p>
  <?php else : ?>
    <table class="data">
      <thead><tr><th>Individual NPI</th><th>Name</th><th>City</th><th>ST</th><th>Relationship</th></tr></thead>
      <tbody>
        <?php foreach ($children as $c) : ?>
          <tr>
            <td><a href="<?= ehr1_h(ehr1_url('/reports/by_npi.php?npi=' . urlencode((string) $c['child_npi']))) ?>"><?= ehr1_h((string) $c['child_npi']) ?></a></td>
            <td><?= ehr1_h(trim(($c['provider_last_name'] ?? '') . ', ' . ($c['provider_first_name'] ?? ''))) ?></td>
            <td><?= ehr1_h((string) ($c['practice_city'] ?? '')) ?></td>
            <td><?= ehr1_h((string) ($c['practice_state'] ?? '')) ?></td>
            <td><?= ehr1_h((string) ($c['relationship_type'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php elseif ($parentNpi === null && $npiRaw === '') : ?>
  <p class="muted">Enter a practice or group NPI.</p>
<?php endif; ?>

<div class="callout">
  Higher-level <strong>group NPIs</strong> can have many <strong>individual</strong> NPIs. Data is keyed by NPI everywhere; relationships are stored explicitly for reporting and can be extended from NPPES parent/subpart fields when those columns are loaded.
</div>
<?php
ehr1_layout_end();
