
<?php

require_once __DIR__ . '/../config/database.php';

if (
    !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
) {
    http_response_code(403);
    exit('This password reset page is available only from the local computer.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['reset_csrf'])) {
    $_SESSION['reset_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['reset_csrf'], $token)
    ) {
        http_response_code(403);
        exit('Invalid security token. Reload the page and try again.');
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid Super Admin email address.';
    } elseif (!is_string($password) || strlen($password) < 12) {
        $message = 'The new password must contain at least 12 characters.';
    } elseif ($password !== $confirm) {
        $message = 'The passwords do not match.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM users
                 WHERE email = ? AND role = 'super_admin'
                 LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $message = 'No Super Admin account was found for that email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $update = $pdo->prepare(
                    "UPDATE users
                     SET password_hash = ?, is_active = 1
                     WHERE id = ? AND role = 'super_admin'"
                );
                $update->execute([$hash, $user['id']]);

                if ($update->rowCount() === 1) {
                    $success = true;
                    $message = 'Password reset successfully. You can now sign in.';
                    unset($_SESSION['reset_csrf']);
                } else {
                    $message = 'The password was not changed. Please retry.';
                }
            }
        } catch (PDOException $exception) {
            error_log('BIST password reset failed: ' . $exception->getMessage());
            $message = 'A database error occurred. Check the PHP error log.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Super Admin Password | BIST</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1rem;
            background: #f5f7fb;
            color: #1b1b1b;
            font-family: Arial, sans-serif;
        }
        .card {
            width: 100%;
            max-width: 440px;
            padding: 2rem;
            border: 1px solid #e3e9f2;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 12px 36px #0b2e5910;
        }
        h1 { margin-top: 0; color: #0b2e59; font-size: 1.5rem; }
        p { color: #64748b; line-height: 1.6; }
        label { display: block; margin-top: 1rem; font-weight: 700; }
        input {
            width: 100%;
            margin-top: .45rem;
            padding: .8rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font: inherit;
        }
        button {
            width: 100%;
            margin-top: 1.4rem;
            padding: .9rem;
            border: 0;
            border-radius: 8px;
            background: #0b2e59;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }
        .message {
            padding: .8rem;
            border-radius: 8px;
            background: #fff4dc;
            color: #714b00;
            overflow-wrap: anywhere;
        }
        .success { background: #e8f7ef; color: #12663e; }
    </style>
</head>
<body>
<main class="card">
    <h1>Reset Super Admin Password</h1>
    <p>Enter the email address of your existing Super Admin account and choose a new password.</p>

    <?php if ($message !== ''): ?>
        <p class="message <?= $success ? 'success' : '' ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if (!$success): ?>
        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['reset_csrf'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="email">Super Admin email</label>
            <input
                id="email"
                type="email"
                name="email"
                maxlength="190"
                required
                autocomplete="username"
            >

            <label for="password">New password</label>
            <input
                id="password"
                type="password"
                name="password"
                minlength="12"
                required
                autocomplete="new-password"
            >

            <label for="confirm_password">Confirm new password</label>
            <input
                id="confirm_password"
                type="password"
                name="confirm_password"
                minlength="12"
                required
                autocomplete="new-password"
            >

            <button type="submit">Reset Password</button>
        </form>
    <?php else: ?>
        <p><a href="login.php">Go to Admin Login</a></p>
    <?php endif; ?>
</main>
</body>
</html>
