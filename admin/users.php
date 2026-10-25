<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

requireAdminLogin();
verifyCurrentAdmin($pdo);

$admin = currentAdmin();

// Only Super Admin can manage staff accounts.
if (($admin['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    exit('Access denied. Only the Super Admin can manage user accounts.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['users_csrf_token'])) {
    $_SESSION['users_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['users_csrf_token'];
$message = '';
$error = '';

$allowedRoles = [
    'administrator' => 'Administrator',
    'editor' => 'Editor',
    'publisher' => 'Publisher',
    'super_admin' => 'Super Admin',
];

function escapeUserPage(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $postedToken)) {
        $error = 'Security verification failed. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === 'create') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $password = (string) ($_POST['password'] ?? '');
                $role = (string) ($_POST['role'] ?? '');

                if ($name === '' || mb_strlen($name) > 120) {
                    throw new RuntimeException(
                        'Enter a name containing no more than 120 characters.'
                    );
                }

                if (
                    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                    strlen($email) > 190
                ) {
                    throw new RuntimeException('Enter a valid email address.');
                }

                if (
                    !isset($allowedRoles[$role])
                ) {
                    throw new RuntimeException('Select a valid account role.');
                }

                if (strlen($password) < 12 || strlen($password) > 72) {
                    throw new RuntimeException(
                        'Use a password of 12–72 characters.'
                    );
                }

                if (password_get_info($password)['algo'] !== null) {
                    throw new RuntimeException(
                        'Please enter a plain-text password, not a password hash.'
                    );
                }

                $check = $pdo->prepare(
                    'SELECT id FROM users WHERE email = :email LIMIT 1'
                );
                $check->execute(['email' => $email]);

                if ($check->fetch()) {
                    throw new RuntimeException(
                        'An account with this email already exists.'
                    );
                }

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $insert = $pdo->prepare(
                    'INSERT INTO users
                        (name, email, password_hash, role, is_active)
                     VALUES
                        (:name, :email, :password_hash, :role, 1)'
                );

                $insert->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'role' => $role,
                ]);

                $_SESSION['users_csrf_token'] = bin2hex(random_bytes(32));

                header('Location: users.php?created=1');
                exit;
            }

            if ($action === 'toggle_status') {
                $userId = filter_var(
                    $_POST['user_id'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($userId === false || $userId === null) {
                    throw new RuntimeException('Invalid user ID.');
                }

                if ((int) $userId === (int) $admin['id']) {
                    throw new RuntimeException(
                        'You cannot deactivate your own account here.'
                    );
                }

                $findUser = $pdo->prepare(
                    'SELECT id, role, is_active
                     FROM users WHERE id = :id LIMIT 1'
                );
                $findUser->execute(['id' => $userId]);
                $target = $findUser->fetch(PDO::FETCH_ASSOC);

                if (!$target) {
                    throw new RuntimeException('User account not found.');
                }

                // Prevent disabling another Super Admin from this page.
                if ($target['role'] === 'super_admin') {
                    throw new RuntimeException(
                        'Super Admin accounts cannot be changed here.'
                    );
                }

                $newStatus = (int) $target['is_active'] === 1 ? 0 : 1;

                $update = $pdo->prepare(
                    'UPDATE users
                     SET is_active = :status
                     WHERE id = :id'
                );
                $update->execute([
                    'status' => $newStatus,
                    'id' => $userId,
                ]);

                $_SESSION['users_csrf_token'] = bin2hex(random_bytes(32));

                header('Location: users.php?status=updated');
                exit;
            }

            throw new RuntimeException('Invalid request.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'This email address is already registered.';
            } else {
                error_log('BIST user management database error: ' . $e->getMessage());
                $error = 'The operation failed. Please check the server error log.';
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['created'])) {
    $message = 'New staff account created successfully.';
} elseif (isset($_GET['status'])) {
    $message = 'Account status updated successfully.';
}

$users = $pdo->query(
    'SELECT id, name, email, role, is_active, created_at
     FROM users
     ORDER BY created_at DESC, id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$totalUsers = count($users);
$activeUsers = 0;
$inactiveUsers = 0;

foreach ($users as $user) {
    if ((int) $user['is_active'] === 1) {
        $activeUsers++;
    } else {
        $inactiveUsers++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | BIST College CMS</title>
    <style>
        :root {
            --navy: #0B2E59;
            --blue: #1E5AA8;
            --gold: #F4B400;
            --background: #F7F9FC;
            --text: #1B1B1B;
            --muted: #667085;
            --border: #E3E8EF;
            --white: #FFFFFF;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        .topbar {
            background: var(--navy);
            color: white;
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .brand { font-size: 18px; font-weight: 700; }
        .brand small {
            display: block;
            font-size: 12px;
            font-weight: 400;
            opacity: .8;
        }

        .topbar a { color: white; text-decoration: none; }

        .container {
            width: min(1440px, 100%);
            margin: auto;
            padding: 28px;
        }

        .heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        h1 { margin: 0 0 6px; font-size: 28px; color: var(--navy); }
        h2 { margin: 0 0 18px; font-size: 18px; color: var(--navy); }
        p { margin-top: 0; }
        .muted { color: var(--muted); }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 8px rgba(16, 24, 40, .03);
            min-width: 0;
        }

        .stat-label { color: var(--muted); font-size: 13px; }
        .stat-number { font-size: 30px; font-weight: 700; color: var(--navy); }

        .layout {
            display: grid;
            grid-template-columns: minmax(280px, .8fr) minmax(0, 1.7fr);
            gap: 22px;
            align-items: start;
        }

        label {
            display: block;
            margin: 14px 0 6px;
            font-weight: 600;
        }

        input, select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #CCD5E0;
            border-radius: 7px;
            font: inherit;
            background: white;
            color: var(--text);
        }

        input:focus, select:focus {
            outline: 2px solid #BBD5F7;
            border-color: var(--blue);
        }

        button, .button {
            display: inline-block;
            border: 0;
            border-radius: 7px;
            padding: 10px 14px;
            background: var(--blue);
            color: white;
            font: inherit;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .submit { width: 100%; margin-top: 20px; padding: 12px; }
        .secondary { background: #EEF3FA; color: var(--navy); }
        .warning { background: #FFF1F0; color: #B42318; }
        .success {
            padding: 12px 14px;
            border-radius: 8px;
            background: #ECFDF3;
            color: #067647;
            margin-bottom: 18px;
        }

        .error {
            padding: 12px 14px;
            border-radius: 8px;
            background: #FEF3F2;
            color: #B42318;
            margin-bottom: 18px;
        }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            text-align: left;
            padding: 13px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            background: #FAFBFD;
        }

        .person { font-weight: 700; color: var(--navy); }
        .pill {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .active { background: #ECFDF3; color: #067647; }
        .inactive { background: #FEF3F2; color: #B42318; }
        .role { background: #EEF4FF; color: #3538CD; }
        .action-form { display: inline; }
        .action-form button { padding: 7px 10px; font-size: 12px; }
        .empty { padding: 28px; text-align: center; color: var(--muted); }

        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .container { padding: 16px; }
            .topbar { padding: 16px; }
            h1 { font-size: 23px; }
            .stats { grid-template-columns: 1fr; gap: 10px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">
        Bengal Institute of Science &amp; Technology
        <small>College Administration · Content Management System</small>
    </div>
    <a href="dashboard.php">← Back to Dashboard</a>
</header>

<main class="container">
    <div class="heading">
        <div>
            <h1>User Management</h1>
            <p class="muted">Register staff members and manage their account access.</p>
        </div>
        <span class="pill role">Super Admin</span>
    </div>

    <?php if ($message !== ''): ?>
        <div class="success" role="status">
            <?= escapeUserPage($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error" role="alert">
            <?= escapeUserPage($error) ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <div class="card">
            <div class="stat-label">Total Accounts</div>
            <div class="stat-number"><?= $totalUsers ?></div>
        </div>
        <div class="card">
            <div class="stat-label">Active Accounts</div>
            <div class="stat-number"><?= $activeUsers ?></div>
        </div>
        <div class="card">
            <div class="stat-label">Inactive Accounts</div>
            <div class="stat-number"><?= $inactiveUsers ?></div>
        </div>
    </section>

    <div class="layout">
        <section class="card">
            <h2>Register a New Person</h2>
            <p class="muted">Create a staff account with a specific access role.</p>

            <form method="post" action="users.php" autocomplete="off">
                <input type="hidden" name="csrf_token"
                       value="<?= escapeUserPage($csrfToken) ?>">
                <input type="hidden" name="action" value="create">

                <label for="name">Full Name</label>
                <input id="name" name="name" type="text"
                       maxlength="120" required autocomplete="name"
                       placeholder="Enter full name">

                <label for="email">Email Address</label>
                <input id="email" name="email" type="email"
                       maxlength="190" required autocomplete="email"
                       placeholder="name@college.edu.in">

                <label for="password">Temporary Password</label>
                <input id="password" name="password" type="password"
                       minlength="12" maxlength="72" required
                       autocomplete="new-password"
                       placeholder="At least 12 characters">
                <p class="muted">Share passwords securely. Password reset functionality can be added separately.</p>

                <label for="role">Assign Role</label>
                <select id="role" name="role" required>
                    <option value="">Select a role</option>
                    <?php foreach ($allowedRoles as $value => $label): ?>
                        <option value="<?= escapeUserPage($value) ?>">
                            <?= escapeUserPage($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="submit" type="submit">Create Staff Account</button>
            </form>
        </section>

        <section class="card">
            <h2>Registered Users</h2>
            <p class="muted">Review account roles and current access status.</p>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Person</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="5" class="empty">
                                No user accounts found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="person">
                                        <?= escapeUserPage($user['name']) ?>
                                    </div>
                                    <div class="muted">
                                        <?= escapeUserPage($user['email']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="pill role">
                                        <?= escapeUserPage(
                                            $allowedRoles[$user['role']] ?? $user['role']
                                        ) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="pill <?= (int) $user['is_active'] === 1 ? 'active' : 'inactive' ?>">
                                        <?= (int) $user['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= escapeUserPage(
                                        $user['created_at'] ?? '—'
                                    ) ?>
                                </td>
                                <td>
                                    <?php if (
                                        $user['role'] !== 'super_admin'
                                        && (int) $user['id'] !== (int) $admin['id']
                                    ): ?>
                                        <form class="action-form" method="post"
                                              action="users.php"
                                              onsubmit="return confirm('Change this user account status?');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= escapeUserPage($csrfToken) ?>">
                                            <input type="hidden" name="action"
                                                   value="toggle_status">
                                            <input type="hidden" name="user_id"
                                                   value="<?= (int) $user['id'] ?>">
                                            <button type="submit"
                                                class="<?= (int) $user['is_active'] === 1 ? 'warning' : 'secondary' ?>">
                                                <?= (int) $user['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">Protected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
</body>
</html>
