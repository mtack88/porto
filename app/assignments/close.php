<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /app/slots/list.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Token non valido');
    header('Location: /app/slots/list.php');
    exit;
}

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$slot_id = (int)($_POST['slot_id'] ?? 0);

if ($assignment_id <= 0 || $slot_id <= 0) {
    set_flash('error', 'Dati non validi');
    header('Location: /app/slots/list.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Chiudi assegnazione
    $stmt = $pdo->prepare("
        UPDATE assignments 
        SET data_fine = CURDATE() 
        WHERE id = ? AND data_fine IS NULL
    ");
    $stmt->execute([$assignment_id]);
    
    // Crea nuova assegnazione libera
    $stmt = $pdo->prepare("
        INSERT INTO assignments 
        (slot_id, stato, data_inizio, created_by, created_at)
        VALUES (?, 'Libero', CURDATE(), ?, NOW())
    ");
    $stmt->execute([$slot_id, current_user_id()]);
    
    // Aggiorna stato slot
    $stmt = $pdo->prepare("
        UPDATE slots 
        SET stato = 'Libero', updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$slot_id]);
    
    $pdo->commit();
    
    set_flash('success', 'Posto liberato con successo');
    header('Location: /app/slots/view.php?id=' . $slot_id);
    
} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('error', 'Errore: ' . $e->getMessage());
    header('Location: /app/slots/view.php?id=' . $slot_id);
}
exit;