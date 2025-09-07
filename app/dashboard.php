<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_login();

$stmt = $pdo->query("
  SELECT m.code, m.name, m.kind,
         SUM(CASE WHEN s.stato='Libero' THEN 1 ELSE 0 END) AS liberi,
         SUM(CASE WHEN s.stato='Occupato' THEN 1 ELSE 0 END) AS occupati,
         SUM(CASE WHEN s.stato='Riservato' THEN 1 ELSE 0 END) AS riservati,
         SUM(CASE WHEN s.stato='Manutenzione' THEN 1 ELSE 0 END) AS manutenzione,
         COUNT(*) as tot
  FROM marinas m
  JOIN slots s ON s.marina_id = m.id AND s.deleted_at IS NULL
  GROUP BY m.id
  ORDER BY m.code
");
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$title = 'Dashboard';
$active = 'dashboard';
include __DIR__ . '/../inc/layout/header.php';
include __DIR__ . '/../inc/layout/navbar.php';
?>
<div class="container py-4">
  <div class="row g-3">
    <?php foreach ($stats as $st): ?>
      <div class="col-md-4">
        <div class="card card-brand shadow-sm">
          <div class="card-body">
            <h5 class="card-title mb-3"><?= e($st['name']) ?> <span class="badge bg-secondary"><?= e($st['kind']) ?></span></h5>
            <div class="row text-center">
              <div class="col">
                <div class="fw-bold"><?= (int)$st['liberi'] ?></div>
                <div class="small text-success">Liberi</div>
              </div>
              <div class="col">
                <div class="fw-bold"><?= (int)$st['occupati'] ?></div>
                <div class="small text-danger">Occupati</div>
              </div>
              <div class="col">
                <div class="fw-bold"><?= (int)$st['riservati'] ?></div>
                <div class="small text-warning">Riservati</div>
              </div>
              <div class="col">
                <div class="fw-bold"><?= (int)$st['manutenzione'] ?></div>
                <div class="small text-muted">Manut.</div>
              </div>
            </div>
            <div class="small-muted mt-2">Totale posti: <?= (int)$st['tot'] ?></div>
          </div>
          <div class="card-footer d-flex gap-2">
            <?php if ($st['code']==='BOLA'): ?>
              <a href="/app/map/bola.php" class="btn btn-outline-primary btn-sm">Vedi mappa</a>
            <?php elseif ($st['code']==='RITTER'): ?>
              <a href="/app/map/ritter.php" class="btn btn-outline-primary btn-sm">Vedi mappa</a>
            <?php else: ?>
              <a href="/app/rack/grid.php" class="btn btn-outline-primary btn-sm">Vedi griglia</a>
            <?php endif; ?>
            <a href="/app/slots/list.php?marina=<?= e($st['code']) ?>" class="btn btn-outline-secondary btn-sm">Vedi tabella</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/../inc/layout/footer.php'; ?>