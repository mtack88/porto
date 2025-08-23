<?php
declare(strict_types=1);
?>
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="/app/dashboard.php">
      <img src="/assets/img/logo.svg" alt="Logo" class="me-2" style="height:28px;">
      <?= e(env('APP_NAME','Gestione Porti')) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= ($active??'')==='dashboard'?'active':'' ?>" href="/app/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= ($active??'')==='map_bola'?'active':'' ?>" href="/app/map_bola.php">Mappa Bola</a></li>
        <li class="nav-item"><a class="nav-link <?= ($active??'')==='map_ritter'?'active':'' ?>" href="/app/map_ritter.php">Mappa Ritter</a></li>
        <li class="nav-item"><a class="nav-link <?= ($active??'')==='rack'?'active':'' ?>" href="/app/rack/grid.php">Rastrelliera</a></li>
        <li class="nav-item"><a class="nav-link <?= ($active??'')==='slots_list'?'active':'' ?>" href="/app/slots/list.php">Tabella posti</a></li>
        <li class="nav-item"><a class="nav-link <?= ($active??'')==='history'?'active':'' ?>" href="/app/history/list.php">Storico</a></li>
        <li class="nav-item"><a class="nav-link <?= ($active??'')==='import'?'active':'' ?>" href="/app/slots/import.php">Import CSV</a></li>
      </ul>
      <span class="navbar-text me-3">Ciao, <?= e(current_user()['name'] ?? '') ?></span>
      <a class="btn btn-outline-light btn-sm" href="/auth/logout.php" data-confirm="Vuoi uscire dall'applicazione?">Esci</a>
    </div>
  </div>
</nav>