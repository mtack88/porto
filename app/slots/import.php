<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$title = 'Import CSV';
$active = 'import';

// Azione conferma import
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_import'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error','Token CSRF non valido.');
    header('Location: /app/slots/import.php'); exit;
  }
  $rows = $_SESSION['import_preview'] ?? [];
  if (!$rows) { set_flash('error','Nessun dato da importare.'); header('Location: /app/slots/import.php'); exit; }

  $okCount = 0; $err = [];
  try{
    $pdo->beginTransaction();
    foreach ($rows as $i=>$r) {
      try {
        // Mapping colonne
        $tipo_record = strtolower(trim($r['tipo_record']));
        $pontile = strtolower(trim($r['pontile']));
        $numero_posto = (int)$r['numero_posto'];
        $numero_interno = trim($r['numero_interno'] ?? '');
        $tipo = trim($r['tipo'] ?? '');
        $stato = trim($r['stato'] ?? 'Libero');
        $proprietario = trim($r['proprietario'] ?? '');
        $targa = trim($r['targa'] ?? '');
        $email = trim($r['email'] ?? '');
        $telefono = trim($r['telefono'] ?? '');
        $data_inizio = parse_date_to_ymd(trim($r['data_inizio'] ?? '')) ?: date('Y-m-d');
        $data_fine = parse_date_to_ymd(trim($r['data_fine'] ?? ''));
        $note = trim($r['note'] ?? '');

        $code = match($pontile){
          'bola'=>'BOLA',
          'ritter'=>'RITTER',
          'rastrelliera'=>'RAST',
          default => throw new Exception("Pontile non valido: ".$r['pontile'])
        };
        $marina = get_marina_by_code($code);
        if (!$marina) throw new Exception("Marina inesistente: ".$code);

        // trova/crea slot
        $stmt = $pdo->prepare("SELECT * FROM slots WHERE marina_id=:mid AND numero_esterno=:num LIMIT 1");
        $stmt->execute([':mid'=>$marina['id'], ':num'=>$numero_posto]);
        $slot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$slot) {
          $stmt = $pdo->prepare("INSERT INTO slots (marina_id, numero_esterno, numero_interno, tipo, stato, note, created_at) VALUES (:mid,:num,:ni,:tipo,:stato,:note,NOW())");
          $t = $code==='RAST' ? null : ($tipo ?: 'carrello');
          $stmt->execute([':mid'=>$marina['id'], ':num'=>$numero_posto, ':ni'=>$numero_interno ?: null, ':tipo'=>$t, ':stato'=>$stato, ':note'=>$note ?: null]);
          $slotId = (int)$pdo->lastInsertId();
        } else {
          $slotId = (int)$slot['id'];
          $t = $code==='RAST' ? null : ($tipo ?: ($slot['tipo'] ?: 'carrello'));
          $stmt = $pdo->prepare("UPDATE slots SET numero_interno=:ni, tipo=:tipo, stato=:stato, note=:note, updated_at=NOW() WHERE id=:id");
          $stmt->execute([':ni'=>$numero_interno ?: null, ':tipo'=>$t, ':stato'=>$stato, ':note'=>$note ?: null, ':id'=>$slotId]);
        }

        // chiudi eventuale assegnazione aperta
        $stmt = $pdo->prepare("UPDATE assignments SET data_fine = :fine WHERE slot_id=:sid AND data_fine IS NULL");
        // close fine = giorno precedente a data_inizio
        $closeFine = $data_inizio;
        $d = new DateTime($closeFine); $d->modify('-1 day'); $closeFine = $d->format('Y-m-d');
        $stmt->execute([':fine'=>$closeFine, ':sid'=>$slotId]);

        // crea nuova assegnazione
        $stmt = $pdo->prepare("INSERT INTO assignments (slot_id, stato, proprietario, targa, email, telefono, data_inizio, data_fine, created_by)
          VALUES (:sid,:stato,:prop,:targa,:email,:tel,:di,:df,:uid)");
        $stmt->execute([
          ':sid'=>$slotId, ':stato'=>$stato, ':prop'=>$proprietario, ':targa'=>$targa, ':email'=>$email, ':tel'=>$telefono,
          ':di'=>$data_inizio, ':df'=>$data_fine ?: null, ':uid'=>current_user_id()
        ]);

        $okCount++;
      } catch (Throwable $ie) {
        $err[] = "Riga ".($i+1).": ".$ie->getMessage();
      }
    }
    log_event('import', 0, 'import', ['rows'=>$okCount]);
    $pdo->commit();
    unset($_SESSION['import_preview']);
    set_flash('success', "Import completato: $okCount righe ok. ".(count($err)?'Errori: '.count($err):''));
    if ($err) set_flash('error', implode("\n",$err));
    header('Location: /app/slots/list.php'); exit;
  }catch(Throwable $e){
    $pdo->rollBack();
    set_flash('error','Errore import: '.$e->getMessage());
    header('Location: /app/slots/import.php'); exit;
  }
}

