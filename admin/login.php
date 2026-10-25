
<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['admin_user']['id'])) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyAdminCsrfToken($_POST['csrf_token'] ?? null);

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, email, password_hash, role, is_active
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (
            $user &&
            (int) $user['is_active'] === 1 &&
            password_verify($password, $user['password_hash'])
        ) {
            session_regenerate_id(true);

            $_SESSION['admin_user'] = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];

            unset($_SESSION['admin_csrf_token']);

            header('Location: ' . url('admin/dashboard.php'));
            exit;
        }

        $error = 'Invalid credentials or inactive account.';
    }
}

function loginEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Login | BIST Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid;
            place-items: center; padding: 1.25rem;
            background: #f3f6fb; color: #1b1b1b;
            font-family: "DM Sans", sans-serif;
        }
        .login-card {
            width: 100%; max-width: 27rem; background: white;
            padding: 2.25rem; border-radius: 1.25rem;
            box-shadow: 0 1rem 3rem #0b2e5914;
            border: 1px solid #e4eaf2;
        }
        .brand {
            background: #0b2e59; color: white; padding: 1.1rem;
            border-radius: .9rem; margin-bottom: 1.75rem;
        }
        .brand strong { display: block; font: 800 1.25rem Manrope, sans-serif; }
        .brand span { display: block; margin-top: .35rem; color: #dce7f5; font-size: .85rem; }
        h1 { font: 800 1.65rem Manrope, sans-serif; margin: 0 0 .5rem; }
        .muted { color: #64748b; line-height: 1.6; margin-bottom: 1.5rem; }
        label { display: block; font-weight: 700; margin: 1rem 0 .45rem; }
        input {
            width: 100%; padding: .85rem .9rem; border: 1px solid #cbd5e1;
            border-radius: .65rem; font: inherit; background: white;
        }
        input:focus { outline: 3px solid #1e5aa826; border-color: #1e5aa8; }
        button {
            width: 100%; margin-top: 1.5rem; border: 0;
            padding: .95rem; border-radius: .65rem; cursor: pointer;
            color: #0b2e59; background: #f4b400;
            font: 700 1rem "DM Sans", sans-serif;
        }
        button:hover { filter: brightness(.96); }
        .error {
            background: #fff1f0; border: 1px solid #fecaca;
            color: #991b1b; padding: .8rem; border-radius: .6rem;
        }
        .foot { color: #64748b; text-align: center; font-size: .82rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
<main class="login-card">
    <div class="brand">
        <strong>BIST Admin</strong>
        <span>Bengal Institute of Science &amp; Technology</span>
    </div>

    <h1>Staff sign in</h1>
    <p class="muted">Sign in with your authorized institutional account to manage website content.</p>

    <?php if ($error !== ''): ?>
        <p class="error"><?= loginEscape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="">
        <input type="hidden" name="csrf_token"
               value="<?= loginEscape(adminCsrfToken()) ?>">

        <label for="email">Email address</label>
        <input id="email" name="email" type="email"
               autocomplete="username" required maxlength="190"
               value="<?= loginEscape($email) ?>">

        <label for="password">Password</label>
        <input id="password" name="password" type="password"
               autocomplete="current-password" required>

        <button type="submit">Sign in securely</button>
    </form>

    <p class="foot">Authorized personnel only · BIST Website Administration</p>
</main>
</body>
</html>
