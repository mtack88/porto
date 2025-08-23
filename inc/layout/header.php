<?php
declare(strict_types=1);
?><!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(get_csrf_token()) ?>">
  <title><?= e($title ?? env('APP_NAME','Gestione Porti')) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/css/custom.css" rel="stylesheet">
  <link rel="icon" href="/assets/img/logo.svg">
</head>
<body>