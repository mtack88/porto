<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
if (is_logged_in()) {
    header('Location: /app/dashboard.php'); exit;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF non valido. Riprova.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            $errors[] = 'Inserisci email e password.';
        } else {
            $ok = login($email, $password);
            if ($ok) {
                header('Location: /app/dashboard.php'); exit;
            } else {
                $errors[] = 'Credenziali non valide.';
                // backoff semplice
                usleep(300000);
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
  <?php if (env('ALLOW_REGISTRATION','false')==='true'): ?>
  <div class="text-center mt-3">
    <a href="/auth/register.php">Registrazione iniziale Admin</a>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../inc/layout/footer.php'; ?>
