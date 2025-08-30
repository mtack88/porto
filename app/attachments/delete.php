<?php
declare(strict_types=1);
require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/../../inc/upload_handler.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$return = $_GET['return'] ?? '';
$return_id = (int)($_GET['return_id'] ?? 0);

// Determina URL di ritorno
$return_url = '/';
if ($return === 'slot' && $return_id > 0) {
    $return_url = '/app/slots/view.php?id=' . $return_id;
} elseif ($return === 'waiting' && $return_id > 0) {
    $return_url = '/app/waiting/edit.php?id=' . $return_id;
}

if ($id <= 0) {
    set_flash('error', 'ID allegato non valido');
    header('Location: ' . $return_url);
    exit;
}

// Elimina allegato
if (delete_attachment($pdo, $id)) {
    set_flash('success', 'Allegato eliminato');
} else {
    set_flash('error', 'Errore eliminazione allegato');
}

header('Location: ' . $return_url);
exit;