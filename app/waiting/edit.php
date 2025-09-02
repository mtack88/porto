<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /app/waiting/list.php');
    exit;
}

// Carica record
$stmt = $pdo->prepare("SELECT * FROM waiting_list WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    set_flash('error', 'Record non trovato');
    header('Location: /app/waiting/list.php');
    exit;
}

// Gestione eliminazione
if (isset($_GET['delete']) && $_GET['delete'] === '1') {
    $stmt = $pdo->prepare("DELETE FROM waiting_list WHERE id = ?");
    $stmt->execute([$id]);
    set_flash('success', 'Iscrizione eliminata');
    header('Location: /app/waiting/list.php');
    exit;
}

// Gestione toggle attivo
if (isset($_GET['toggle_active'])) {
    $new_status = $record['attivo'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE waiting_list SET attivo = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $id]);
    set_flash('success', $new_status ? 'Iscrizione riattivata' : 'Iscrizione disattivata');
    header('Location: /app/waiting/edit.php?id=' . $id);
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prendi i dati dal form
    $cognome = trim($_POST['cognome'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $luogo = trim($_POST['luogo'] ?? '');
    $via = trim($_POST['via'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $motore_kw = trim($_POST['motore_kw'] ?? '');
    $dimensioni = trim($_POST['dimensioni'] ?? '');
    $targa = trim($_POST['targa'] ?? '');
    $osservazioni = trim($_POST['osservazioni'] ?? '');
    
    // Validazione
    if (!$cognome) $errors[] = 'Cognome obbligatorio';
    if (!$nome) $errors[] = 'Nome obbligatorio';
    if (!$luogo) $errors[] = 'Luogo obbligatorio';
    if (!$via) $errors[] = 'Via obbligatoria';
    if (!$telefono) $errors[] = 'Telefono obbligatorio';
    if (!$email) $errors[] = 'Email obbligatoria';

    if ($record['tipologia'] === 'Barca' && $motore_kw !== '') {
    $kw_value = floatval($motore_kw);
    if ($kw_value > 6) {
        $errors[] = 'Potenza motore massima consentita: 6 KW';
    }
    if ($kw_value < 0) {
        $errors[] = 'La potenza del motore non può essere negativa';
    }
    }
    
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE waiting_list SET 
                    cognome = ?, nome = ?, luogo = ?, via = ?,
                    telefono = ?, email = ?, motore_kw = ?,
                    dimensioni = ?, targa = ?, osservazioni = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $cognome, $nome, $luogo, $via, $telefono, $email,
                $motore_kw ?: null, $dimensioni ?: null, $targa ?: null,
                $osservazioni ?: null, $id
            ]);
            
            set_flash('success', 'Iscrizione aggiornata con successo');
            header('Location: /app/waiting/list.php');
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Errore: ' . $e->getMessage();
        }
    }
} else {
    // Popola i campi con i dati esistenti
    $cognome = $record['cognome'];
    $nome = $record['nome'];
    $luogo = $record['luogo'];
    $via = $record['via'];
    $telefono = $record['telefono'];
    $email = $record['email'];
    $motore_kw = $record['motore_kw'];
    $dimensioni = $record['dimensioni'];
    $targa = $record['targa'];
    $osservazioni = $record['osservazioni'];
}

