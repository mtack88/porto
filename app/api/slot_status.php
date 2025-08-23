<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$code = $_GET['code'] ?? 'BOLA';
$marina = get_marina_by_code($code);
if (!$marina) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("SELECT s.id, s.numero_esterno, s.stato,
  COALESCE(a.proprietario,'') as proprietario
  FROM slots s
  LEFT JOIN assignments a ON a.slot_id = s.id AND a.data_fine IS NULL
  WHERE s.marina_id=:mid AND s.deleted_at IS NULL
  ORDER BY s.numero_esterno");
$stmt->execute([':mid'=>$marina['id']]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));