<?php
/**
 * Archival report: rows that failed the master NPI gate (archive_supplemental_row).
 * Does not query active core/supplemental tables for provider truth—review and export only.
 */
declare(strict_types=1);

$config = require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ui.php';

$GLOBALS['ehr1_http_base'] = ehr1_resolve_http_base($config);
require_once __DIR__ . '/../includes/layout_reports.php';

function ehr1_archive_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM archive_supplemental_row LIMIT 1');

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** @return list<string> */
function ehr1_archive_reject_reasons(PDO $pdo): array
{
    $st = $pdo->query(
        'SELECT DISTINCT reject_reason FROM archive_supplemental_row ORDER BY reject_reason'
    );

    return array_values(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN)));
}

$pdo = ehr1_pdo($config);
$tableOk = ehr1_archive_table_exists($pdo);
$summaries = [];
$batches = [];
$detailRows = [];
$reasons = [];
$orphanBatchCount = 0;
$error = '';
$filterBatch = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : 0;
$filterReason = isset($_GET['reject_reason']) ? trim((string) $_GET['reject_reason']) : '';

if ($tableOk) {
    try {
        $summaries = $pdo->query(
            'SELECT reject_reason, COUNT(*) AS cnt FROM archive_supplemental_row GROUP BY reject_reason ORDER BY cnt DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $batches = $pdo->query(
            'SELECT b.batch_id, b.source_key, b.file_name, b.loaded_at, COUNT(a.archive_id) AS cnt
             FROM ref_source_batch b
             INNER JOIN archive_supplemental_row a ON a.source_batch_id = b.batch_id
             GROUP BY b.batch_id, b.source_key, b.file_name, b.loaded_at
             ORDER BY b.loaded_at DESC
             LIMIT 200'
        )->fetchAll(PDO::FETCH_ASSOC);

        $reasons = ehr1_archive_reject_reasons($pdo);

        $orphanSt = $pdo->query(
            'SELECT COUNT(*) FROM archive_supplemental_row WHERE source_batch_id IS NULL'
        );
        $orphanBatchCount = (int) $orphanSt->fetchColumn();

        $sql = 'SELECT a.archive_id, a.source_batch_id, a.npi_raw, a.reject_reason, a.reject_detail,
                       a.source_line_number, a.created_at, a.payload_json, b.file_name, b.source_key
                FROM archive_supplemental_row a
                LEFT JOIN ref_source_batch b ON b.batch_id = a.source_batch_id
                WHERE 1=1';
        $params = [];
        if ($filterBatch > 0) {
            $sql .= ' AND a.source_batch_id = :bid';
            $params['bid'] = $filterBatch;
        }
        if ($filterReason !== '') {
            $sql .= ' AND a.reject_reason = :rr';
            $params['rr'] = $filterReason;
        }
        $sql .= ' ORDER BY a.archive_id DESC LIMIT 500';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $detailRows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = ($config['show_errors'] ?? false) ? $e->getMessage() : 'Query failed.';
    }
}

ehr1_layout_start(['title' => 'Archival supplemental - EHR1 Data']);
?>
<div class="report-hero">
  <h1 class="report-hero-title">Archival supplemental</h1>
  <p class="report-hero-lead muted">Rows from non-master imports that <strong>did not</strong> match an NPI in <code>core_npi_provider</code>. This data is <strong>not</strong> in the active dataset and does not appear in the Data explorer or standard searches.</p>
</div>

<?php if (!$tableOk) : ?>
  <div class="ehr1-alert ehr1-alert-warn" role="status">
    <p><strong>Table missing.</strong> Import <code>sql/mysql/07_archive_supplemental.sql</code> in <strong>hPanel &rarr; phpMyAdmin</strong> (a user with CREATE rights). The app DB user often cannot run DDL; <code>php tools/migrate_archive_only.php</code> only works if that user may CREATE TABLE. See <code>apply_order.txt</code> in the repo.</p>
  </div>
<?php elseif ($error !== '') : ?>
  <div class="ehr1-alert ehr1-alert-error" role="alert"><?= ehr1_h($error) ?></div>
