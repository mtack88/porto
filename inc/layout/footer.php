<?php
declare(strict_types=1);
?>
<footer class="container">
  <div class="d-flex justify-content-between align-items-center py-3">
    <div class="small-muted">© <?= date('Y') ?> <?= e(env('APP_NAME','Gestione Porti')) ?></div>
    <div class="small-muted">Comune — gestione porti e rastrelliera</div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>