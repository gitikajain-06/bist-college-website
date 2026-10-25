
<?php
// admin/includes/csrf.php

function adminCsrfToken(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function verifyAdminCsrfToken(?string $token): void
{
    $stored = $_SESSION['admin_csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !is_string($stored) ||
        $stored === '' ||
        !hash_equals($stored, $token)
    ) {
        http_response_code(403);
        exit('Security token expired or invalid. Please reload and retry.');
    }
}
