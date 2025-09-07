<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$slot_id = (int)($data['slot_id'] ?? 0);

if ($slot_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID posto non valido']);
    exit;
}

try {
    // Elimina le coordinate del posto
    $stmt = $pdo->prepare("DELETE FROM slot_coordinates WHERE slot_id = :slot_id");
    $stmt->execute([':slot_id' => $slot_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}