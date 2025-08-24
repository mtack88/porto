<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

// Solo porti (BOLA, RITTER)
$portMarinas = array_values(array_filter(get_all_marinas(), fn($m) => $m['kind'] === 'porto'));
$selCode = $_GET['marina'] ?? ($portMarinas[0]['code'] ?? 'BOLA');
if (!in_array($selCode, array_column($portMarinas, 'code'), true)) {
  $selCode = $portMarinas[0]['code'] ?? 'BOLA';
}

// Precalcola "prossimo numero" per ogni pontile
$nextByCode = [];
foreach ($portMarinas as $m) {
  $nextByCode[$m['code']] = next_external_number((int)$m['id']);
}
$defaultNumero = $nextByCode[$selCode] ?? 1;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Token CSRF non valido.';
  } else {
    $code = $_POST['marina'] ?? $selCode;
    $marina = get_marina_by_code($code);
    if (!$marina || $marina['kind'] !== 'porto') {
      $errors[] = 'Pontile non valido.';
    } else {
      $numero_esterno = (int)($_POST['numero_esterno'] ?? 0);
      if ($numero_esterno <= 0) $numero_esterno = $nextByCode[$code] ?? 1;
      $numero_interno = trim($_POST['numero_interno'] ?? '');
      $tipo = $_POST['tipo'] ?? 'carrello';
      $note = trim($_POST['note'] ?? '');

      try {
        // verifica univocità
        $stmt = $pdo->prepare("SELECT 1 FROM slots WHERE marina_id=:mid AND numero_esterno=:num LIMIT 1");
        $stmt->execute([':mid'=>$marina['id'], ':num'=>$numero_esterno]);
        if ($stmt->fetch()) throw new Exception('Esiste già un posto con questo numero in questo pontile.');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO slots (marina_id, numero_esterno, numero_interno, tipo, stato, note, created_at)
                               VALUES (:mid,:num,:ni,:tipo,'Libero',:note,NOW())");
        $stmt->execute([
          ':mid'=>$marina['id'],
          ':num'=>$numero_esterno,
          ':ni'=>$numero_interno ?: null,
          ':tipo'=>$tipo ?: 'carrello',
          ':note'=>$note ?: null
        ]);
        $slotId = (int)$pdo->lastInsertId();

        // Assegnazione iniziale Libero
        $pdo->prepare("INSERT INTO assignments (slot_id, stato, data_inizio, created_by, created_at)
                       VALUES (:sid,'Libero',CURDATE(),:uid,NOW())")
            ->execute([':sid'=>$slotId, ':uid'=>current_user_id()]);

        log_event('slot', $slotId, 'create', ['marina'=>$marina['code'],'numero'=>$numero_esterno]);
        $pdo->commit();

        set_flash('success', 'Posto aggiunto correttamente.');
        header('Location: /app/slots/view.php?id='.$slotId); exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = $e->getMessage();
        if (str_contains($msg, 'uq_marina_num')) $msg = 'Esiste già un posto con questo numero in questo pontile.';
        $errors[] = 'Errore: '.$msg;
      }
    }
  }
}

$title = 'Aggiungi posto';
$active = 'slots_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>
<div class="container py-4" style="max-width: 760px;">
  <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $er) echo '<div>'.e($er).'</div>'; ?></div><?php endif; ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Aggiungi posto (porto)</h1>
      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">Pontile</label>
        <select name="marina" id="marina" class="form-select">
          <?php foreach ($portMarinas as $m): $code=$m['code']; ?>
            <option
              value="<?= e($code) ?>"
              data-next="<?= (int)($nextByCode[$code] ?? 1) ?>"
              <?= ($selCode===$code)?'selected':'' ?>>
              <?= e($m['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-help">Solo porti (Rastrelliera esclusa).</div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Numero esterno</label>
        <input type="number" name="numero_esterno" id="numero_esterno" class="form-control"
               value="<?= e($_POST['numero_esterno'] ?? $defaultNumero) ?>" min="1" required>
        <div class="form-help" id="suggerito">Suggerito: <?= (int)$defaultNumero ?></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select">
          <option value="carrello" <?= (($_POST['tipo'] ?? 'carrello')==='carrello')?'selected':'' ?>>Carrello</option>
          <option value="fune" <?= (($_POST['tipo'] ?? '')==='fune')?'selected':'' ?>>Fune</option>
        </select>
      </div>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-md-6">
        <label class="form-label">Numero interno</label>
        <input type="text" name="numero_interno" class="form-control" value="<?= e($_POST['numero_interno'] ?? '') ?>">
      </div>
      <div class="col-md-12">
        <label class="form-label">Note</label>
        <textarea name="note" rows="3" class="form-control"><?= e($_POST['note'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary">Aggiungi posto</button>
      <a class="btn btn-outline-secondary" href="/app/slots/list.php?marina=<?= e($selCode) ?>">Annulla</a>
    </div>
  </form>
</div>
<div class="card-footer small-muted">
  Lo stato iniziale sarà "Libero" con inserimento automatico nello storico.
</div>
  </div>
</div>
<script>
  // Aggiorna suggerimento del numero esterno al cambio Pontile
  (function(){
    const sel = document.getElementById('marina');
    const num = document.getElementById('numero_esterno');
    const sug = document.getElementById('suggerito');
    if (!sel || !num || !sug) return;
    function update(){
      const opt = sel.options[sel.selectedIndex];
      const next = opt ? parseInt(opt.getAttribute('data-next') || '1', 10) : 1;
      if (!num.value) num.value = next;
      sug.textContent = 'Suggerito: ' + next;
    }
    sel.addEventListener('change', update);
    // Non forziamo il valore se l'utente ne ha già digitato uno
    if (!num.value) update();
  })();
</script>
<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>
