<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

// Ottieni TUTTI i marina (non solo porti)
$all_marinas = get_all_marinas();
$selected_code = $_GET['marina'] ?? $_POST['marina'] ?? 'BOLA';

// Verifica che il codice selezionato sia valido
$marina_codes = array_column($all_marinas, 'code');
if (!in_array($selected_code, $marina_codes)) {
    $selected_code = 'BOLA';
}

// Trova la marina selezionata
$selected_marina = null;
foreach ($all_marinas as $m) {
    if ($m['code'] === $selected_code) {
        $selected_marina = $m;
        break;
    }
}

// Calcola prossimo numero per ogni marina
$next_numbers = array();
foreach ($all_marinas as $m) {
    $next_numbers[$m['code']] = next_external_number((int)$m['id']);
}

$errors = array();
$success = false;

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Token di sicurezza non valido. Ricarica la pagina.';
    } else {
        $marina_code = $_POST['marina'] ?? '';
        $numero_esterno = (int)($_POST['numero_esterno'] ?? 0);
        $numero_interno = trim($_POST['numero_interno'] ?? '');
        $tipo = $_POST['tipo'] ?? 'carrello';
        $note = trim($_POST['note'] ?? '');
        
        // Trova marina
        $marina = get_marina_by_code($marina_code);
        
        if (!$marina) {
            $errors[] = 'Pontile/Marina non valido.';
        } else {
            // Se numero non specificato, usa il prossimo disponibile
            if ($numero_esterno <= 0) {
                $numero_esterno = next_external_number((int)$marina['id']);
            }
            
            // Per rastrelliera, ignora il tipo
            if ($marina['code'] === 'RAST') {
                $tipo = null;
            }
            
            try {
                // Verifica duplicati
                $check = $pdo->prepare("SELECT id FROM slots WHERE marina_id = ? AND numero_esterno = ? LIMIT 1");
                $check->execute(array($marina['id'], $numero_esterno));
                
                if ($check->fetch()) {
                    $errors[] = "Il posto numero $numero_esterno esiste già in " . $marina['name'];
                } else {
                    // Inizia transazione
                    $pdo->beginTransaction();
                    
                    // Inserisci slot
                    $insert_slot = $pdo->prepare("
                        INSERT INTO slots (marina_id, numero_esterno, numero_interno, tipo, stato, note, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 'Libero', ?, NOW(), NOW())
                    ");
                    
                    $insert_slot->execute(array(
                        $marina['id'],
                        $numero_esterno,
                        $numero_interno ?: null,
                        $tipo,
                        $note ?: null
                    ));
                    
                    $new_slot_id = (int)$pdo->lastInsertId();
                    
                    // Crea assegnazione iniziale
                    $insert_assignment = $pdo->prepare("
                        INSERT INTO assignments (slot_id, stato, data_inizio, created_by, created_at)
                        VALUES (?, 'Libero', CURDATE(), ?, NOW())
                    ");
                    
                    $insert_assignment->execute(array($new_slot_id, current_user_id()));
                    
                    // Log evento
                    log_event('slot', $new_slot_id, 'create', array(
                        'marina' => $marina['code'],
                        'numero' => $numero_esterno
                    ));
                    
                    $pdo->commit();
                    
                    set_flash('success', "Posto $numero_esterno aggiunto con successo in " . $marina['name']);
                    header('Location: /app/slots/view.php?id=' . $new_slot_id);
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Errore database: ' . $e->getMessage();
            }
        }
    }
}

$title = 'Aggiungi nuovo posto';
$active = 'slots_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Aggiungi nuovo posto</h5>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="/app/slots/create.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="marina" class="form-label">Pontile/Marina *</label>
                                <select name="marina" id="marina" class="form-select" required onchange="updateNextNumber()">
                                    <?php foreach ($all_marinas as $m): ?>
                                        <option value="<?php echo htmlspecialchars($m['code']); ?>"
                                                data-next="<?php echo $next_numbers[$m['code']]; ?>"
                                                data-kind="<?php echo htmlspecialchars($m['kind']); ?>"
                                                <?php echo ($selected_code === $m['code']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($m['name']); ?> 
                                            (<?php echo htmlspecialchars($m['kind']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="numero_esterno" class="form-label">Numero posto *</label>
                                <input type="number" 
                                       name="numero_esterno" 
                                       id="numero_esterno" 
                                       class="form-control" 
                                       min="1" 
                                       required
                                       placeholder="Lascia vuoto per il prossimo disponibile">
                                <small class="text-muted" id="next_hint">
                                    Prossimo disponibile: <?php echo $next_numbers[$selected_code]; ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="numero_interno" class="form-label">Numero interno (opzionale)</label>
                                <input type="text" 
                                       name="numero_interno" 
                                       id="numero_interno" 
                                       class="form-control"
                                       placeholder="Codice interno">
                            </div>
                            
                            <div class="col-md-6" id="tipo_container">
                                <label for="tipo" class="form-label">Tipo</label>
                                <select name="tipo" id="tipo" class="form-select">
                                    <option value="carrello">Carrello</option>
                                    <option value="fune">Fune</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="note" class="form-label">Note (opzionale)</label>
                            <textarea name="note" 
                                      id="note" 
                                      class="form-control" 
                                      rows="3"
                                      placeholder="Eventuali annotazioni..."></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <strong>Aggiungi posto</strong>
                            </button>
                            <a href="/app/slots/list.php" class="btn btn-outline-secondary">
                                Annulla
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer text-muted">
                    <small>Il posto verrà creato con stato iniziale "Libero"</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateNextNumber() {
    const select = document.getElementById('marina');
    const option = select.options[select.selectedIndex];
    const next = option.getAttribute('data-next');
    const kind = option.getAttribute('data-kind');
    const hint = document.getElementById('next_hint');
    const tipoContainer = document.getElementById('tipo_container');
    
    hint.textContent = 'Prossimo disponibile: ' + next;
    
    // Nascondi tipo per rastrelliera
    if (select.value === 'RAST') {
        tipoContainer.style.display = 'none';
    } else {
        tipoContainer.style.display = 'block';
    }
}

// Inizializza al caricamento
document.addEventListener('DOMContentLoaded', function() {
    updateNextNumber();
});
</script>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>