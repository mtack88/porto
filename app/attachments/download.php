<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

// IMPORTANTE: Definisci il percorso base per gli upload
define('UPLOAD_BASE_DIR', __DIR__ . '/../../uploads/');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit('File non trovato');
}

try {
    // Recupera info allegato
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
    $stmt->execute([$id]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        http_response_code(404);
        exit('File non trovato nel database');
    }

    // Costruisci percorso file
    $file_path = UPLOAD_BASE_DIR . $attachment['entity_type'] . '/' . $attachment['filename'];
    
    // Debug (rimuovi in produzione)
    if (!file_exists(UPLOAD_BASE_DIR)) {
        exit('Directory upload non trovata: ' . UPLOAD_BASE_DIR);
    }
    
    if (!file_exists($file_path)) {
        // Prova percorso alternativo
        $alt_path = __DIR__ . '/../../uploads/' . $attachment['entity_type'] . '/' . $attachment['filename'];
        if (file_exists($alt_path)) {
            $file_path = $alt_path;
        } else {
            http_response_code(404);
            exit('File fisico non trovato: ' . $attachment['filename']);
        }
    }

    // Determina content type
    $content_types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

    $content_type = $content_types[$attachment['file_type']] ?? 'application/octet-stream';

    // Pulisci buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Invia headers
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . $attachment['original_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=3600');

    // Output file
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Errore: ' . $e->getMessage());
}