// Anteprima
$preview = [];
$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['preview'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Token CSRF non valido.';
  } elseif (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Caricamento file non riuscito.';
  } else {
    $tmp = $_FILES['csv']['tmp_name'];
    $fh = fopen($tmp, 'r');
    if (!$fh) $errors[] = 'Impossibile leggere il file.';
    else {
      $header = fgetcsv($fh, 0, ';');
      $expected = ['tipo_record','pontile','numero_posto','numero_interno','tipo','stato','proprietario','targa','email','telefono','data_inizio','data_fine','note'];
      if (!$header || array_map('trim',$header)!==$expected) {
        $errors[] = 'Intestazione CSV non valida. Attesa: '.implode(';',$expected);
      } else {
        $count = 0;
        while (($row = fgetcsv($fh, 0, ';')) !== false) {
          $count++;
          $assoc = array_combine($expected, $row);
          $preview[] = $assoc;
          if ($count >= 2000) break; // safety
        }
      }
      fclose($fh);
    }
  }
  if (!$errors) {
    $_SESSION['import_preview'] = $preview;
    set_flash('success', 'Anteprima pronta: '.count($preview).' righe.');
    header('Location: /app/slots/import.php'); exit;
  }
}

$title = 'Import CSV';
$active = 'import';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>
<div class="container py-4">
  <h1 class="h4 mb-3">Import da CSV</h1>

  <?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success"><?= nl2br(e($msg)) ?></div>
  <?php endif; ?>
  <?php if ($msg = get_flash('error')): ?>
    <div class="alert alert-danger" style="white-space:pre-wrap;"><?= e($msg) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $er) echo '<div>'.e($er).'</div>';?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
        <div class="mb-2">
          <label class="form-label">Seleziona file CSV</label>
          <input type="file" name="csv" class="form-control" accept=".csv" required>
        </div>
        <div class="small-muted">Formato atteso (separatore ;):<br>
          <code>tipo_record;pontile;numero_posto;numero_interno;tipo;stato;proprietario;targa;email;telefono;data_inizio;data_fine;note</code>
        </div>
        <div class="mt-3">
          <button name="preview" class="btn btn-primary">Carica anteprima</button>
          <a href="/example_import.csv" class="btn btn-outline-secondary">Scarica template d'esempio</a>
        </div>
      </form>
    </div>
  </div>

  <?php $pv = $_SESSION['import_preview'] ?? []; if ($pv): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title">Anteprima (prime <?= min(20,count($pv)) ?> di <?= count($pv) ?> righe)</h5>
        <div class="table-responsive" style="max-height:360px;">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <?php foreach (array_keys($pv[0]) as $k): ?><th><?= e($k) ?></th><?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($pv,0,20) as $r): ?>
                <tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <form method="post" class="mt-3">
          <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
          <button class="btn btn-success" name="do_import" value="1" data-confirm="Confermi l'importazione?">Conferma import</button>
          <a class="btn btn-outline-secondary" href="/app/slots/import.php" data-confirm="Annullare l'anteprima?">Annulla</a>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>