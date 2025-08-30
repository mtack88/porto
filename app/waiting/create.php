<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validazione
    $tipologia = $_POST['tipologia'] ?? '';
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
    $data_iscrizione = $_POST['data_iscrizione'] ?? date('Y-m-d');
    
    // Controlli campi obbligatori
    if (!$tipologia) $errors[] = 'Tipologia obbligatoria';
    if (!$cognome) $errors[] = 'Cognome obbligatorio';
    if (!$nome) $errors[] = 'Nome obbligatorio';
    if (!$luogo) $errors[] = 'Luogo obbligatorio';
    if (!$via) $errors[] = 'Via obbligatoria';
    if (!$telefono) $errors[] = 'Telefono obbligatorio';
    if (!$email) $errors[] = 'Email obbligatoria';
    
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO waiting_list 
                (tipologia, cognome, nome, luogo, via, telefono, email, 
                 motore_kw, dimensioni, targa, osservazioni, data_iscrizione, 
                 attivo, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
            ");
            
            $stmt->execute([
                $tipologia,
                $cognome,
                $nome,
                $luogo,
                $via,
                $telefono,
                $email,
                $motore_kw ?: null,
                $dimensioni ?: null,
                $targa ?: null,
                $osservazioni ?: null,
                $data_iscrizione,
                current_user_id()
            ]);
            
            $new_id = $pdo->lastInsertId();
            
            // TENTATIVO INVIO EMAIL (non bloccare se fallisce)
            try {
                // Verifica se il file mailer esiste prima di includerlo
                $mailer_file = __DIR__ . '/../../inc/mailer.php';
                if (file_exists($mailer_file)) {
                    require_once $mailer_file;
                    
                    // Recupera record completo per email
                    $stmt = $pdo->prepare("SELECT * FROM waiting_list WHERE id = ?");
                    $stmt->execute([$new_id]);
                    $new_record = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Prova a inviare email solo se la funzione esiste
                    if (function_exists('send_welcome_email')) {
                        send_welcome_email($new_record);
                    }
                }
            } catch (Exception $e) {
                // Log errore email ma non bloccare
                error_log("Errore invio email benvenuto: " . $e->getMessage());
                // Non mostrare errore all'utente - l'iscrizione è comunque salvata
            }
            
            set_flash('success', 'Iscrizione creata con successo! Ora puoi aggiungere allegati.');
            header('Location: /app/waiting/edit.php?id=' . $new_id . '&created=1');
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Errore database: ' . $e->getMessage();
        }
    }
}

$title = 'Nuova iscrizione lista d\'attesa';
$active = 'waiting_list';
include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <h1 class="h4 mb-3">Nuova iscrizione lista d'attesa</h1>
            
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
                    <strong>Dati iscrizione</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        
                        <!-- Tipologia -->
                        <div class="mb-3">
                            <label class="form-label">Tipologia *</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipologia" 
                                           id="tipo_barca" value="Barca" required
                                           <?php echo ($_POST['tipologia'] ?? '') === 'Barca' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="tipo_barca">
                                        🚤 Barca
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipologia" 
                                           id="tipo_canoa" value="Canoa" required
                                           <?php echo ($_POST['tipologia'] ?? '') === 'Canoa' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="tipo_canoa">
                                        🛶 Canoa
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dati personali -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cognome *</label>
                                <input type="text" name="cognome" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['cognome'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome *</label>
                                <input type="text" name="nome" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Indirizzo -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Luogo *</label>
                                <input type="text" name="luogo" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['luogo'] ?? ''); ?>"
                                       placeholder="es. Melide">
                                <small class="text-muted">Residenti a Melide hanno priorità</small>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Via *</label>
                                <input type="text" name="via" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['via'] ?? ''); ?>"
                                       placeholder="Via e numero civico">
                            </div>
                        </div>
                        
                        <!-- Contatti -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telefono *</label>
                                <input type="tel" name="telefono" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>"
                                       placeholder="+41 79 123 45 67">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="email@esempio.ch">
                            </div>
                        </div>
                        
                        <!-- Info imbarcazione (mostrati dinamicamente) -->
                        <div id="info_barca" style="display: none;">
                            <h6 class="mb-3">Informazioni imbarcazione</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Motore (KW)</label>
                                    <input type="text" name="motore_kw" class="form-control"
                                           value="<?php echo htmlspecialchars($_POST['motore_kw'] ?? ''); ?>"
                                           placeholder="es. 50">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Dimensioni</label>
                                    <input type="text" name="dimensioni" class="form-control"
                                           value="<?php echo htmlspecialchars($_POST['dimensioni'] ?? ''); ?>"
                                           placeholder="es. 5.5m x 2.2m">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Targa</label>
                                    <input type="text" name="targa" class="form-control"
                                           value="<?php echo htmlspecialchars($_POST['targa'] ?? ''); ?>"
                                           placeholder="es. TI 12345">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Osservazioni -->
                        <div class="mb-3">
                            <label class="form-label">Osservazioni</label>
                            <textarea name="osservazioni" class="form-control" rows="3"
                                      placeholder="Eventuali note o richieste particolari..."><?php echo htmlspecialchars($_POST['osservazioni'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Data iscrizione -->
                        <div class="mb-3">
                            <label class="form-label">Data iscrizione</label>
                            <input type="date" name="data_iscrizione" class="form-control"
                                   value="<?php echo $_POST['data_iscrizione'] ?? date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">
                                <strong>Conferma iscrizione</strong>
                            </button>
                            <a href="/app/waiting/list.php" class="btn btn-outline-secondary">
                                Annulla
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Mostra/nasconde campi barca
document.addEventListener('DOMContentLoaded', function() {
    const radioBarca = document.getElementById('tipo_barca');
    const radioCanoa = document.getElementById('tipo_canoa');
    const infoBarca = document.getElementById('info_barca');
    
    function toggleInfoBarca() {
        if (radioBarca.checked) {
            infoBarca.style.display = 'block';
        } else {
            infoBarca.style.display = 'none';
        }
    }
    
    radioBarca.addEventListener('change', toggleInfoBarca);
    radioCanoa.addEventListener('change', toggleInfoBarca);
    
    // Check iniziale
    toggleInfoBarca();
});
</script>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>