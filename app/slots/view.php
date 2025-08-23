<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$slot = get_slot_by_id($id);
if (!$slot) { http_response_code(404); echo 'Posto non trovato'; exit; }

$marina = get_marina_by_id((int)$slot['marina_id']);
$current = get_current_assignment($id);
$history = get_assignments_history($id);

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Token CSRF non valido.';
  } else {
    // Cambio stato / nuova assegnazione
    $new = [
      'stato' => $_POST['stato'] ?? 'Libero',
      'proprietario' => trim($_POST['proprietario'] ?? ''),
      'targa' => trim($_POST['targa'] ?? ''),
      'email' => trim($_POST['email'] ?? ''),
      'telefono' => trim($_POST['telefono'] ?? ''),
      'data_inizio' => parse_date_to_ymd($_POST['data_inizio'] ?? ''),
      'data_fine' => parse_date_to_ymd($_POST['data_fine'] ?? '')
    ];
    if (!$new['data_inizio']) $errors[] = 'Data inizio obbligatoria (formato gg/mm/aaaa)';
    if (!$errors) {
      try{
        $pdo->beginTransaction();
        // chiudi eventuale assegnazione aperta
        $stmt = $pdo->prepare("UPDATE assignments SET data_fine = :fine WHERE slot_id = :sid AND data_fine IS NULL");
        $closeFine = $new['data_inizio'];
        if ($closeFine) {
          $d = new DateTime($closeFine);
          $d->modify('-1 day');
          $closeFine = $d->format('Y-m-d');
        }
        $stmt->execute([':fine'=>$closeFine, ':sid'=>$id]);

        // inserisci nuova
        $stmt = $pdo->prepare("INSERT INTO assignments
          (slot_id, stato, proprietario, targa, email, telefono, data_inizio, data_fine, created_by)
          VALUES (:sid,:stato,:prop,:targa,:email,:tel,:di,:df,:uid)");
        $stmt->execute([
          ':sid'=>$id, ':stato'=>$new['stato'], ':prop'=>$new['proprietario'], ':targa'=>$new['targa'],
          ':email'=>$new['email'], ':tel'=>$new['telefono'],
          ':di'=>$new['data_inizio'], ':df'=>$new['data_fine'] ?: null, ':uid'=>current_user_id()
        ]);
        // aggiorna stato slot
        $stmt = $pdo->prepare("UPDATE slots SET stato=:stato, updated_at=NOW() WHERE id=:id");
        $stmt->execute([':stato'=>$new['stato'], ':id'=>$id]);

        // log evento
        log_event('assignment', (int)$pdo->lastInsertId(), 'create', [
          'slot_id'=>$id,'stato'=>$new['stato'],'proprietario'=>$new['proprietario']
        ]);

        $pdo->commit();
        set_flash('success','Stato/assegnazione aggiornati.');
        header('Location: /app/slots/view.php?id='.$id); exit;
      }catch(Throwable $e){
        $pdo->rollBack();
        $errors[] = 'Errore: '.$e->getMessage();
      }
    }
  }
}

