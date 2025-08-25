<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /app/slots/list.php');
    exit;
}

$slot_id = (int)($_POST['slot_id'] ?? 0);
if ($slot_id <= 0) {
    header('Location: /app/slots/list.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Chiudi assegnazioni aperte
    $stmt = $pdo->prepare("UPDATE assignments SET data_fine = CURDATE() WHERE slot_id = ? AND data_fine IS NULL");
    $stmt->execute([$slot_id]);
    
    // Crea nuova assegnazione "Libero"
    $stmt = $pdo->prepare("
        INSERT INTO assignments (slot_id, stato, data_inizio, created_by, created_at)
        VALUES (?, 'Libero', CURDATE(), ?, NOW())
    ");
    $stmt->execute([$slot_id, current_user_id()]);
    
    // Aggiorna slot a Libero
    $stmt = $pdo->prepare("UPDATE slots SET stato = 'Libero', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$slot_id]);
    
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
}

header('Location: /app/slots/view.php?id=' . $slot_id);
exit;