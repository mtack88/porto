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
        
        // Chiudi eventuale assegnazione precedente
        $stmt = $pdo->prepare("UPDATE assignments SET data_fine = ? WHERE slot_id = ? AND data_fine IS NULL");
        $stmt->execute([date('Y-m-d'), $slot_id]);
        
        // Crea nuova assegnazione
        $stmt = $pdo->prepare("
            INSERT INTO assignments 
            (slot_id, stato, proprietario, targa, email, telefono, data_inizio, data_fine, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $slot_id,
            $stato,
            $proprietario ?: null,
            $targa ?: null,
            $email ?: null,
            $telefono ?: null,
            $data_inizio,
            $data_fine,
            current_user_id()
        ]);
        
        // Aggiorna stato slot
        $stmt = $pdo->prepare("UPDATE slots SET stato = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$stato, $slot_id]);
        
        $pdo->commit();
        
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
                Assegna Posto <?php echo $slot['numero_esterno']; ?> 
                (<?php echo $marina['name']; ?>)
            </h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    Errore: <?php echo htmlspecialchars($error); ?>
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
                            <small class="text-muted">Lascia vuoto per stato "Libero"</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Targa/Matricola</label>
                                <input type="text" name="targa" class="form-control" 
                                       placeholder="Es: BA123XY">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefono</label>
                                <input type="tel" name="telefono" class="form-control" 
                                       placeholder="333 1234567">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="email@esempio.it">
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