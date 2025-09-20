<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/rate_limiter.php';

if (is_logged_in()) {
    header('Location: /app/dashboard.php'); 
    exit;
}

// Inizializza rate limiter
$rateLimiter = new RateLimiter($pdo);
$userIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$errors = [];

// Verifica se l'IP è bloccato
if ($rateLimiter->isBlocked($userIp)) {
    $remainingSeconds = $rateLimiter->getBlockedTimeRemaining($userIp);
    $remainingMinutes = ceil($remainingSeconds / 60);
    $errors[] = "Troppi tentativi falliti. Riprova tra {$remainingMinutes} minuti.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF non valido. Riprova.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Verifica se questo specifico email/IP è bloccato
        if ($rateLimiter->isBlocked($userIp, $email)) {
            $remainingSeconds = $rateLimiter->getBlockedTimeRemaining($userIp);
            $remainingMinutes = ceil($remainingSeconds / 60);
            $errors[] = "Account temporaneamente bloccato. Riprova tra {$remainingMinutes} minuti.";
        } elseif (!$email || !$password) {
            $errors[] = 'Inserisci email e password.';
        } else {
            $loginSuccess = login($email, $password);
            
            if ($loginSuccess) {
                // Login riuscito - reset tentativi
                $rateLimiter->resetAttempts($userIp, $email);
                $rateLimiter->recordAttempt($userIp, $email, true);
                header('Location: /app/dashboard.php'); 
                exit;
            } else {
                // Login fallito - registra tentativo
                $rateLimiter->recordAttempt($userIp, $email, false);
                $errors[] = 'Credenziali non valide.';
                
                // Controlla se ora è bloccato
                if ($rateLimiter->isBlocked($userIp, $email)) {
                    $errors[] = 'Hai raggiunto il numero massimo di tentativi. Account bloccato temporaneamente.';
                }
            }
        }
    }
}

$title = 'Accedi';
include __DIR__ . '/../inc/layout/header.php';
?>
<div class="container py-5" style="max-width:480px;">
  <div class="text-center mb-4">
    <img src="/assets/img/logo.svg" alt="Logo" class="logo mb-2">
    <h1 class="h4"><?= e(env('APP_NAME','Gestione Porti')) ?></h1>
    <p class="small-muted">Accedi per gestire i porti e la rastrelliera.</p>
  </div>
  
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $er) echo '<div>'.e($er).'</div>'; ?>
    </div>
  <?php endif; ?>
  
  <?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
  <?php endif; ?>
  
  <!-- Mostra form solo se non bloccato -->
  <?php if (!$rateLimiter->isBlocked($userIp)): ?>
  <form method="post" class="card p-3 shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" placeholder="email@esempio.it" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" placeholder="********" required>
    </div>
    <button class="btn btn-primary w-100" type="submit">Accedi</button>
  </form>
  <?php else: ?>
  <div class="alert alert-warning">
    <strong>⏰ Account temporaneamente bloccato</strong><br>
    Hai superato il numero massimo di tentativi di accesso.<br>
    Riprova più tardi o contatta l'amministratore.
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../inc/layout/footer.php'; ?>