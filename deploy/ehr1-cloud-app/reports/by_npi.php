<?php
/**
 * Report: single provider by NPI (exact key) + related rows and hierarchy.
 */
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/npi.php';
require_once __DIR__ . '/../includes/report_service.php';
require_once __DIR__ . '/../includes/ui.php';

$GLOBALS['ehr1_http_base'] = $config['http_base_path'] ?? '/ehr1-data';
require_once __DIR__ . '/../includes/layout_reports.php';

$npiRaw = isset($_GET['npi']) ? (string) $_GET['npi'] : '';
$npi = ehr1_normalize_npi($npiRaw);
$error = '';
$provider = null;
$endpoints = [];
$locations = [];
$otherNames = [];
$parents = [];
$children = [];

try {
    $pdo = ehr1_pdo($config);
    if ($npi !== null) {
        $provider = ehr1_fetch_provider($pdo, $npi);
        if ($provider === null) {
            $error = 'No provider row for this NPI. Try cross-reference search with name or address.';
        } else {
            $st = $pdo->prepare('SELECT * FROM core_npi_endpoint WHERE npi = :n ORDER BY endpoint_id');
            $st->execute(['n' => $npi]);
            $endpoints = $st->fetchAll();

            $st = $pdo->prepare('SELECT * FROM core_npi_practice_location WHERE npi = :n ORDER BY practice_location_id');
            $st->execute(['n' => $npi]);
            $locations = $st->fetchAll();

            $st = $pdo->prepare('SELECT * FROM core_npi_other_name WHERE npi = :n ORDER BY other_name_id');
            $st->execute(['n' => $npi]);
            $otherNames = $st->fetchAll();

            try {
                $parents = ehr1_parents_for_individual($pdo, $npi);
                $children = ehr1_children_for_parent($pdo, $npi);
            } catch (Throwable $e) {
                $parents = [];
                $children = [];
            }
        }
    }
} catch (Throwable $e) {
    $error = ($config['show_errors'] ?? false) ? $e->getMessage() : 'Query failed.';
}

ehr1_layout_start(['title' => 'Provider by NPI · EHR1 Data', 'active' => 'npi']);
?>
<h1>Provider by NPI</h1>
<form method="get" class="stacked" action="">
  <label>NPI (10 digits)
    <input type="text" name="npi" value="<?= ehr1_h($npiRaw) ?>" maxlength="10" inputmode="numeric" autocomplete="off">
  </label>
  <button type="submit">Run report</button>
</form>

<?php if ($npi === null && $npiRaw !== '') : ?>
  <p class="bad">Invalid NPI — enter exactly 10 digits.</p>
<?php elseif ($error !== '') : ?>
  <p class="bad"><?= ehr1_h($error) ?></p>
<?php elseif ($provider !== null) : ?>
  <?php $et = ehr1_entity_type(isset($provider['entity_type_code']) ? (int) $provider['entity_type_code'] : null); ?>
  <h2><?= ehr1_h((string) ($provider['provider_organization_name'] ?: (($provider['provider_last_name'] ?? '') . ', ' . ($provider['provider_first_name'] ?? '')))) ?></h2>
  <p class="muted">NPI <code><?= ehr1_h((string) $provider['npi']) ?></code> · <?= ehr1_h($et['label']) ?></p>

  <h2>Practice / group links</h2>
  <?php if ($parents !== []) : ?>
    <p class="muted">This NPI is linked as an <strong>individual</strong> under:</p>
    <table class="data">
      <thead><tr><th>Parent NPI</th><th>Organization</th><th>City</th><th>State</th><th>Type</th></tr></thead>
      <tbody>
        <?php foreach ($parents as $p) : ?>
          <tr>
            <td><a href="<?= ehr1_h(ehr1_url('/reports/by_npi.php?npi=' . urlencode((string) $p['parent_npi']))) ?>"><?= ehr1_h((string) $p['parent_npi']) ?></a></td>
            <td><?= ehr1_h((string) ($p['provider_organization_name'] ?? '')) ?></td>
            <td><?= ehr1_h((string) ($p['practice_city'] ?? '')) ?></td>
            <td><?= ehr1_h((string) ($p['practice_state'] ?? '')) ?></td>
            <td><?= ehr1_h((string) ($p['relationship_type'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($children !== []) : ?>
    <p class="muted">Individual NPIs attached to this <strong>practice/group</strong> NPI:</p>
    <table class="data">
      <thead><tr><th>Child NPI</th><th>Name</th><th>City</th><th>State</th><th>Link type</th></tr></thead>
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

  <?php if ($parents === [] && $children === []) : ?>
    <p class="muted">No rows in <code>core_npi_relationship</code> for this NPI.</p>
  <?php endif; ?>

  <h2>Endpoints</h2>
  <?php if ($endpoints === []) : ?>
    <p class="muted">None.</p>
  <?php else : ?>
    <table class="data"><thead><tr><th>Type</th><th>URL / value</th></tr></thead><tbody>
      <?php foreach ($endpoints as $e) : ?>
        <tr>
          <td><?= ehr1_h((string) ($e['endpoint_type'] ?? '')) ?></td>
          <td><?= ehr1_h((string) ($e['endpoint_url'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>

  <h2>Other practice locations</h2>
  <?php if ($locations === []) : ?>
    <p class="muted">None.</p>
  <?php else : ?>
    <table class="data"><thead><tr><th>Address</th><th>City</th><th>State</th><th>ZIP</th></tr></thead><tbody>
      <?php foreach ($locations as $l) : ?>
        <tr>
          <td><?= ehr1_h(trim(($l['pl_address_line1'] ?? '') . ' ' . ($l['pl_address_line2'] ?? ''))) ?></td>
          <td><?= ehr1_h((string) ($l['pl_city'] ?? '')) ?></td>
          <td><?= ehr1_h((string) ($l['pl_state'] ?? '')) ?></td>
          <td><?= ehr1_h((string) ($l['pl_postal_code'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>

  <h2>Other names</h2>
  <?php if ($otherNames === []) : ?>
    <p class="muted">None.</p>
  <?php else : ?>
    <table class="data"><thead><tr><th>Name</th><th>Type code</th></tr></thead><tbody>
      <?php foreach ($otherNames as $o) : ?>
        <tr>
          <td><?= ehr1_h((string) ($o['provider_other_organization_name'] ?? '')) ?></td>
          <td><?= ehr1_h((string) ($o['provider_other_organization_name_type_code'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>

<?php elseif ($npi === null && $npiRaw === '') : ?>
  <p class="muted">Enter an NPI to run the report.</p>
<?php endif; ?>

<?php
ehr1_layout_end();