<?php else : ?>

  <?php if ($orphanBatchCount > 0) : ?>
    <p class="muted explorer-results-note" role="status"><strong><?= number_format($orphanBatchCount) ?></strong> archived row(s) have no <code>source_batch_id</code> (not listed under &ldquo;By import batch&rdquo; below).</p>
  <?php endif; ?>

  <section class="ehr1-surface explorer-panel">
    <h2 class="explorer-panel-title">Summary by reject reason</h2>
    <?php if ($summaries === []) : ?>
      <p class="muted">No archived rows yet.</p>
    <?php else : ?>
      <table class="data">
        <thead><tr><th>Reason</th><th>Rows</th></tr></thead>
        <tbody>
          <?php foreach ($summaries as $s) : ?>
            <tr>
              <td><code><?= ehr1_h((string) $s['reject_reason']) ?></code></td>
              <td><?= (int) $s['cnt'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="ehr1-surface explorer-panel">
    <h2 class="explorer-panel-title">By import batch</h2>
    <?php if ($batches === []) : ?>
      <p class="muted">No batch-linked archives (or empty table).</p>
    <?php else : ?>
      <table class="data">
        <thead><tr><th>Batch</th><th>Source</th><th>File</th><th>Loaded</th><th>Archived rows</th></tr></thead>
        <tbody>
          <?php foreach ($batches as $b) : ?>
            <tr>
              <td><?= (int) $b['batch_id'] ?></td>
              <td><?= ehr1_h((string) $b['source_key']) ?></td>
              <td><?= ehr1_h((string) $b['file_name']) ?></td>
              <td><?= ehr1_h((string) $b['loaded_at']) ?></td>
              <td><?= (int) $b['cnt'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="ehr1-surface explorer-panel">
    <h2 class="explorer-panel-title">Detail (latest 500 matching filters)</h2>
    <form method="get" action="<?= ehr1_h(ehr1_url('/reports/archive_supplemental.php')) ?>" class="toolbar-grid explorer-text-filters">
      <label>Batch ID <input type="number" name="batch_id" value="<?= $filterBatch > 0 ? ehr1_h((string) $filterBatch) : '' ?>" min="1" placeholder="Any"></label>
      <label>Reject reason
        <select name="reject_reason">
          <option value="">Any</option>
          <?php foreach ($reasons as $r) : ?>
            <option value="<?= ehr1_h($r) ?>"<?= $filterReason === $r ? ' selected' : '' ?>><?= ehr1_h($r) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="explorer-span-2"><span class="muted">&nbsp;</span><button type="submit" class="btn btn-secondary">Apply filters</button></label>
    </form>

    <?php if ($detailRows === []) : ?>
      <p class="muted">No rows match the current filters.</p>
    <?php else : ?>
      <table class="data">
        <thead>
          <tr>
            <th>ID</th><th>Batch</th><th>Source</th><th>NPI (raw)</th><th>Reason</th><th>Detail</th><th>Line</th><th>Created</th><th>Payload</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($detailRows as $dr) : ?>
            <?php
            $aid = (int) $dr['archive_id'];
            $payloadRaw = isset($dr['payload_json']) ? (string) $dr['payload_json'] : '';
            $decoded = json_decode($payloadRaw, true);
            $payloadPretty = is_array($decoded)
                ? (json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $payloadRaw)
                : $payloadRaw;
            ?>
            <tr>
              <td><?= $aid ?></td>
              <td><?= $dr['source_batch_id'] !== null ? (int) $dr['source_batch_id'] : '&mdash;' ?></td>
              <td><?= ehr1_h((string) ($dr['source_key'] ?? '')) ?></td>
              <td><code><?= ehr1_h((string) ($dr['npi_raw'] ?? '')) ?></code></td>
              <td><code><?= ehr1_h((string) $dr['reject_reason']) ?></code></td>
              <td><?= isset($dr['reject_detail']) && (string) $dr['reject_detail'] !== '' ? ehr1_h((string) $dr['reject_detail']) : '&mdash;' ?></td>
              <td><?= $dr['source_line_number'] !== null ? (int) $dr['source_line_number'] : '&mdash;' ?></td>
              <td><?= ehr1_h((string) $dr['created_at']) ?></td>
              <td>
                <details>
                  <summary class="linkish">JSON</summary>
                  <pre class="archive-payload-pre"><?= ehr1_h($payloadPretty) ?></pre>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

<?php endif; ?>
<?php
ehr1_layout_end();
