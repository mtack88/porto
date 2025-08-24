<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();
$title = 'Rastrelliera — griglia 3×6';
$active = 'rack';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';

$marina = get_marina_by_code('RAST');
$all = get_slots_with_current_assignment($marina['id']);

// Map numero_esterno -> slot
$byNum = [];
foreach ($all as $s) { $byNum[(int)$s['numero_esterno']] = $s; }

// Costruisci tabella 6 righe x 3 colonne: R1–R6, R7–R12, R13–R18 per colonne
?>
<div class="container py-4">
  <h1 class="h4 mb-3">Rastrelliera — 3 colonne × 6 righe</h1>
  <div class="table-responsive">
    <table class="table rack-table align-middle">
      <tbody>
        <?php for ($row=0; $row<6; $row++): ?>
          <tr>
            <?php for ($col=0; $col<3; $col++):
              $num = $col*6 + ($row+1);
              $s = $byNum[$num] ?? null;
              $cls = $s ? strtolower($s['stato']) : 'manutenzione';
              ?>
              <td>
                <?php if ($s): ?>
                  <a href="/app/slots/view.php?id=<?= (int)$s['id'] ?>" class="text-decoration-none text-reset">
                    <div class="rack-cell <?= e($cls) ?>">
                      <div class="num">R<?= e($s['numero_esterno']) ?></div>
                      <div><?= status_badge($s['stato']) ?></div>
                      <?php if ($s['proprietario']): ?>
                        <div class="small mt-1"><?= e($s['proprietario']) ?></div>
                      <?php endif; ?>
                    </div>
                  </a>
                <?php else: ?>
                  <div class="rack-cell manutenzione">
                    <div class="num">R<?= e($num) ?></div>
                    <div><span class="badge badge-stato badge-manutenzione">N/D</span></div>
                  </div>
                <?php endif; ?>
              </td>
            <?php endfor; ?>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-3">
    <div class="alert alert-info">
      <strong>Legenda colori:</strong><br>
      <span class="badge badge-stato badge-libero">Libero</span>
      <span class="badge badge-stato badge-occupato">Occupato</span>
      <span class="badge badge-stato badge-riservato">Riservato</span>
      <span class="badge badge-stato badge-manutenzione">Manutenzione</span>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>