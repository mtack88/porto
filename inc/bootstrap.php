<?php
declare(strict_types=1);

// Base path
define('BASE_PATH', dirname(__DIR__));
// Session e timezone
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Europe/Rome');

// Carica helper
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

// Carica .env semplice
load_env_file(BASE_PATH.'/.env');

// Costanti app
define('APP_NAME', env('APP_NAME','Gestione Porti'));

// Connessione DB
require_once __DIR__ . '/db.php';