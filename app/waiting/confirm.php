<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';

$message = '';
$success = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $decoded = base64_decode($token);
    $parts = explode('|', $decoded);
    
    if (count($parts) === 3) {
        $id = (int)$parts[0];
        $year = $parts[1];
        $email = $parts[2];
        
        // Verifica che il token sia valido
        $stmt = $pdo->prepare("
            SELECT * FROM waiting_list 
            WHERE id = ? AND email = ? AND attivo = 1
        ");
        $stmt->execute([$id, $email]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            // Aggiorna ultima verifica
            $stmt = $pdo->prepare("
                UPDATE waiting_list 
                SET ultima_verifica = CURDATE(), updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            $success = true;
            $message = "Grazie {$record['cognome']} {$record['nome']}! 
                       La sua iscrizione è stata confermata per un altro anno.";
        } else {
            $message = "Token non valido o iscrizione non trovata.";
        }
    } else {
        $message = "Token non valido.";
    }
} else {
    $message = "Token mancante.";
}

// Semplice pagina HTML di risposta
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma Lista d'Attesa - Porto di Melide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header <?php echo $success ? 'bg-success' : 'bg-danger'; ?> text-white">
                        <h4><?php echo $success ? '✓ Conferma ricevuta' : '✗ Errore'; ?></h4>
                    </div>
                    <div class="card-body">
                        <p><?php echo htmlspecialchars($message); ?></p>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-info">
                            <strong>Prossimi passi:</strong><br>
                            • La sua posizione in lista è mantenuta<br>
                            • Riceverà una nuova verifica tra un anno<br>
                            • La contatteremo quando si libererà un posto
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">Comune di Melide, Via S. Franscini 6, 6815 Melide</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>