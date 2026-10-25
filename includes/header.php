
<?php
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Home';
$fullTitle = $pageTitle . ' | Bengal Institute of Science & Technology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0B2E59">

    <title><?= e($fullTitle) ?></title>

    <meta name="description"
          content="Discover Bengal Institute of Science & Technology, Purulia. Explore academic programmes, admissions, student resources, notices and campus activities.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
          rel="stylesheet">

    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>

<?php require __DIR__ . '/navbar.php'; ?>
