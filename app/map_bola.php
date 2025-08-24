<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_login();
$title = 'Mappa • Porto alla Bola';
$active = 'map_bola';
include __DIR__ . '/../inc/layout/header.php';
include __DIR__ . '/../inc/layout/navbar.php';
?>
<div class="container py-4">
  <h1 class="h4 mb-3">Porto alla Bola — Mappa stilizzata</h1>
  <div id="dock-bola"></div>
</div>
<script src="/assets/js/map.js"></script>
<script>
  renderDock({
    containerId:'dock-bola',
    code:'BOLA',
    count:47,
    apiUrl:'/app/api/slot_status.php?code=BOLA&t=' + Date.now(),
    title:'Porto alla Bola'
  });
</script>
<?php include __DIR__ . '/../inc/layout/footer.php'; ?>