$title = 'Posto '.$slot['numero_esterno'].' — '.$marina['name'];
$active = 'slots_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>
<div class="container py-4">
  <?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $er) echo '<div>'.e($er).'</div>';?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title mb-3">Dati posto</h5>
          <dl class="row mb-0">
            <dt class="col-5">Pontile</dt><dd class="col-7"><?= e($marina['name']) ?></dd>
            <dt class="col-5">Numero</dt><dd class="col-7"><?= e($slot['numero_esterno']) ?></dd>
            <dt class="col-5">Numero interno</dt><dd class="col-7"><?= e($slot['numero_interno']) ?: '—' ?></dd>
            <dt class="col-5">Tipo</dt><dd class="col-7"><?= e($slot['tipo'] ?: '—') ?></dd>
            <dt class="col-5">Stato</dt><dd class="col-7"><?= status_badge($slot['stato']) ?></dd>
            <dt class="col-5">Note</dt><dd class="col-7"><?= nl2br(e($slot['note'] ?? '')) ?: '—' ?></dd>
          </dl>
          <div class="mt-3 d-flex gap-2">
            <a href="/app/slots/edit.php?id=<?= (int)$slot['id'] ?>" class="btn btn-outline-secondary btn-sm">Modifica posto</a>
            <a href="/app/slots/list.php?marina=<?= e($marina['code']) ?>" class="btn btn-outline-primary btn-sm">Torna alla lista</a>
          </div>
        </div>
      </div>
      <?php if ($marina['code']!=='RAST'): ?>
        <div class="alert alert-info mt-3">
          Suggerimento: vedi la posizione sulla mappa:
          <a href="<?= $marina['code']==='BOLA'?'/app/map_bola.php':'/app/map_ritter.php' ?>">apri mappa</a>.
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title mb-3">Assegnazione corrente</h5>
          <?php if ($current): ?>
            <dl class="row mb-0">
              <dt class="col-5">Stato</dt><dd class="col-7"><?= status_badge($current['stato']) ?></dd>
              <dt class="col-5">Proprietario</dt><dd class="col-7"><?= e($current['proprietario'] ?: '—') ?></dd>
              <dt class="col-5">Targa</dt><dd class="col-7"><?= e($current['targa'] ?: '—') ?></dd>
              <dt class="col-5">Email</dt><dd class="col-7"><?= e($current['email'] ?: '—') ?></dd>
              <dt class="col-5">Telefono</dt><dd class="col-7"><?= e($current['telefono'] ?: '—') ?></dd>
              <dt class="col-5">Dal</dt><dd class="col-7"><?= e(format_date_from_ymd($current['data_inizio'])) ?></dd>
              <dt class="col-5">Al</dt><dd class="col-7"><?= e(format_date_from_ymd($current['data_fine'])) ?: '—' ?></dd>
            </dl>
          <?php else: ?>
            <p class="text-muted mb-0">Nessuna assegnazione attiva.</p>
          <?php endif; ?>
        </div>
        <div class="card-footer d-flex gap-2">
          <a class="btn btn-outline-primary btn-sm" href="/app/assignments/create.php?slot_id=<?= (int)$slot['id'] ?>">Nuova assegnazione</a>
          <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#changeState">Cambia stato / aggiornare assegnazione</button>
        </div>
      </div>

      <div id="changeState" class="collapse mt-3">
        <div class="card shadow-sm">
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label">Stato</label>
                  <select name="stato" class="form-select">
                    <?php foreach (['Libero','Occupato','Riservato','Manutenzione'] as $s): ?>
                      <option value="<?= $s ?>" <?= $slot['stato']===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Data inizio</label>
                  <input type="text" name="data_inizio" class="form-control" placeholder="gg/mm/aaaa" value="<?= e(date('d/m/Y')) ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Data fine</label>
                  <input type="text" name="data_fine" class="form-control" placeholder="gg/mm/aaaa">
                </div>
              </div>
              <div class="row g-2 mt-2">
                <div class="col-md-6">
                  <label class="form-label">Proprietario</label>
                  <input type="text" name="proprietario" class="form-control" value="<?= e($current['proprietario'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Targa</label>
                  <input type="text" name="targa" class="form-control" value="<?= e($current['targa'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" value="<?= e($current['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Telefono</label>
                  <input type="text" name="telefono" class="form-control" value="<?= e($current['telefono'] ?? '') ?>">
                </div>
              </div>
              <div class="mt-3">
                <button class="btn btn-primary">Salva</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-body">
          <h5 class="card-title mb-3">Storico</h5>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>Stato</th><th>Proprietario</th><th>Targa</th><th>Dal</th><th>Al</th></tr></thead>
              <tbody>
                <?php foreach ($history as $h): ?>
                  <tr>
                    <td><?= status_badge($h['stato']) ?></td>
                    <td><?= e($h['proprietario']) ?></td>
                    <td><?= e($h['targa']) ?></td>
                    <td><?= e(format_date_from_ymd($h['data_inizio'])) ?></td>
                    <td><?= e(format_date_from_ymd($h['data_fine'])) ?: '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <a class="btn btn-outline-secondary btn-sm" href="/app/history/list.php?slot_id=<?= (int)$slot['id'] ?>">Vedi storico completo</a>
        </div>
      </div>

    </div>
  </div>
</div>
<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>
