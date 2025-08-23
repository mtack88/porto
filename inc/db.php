<?php
declare(strict_types=1);
try{
  $dsn = 'mysql:host='.env('DB_HOST','localhost').';dbname='.env('DB_NAME','porto').';charset=utf8mb4';
  $pdo = new PDO($dsn, env('DB_USER','root'), env('DB_PASS',''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}catch(Throwable $e){
  http_response_code(500);
  echo "Errore connessione DB: ".$e->getMessage(); exit;
}
