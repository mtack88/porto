<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
if (env('ALLOW_REGISTRATION','false')!=='true') {
    header('Location: /auth/login.php'); exit;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF non valido.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        if (!$name || !$email || !$password) {
            $errors[] = 'Compila tutti i campi obbligatori.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'La password deve contenere almeno 8 caratteri.';
        } elseif ($password !== $password2) {
            $errors[] = 'Le password non coincidono.';
        } else {
            try {
                $ok = register_admin($name, $email, $password);
                if ($ok) {
                    set_flash('success', 'Registrazione completata. Ora puoi accedere. Ricorda di impostare ALLOW_REGISTRATION=false nel file .env.');
                    header('Location: /auth/login.php'); exit;
                } else {
                    $errors[] = 'Impossibile creare l\'utente. Forse esiste già?';
                }
            } catch (Throwable $e) {
                $errors[] = 'Errore: '.$e->getMessage();
            }
        }
    }
}
$title = 'Registrazione Admin';
include __DIR__ . '/../inc/layout/header.php';
?>
<div class="container py-5" style="max-width:520px;">
  <h1 class="h4 mb-3">Registrazione amministratore</h1>
  <p class="small-muted">Questa pagina è visibile solo se ALLOW_REGISTRATION=true in .env. Dopo la creazione del primo Admin, modifica il file .env e imposta ALLOW_REGISTRATION=false.</p>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $er) echo '<div>'.e($er).'</div>';?></div>
  <?php endif; ?>
  <form method="post" class="card p-3 shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
    <div class="mb-3">
      <label class="form-label">Nome e cognome</label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" minlength="8" required>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Conferma password</label>
        <input type="password" name="password2" class="form-control" minlength="8" required>
      </div>
    </div>
    <button class="btn btn-primary w-100" type="submit">Crea Admin</button>
  </form>
  <div class="alert alert-warning mt-3">
    Suggerimento: dopo la registrazione, apri <code>.env</code> e imposta <strong>ALLOW_REGISTRATION=false</strong>.
  </div>
</div>
<?php include __DIR__ . '/../inc/layout/footer.php'; ?>