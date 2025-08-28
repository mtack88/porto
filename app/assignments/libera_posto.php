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
    
    // 1. Trova l'ultima assegnazione NON "Libero"
    $stmt = $pdo->prepare("
        SELECT * FROM assignments 
        WHERE slot_id = :sid 
        AND stato != 'Libero'
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([':sid' => $slot_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current) {
        // Aggiorna data_fine se necessario
        $current_fine = $current['data_fine'] ?? '0000-00-00';
        
        if ($current_fine == '0000-00-00' || 
            $current_fine == '' || 
            $current_fine === null ||
            $current_fine > $data_liberazione) {
            
            $stmt = $pdo->prepare("
                UPDATE assignments 
                SET data_fine = :df
                WHERE id = :id
            ");
            $result = $stmt->execute([
                ':df' => $data_liberazione,
                ':id' => $current['id']
            ]);
            
            if (!$result) {
                throw new Exception("Impossibile aggiornare data_fine");
            }
        }
    }
    
    // 2. Chiudi TUTTE le altre assegnazioni senza data_fine o con data posteriore
    $stmt = $pdo->prepare("
        UPDATE assignments 
        SET data_fine = :df
        WHERE slot_id = :sid 
        AND (
            data_fine IS NULL 
            OR data_fine = '0000-00-00' 
            OR data_fine = ''
            OR data_fine > :df2
        )
    ");
    $stmt->execute([
        ':df' => $data_liberazione,
        ':df2' => $data_liberazione,
        ':sid' => $slot_id
    ]);
    
    // 3. Crea nuova assegnazione "Libero" dal giorno dopo
    $data_inizio_libero = new DateTime($data_liberazione);
    $data_inizio_libero->modify('+1 day');
    
    $stmt = $pdo->prepare("
        INSERT INTO assignments 
        (slot_id, stato, proprietario, data_inizio, data_fine, created_by, created_at)
        VALUES (:sid, 'Libero', NULL, :di, NULL, :uid, NOW())
    ");
    $stmt->execute([
        ':sid' => $slot_id,
        ':di' => $data_inizio_libero->format('Y-m-d'),
        ':uid' => current_user_id()
    ]);
    
    // 4. Aggiorna stato slot a Libero
    $stmt = $pdo->prepare("
        UPDATE slots 
        SET stato = 'Libero', updated_at = NOW() 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $slot_id]);
    
    $pdo->commit();
    
    set_flash('success', 'Posto liberato correttamente dal ' . format_date_from_ymd($data_liberazione));
    
} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('error', 'Errore: ' . $e->getMessage());
}

// Reindirizza al view del posto
header('Location: /app/slots/view.php?id=' . $slot_id);
exit;