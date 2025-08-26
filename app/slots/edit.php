<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /app/slots/list.php');
    exit;
}

$slot = get_slot_by_id($id);
if (!$slot) {
    header('Location: /app/slots/list.php');
    exit;
}

$marina = get_marina_by_id((int)$slot['marina_id']);
if (!$marina) {
    header('Location: /app/slots/list.php');
    exit;
}

// Gestione eliminazione
if (isset($_GET['delete']) && $_GET['delete'] === '1') {
    $stmt = $pdo->prepare("UPDATE slots SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    log_event('slot', $id, 'delete', ['soft' => true]);
    header('Location: /app/slots/list.php');
    exit;
}

// Gestione ripristino
if (isset($_GET['restore']) && $_GET['restore'] === '1') {
    $stmt = $pdo->prepare("UPDATE slots SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$id]);
    log_event('slot', $id, 'restore', []);
    header('Location: /app/slots/list.php?cestino=1');
    exit;
}

// Gestione form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_interno = trim($_POST['numero_interno'] ?? '');
    $tipo = $_POST['tipo'] ?? null;
    $note = trim($_POST['note'] ?? '');
    
    // Per rastrelliera, ignora il tipo
    if ($marina['code'] === 'RAST') {
        $tipo = null;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE slots 
            SET numero_interno = ?, tipo = ?, note = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([
            $numero_interno ?: null,
            $tipo,
            $note ?: null,
            $id
        ]);
        
        log_event('slot', $id, 'update', [
            'numero_interno' => $numero_interno,
            'tipo' => $tipo,
            'note' => $note
        ]);
        
        header('Location: /app/slots/view.php?id=' . $id);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$title = 'Modifica posto ' . $slot['numero_esterno'] . ' - ' . $marina['name'];
$active = 'slots_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            
            <h1 class="h4 mb-3">
                Modifica posto <?php echo $slot['numero_esterno']; ?>
            </h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    Errore: <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <strong>Modifica informazioni posto</strong>
                </div>
                
                <div class="card-body">
                    <form method="POST" action="">
                        
                        <!-- Info non modificabili -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Pontile</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo $marina['name']; ?>" 
                                       readonly disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Numero posto</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo $slot['numero_esterno']; ?>" 
                                       readonly disabled>
                            </div>
                        </div>
                        
                        <!-- Campi modificabili -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="numero_interno" class="form-label">Numero interno</label>
                                <input type="text" 
                                       name="numero_interno" 
                                       id="numero_interno" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($slot['numero_interno'] ?? ''); ?>"
                                       placeholder="Codice interno opzionale">
                            </div>
                            
                            <?php if ($marina['code'] !== 'RAST'): ?>
                            <div class="col-md-6">
                                <label for="tipo" class="form-label">Tipo</label>
                                <select name="tipo" id="tipo" class="form-select">
                                    <option value="">— Seleziona —</option>
                                    <option value="carrello" 
                                            <?php echo ($slot['tipo'] ?? '') === 'carrello' ? 'selected' : ''; ?>>
                                        Carrello
                                    </option>
                                    <option value="fune" 
                                            <?php echo ($slot['tipo'] ?? '') === 'fune' ? 'selected' : ''; ?>>
                                        Fune
                                    </option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="note" class="form-label">Note</label>
                            <textarea name="note" 
                                      id="note" 
                                      class="form-control" 
                                      rows="4"
                                      placeholder="Eventuali annotazioni..."><?php echo htmlspecialchars($slot['note'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                Salva modifiche
                            </button>
                            <a href="/app/slots/view.php?id=<?php echo $id; ?>" 
                               class="btn btn-outline-secondary">
                                Annulla
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Stato attuale: <?php echo status_badge($slot['stato']); ?>
                        </small>
                        <?php if (!$slot['deleted_at']): ?>
                        <a href="?id=<?php echo $id; ?>&delete=1" 
                           class="btn btn-outline-danger btn-sm"
                           onclick="return confirm('Eliminare questo posto?')">
                            Elimina posto
                        </a>
                        <?php else: ?>
                        <a href="?id=<?php echo $id; ?>&restore=1" 
                           class="btn btn-success btn-sm"
                           onclick="return confirm('Ripristinare questo posto?')">
                            Ripristina
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>