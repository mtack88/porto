=============================
<?php
declare(strict_types=1);
session_start();
$logged = isset($_SESSION['user']);
if ($logged) {
    header('Location: /app/dashboard.php');
    exit;
} else {
    header('Location: /auth/login.php');
    exit;
}
