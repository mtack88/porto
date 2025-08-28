<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$slot_id = (int)($_GET['slot_id'] ?? $_POST['slot_id'] ?? 0);
if ($slot_id <= 0) {
    header('Location: /app/slots/list.php');
    exit;
}

// Carica posto
$slot = get_slot_by_id($slot_id);
if (!$slot) {
    header('Location: /app/slots/list.php');
    exit;
}

// Carica marina
$marina = get_marina_by_id((int)$slot['marina_id']);

// Trova l'assegnazione corrente - LOGICA AGGIORNATA:
// Prendi l'ultima assegnazione che NON è "Libero"
// indipendentemente dalla data_fine
$stmt = $pdo->prepare("
    SELECT * FROM assignments 
    WHERE slot_id = :sid 
    AND stato != 'Libero'
    ORDER BY id DESC 
    LIMIT 1
");
$stmt->execute([':sid' => $slot_id]);
$current_assignment = $stmt->fetch(PDO::FETCH_ASSOC);

// Se c'è POST, processa il form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stato = $_POST['stato'] ?? 'Occupato';
    $proprietario = trim($_POST['proprietario'] ?? '');
    $targa = trim($_POST['targa'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $data_inizio = $_POST['data_inizio'] ?? date('Y-m-d');
    $data_fine = $_POST['data_fine'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Se c'è un'assegnazione corrente NON libera
        if ($current_assignment) {
            // Calcola data chiusura (giorno prima della nuova)
            $data_chiusura = new DateTime($data_inizio);
            $data_chiusura->modify('-1 day');
            $data_chiusura_str = $data_chiusura->format('Y-m-d');
            
            // Aggiorna data_fine solo se:
            // 1. È vuota (0000-00-00 o NULL)
            // 2. O se è posteriore alla nuova data di chiusura
            $current_data_fine = $current_assignment['data_fine'] ?? '0000-00-00';
            
            if ($current_data_fine == '0000-00-00' || 
                $current_data_fine == '' || 
                $current_data_fine === null ||
                $current_data_fine > $data_chiusura_str) {
                
                $stmt = $pdo->prepare("
                    UPDATE assignments 
                    SET data_fine = :df 
                    WHERE id = :id
                ");
                $result = $stmt->execute([
                    ':df' => $data_chiusura_str,
                    ':id' => $current_assignment['id']
                ]);
                
                // Debug
                if (!$result) {
                    throw new Exception("Impossibile aggiornare data_fine per assignment " . $current_assignment['id']);
                }
            }
        }
        
        // Crea nuova assegnazione
        $stmt = $pdo->prepare("
            INSERT INTO assignments 
            (slot_id, stato, proprietario, targa, email, telefono, data_inizio, data_fine, created_by, created_at)
            VALUES (:sid, :stato, :prop, :targa, :email, :tel, :di, :df, :uid, NOW())
        ");
        
        // Gestisci data_fine vuota
        $data_fine_insert = (!empty($data_fine)) ? $data_fine : null;
        
        $stmt->execute([
            ':sid' => $slot_id,
            ':stato' => $stato,
            ':prop' => $proprietario ?: null,
            ':targa' => $targa ?: null,
            ':email' => $email ?: null,
            ':tel' => $telefono ?: null,
            ':di' => $data_inizio,
            ':df' => $data_fine_insert,
            ':uid' => current_user_id()
        ]);
        
        $new_assignment_id = (int)$pdo->lastInsertId();
        
        // Aggiorna stato slot
        $stmt = $pdo->prepare("UPDATE slots SET stato = :stato, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':stato' => $stato, ':id' => $slot_id]);
        
        // Log
        log_event('assignment', $new_assignment_id, 'create', [
            'slot_id' => $slot_id,
            'stato' => $stato,
            'proprietario' => $proprietario,
            'chiusa_precedente' => $current_assignment ? $current_assignment['id'] : null
        ]);
        
        $pdo->commit();
        
        // Messaggio di successo
        $msg = 'Assegnazione creata con successo';
        if ($current_assignment && !empty($current_assignment['proprietario'])) {
            $msg = sprintf(
                'Assegnazione cambiata. %s termina il %s, %s inizia il %s',
                $current_assignment['proprietario'],
                format_date_from_ymd($data_chiusura_str),
                $proprietario ?: 'Nuova assegnazione',
                format_date_from_ymd($data_inizio)
            );
        }
        set_flash('success', $msg);
        
        header('Location: /app/slots/view.php?id=' . $slot_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
        set_flash('error', 'Errore: ' . $error);
    }
}

$title = 'Assegna posto ' . $slot['numero_esterno'];
$active = 'slots_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            
            <h1 class="h4 mb-3">
                <?php if ($current_assignment && !empty($current_assignment['proprietario'])): ?>
                    Cambia assegnazione - Posto <?php echo $slot['numero_esterno']; ?>
                <?php else: ?>
                    Assegna Posto <?php echo $slot['numero_esterno']; ?>
                <?php endif; ?>
                (<?php echo $marina['name']; ?>)
            </h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($msg = get_flash('error')): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            
            <?php if ($current_assignment && !empty($current_assignment['proprietario'])): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Assegnazione attuale:</strong><br>
                    Proprietario: <strong><?php echo htmlspecialchars($current_assignment['proprietario']); ?></strong><br>
                    <?php if (!empty($current_assignment['targa'])): ?>
                    Targa: <?php echo htmlspecialchars($current_assignment['targa']); ?><br>
                    <?php endif; ?>
                    Dal: <?php echo format_date_from_ymd($current_assignment['data_inizio']); ?><br>
                    <?php 
                    $current_fine = $current_assignment['data_fine'] ?? '0000-00-00';
                    if ($current_fine && $current_fine != '0000-00-00'): ?>
                    Al: <?php echo format_date_from_ymd($current_fine); ?><br>
                    <?php endif; ?>
                    <hr>
                    <small>✓ Questa assegnazione terminerà automaticamente il giorno prima della nuova data di inizio</small>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-success text-white">
                    <strong>Nuova Assegnazione</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="slot_id" value="<?php echo $slot_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Stato *</label>
                            <select name="stato" class="form-select" required>
                                <option value="Occupato">Occupato (assegnato a qualcuno)</option>
                                <option value="Riservato">Riservato</option>
                                <option value="Libero">Libero</option>
                                <option value="Manutenzione">Manutenzione</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Proprietario/Assegnatario</label>
                            <input type="text" name="proprietario" class="form-control" 
                                   placeholder="Nome e cognome">
                            <small class="text-muted">Obbligatorio per stato "Occupato"</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Targa/Matricola</label>
                                <input type="text" name="targa" class="form-control" 
                                       placeholder="Es: TI 12345">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefono</label>
                                <input type="tel" name="telefono" class="form-control" 
                                       placeholder="+41 79 123 45 67">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="email@esempio.ch">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data inizio *</label>
                                <input type="date" name="data_inizio" class="form-control" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data fine</label>
                                <input type="date" name="data_fine" class="form-control">
                                <small class="text-muted">Lascia vuoto se indeterminata</small>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">
                                <strong>CONFERMA ASSEGNAZIONE</strong>
                            </button>
                            <a href="/app/slots/view.php?id=<?php echo $slot_id; ?>" 
                               class="btn btn-outline-secondary">
                                Annulla
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>