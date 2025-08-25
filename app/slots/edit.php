<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', 'ID posto non valido');
    header('Location: /app/slots/list.php');
    exit;
}

$slot = get_slot_by_id($id);
if (!$slot) {
    set_flash('error', 'Posto non trovato');
    header('Location: /app/slots/list.php');
    exit;
}

$marina = get_marina_by_id((int)$slot['marina_id']);

// Gestione eliminazione
if (isset($_GET['delete']) && $_GET['delete'] === '1') {
    try {
        $stmt = $pdo->prepare("UPDATE slots SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute([':id' => $id]);
        
        log_event('slot', $id, 'delete', ['soft' => true]);
        set_flash('success', 'Posto spostato nel cestino');
        header('Location: /app/slots/list.php');
        exit;
    } catch (Exception $e) {
        set_flash('error', 'Errore durante l\'eliminazione: ' . $e->getMessage());
    }
}

// Gestione ripristino
if (isset($_GET['restore']) && $_GET['restore'] === '1') {
    try {
        $stmt = $pdo->prepare("UPDATE slots SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL");
        $stmt->execute([':id' => $id]);
        
        log_event('slot', $id, 'restore', []);
        set_flash('success', 'Posto ripristinato con successo');
        header('Location: /app/slots/list.php?cestino=1');
        exit;
    } catch (Exception $e) {
        set_flash('error', 'Errore durante il ripristino: ' . $e->getMessage());
    }
}

$errors = array();
$form_data = array(
    'numero_interno' => $slot['numero_interno'] ?? '',
    'tipo' => $slot['tipo'] ?? '',
    'note' => $slot['note'] ?? ''
);

// Gestione POST per modifica
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token di sicurezza non valido. Ricarica la pagina.';
    } else {
        $form_data = array(
            'numero_interno' => trim($_POST['numero_interno'] ?? ''),
            'tipo' => $_POST['tipo'] ?? null,
            'note' => trim($_POST['note'] ?? '')
        );
        
        // Per rastrelliera, ignora il tipo
        if ($marina['code'] === 'RAST') {
            $form_data['tipo'] = null;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE slots 
                SET numero_interno = :ni, tipo = :tipo, note = :note, updated_at = NOW() 
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':ni' => $form_data['numero_interno'] ?: null,
                ':tipo' => $form_data['tipo'],
                ':note' => $form_data['note'] ?: null,
                ':id' => $id
            ]);
            
            log_event('slot', $id, 'update', $form_data);
            set_flash('success', 'Posto aggiornato con successo');
            header('Location: /app/slots/view.php?id=' . $id);
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Errore durante l\'aggiornamento: ' . $e->getMessage();
        }
    }
}

$title = 'Modifica posto ' . $slot['numero_esterno'] . ' — ' . $marina['name'];
$active = 'slots_list';

include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/app/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/app/slots/list.php">Posti</a></li>
                    <li class="breadcrumb-item">
                        <a href="/app/slots/view.php?id=<?php echo $id; ?>">
                            Posto <?php echo htmlspecialchars($slot['numero_esterno']); ?>
                        </a>
                    </li>
                    <li class="breadcrumb-item active">Modifica</li>
                </ol>
            </nav>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($msg = get_flash('error')): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        Modifica posto <?php echo htmlspecialchars($slot['numero_esterno']); ?>
                    </h5>
                </div>
                
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                        
                        <!-- Info non modificabili -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Pontile</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($marina['name']); ?>" 
                                       readonly disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Numero posto</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($slot['numero_esterno']); ?>" 
                                       readonly disabled>
                                <small class="text-muted">Il numero non può essere modificato</small>
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
                                       value="<?php echo htmlspecialchars($form_data['numero_interno']); ?>"
                                       placeholder="Codice interno opzionale">
                            </div>
                            
                            <?php if ($marina['code'] !== 'RAST'): ?>
                            <div class="col-md-6">
                                <label for="tipo" class="form-label">Tipo</label>
                                <select name="tipo" id="tipo" class="form-select">
                                    <option value="">— Seleziona —</option>
                                    <option value="carrello" 
                                            <?php echo $form_data['tipo'] === 'carrello' ? 'selected' : ''; ?>>
                                        Carrello
                                    </option>
                                    <option value="fune" 
                                            <?php echo $form_data['tipo'] === 'fune' ? 'selected' : ''; ?>>
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
                                      placeholder="Eventuali annotazioni sul posto..."><?php echo htmlspecialchars($form_data['note']); ?></textarea>
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
                
                <?php if (!$slot['deleted_at']): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Ultima modifica: <?php echo format_date_from_ymd($slot['updated_at']); ?>
                        </small>
                        <a href="?id=<?php echo $id; ?>&delete=1" 
                           class="btn btn-outline-danger btn-sm"
                           onclick="return confirm('Sei sicuro di voler eliminare questo posto?\nPotrà essere recuperato dal cestino.')">
                            <i class="bi bi-trash"></i> Elimina posto
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="card-footer bg-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-danger">
                            <i class="bi bi-exclamation-triangle"></i> 
                            Questo posto è stato eliminato
                        </span>
                        <a href="?id=<?php echo $id; ?>&restore=1" 
                           class="btn btn-success btn-sm"
                           onclick="return confirm('Ripristinare questo posto?')">
                            <i class="bi bi-arrow-counterclockwise"></i> Ripristina
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>