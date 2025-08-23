<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$slot_id = (int)($_GET['slot_id'] ?? $_POST['slot_id'] ?? 0);
$slot = get_slot_by_id($slot_id);
if (!$slot) { http_response_code(404); echo 'Posto non trovato'; exit; }
$marina = get_marina_by_id((int)$slot['marina_id']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Token CSRF non valido.';
  } else {
    $stato = $_POST['stato'] ?? 'Occupato';
    $proprietario = trim($_POST['proprietario'] ?? '');
    $targa = trim($_POST['targa'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $data_inizio = parse_date_to_ymd($_POST['data_inizio'] ?? '');
    $data_fine = parse_date_to_ymd($_POST['data_fine'] ?? '');

    if (!$data_inizio) $errors[] = 'Data inizio obbligatoria (gg/mm/aaaa).';

    if (!$errors) {
      try {
        $pdo->beginTransaction();

        // chiudi eventuale assegnazione aperta fino al giorno precedente
        $closeFine = $data_inizio;
        $d = new DateTime($closeFine);
        $d->modify('-1 day');
        $closeFine = $d->format('Y-m-d');

        $stmt = $pdo->prepare("UPDATE assignments SET data_fine = :fine WHERE slot_id = :sid AND data_fine IS NULL");
        $stmt->execute([':fine'=>$closeFine, ':sid'=>$slot_id]);

        // inserisci nuova assegnazione
        $stmt = $pdo->prepare("INSERT INTO assignments
          (slot_id, stato, proprietario, targa, email, telefono, data_inizio, data_fine, created_by)
          VALUES (:sid,:stato,:prop,:targa,:email,:tel,:di,:df,:uid)");
        $stmt->execute([
          ':sid'=>$slot_id,
          ':stato'=>$stato,
          ':prop'=>$proprietario,
          ':targa'=>$targa,
          ':email'=>$email,
          ':tel'=>$telefono,
          ':di'=>$data_inizio,
          ':df'=>$data_fine ?: null,
          ':uid'=>current_user_id()
        ]);

        // aggiorna stato slot
        $stmt = $pdo->prepare("UPDATE slots SET stato=:st, updated_at=NOW() WHERE id=:id");
        $stmt->execute([':st'=>$stato, ':id'=>$slot_id]);

        log_event('assignment', (int)$pdo->lastInsertId(), 'create', ['slot_id'=>$slot_id,'stato'=>$stato]);

        $pdo->commit();
        set_flash('success', 'Nuova assegnazione inserita correttamente.');
        header('Location: /app/slots/view.php?id='.$slot_id); exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Errore: '.$e->getMessage();
      }
    }
  }
}

$title = 'Nuova assegnazione — Posto '.$slot['numero_esterno'].' ('.$marina['name'].')';
$active = 'slots_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>
<div class="container py-4" style="max-width: 760px;">
  <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $er) echo '<div>'.e($er).'</div>'; ?></div><?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Nuova assegnazione</h1>
      <p class="small-muted mb-3">
        Verrà chiusa automaticamente l’eventuale assegnazione precedente e lo stato del posto sarà aggiornato.
      </p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
        <input type="hidden" name="slot_id" value="<?= (int)$slot_id ?>">

        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Pontile</label>
            <input class="form-control" value="<?= e($marina['name']) ?>" disabled>
          </div>
          <div class="col-md-4">
            <label class="form-label">Numero posto</label>
            <input class="form-control" value="<?= e($slot['numero_esterno']) ?>" disabled>
          </div>
          <div class="col-md-4">
            <label class="form-label">Stato</label>
            <select name="stato" class="form-select">
              <?php foreach (['Occupato','Libero','Riservato','Manutenzione'] as $s): ?>
                <option value="<?= $s ?>" <?= $s==='Occupato'?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row g-2 mt-2">
          <div class="col-md-6">
            <label class="form-label">Proprietario</label>
            <input type="text" name="proprietario" class="form-control" placeholder="Nome cognome / Ente">
          </div>
          <div class="col-md-6">
            <label class="form-label">Targa</label>
            <input type="text" name="targa" class="form-control" placeholder="Identificativo imbarcazione">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="email@esempio.it">
          </div>
          <div class="col-md-6">
            <label class="form-label">Telefono</label>
            <input type="text" name="telefono" class="form-control" placeholder="333...">
          </div>
        </div>

        <div class="row g-2 mt-2">
          <div class="col-md-6">
            <label class="form-label">Data inizio</label>
            <input type="text" name="data_inizio" class="form-control" value="<?= e(date('d/m/Y')) ?>" placeholder="gg/mm/aaaa" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Data fine</label>
            <input type="text" name="data_fine" class="form-control" placeholder="gg/mm/aaaa">
          </div>
        </div>

        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-primary">Salva assegnazione</button>
          <a class="btn btn-outline-secondary" href="/app/slots/view.php?id=<?= (int)$slot_id ?>">Annulla</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>