<?php
// Configurazione upload
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_BASE_DIR', __DIR__ . '/../uploads/');
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'doc', 'xls']);
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
]);

/**
 * Gestisce l'upload di un file
 * @return array ['success' => bool, 'filename' => string, 'error' => string]
 */
function handle_file_upload($file, string $entity_type, int $entity_id): array {
    // Verifica errori PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'error' => 'Errore upload: ' . get_upload_error_message($file['error'])
        ];
    }
    
    // Verifica dimensione
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return [
            'success' => false,
            'error' => 'File troppo grande. Max ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB'
        ];
    }
    
    // Verifica estensione
    $original_name = $file['name'];
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return [
            'success' => false,
            'error' => 'Tipo file non permesso. Permessi: ' . implode(', ', ALLOWED_EXTENSIONS)
        ];
    }
    
    // Verifica MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        return [
            'success' => false,
            'error' => 'Tipo MIME non valido'
        ];
    }
    
    // Crea directory se non esiste
    $upload_dir = UPLOAD_BASE_DIR . $entity_type . '/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return [
                'success' => false,
                'error' => 'Impossibile creare directory upload'
            ];
        }
    }
    
    // Genera nome file univoco
    $new_filename = sprintf(
        '%s_%d_%s_%s.%s',
        $entity_type,
        $entity_id,
        date('Ymd_His'),
        uniqid(),
        $extension
    );
    
    $destination = $upload_dir . $new_filename;
    
    // Sposta il file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'success' => false,
            'error' => 'Impossibile salvare il file'
        ];
    }
    
    // Imposta permessi
    chmod($destination, 0644);
    
    return [
        'success' => true,
        'filename' => $new_filename,
        'original_name' => $original_name,
        'file_type' => $extension,
        'file_size' => $file['size'],
        'mime_type' => $mime_type
    ];
}

/**
 * Salva info allegato nel database
 */
function save_attachment(PDO $pdo, string $entity_type, int $entity_id, array $file_info, ?string $description = null): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO attachments 
            (entity_type, entity_id, filename, original_name, file_type, file_size, description, uploaded_by, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $entity_type,
            $entity_id,
            $file_info['filename'],
            $file_info['original_name'],
            $file_info['file_type'],
            $file_info['file_size'],
            $description,
            current_user_id()
        ]);
    } catch (PDOException $e) {
        error_log("Errore salvataggio attachment: " . $e->getMessage());
        return false;
    }
}

/**
 * Elimina un allegato
 */
function delete_attachment(PDO $pdo, int $attachment_id): bool {
    try {
        // Recupera info file
        $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
        $stmt->execute([$attachment_id]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attachment) {
            return false;
        }
        
        // Elimina file fisico
        $file_path = UPLOAD_BASE_DIR . $attachment['entity_type'] . '/' . $attachment['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Elimina record database
        $stmt = $pdo->prepare("DELETE FROM attachments WHERE id = ?");
        return $stmt->execute([$attachment_id]);
        
    } catch (Exception $e) {
        error_log("Errore eliminazione attachment: " . $e->getMessage());
        return false;
    }
}

/**
 * Recupera allegati per entità
 */
function get_attachments(PDO $pdo, string $entity_type, int $entity_id): array {
    // DEBUG
    error_log("get_attachments: entity_type='$entity_type', entity_id=$entity_id");
    
    $stmt = $pdo->prepare("
        SELECT a.*, u.username 
        FROM attachments a
        LEFT JOIN users u ON u.id = a.uploaded_by
        WHERE a.entity_type = ? AND a.entity_id = ?
        ORDER BY a.uploaded_at DESC
    ");
    $stmt->execute([$entity_type, $entity_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // DEBUG
    error_log("get_attachments: trovati " . count($results) . " risultati");
    
    return $results;
}

/**
 * Formatta dimensione file
 */
function format_file_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

/**
 * Icona per tipo file
 */
function get_file_icon(string $extension): string {
    $icons = [
        'pdf' => '📄',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'doc' => '📝',
        'docx' => '📝',
        'xls' => '📊',
        'xlsx' => '📊'
    ];
    return $icons[$extension] ?? '📎';
}

/**
 * Messaggio errore upload
 */
function get_upload_error_message(int $error_code): string {
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite PHP)',
        UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
        UPLOAD_ERR_PARTIAL => 'Upload parziale',
        UPLOAD_ERR_NO_FILE => 'Nessun file caricato',
        UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
        UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere su disco',
        UPLOAD_ERR_EXTENSION => 'Upload bloccato da estensione PHP'
    ];
    return $messages[$error_code] ?? 'Errore sconosciuto';
}