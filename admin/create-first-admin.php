
<?php
// require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/includes/auth.php';

// Keep the existing Super Admin setup logic below this line.

// header('Content-Type: text/plain; charset=UTF-8');

$count = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'super_admin'"
)->fetchColumn();

if ($count > 0) {
    http_response_code(403);
    exit("A Super Admin already exists. Setup is locked.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><title>Initial BIST Admin Setup</title></head>
    <body>
    <h1>Create the first Super Admin</h1>
    <form method="post">
        <label>Name <input name="name" required maxlength="120"></label><br><br>
        <label>Institutional email <input name="email" type="email" required maxlength="190"></label><br><br>
        <label>Password (minimum 12 characters)
            <input name="password" type="password" required minlength="12">
        </label><br><br>
        <button type="submit">Create Super Admin</button>
    </form>
    </body>
    </html>
    <?php
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if (
    $name === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($password) < 12
) {
    http_response_code(422);
    exit("Enter a valid name, email and password of at least 12 characters.");
}

try {
    $pdo->beginTransaction();

    // Lock the user table's current rows to reduce setup races.
    $pdo->query("SELECT id FROM users FOR UPDATE")->fetchAll();

    $count = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'super_admin'"
    )->fetchColumn();

    if ($count > 0) {
        $pdo->rollBack();
        http_response_code(403);
        exit("A Super Admin already exists. Setup is locked.");
    }

    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role, is_active)
         VALUES (?, ?, ?, 'super_admin', 1)"
    );

    $stmt->execute([
        $name,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
    ]);

    $pdo->commit();

    echo "Super Admin created successfully. Delete create-first-admin.php now.";
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('BIST initial admin setup failed: ' . $exception->getMessage());
    http_response_code(500);
    echo "Could not create the account. Check the email and database configuration.";
}
