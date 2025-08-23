<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$slot = get_slot_by_id($id);
if (!$slot) { http_response_code(404); echo 'Posto non trovato'; exit; }
$marina = get_marina_by_id((int)$slot['marina_id']);

$errors = [];
if (isset($_GET['delete']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
  // Soft delete
  $stmt = $pdo->prepare("UPDATE slots SET deleted_at = NOW() WHERE id=:id AND deleted_at IS NULL");
  $stmt->execute([':id'=>$id]);
  log_event('slot',$id,'delete',['soft'=>true]);
  set_flash('success','Posto spostato nel cestino.');
  header('Location: /app/slots/list.php'); exit;
}
if (isset($_GET['restore']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $stmt = $pdo->prepare("UPDATE slots SET deleted_at = NULL WHERE id=:id AND deleted_at IS NOT NULL");
  $stmt->execute([':id'=>$id]);
  log_event('slot',$id,'restore',[]);
  set_flash('success','Posto ripristinato.');
  header('Location: /app/slots/list.php?cestino=1'); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $errors[] = 'Token CSRF non valido.'; }
  else {
    $numero_interno = trim($_POST['numero_interno'] ?? '');
    $tipo = $_POST['tipo'] ?? null;
    if ($marina['code']==='RAST') { $tipo = null; } // nessun tipo per rastrelliera
    $note = trim($_POST['note'] ?? '');
    try{
      $stmt = $pdo->prepare("UPDATE slots SET numero_interno=:ni, tipo=:tipo, note=:note, updated_at=NOW() WHERE id=:id");
      $stmt->execute([':ni'=>$numero_interno ?: null, ':tipo'=>$tipo ?: null, ':note'=>$note ?: null, ':id'=>$id]);
      log_event('slot',$id,'update',['numero_interno'=>$numero_interno,'tipo'=>$tipo]);
      set_flash('success','Posto aggiornato.');
      header('Location: /app/slots/view.php?id='.$id); exit;
    }catch(Throwable $e){ $errors[] = 'Errore: '.$e->getMessage(); }
  }
}

$title = 'Modifica posto '.$slot['numero_esterno'];
$active = 'slots_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>
<div class="container py-4" style="max-width:720px;">
  <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $er) echo '<div>'.e($er).'</div>';?></div><?php endif; ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h5 mb-3">Modifica posto — <?= e($marina['name']) ?> n. <?= e($slot['numero_esterno']) ?></h1>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Numero interno</label>
            <input type="text" name="numero_interno" class="form-control" value="<?= e($slot['numero_interno']) ?>">
          </div>
          <?php if ($marina['code']!=='RAST'): ?>
          <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
              <option value="">—</option>
              <option value="carrello" <?= $slot['tipo']==='carrello'?'selected':'' ?>>Carrello</option>
              <option value="fune" <?= $slot['tipo']==='fune'?'selected':'' ?>>Fune</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-12">
            <label class="form-label">Note</label>
            <textarea name="note" rows="3" class="form-control"><?= e($slot['note'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-primary">Salva</button>
          <a class="btn btn-outline-secondary" href="/app/slots/view.php?id=<?= (int)$slot['id'] ?>">Annulla</a>
        </div>
      </form>
    </div>
    <div class="card-footer d-flex justify-content-between">
      <div class="small-muted">Eliminazione con conferma (soft delete)</div>
      <?php if(!$slot['deleted_at']): ?>
        <a class="btn btn-outline-danger btn-sm" href="?id=<?= (int)$slot['id'] ?>&delete=1" data-confirm="Confermi l'eliminazione di questo posto?">Elimina</a>
      <?php else: ?>
        <a class="btn btn-outline-success btn-sm" href="?id=<?= (int)$slot['id'] ?>&restore=1" data-confirm="Ripristinare questo posto?">Ripristina</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>