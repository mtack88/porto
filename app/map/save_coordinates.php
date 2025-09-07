<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non permesso']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$slot_id = (int)($data['slot_id'] ?? 0);
$north = (float)($data['north'] ?? 0);
$south = (float)($data['south'] ?? 0);
$east = (float)($data['east'] ?? 0);
$west = (float)($data['west'] ?? 0);
$rotation = (float)($data['rotation'] ?? 0);
$center_lat = (float)($data['center_lat'] ?? 0);
$center_lng = (float)($data['center_lng'] ?? 0);
$width = (float)($data['width'] ?? 0);
$height = (float)($data['height'] ?? 0);

if (!$slot_id || !$center_lat || !$center_lng) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

try {
    // Verifica che lo slot esista (senza limitare a un porto specifico)
    $stmt = $pdo->prepare("SELECT id FROM slots WHERE id = ?");
    $stmt->execute([$slot_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Posto non valido']);
        exit;
    }
    
    // Inserisci o aggiorna
    $stmt = $pdo->prepare("
        INSERT INTO slot_coordinates 
        (slot_id, north, south, east, west, rotation, center_lat, center_lng, width, height)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        north = VALUES(north),
        south = VALUES(south),
        east = VALUES(east),
        west = VALUES(west),
        rotation = VALUES(rotation),
        center_lat = VALUES(center_lat),
        center_lng = VALUES(center_lng),
        width = VALUES(width),
        height = VALUES(height),
        updated_at = NOW()
    ");
    
    $stmt->execute([
        $slot_id, $north, $south, $east, $west, 
        $rotation, $center_lat, $center_lng, $width, $height
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}