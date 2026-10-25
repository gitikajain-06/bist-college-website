
<?php

$host = '127.0.0.1';
$database = 'bist_college';
$username = 'root';
$password = ''; // Default XAMPP password; change if yours is different.

$dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log(
        'BIST database connection failed: ' . $exception->getMessage()
    );

    http_response_code(500);
    exit('The website is temporarily unavailable. Please try again later.');
}
