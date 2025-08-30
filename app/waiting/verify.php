<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/mailer.php';
require_login();

$title = 'Verifica annuale lista d\'attesa';
$active = 'waiting_list';

// Se richiesto invio email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_verification'])) {
    $stmt = $pdo->query("
        SELECT * FROM waiting_list 
        WHERE attivo = 1 
        AND (ultima_verifica IS NULL OR ultima_verifica < DATE_SUB(NOW(), INTERVAL 1 YEAR))
    ");
    $da_verificare = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sent = 0;
    $failed = 0;
    $errors = [];
    
    foreach ($da_verificare as $record) {
        if (send_verification_email($record)) {
            $sent++;
            // Aggiorna data invio (non data verifica - quella si aggiorna alla conferma)
            $stmt = $pdo->prepare("
                UPDATE waiting_list 
                SET updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$record['id']]);
        } else {
            $failed++;
            $errors[] = $record['email'];
        }
        
        // Pausa tra invii per evitare limiti SMTP
        if ($sent % 10 == 0) {
            sleep(2);
        }
    }
    
    $message = "Email inviate con successo: $sent";
    if ($failed > 0) {
        $message .= ", Fallite: $failed";
        if (!empty($errors)) {
            $message .= " (" . implode(', ', array_slice($errors, 0, 3)) . "...)";
        }
    }
    
    set_flash('success', $message);
    header('Location: /app/waiting/verify.php');
    exit;
}

// Test invio singolo
if (isset($_GET['test_email']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM waiting_list WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        if (send_verification_email($record)) {
            set_flash('success', 'Email di test inviata a ' . $record['email']);
        } else {
            set_flash('error', 'Errore invio email a ' . $record['email']);
        }
    }
    header('Location: /app/waiting/verify.php');
    exit;
}

// Statistiche
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as totale,
        SUM(attivo = 1) as attivi,
        SUM(attivo = 0) as non_attivi,
        SUM(ultima_verifica IS NULL) as mai_verificati,
        SUM(ultima_verifica < DATE_SUB(NOW(), INTERVAL 1 YEAR)) as da_verificare
    FROM waiting_list
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Lista da verificare
$stmt = $pdo->query("
    SELECT * FROM waiting_list 
    WHERE attivo = 1 
    AND (ultima_verifica IS NULL OR ultima_verifica < DATE_SUB(NOW(), INTERVAL 1 YEAR))
    ORDER BY ultima_verifica ASC, data_iscrizione ASC
");
$da_verificare = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../inc/layout/header.php';
include __DIR__ . '/../../inc/layout/navbar.php';
?>

<div class="container py-4">
    <h1 class="h4 mb-4">Verifica annuale lista d'attesa</h1>
    
    <?php if ($msg = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    
    <!-- Statistiche -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $stats['totale']; ?></h5>
                    <p class="card-text text-muted">Totale iscritti</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-success"><?php echo $stats['attivi']; ?></h5>
                    <p class="card-text text-muted">Attivi</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-warning"><?php echo $stats['mai_verificati']; ?></h5>
                    <p class="card-text text-muted">Mai verificati</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-danger"><?php echo $stats['da_verificare']; ?></h5>
                    <p class="card-text text-muted">Da verificare</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista da verificare -->
    <div class="card">
        <div class="card-header bg-warning">
            <strong>Iscrizioni da verificare (<?php echo count($da_verificare); ?>)</strong>
        </div>
        <div class="card-body">
            <?php if (count($da_verificare) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tipologia</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Telefono</th>
                                <th>Iscrizione</th>
                                <th>Ultima verifica</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($da_verificare as $row): ?>
                            <tr>
                                <td><?php echo $row['tipologia']; ?></td>
                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($row['cognome'] . ' ' . $row['nome']); ?>
                                    </strong>
                                </td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                <td><?php echo format_date_from_ymd($row['data_iscrizione']); ?></td>
                                <td>
                                    <?php if ($row['ultima_verifica']): ?>
                                        <span class="text-danger">
                                            <?php echo format_date_from_ymd($row['ultima_verifica']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-warning">Mai</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/app/waiting/edit.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        Verifica
                                    </a>
                                </td>
                                <td>
                                    <a href="?test_email=1&id=<?php echo $row['id']; ?>" 
                                    class="btn btn-sm btn-outline-info" 
                                    title="Invia email di test">
                                        📧 Test
                                    </a>
                                    <a href="/app/waiting/edit.php?id=<?php echo $row['id']; ?>" 
                                    class="btn btn-sm btn-outline-primary">
                                        Verifica
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <form method="POST" class="mt-3">
                    <button type="submit" name="send_verification" class="btn btn-warning"
                            onclick="return confirm('Inviare email di verifica a tutti gli iscritti da verificare?')">
                        Invia email di verifica
                    </button>
                    <small class="text-muted d-block mt-2">
                        Verrà inviata un'email a tutti gli iscritti per confermare l'interesse a rimanere in lista.
                    </small>
                </form>
            <?php else: ?>
                <p class="text-muted mb-0">Nessuna iscrizione da verificare al momento.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="/app/waiting/list.php" class="btn btn-outline-secondary">
            ← Torna alla lista
        </a>
    </div>
</div>

<?php include __DIR__ . '/../../inc/layout/footer.php'; ?>