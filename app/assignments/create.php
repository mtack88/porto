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

// Trova l'assegnazione attuale (quella senza data_fine o con data 0000-00-00)
$stmt = $pdo->prepare("
    SELECT * FROM assignments 
    WHERE slot_id = :sid 
    AND (data_fine IS NULL OR data_fine = '0000-00-00' OR data_fine = '')
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
    $data_fine = $_POST['data_fine'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // Se c'è un'assegnazione corrente, chiudila il giorno prima
        if ($current_assignment) {
            // Calcola data chiusura (giorno prima della nuova)
            $data_chiusura = new DateTime($data_inizio);
            $data_chiusura->modify('-1 day');
            
            $stmt = $pdo->prepare("
                UPDATE assignments 
                SET data_fine = :df 
                WHERE id = :id
            ");
            $stmt->execute([
                ':df' => $data_chiusura->format('Y-m-d'),
                ':id' => $current_assignment['id']
            ]);
            
            // Debug
            error_log("Chiusa assegnazione ID " . $current_assignment['id'] . " con data_fine: " . $data_chiusura->format('Y-m-d'));
        }
        
        // Chiudi TUTTE le altre assegnazioni ancora aperte
        $data_chiusura_altre = new DateTime($data_inizio);
        $data_chiusura_altre->modify('-1 day');
        
        $stmt = $pdo->prepare("
            UPDATE assignments 
            SET data_fine = :df 
            WHERE slot_id = :sid 
            AND (data_fine IS NULL OR data_fine = '0000-00-00' OR data_fine = '')
            AND id != :exclude_id
        ");
        $stmt->execute([
            ':df' => $data_chiusura_altre->format('Y-m-d'),
            ':sid' => $slot_id,
            ':exclude_id' => $current_assignment ? $current_assignment['id'] : 0
        ]);
        
        // Crea nuova assegnazione
        $stmt = $pdo->prepare("
            INSERT INTO assignments 
            (slot_id, stato, proprietario, targa, email, telefono, data_inizio, data_fine, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Se data_fine è vuota, usa NULL o '0000-00-00' a seconda del database
        $data_fine_value = $data_fine ?: null;
        
        $stmt->execute([
            $slot_id,
            $stato,
            $proprietario ?: null,
            $targa ?: null,
            $email ?: null,
            $telefono ?: null,
            $data_inizio,
            $data_fine_value,
            current_user_id()
        ]);
        
        $new_assignment_id = (int)$pdo->lastInsertId();
        
        // Aggiorna stato slot
        $stmt = $pdo->prepare("UPDATE slots SET stato = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$stato, $slot_id]);
        
        // Log
        log_event('assignment', $new_assignment_id, 'create', [
            'slot_id' => $slot_id,
            'stato' => $stato,
            'proprietario' => $proprietario,
            'chiusa_precedente' => $current_assignment ? $current_assignment['id'] : null
        ]);
        
        $pdo->commit();
        
        // Messaggio di successo
        if ($current_assignment && !empty($current_assignment['proprietario'])) {
            set_flash('success', sprintf(
                'Assegnazione cambiata. %s termina il %s, %s inizia il %s',
                $current_assignment['proprietario'],
                format_date_from_ymd($data_chiusura->format('Y-m-d')),
                $proprietario ?: 'Nuova assegnazione',
                format_date_from_ymd($data_inizio)
            ));
        } else {
            set_flash('success', 'Assegnazione creata con successo');
        }
        
        header('Location: /app/slots/view.php?id=' . $slot_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
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
                <?php if ($current_assignment): ?>
                    Cambia assegnazione - Posto <?php echo $slot['numero_esterno']; ?>
                <?php else: ?>
                    Assegna Posto <?php echo $slot['numero_esterno']; ?>
                <?php endif; ?>
                (<?php echo $marina['name']; ?>)
            </h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    Errore: <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($current_assignment && !empty($current_assignment['proprietario'])): ?>
                <div class="alert alert-info">
                    <strong>⚠️ Assegnazione attuale:</strong><br>
                    Proprietario: <strong><?php echo htmlspecialchars($current_assignment['proprietario']); ?></strong><br>
                    Dal: <?php echo format_date_from_ymd($current_assignment['data_inizio']); ?><br>
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
                                   placeholder="Nome e cognome" 
                                   value="<?php echo htmlspecialchars($_POST['proprietario'] ?? ''); ?>">
                            <small class="text-muted">Lascia vuoto per stato "Libero"</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Targa/Matricola</label>
                                <input type="text" name="targa" class="form-control" 
                                       placeholder="Es: BA123XY"
                                       value="<?php echo htmlspecialchars($_POST['targa'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefono</label>
                                <input type="tel" name="telefono" class="form-control" 
                                       placeholder="333 1234567"
                                       value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="email@esempio.it"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data inizio *</label>
                                <input type="date" name="data_inizio" class="form-control" 
                                       value="<?php echo $_POST['data_inizio'] ?? date('Y-m-d'); ?>" 
                                       required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data fine</label>
                                <input type="date" name="data_fine" class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['data_fine'] ?? ''); ?>">
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