$title = 'Modifica iscrizione';
$active = 'waiting_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <h1 class="h4 mb-3">
                Modifica iscrizione - <?php echo htmlspecialchars($record['cognome'] . ' ' . $record['nome']); ?>
            </h1>
            
            <?php if ($msg = get_flash('success')): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Info stato -->
            <div class="alert alert-info">
                <strong>Informazioni iscrizione:</strong><br>
                Tipologia: <strong><?php echo $record['tipologia']; ?></strong><br>
                Data iscrizione: <?php echo format_date_from_ymd($record['data_iscrizione']); ?><br>
                Ultima verifica: <?php echo $record['ultima_verifica'] ? format_date_from_ymd($record['ultima_verifica']) : 'Mai'; ?><br>
                Stato: <?php echo $record['attivo'] ? '<span class="badge bg-success">Attivo</span>' : '<span class="badge bg-secondary">Non attivo</span>'; ?>
            </div>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <strong>Modifica dati</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        
                        <!-- Dati personali -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cognome *</label>
                                <input type="text" name="cognome" class="form-control" required
                                       value="<?php echo htmlspecialchars($cognome); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" required
                                       value="<?php echo htmlspecialchars($nome); ?>">
                            </div>
                        </div>
                        
                        <!-- Indirizzo -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Luogo *</label>
                                <input type="text" name="luogo" class="form-control" required
                                       value="<?php echo htmlspecialchars($luogo); ?>">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Via *</label>
                                <input type="text" name="via" class="form-control" required
                                       value="<?php echo htmlspecialchars($via); ?>">
                            </div>
                        </div>
                        
                        <!-- Contatti -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefono *</label>
                                <input type="tel" name="telefono" class="form-control" required
                                       value="<?php echo htmlspecialchars($telefono); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                        </div>
                        
                        <?php if ($record['tipologia'] === 'Barca'): ?>
                        <!-- Info barca -->
                        <h6 class="mb-3">Informazioni imbarcazione</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Motore (KW)</label>
                                <input type="number" 
                                    name="motore_kw" 
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($motore_kw ?? ''); ?>"
                                    min="0"
                                    max="6"
                                    step="0.1"
                                    title="Massimo 6 KW">
                                <small class="text-muted">Max 6 KW consentiti</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Dimensioni</label>
                                <input type="text" name="dimensioni" class="form-control"
                                       value="<?php echo htmlspecialchars($dimensioni ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Targa</label>
                                <input type="text" name="targa" class="form-control"
                                       value="<?php echo htmlspecialchars($targa ?? ''); ?>">
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Info canoa -->
                        <div class="mb-3">
                            <label class="form-label">Dimensioni</label>
                            <input type="text" name="dimensioni" class="form-control"
                                   value="<?php echo htmlspecialchars($dimensioni ?? ''); ?>">
                        </div>
                        <?php endif; ?>
                        
                        <!-- Osservazioni -->
                        <div class="mb-3">
                            <label class="form-label">Osservazioni</label>
                            <textarea name="osservazioni" class="form-control" rows="3"><?php echo htmlspecialchars($osservazioni ?? ''); ?></textarea>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- PULSANTI SALVA/ANNULLA -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <strong>💾 SALVA MODIFICHE</strong>
                            </button>
                            <a href="/app/waiting/list.php" class="btn btn-outline-secondary">
                                ← Annulla e torna alla lista
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Footer con azioni pericolose -->
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <div>
                            <?php if ($record['attivo']): ?>
                                <a href="?id=<?php echo $id; ?>&toggle_active=1" 
                                   class="btn btn-warning btn-sm"
                                   onclick="return confirm('Disattivare questa iscrizione?')">
                                    ⚠️ Disattiva
                                </a>
                            <?php else: ?>
                                <a href="?id=<?php echo $id; ?>&toggle_active=1" 
                                   class="btn btn-success btn-sm">
                                    ✓ Riattiva
                                </a>
                            <?php endif; ?>
                        </div>
                        <a href="?id=<?php echo $id; ?>&delete=1" 
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('⚠️ ATTENZIONE!\n\nEliminare definitivamente questa iscrizione?\nQuesta azione non può essere annullata.')">
                            🗑️ Elimina definitivamente
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- SEZIONE ALLEGATI (dopo la card principale, prima del footer) -->
            <div class="card mt-3">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>📎 Allegati</strong>
                        <a href="/app/attachments/upload_form.php?entity_type=waiting_list&entity_id=<?php echo $id; ?>" 
                           class="btn btn-sm btn-success">
                            + Aggiungi allegato
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php 
                    // Query diretta per waiting_list
                    $stmt = $pdo->prepare("
                        SELECT * FROM attachments 
                        WHERE entity_type = ? AND entity_id = ?
                        ORDER BY uploaded_at DESC
                    ");
                    $stmt->execute(['waiting_list', $id]);
                    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Debug - rimuovi dopo aver verificato
                    echo "<!-- Debug: trovati " . count($attachments) . " allegati per waiting_list id=$id -->";
                    ?>
                    
                    <?php if (count($attachments) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($attachments as $att): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <?php 
                                    $icons = [
                                        'pdf' => '📄',
                                        'jpg' => '🖼️',
                                        'jpeg' => '🖼️',
                                        'png' => '🖼️',
                                        'doc' => '📝',
                                        'docx' => '📝',
                                        'xls' => '📊',
                                        'xlsx' => '📊'
                                    ];
                                    echo $icons[$att['file_type']] ?? '📎';
                                    ?>
                                    <a href="/app/attachments/download.php?id=<?php echo $att['id']; ?>" 
                                       target="_blank">
                                        <?php echo htmlspecialchars($att['original_name']); ?>
                                    </a>
                                    <small class="text-muted">
                                        (<?php 
                                        $bytes = (int)$att['file_size'];
                                        if ($bytes < 1024) echo $bytes . ' B';
                                        elseif ($bytes < 1048576) echo round($bytes / 1024, 1) . ' KB';
                                        else echo round($bytes / 1048576, 1) . ' MB';
                                        ?>)
                                    </small>
                                    <?php if ($att['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($att['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <a href="/app/attachments/delete.php?id=<?php echo $att['id']; ?>&return=waiting&return_id=<?php echo $id; ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Eliminare questo allegato?')">
                                    Elimina
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Nessun allegato presente</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pulsante per tornare alla lista -->
            <div class="mt-3 text-center">
                <a href="/app/waiting/list.php" class="btn btn-outline-primary">
                    ← Torna alla lista d'attesa
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Upload (FUORI da tutto) -->
<div class="modal fade" id="uploadModalWaiting" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/app/attachments/upload.php" enctype="multipart/form-data">
                <input type="hidden" name="entity_type" value="waiting_list">
                <input type="hidden" name="entity_id" value="<?php echo $id; ?>">
                <input type="hidden" name="return_url" value="/app/waiting/edit.php?id=<?php echo $id; ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Carica allegato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">File *</label>
                        <input type="file" name="attachment" class="form-control" required
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                        <small class="text-muted">
                            Formati: PDF, JPG, PNG, Word, Excel<br>
                            Dimensione massima: 10MB
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrizione (opzionale)</label>
                        <input type="text" name="description" class="form-control"
                               placeholder="es. Patente nautica, Assicurazione, Foto imbarcazione...">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Annulla
                    </button>
                    <button type="submit" class="btn btn-success">
                        Carica allegato
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validazione KW motore
    const motoreInput = document.querySelector('input[name="motore_kw"]');
    if (motoreInput) {
        motoreInput.addEventListener('input', function() {
            const value = parseFloat(this.value);
            if (value > 6) {
                this.value = 6;
                alert('Attenzione: Il massimo consentito è 6 KW');
            }
        });
        
        // Validazione al blur (quando l'utente esce dal campo)
        motoreInput.addEventListener('blur', function() {
            if (this.value !== '' && parseFloat(this.value) > 6) {
                this.value = 6;
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>