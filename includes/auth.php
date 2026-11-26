<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

// Re-reads the user from the DB on every request, so blocking or deleting
// an account takes effect immediately, not at next login.
function current_user(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $st = db()->prepare('SELECT id, name, email, role, is_blocked FROM users WHERE id = ?');
        $st->execute([$_SESSION['user_id']]);
        $row = $st->fetch();
        if ($row && !$row['is_blocked']) {
            $user = $row;
        } else {
            logout_user();
        }
    }
    return $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}

function require_role(string $role): array {
    $u = require_login();
    if ($u['role'] !== $role) {
        http_response_code(403);
        exit('403 Forbidden');
    }
    return $u;
}

function login_user(array $user): void {
    session_regenerate_id(true);               // prevents session fixation
    $_SESSION['user_id'] = (int)$user['id'];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}