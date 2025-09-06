<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

// Carica lista pontili per il dropdown
$stmt = $pdo->query("SELECT id, code, name FROM marinas ORDER BY code");
$marinas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validazione
    $marina_id = (int)($_POST['marina_id'] ?? 0);
    $numero_esterno = trim($_POST['numero_esterno'] ?? '');
    $numero_interno = trim($_POST['numero_interno'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $stato = $_POST['stato'] ?? 'Libero';
    $note = trim($_POST['note'] ?? '');
    
    // Controlli
    if (!$marina_id) $errors[] = 'Seleziona un pontile';
    if (!$numero_esterno) $errors[] = 'Numero posto obbligatorio';
    
    // Verifica che il numero non sia già in uso per questo pontile
    if ($marina_id && $numero_esterno) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM slots 
            WHERE marina_id = ? AND numero_esterno = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$marina_id, $numero_esterno]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Numero posto già esistente per questo pontile';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO slots (marina_id, numero_esterno, numero_interno, tipo, stato, note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $marina_id,
                $numero_esterno,
                $numero_interno ?: null,
                $tipo ?: null,
                $stato,
                $note ?: null
            ]);
            
            $new_id = $pdo->lastInsertId();
            
            set_flash('success', 'Posto creato con successo');
            header('Location: /app/slots/view.php?id=' . $new_id);
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Errore database: ' . $e->getMessage();
        }
    }
}

$title = 'Nuovo posto barca';
$active = 'slots';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="h4 mb-4">Aggiungi nuovo posto barca</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <strong>Dati posto</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        
                        <!-- Pontile -->
                        <div class="mb-3">
                            <label class="form-label">Pontile *</label>
                            <select name="marina_id" class="form-select" required>
                                <option value="">-- Seleziona pontile --</option>
                                <?php foreach ($marinas as $marina): ?>
                                <option value="<?php echo $marina['id']; ?>"
                                        <?php echo ($_POST['marina_id'] ?? '') == $marina['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($marina['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Numeri -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Numero posto *</label>
                                <input type="text" name="numero_esterno" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['numero_esterno'] ?? ''); ?>"
                                       placeholder="es. 1, 2, 3...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Numero interno</label>
                                <input type="text" name="numero_interno" class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['numero_interno'] ?? ''); ?>"
                                       placeholder="Opzionale">
                            </div>
                        </div>
                        
                        <!-- Tipo e Stato -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="">-- Non specificato --</option>
                                    <option value="Carrello" <?php echo ($_POST['tipo'] ?? '') === 'Carrello' ? 'selected' : ''; ?>>
                                        Carrello
                                    </option>
                                    <option value="Fune" <?php echo ($_POST['tipo'] ?? '') === 'Fune' ? 'selected' : ''; ?>>
                                        Fune
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stato iniziale</label>
                                <select name="stato" class="form-select">
                                    <option value="Libero" <?php echo ($_POST['stato'] ?? 'Libero') === 'Libero' ? 'selected' : ''; ?>>
                                        🟢 Libero
                                    </option>
                                    <option value="Riservato" <?php echo ($_POST['stato'] ?? '') === 'Riservato' ? 'selected' : ''; ?>>
                                        🟡 Riservato
                                    </option>
                                    <option value="Manutenzione" <?php echo ($_POST['stato'] ?? '') === 'Manutenzione' ? 'selected' : ''; ?>>
                                        🔧 Manutenzione
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Note -->
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea name="note" class="form-control" rows="3"
                                      placeholder="Eventuali note o osservazioni..."><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                        </div>
                        
                        <hr>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">
                                <strong>✓ Crea posto</strong>
                            </button>
                            <a href="/app/slots/list.php" class="btn btn-outline-secondary">
                                Annulla
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="alert alert-info mt-3">
                <strong>Suggerimento:</strong> Dopo aver creato il posto, potrai assegnarlo a un proprietario dalla pagina dei dettagli.
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>