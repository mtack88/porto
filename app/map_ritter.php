<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_login();
$title = 'Mappa • Porto W. Ritter';
$active = 'map_ritter';
include __DIR__ . '/../inc/layout/header.php';
include __DIR__ . '/../inc/layout/navbar.php';
?>
<div class="container py-4">
  <h1 class="h4 mb-3">Porto W. Ritter — Mappa stilizzata</h1>
  <div id="dock-ritter"></div>
</div>
<script src="/assets/js/map.js"></script>
<script>
  renderDock({
    containerId:'dock-ritter',
    code:'RITTER',
    count:18,
    apiUrl:'/app/api/slot_status.php?code=RITTER',
    title:'Porto W. Ritter'
  });
</script>
<?php include __DIR__ . '/../inc/layout/footer.php'; ?>
