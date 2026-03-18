<?php
if (!defined('APP_RUNNING')) exit;

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $s = getDB()->prepare('SELECT id,name,email,phone,role,theme_pref FROM users WHERE id=?');
    $s->execute([$_SESSION['user_id']]);
    return $s->fetch() ?: null;
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function isStaff(): bool {
    return in_array($_SESSION['user_role'] ?? '', ['admin','staff'], true);
}

function isCustomer(): bool {
    return ($_SESSION['user_role'] ?? '') === 'customer';
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . url('login.php')); exit;
    }
}

function requireStaff(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . url('login.php')); exit;
    }
    if (!isStaff()) {
        header('Location: ' . url('shop.php')); exit;
    }
}

function requireAdmin(): void {
    requireStaff();
    if (!isAdmin()) {
        header('Location: ' . url('admin/dashboard.php')); exit;
    }
}

function verifyCsrf(): bool {
    return isset($_POST['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

// ── Login ─────────────────────────────────────────────────────────────────
function handleLogin(): ?string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
    if (!verifyCsrf()) return 'Invalid request.';

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $pass  = $_POST['password'] ?? '';
    if (!$email) return 'Enter a valid email address.';
    if (!$pass)  return 'Enter your password.';

    $s = getDB()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
    $s->execute([$email]);
    $user = $s->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        return 'Invalid email or password.';
    }

    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];

    $redirect = $_SESSION['redirect_after_login']
        ?? (isStaff() ? url('admin/dashboard.php') : url('shop.php'));
    unset($_SESSION['redirect_after_login']);
    header("Location: $redirect"); exit;
}

// ── Register ──────────────────────────────────────────────────────────────
function handleRegister(): ?string {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
    if (!verifyCsrf()) return 'Invalid request.';

    $name    = trim($_POST['name']    ?? '');
    $email   = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone   = trim($_POST['phone']   ?? '');
    $pass    = $_POST['password']     ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($name) < 2)  return 'Name must be at least 2 characters.';
    if (!$email)             return 'Enter a valid email address.';
    if (strlen($pass) < 6)  return 'Password must be at least 6 characters.';
    if ($pass !== $confirm)  return 'Passwords do not match.';

    $pdo = getDB();
    $chk = $pdo->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1');
    $chk->execute([$email]);
    if ($chk->fetch()) return 'That email is already registered.';

    $ins = $pdo->prepare(
        'INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,?)'
    );
    $ins->execute([
        htmlspecialchars(strip_tags($name)),
        $email,
        $phone ?: null,
        password_hash($pass, PASSWORD_BCRYPT),
        'customer',
    ]);

    session_regenerate_id(true);
    $_SESSION['user_id']   = $pdo->lastInsertId();
    $_SESSION['user_role'] = 'customer';

    flashSet('success', 'Welcome! Your account has been created.');
    header('Location: ' . url('shop.php')); exit;
}

// ── Logout ────────────────────────────────────────────────────────────────
function handleLogout(): void {
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', [
            'expires'  => time() - 3600, 'path' => '/',
            'secure'   => ($_SERVER['HTTPS'] ?? 'off') === 'on',
            'httponly' => true, 'samesite' => 'Strict',
        ]);
    }
    header('Location: ' . url('login.php')); exit;
}
