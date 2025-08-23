<?php
declare(strict_types=1);

function env(string $key, ?string $default=null): ?string {
  $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
  if ($val === null) return $default;
  return $val;
}

// Loader .env minimalista
function load_env_file(string $path): void {
  if (!is_file($path)) return;
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    if (!str_contains($line, '=')) continue;
    [$k,$v] = explode('=', $line, 2);
    $k = trim($k); $v = trim($v);
    $v = trim($v, "\"'");
    $_ENV[$k] = $v; $_SERVER[$k] = $v;
  }
}

function is_logged_in(): bool { return isset($_SESSION['user']); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function current_user_id(): ?int { return $_SESSION['user']['id'] ?? null; }

function require_login(): void {
  if (!is_logged_in()) { header('Location: /auth/login.php'); exit; }
}

function login(string $email, string $password): bool {
  global $pdo;
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email=:e AND deleted_at IS NULL LIMIT 1");
  $stmt->execute([':e'=>$email]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($u && password_verify($password, $u['password_hash'])) {
    $_SESSION['user'] = ['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];
    log_event('user',$u['id'],'login',[]);
    return true;
  }
  return false;
}

function register_admin(string $name, string $email, string $password): bool {
  global $pdo;
  $hash = password_hash($password, PASSWORD_BCRYPT);
  $stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,created_at) VALUES (:n,:e,:p,'admin',NOW())");
  return $stmt->execute([':n'=>$name, ':e'=>$email, ':p'=>$hash]);
}

// CSRF
function get_csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function verify_csrf_token(?string $t): bool {
  return is_string($t) && hash_equals($_SESSION['csrf_token'] ?? '', $t);
}
