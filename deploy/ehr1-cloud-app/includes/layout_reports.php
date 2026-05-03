<?php
/**
 * @param array{title:string, active?:string} $opts
 */
require_once __DIR__ . '/brand.php';

function ehr1_layout_start(array $opts): void
{
    $title = $opts['title'] ?? 'EHR1 Data';
    $active = $opts['active'] ?? '';
    $nav = [
        'explorer' => ['Data explorer', ehr1_url('/reports/index.php')],
    ];
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0d47a1">
  <title><?= ehr1_h($title) ?></title>
  <link rel="stylesheet" href="<?= ehr1_h(ehr1_url('/assets/app.css')) ?>">
</head>
<body class="ehr1-body">
  <?php ehr1_brand_topbar(); ?>
  <div class="ehr1-page">
    <nav class="main ehr1-subnav" aria-label="Main">
      <span class="brand"><a href="<?= ehr1_h(ehr1_url('/index.php')) ?>">EHR1 Data</a></span>
      <a href="<?= ehr1_h(ehr1_url('/index.php')) ?>">Status</a>
      <?php foreach ($nav as $key => $item) : ?>
        <?php
        [$label, $href] = $item;
        $aria = ($active === $key) ? ' aria-current="page"' : '';
        $cls = ($active === $key) ? ' class="is-active"' : '';
        ?>
      <a href="<?= ehr1_h($href) ?>"<?= $aria ?><?= $cls ?>><?= ehr1_h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <p class="ehr1-nav-tools muted">Other tools:
      <a href="<?= ehr1_h(ehr1_url('/reports/by_npi.php')) ?>">One NPI</a> &middot;
      <a href="<?= ehr1_h(ehr1_url('/reports/crossref.php')) ?>">Search</a> &middot;
      <a href="<?= ehr1_h(ehr1_url('/reports/hierarchy.php')) ?>">Practice groups</a>
    </p>
    <main class="ehr1-main">
    <?php
}

function ehr1_layout_end(): void
{
    ?>
    </main>
    <footer class="ehr1-footer muted">EHR1 Data · <?= ehr1_h(date('Y-m-d H:i')) ?> (server)</footer>
  </div>
</body>
</html>
    <?php
}
