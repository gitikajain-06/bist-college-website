
<?php
// admin/includes/auth.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$isHttps = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== ''
    && $_SERVER['HTTPS'] !== 'off';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    session_start();
}

require_once __DIR__ . '/csrf.php';

function requireAdminLogin(): void
{
    if (empty($_SESSION['admin_user']['id'])) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
}

function requireAdminRole(array $allowedRoles): void
{
    requireAdminLogin();

    $role = $_SESSION['admin_user']['role'] ?? '';

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        exit('Access denied. You do not have permission.');
    }
}

function currentAdmin(): array
{
    return $_SESSION['admin_user'] ?? [];
}

function verifyCurrentAdmin(PDO $pdo): void
{
    requireAdminLogin();

    $stmt = $pdo->prepare(
        'SELECT id, name, email, role, is_active
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['admin_user']['id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        $_SESSION = [];
        session_destroy();

        header('Location: ' . url('admin/login.php'));
        exit;
    }

    $_SESSION['admin_user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}
