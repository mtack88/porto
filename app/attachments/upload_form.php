<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$entity_type = $_GET['entity_type'] ?? '';
$entity_id = (int)($_GET['entity_id'] ?? 0);

// Determina URL di ritorno
$return_url = '/';
if ($entity_type === 'slot') {
    $return_url = '/app/slots/view.php?id=' . $entity_id;
} elseif ($entity_type === 'waiting_list') {
    $return_url = '/app/waiting/edit.php?id=' . $entity_id;
}

$title = 'Carica allegato';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="h4 mb-3">Carica allegato</h1>
            
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/app/attachments/upload.php" enctype="multipart/form-data">
                        <input type="hidden" name="entity_type" value="<?php echo htmlspecialchars($entity_type); ?>">
                        <input type="hidden" name="entity_id" value="<?php echo $entity_id; ?>">
                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">File *</label>
                            <input type="file" name="attachment" class="form-control" required
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                            <small class="text-muted">
                                Formati: PDF, JPG, PNG, Word, Excel. Max 10MB
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrizione (opzionale)</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="Descrivi il contenuto del file..."></textarea>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">
                                Carica file
                            </button>
                            <a href="<?php echo htmlspecialchars($return_url); ?>" 
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