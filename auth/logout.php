<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
session_destroy();
header('Location: /auth/login.php');
exit;