<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/upload_handler.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$entity_type = $_POST['entity_type'] ?? '';
$entity_id = (int)($_POST['entity_id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$return_url = $_POST['return_url'] ?? '/';

// Validazione
if (!in_array($entity_type, ['slot', 'waiting_list', 'assignment'])) {
    set_flash('error', 'Tipo entità non valido');
    header('Location: ' . $return_url);
    exit;
}

if ($entity_id <= 0) {
    set_flash('error', 'ID entità non valido');
    header('Location: ' . $return_url);
    exit;
}

// Verifica che il file sia stato caricato
if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
    set_flash('error', 'Nessun file selezionato');
    header('Location: ' . $return_url);
    exit;
}

// Gestisci upload
$result = handle_file_upload($_FILES['attachment'], $entity_type, $entity_id);

if ($result['success']) {
    // Debug prima del salvataggio
    error_log("Salvo allegato: entity_type=$entity_type, entity_id=$entity_id");

    // Verifica che entity_type sia esattamente 'slot' (non 'slots')
    if ($entity_type === 'slots') {
        $entity_type = 'slot'; // Correzione
    }

    // Salva nel database
    if (save_attachment($pdo, $entity_type, $entity_id, $result, $description)) {
        set_flash('success', 'File caricato con successo');
    } else {
        // Elimina file se salvataggio DB fallisce
        $file_path = UPLOAD_BASE_DIR . $entity_type . '/' . $result['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        set_flash('error', 'Errore salvataggio nel database');
    }
} else {
    set_flash('error', $result['error']);
}

header('Location: ' . $return_url);
exit;