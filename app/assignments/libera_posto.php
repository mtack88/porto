<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /app/slots/list.php');
    exit;
}

$slot_id = (int)($_POST['slot_id'] ?? 0);
$data_liberazione = $_POST['data_liberazione'] ?? date('Y-m-d');
$note = trim($_POST['note'] ?? '');

if ($slot_id <= 0) {
    set_flash('error', 'ID posto non valido');
    header('Location: /app/slots/list.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 1. Trova tutte le assegnazioni aperte (con data_fine NULL o 0000-00-00)
    $stmt = $pdo->prepare("
        SELECT * FROM assignments 
        WHERE slot_id = :sid 
        AND (data_fine IS NULL OR data_fine = '0000-00-00' OR data_fine = '')
        ORDER BY id DESC
    ");
    $stmt->execute([':sid' => $slot_id]);
    $open_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Chiudi tutte le assegnazioni aperte con la data di liberazione
    if (count($open_assignments) > 0) {
        $stmt = $pdo->prepare("
            UPDATE assignments 
            SET data_fine = :df
            WHERE slot_id = :sid 
            AND (data_fine IS NULL OR data_fine = '0000-00-00' OR data_fine = '')
        ");
        $stmt->execute([
            ':df' => $data_liberazione,
            ':sid' => $slot_id
        ]);
        
        // Debug
        error_log("Chiuse " . count($open_assignments) . " assegnazioni per slot $slot_id con data $data_liberazione");
    }
    
    // 3. Crea nuova assegnazione "Libero" dal giorno dopo
    $data_inizio_libero = new DateTime($data_liberazione);
    $data_inizio_libero->modify('+1 day');
    
    $stmt = $pdo->prepare("
        INSERT INTO assignments 
        (slot_id, stato, proprietario, data_inizio, data_fine, note, created_by, created_at)
        VALUES (:sid, 'Libero', NULL, :di, NULL, :note, :uid, NOW())
    ");
    $stmt->execute([
        ':sid' => $slot_id,
        ':di' => $data_inizio_libero->format('Y-m-d'),
        ':note' => $note ?: null,
        ':uid' => current_user_id()
    ]);
    
    $new_id = (int)$pdo->lastInsertId();
    
    // 4. Aggiorna stato slot a Libero
    $stmt = $pdo->prepare("
        UPDATE slots 
        SET stato = 'Libero', updated_at = NOW() 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $slot_id]);
    
    // 5. Log evento
    log_event('assignment', $new_id, 'libera', [
        'slot_id' => $slot_id,
        'data_liberazione' => $data_liberazione,
        'assegnazioni_chiuse' => count($open_assignments),
        'note' => $note
    ]);
    
    $pdo->commit();
    
    set_flash('success', 'Posto liberato correttamente dal ' . format_date_from_ymd($data_liberazione));
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Errore liberazione posto: " . $e->getMessage());
    set_flash('error', 'Errore durante la liberazione: ' . $e->getMessage());
}

// Reindirizza al view del posto
header('Location: /app/slots/view.php?id=' . $slot_id);
exit;