<?php
/**
 * Shared app chrome (top bar).
 */
declare(strict_types=1);

require_once __DIR__ . '/ui.php';

function ehr1_brand_topbar(): void
{
    ?>
  <header class="ehr1-topbar" role="banner">
    <div class="ehr1-topbar-inner">
      <a class="ehr1-logo" href="<?= ehr1_h(ehr1_url('/index.php')) ?>">EHR1 <span class="ehr1-logo-muted">Data</span></a>
    </div>
  </header>
    <?php
}
