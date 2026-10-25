
<?php

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/auth.php';

verifyCurrentAdmin($pdo);

$admin = currentAdmin();

if (!function_exists('adminH')) {
    function adminH($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

$roleLabels = [
    'super_admin' => 'Super Administrator',
    'administrator' => 'Administrator',
    'editor' => 'Content Editor',
    'publisher' => 'Publisher',
];

$role = $admin['role'] ?? 'editor';
$roleLabel = $roleLabels[$role] ?? 'Staff Member';

$stats = [
    'notices' => 0,
    'events' => 0,
    'messages' => 0,
    'gallery' => 0,
    'staff' => 0,
];

$recentNotices = [];

try {
    $stats['notices'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM notices WHERE status = 'published'"
    )->fetchColumn();

    $stats['events'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM events WHERE status = 'published'"
    )->fetchColumn();

    $stats['messages'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM site_messages WHERE is_published = 1"
    )->fetchColumn();

    $stats['gallery'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM gallery_images WHERE is_published = 1"
    )->fetchColumn();

    if ($role === 'super_admin') {
        $stats['staff'] = (int) $pdo->query(
            'SELECT COUNT(*) FROM users'
        )->fetchColumn();
    }

    $recentNotices = $pdo->query(
        "SELECT id, title, category, status, is_urgent, updated_at
         FROM notices
         ORDER BY updated_at DESC
         LIMIT 6"
    )->fetchAll();

} catch (PDOException $exception) {
    error_log('BIST dashboard data error: ' . $exception->getMessage());
}

$navigation = [
    [
        'label' => 'Overview',
        'items' => [
            [
                'title' => 'Dashboard',
                'url' => 'admin/dashboard.php',
                'icon' => '◫',
            ],
        ],
    ],
    [
        'label' => 'Content management',
        'items' => [
            [
                'title' => 'Notices & circulars',
                'url' => 'admin/notices.php',
                'icon' => '▤',
            ],
            [
                'title' => 'Leadership messages',
                'url' => 'admin/messages.php',
                'icon' => '▣',
            ],
            [
                'title' => 'Events',
                'url' => 'admin/events.php',
                'icon' => '▦',
            ],
            [
                'title' => 'Photo gallery',
                'url' => 'admin/gallery.php',
                'icon' => '▧',
            ],
        ],
    ],
];

if ($role === 'super_admin') {
    $navigation[] = [
        'label' => 'Administration',
        'items' => [
            [
                'title' => 'Staff & permissions',
                'url' => 'admin/users.php',
                'icon' => '♙',
            ],
        ],
    ];
}

$firstName = trim(explode(' ', $admin['name'] ?? 'Administrator')[0]);

// Always calculate the greeting using Indian Standard Time.
$indiaTime = new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Kolkata')
);

$hour = (int) $indiaTime->format('G');

if ($hour >= 5 && $hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$today = $indiaTime->format('l, d F Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard | BIST CMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --navy: #0b2e59;
            --blue: #1e5aa8;
            --gold: #f4b400;
            --ink: #182b43;
            --muted: #728096;
            --line: #e8edf4;
            --canvas: #f5f7fb;
            --white: #ffffff;
            --green: #16845b;
            --radius: 1rem;
            --sidebar-width: 16.5rem;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--canvas);
            color: var(--ink);
            font-family: "DM Sans", sans-serif;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font: inherit;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 20;
            display: flex;
            width: var(--sidebar-width);
            flex-direction: column;
            background: var(--navy);
            color: #e8eff9;
        }

        .brand {
            display: flex;
            min-height: 6.1rem;
            align-items: center;
            gap: 0.85rem;
            padding: 1.25rem 1.35rem;
            border-bottom: 1px solid rgb(255 255 255 / 10%);
        }

        .brand-mark {
            display: grid;
            width: 2.75rem;
            height: 2.75rem;
            flex-shrink: 0;
            place-items: center;
            border-radius: 0.8rem;
            background: var(--gold);
            color: var(--navy);
            font-family: "Manrope", sans-serif;
            font-size: 1rem;
            font-weight: 800;
        }

        .brand-name {
            color: white;
            font-family: "Manrope", sans-serif;
            font-size: 0.96rem;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .brand-caption {
            margin-top: 0.2rem;
            color: #afc3df;
            font-size: 0.68rem;
        }

        .sidebar-label {
            margin: 1.7rem 1.4rem 0.65rem;
            color: #91a9c9;
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.11em;
            text-transform: uppercase;
        }

        .nav-list {
            display: grid;
            gap: 0.3rem;
            padding: 0 0.8rem;
        }

        .nav-link {
            display: flex;
            min-height: 2.8rem;
            align-items: center;
            gap: 0.8rem;
            padding: 0.65rem 0.8rem;
            border: 1px solid transparent;
            border-radius: 0.65rem;
            color: #d3dfef;
            font-size: 0.82rem;
            transition: background 160ms ease, color 160ms ease;
        }

        .nav-link:hover {
            background: rgb(255 255 255 / 8%);
            color: white;
        }

        .nav-link.active {
            border-color: rgb(255 255 255 / 8%);
            background: #1e4779;
            color: white;
            font-weight: 700;
        }

        .nav-icon {
            display: grid;
            width: 1.45rem;
            flex-shrink: 0;
            place-items: center;
            color: #b8cbe4;
            font-size: 1.1rem;
        }

        .nav-link.active .nav-icon {
            color: var(--gold);
        }

        .sidebar-bottom {
            margin-top: auto;
            padding: 1rem 1rem 1.15rem;
            border-top: 1px solid rgb(255 255 255 / 10%);
        }

        .institution-card {
            padding: 0.9rem;
            border: 1px solid rgb(255 255 255 / 12%);
            border-radius: 0.8rem;
            background: rgb(255 255 255 / 4%);
        }

        .institution-card strong {
            display: block;
            color: white;
            font-size: 0.78rem;
        }

        .institution-card p {
            margin: 0.35rem 0 0;
            color: #b5c8e1;
            font-size: 0.7rem;
        }

        .main {
            min-height: 100vh;
            margin-left: var(--sidebar-width);
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            min-height: 5.1rem;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.8rem 2rem;
            border-bottom: 1px solid var(--line);
            background: rgb(255 255 255 / 96%);
            backdrop-filter: blur(12px);
        }

        .topbar-left,
        .topbar-right,
        .profile {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .topbar-title {
            margin: 0;
            font-family: "Manrope", sans-serif;
            font-size: 0.92rem;
            font-weight: 800;
        }

        .topbar-subtitle {
            margin-top: 0.12rem;
            color: var(--muted);
            font-size: 0.73rem;
        }

        .icon-button {
            display: none;
            width: 2.5rem;
            height: 2.5rem;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            border-radius: 0.65rem;
            background: white;
            color: var(--navy);
            cursor: pointer;
        }

        .date-label {
            color: var(--muted);
            font-size: 0.78rem;
        }

        .profile-avatar {
            display: grid;
            width: 2.5rem;
            height: 2.5rem;
            place-items: center;
            border: 1px solid #d8e5f6;
            border-radius: 50%;
            background: #edf3fc;
            color: var(--navy);
            font-weight: 800;
        }

        .profile-name {
            color: var(--ink);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .profile-role {
            margin-top: 0.1rem;
            color: var(--muted);
            font-size: 0.7rem;
        }

        .logout-form {
            margin-left: 0.3rem;
        }

        .logout-button {
            padding: 0.6rem 0.85rem;
            border: 1px solid var(--line);
            border-radius: 0.65rem;
            background: white;
            color: #52637a;
            cursor: pointer;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .logout-button:hover {
            border-color: #e7b8b8;
            background: #fff7f7;
            color: #b42318;
        }

        .content {
            width: 100%;
            max-width: 110rem;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 1.8rem;
        }

        .eyebrow {
            margin: 0 0 0.45rem;
            color: var(--blue);
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .welcome h1 {
            margin: 0;
            color: var(--navy);
            font-family: "Manrope", sans-serif;
            font-size: clamp(1.6rem, 2.5vw, 2.15rem);
            font-weight: 800;
            letter-spacing: -0.045em;
            line-height: 1.25;
        }

        .welcome p {
            max-width: 38rem;
            margin: 0.65rem 0 0;
            color: var(--muted);
            font-size: 0.88rem;
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 0.8rem;
            border: 1px solid #dbe6f5;
            border-radius: 999px;
            background: white;
            color: var(--navy);
            font-size: 0.74rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .role-dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 50%;
            background: var(--green);
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.95rem;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--navy);
            font-family: "Manrope", sans-serif;
            font-size: 1rem;
            font-weight: 800;
        }

        .section-heading p {
            margin: 0.2rem 0 0;
            color: var(--muted);
            font-size: 0.76rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            position: relative;
            min-width: 0;
            overflow: hidden;
            padding: 1.25rem;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: white;
            box-shadow: 0 2px 8px rgb(21 44 76 / 2%);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.7rem;
        }

        .stat-label {
            color: #66758b;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .stat-icon {
            display: grid;
            width: 2.45rem;
            height: 2.45rem;
            flex-shrink: 0;
            place-items: center;
            border-radius: 0.75rem;
            background: #edf3fc;
            color: var(--blue);
            font-size: 1.15rem;
        }

        .stat-icon.gold {
            background: #fff6d9;
            color: #8a6500;
        }

        .stat-icon.green {
            background: #e8f7f0;
            color: #147650;
        }

        .stat-icon.purple {
            background: #f0ecff;
            color: #6950ba;
        }

        .stat-number {
            margin-top: 1rem;
            color: var(--navy);
            font-family: "Manrope", sans-serif;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            line-height: 1.1;
        }

        .stat-foot {
            margin-top: 0.45rem;
            color: #8290a3;
            font-size: 0.72rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(17rem, 0.9fr);
            align-items: start;
            gap: 1.25rem;
        }

        .panel {
            min-width: 0;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: white;
            box-shadow: 0 2px 8px rgb(21 44 76 / 2%);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.2rem 1.3rem;
            border-bottom: 1px solid var(--line);
        }

        .panel-header h2 {
            margin: 0;
            color: var(--navy);
            font-family: "Manrope", sans-serif;
            font-size: 0.91rem;
            font-weight: 800;
        }

        .panel-header p {
            margin: 0.25rem 0 0;
            color: var(--muted);
            font-size: 0.73rem;
        }

        .text-link {
            color: var(--blue);
            font-size: 0.75rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .text-link:hover {
            text-decoration: underline;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 0.85rem 1.2rem;
            background: #fafbfd;
            color: #78869a;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.045em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 1rem 1.2rem;
            border-top: 1px solid #eef1f6;
            color: #65748a;
            font-size: 0.76rem;
            vertical-align: middle;
        }

        .notice-title {
            max-width: 19rem;
            color: #203651;
            font-size: 0.79rem;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .notice-category {
            display: inline-block;
            margin-top: 0.25rem;
            color: #8794a6;
            font-size: 0.69rem;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
            background: #f0f3f8;
            color: #5e6e84;
            font-size: 0.66rem;
            font-weight: 800;
            text-transform: capitalize;
        }

        .status.published {
            background: #e8f7ef;
            color: #15764f;
        }

        .status.pending {
            background: #fff5d8;
            color: #8b6500;
        }

        .status.draft {
            background: #edf1f7;
            color: #596b82;
        }

        .status.archived {
            background: #f2eef5;
            color: #776780;
        }

        .urgent-label {
            display: inline-block;
            margin-top: 0.35rem;
            color: #b42318;
            font-size: 0.68rem;
            font-weight: 800;
        }

        .empty-state {
            padding: 2.3rem 1rem;
            color: var(--muted);
            text-align: center;
        }

        .empty-state strong {
            display: block;
            margin-bottom: 0.35rem;
            color: var(--navy);
        }

        .empty-state p {
            margin: 0;
            font-size: 0.78rem;
        }

        .quick-actions {
            display: grid;
            gap: 0.65rem;
            padding: 1rem;
        }

        .action-link {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.9rem;
            border: 1px solid #e8edf4;
            border-radius: 0.8rem;
            background: white;
            transition: border-color 150ms ease, background 150ms ease;
        }

        .action-link:hover {
            border-color: #b8cce9;
            background: #f8faff;
        }

        .action-icon {
            display: grid;
            width: 2.6rem;
            height: 2.6rem;
            flex-shrink: 0;
            place-items: center;
            border-radius: 0.75rem;
            background: #edf3fc;
            color: var(--blue);
            font-size: 1.1rem;
        }

        .action-icon.gold {
            background: #fff6d9;
            color: #8a6500;
        }

        .action-icon.green {
            background: #e8f7f0;
            color: #147650;
        }

        .action-copy {
            min-width: 0;
            flex: 1;
        }

        .action-copy strong {
            display: block;
            color: #203651;
            font-size: 0.78rem;
            font-weight: 800;
        }

        .action-copy span {
            display: block;
            margin-top: 0.2rem;
            color: #8290a3;
            font-size: 0.69rem;
        }

        .action-arrow {
            color: #91a0b3;
            font-size: 1.1rem;
        }

        .role-note {
            margin: 0 1rem 1rem;
            padding: 0.9rem;
            border: 1px solid #dce7f6;
            border-radius: 0.75rem;
            background: #f5f8fe;
        }

        .role-note strong {
            color: var(--navy);
            font-size: 0.76rem;
        }

        .role-note p {
            margin: 0.35rem 0 0;
            color: #687990;
            font-size: 0.72rem;
        }

        .footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 0.7rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
            color: #8491a3;
            font-size: 0.7rem;
        }

        .overlay {
            display: none;
        }

        @media (max-width: 1150px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 760px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 180ms ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .overlay.show {
                position: fixed;
                inset: 0;
                z-index: 15;
                display: block;
                background: rgb(10 24 43 / 45%);
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                min-height: 4.5rem;
                padding: 0.7rem 1rem;
            }

            .icon-button {
                display: flex;
            }

            .date-label,
            .profile-copy {
                display: none;
            }

            .topbar-left {
                gap: 0.65rem;
            }

            .logout-button {
                padding: 0.55rem 0.65rem;
            }

            .content {
                padding: 1.35rem 1rem;
            }

            .welcome {
                flex-direction: column;
                gap: 0.85rem;
            }

            .welcome h1 {
                font-size: 1.65rem;
            }

            .stats-grid {
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.75rem;
            }

            .stat-label {
                font-size: 0.72rem;
            }

            .stat-icon {
                width: 2.1rem;
                height: 2.1rem;
            }

            .panel-header {
                padding: 1rem;
            }

            th,
            td {
                padding: 0.85rem 0.9rem;
            }

            .footer {
                flex-direction: column;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

<div class="overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= adminH(url('admin/dashboard.php')) ?>">
        <span class="brand-mark">B</span>
        <span>
            <span class="brand-name">BIST CMS</span>
            <span class="brand-caption" style="display:block">
                Institutional Administration
            </span>
        </span>
    </a>

    <?php foreach ($navigation as $group): ?>
        <div class="sidebar-label">
            <?= adminH($group['label']) ?>
        </div>

        <nav class="nav-list">
            <?php foreach ($group['items'] as $item): ?>
                <a
                    class="nav-link <?= $item['title'] === 'Dashboard' ? 'active' : '' ?>"
                    href="<?= adminH(url($item['url'])) ?>"
                    <?= $item['title'] === 'Dashboard' ? 'aria-current="page"' : '' ?>
                >
                    <span class="nav-icon" aria-hidden="true">
                        <?= adminH($item['icon']) ?>
                    </span>
                    <span><?= adminH($item['title']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>

    <div class="sidebar-bottom">
        <div class="institution-card">
            <strong>Bengal Institute of Science &amp; Technology</strong>
            <p>Dulmi, Tata Road, Purulia, West Bengal</p>
        </div>
    </div>
</aside>

<main class="main">

    <header class="topbar">
        <div class="topbar-left">
            <button
                class="icon-button"
                id="menuToggle"
                type="button"
                aria-label="Open navigation menu"
                aria-controls="sidebar"
                aria-expanded="false"
            >☰</button>

            <div>
                <h2 class="topbar-title">Administration</h2>
                <div class="topbar-subtitle">Content management system</div>
            </div>
        </div>

        <div class="topbar-right">
            <div class="date-label"><?= adminH($today) ?></div>

            <div class="profile">
                <div class="profile-avatar">
                    <?= adminH(strtoupper(substr($firstName, 0, 1))) ?>
                </div>

                <div class="profile-copy">
                    <div class="profile-name">
                        <?= adminH($admin['name'] ?? 'Administrator') ?>
                    </div>
                    <div class="profile-role"><?= adminH($roleLabel) ?></div>
                </div>
            </div>

            <form
                class="logout-form"
                method="post"
                action="<?= adminH(url('admin/logout.php')) ?>"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= adminH(adminCsrfToken()) ?>"
                >
                <button class="logout-button" type="submit">Sign out</button>
            </form>
        </div>
    </header>

    <div class="content">

        <section class="welcome">
            <div>
                <div class="eyebrow">Institutional CMS · Dashboard</div>
                <h1><?= adminH($greeting) ?>, <?= adminH($firstName) ?>.</h1>
                <p>
                    Manage institutional announcements, leadership messages,
                    events and photographs from one central workspace.
                </p>
            </div>

            <div class="role-pill">
                <span class="role-dot"></span>
                <?= adminH($roleLabel) ?>
            </div>
        </section>

        <section aria-labelledby="overviewHeading">
            <div class="section-heading">
                <div>
                    <h2 id="overviewHeading">Content overview</h2>
                    <p>A snapshot of currently published institutional content.</p>
                </div>
            </div>

            <div class="stats-grid">

                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Published notices</span>
                        <span class="stat-icon">▤</span>
                    </div>
                    <div class="stat-number">
                        <?= number_format($stats['notices']) ?>
                    </div>
                    <div class="stat-foot">Institutional notices and circulars</div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Published events</span>
                        <span class="stat-icon gold">▦</span>
                    </div>
                    <div class="stat-number">
                        <?= number_format($stats['events']) ?>
                    </div>
                    <div class="stat-foot">Events visible on the website</div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Leadership messages</span>
                        <span class="stat-icon green">▣</span>
                    </div>
                    <div class="stat-number">
                        <?= number_format($stats['messages']) ?>
                    </div>
                    <div class="stat-foot">Published presidential and principal messages</div>
                </article>

                <article class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Gallery photographs</span>
                        <span class="stat-icon purple">▧</span>
                    </div>
                    <div class="stat-number">
                        <?= number_format($stats['gallery']) ?>
                    </div>
                    <div class="stat-foot">Published institutional photographs</div>
                </article>

                <?php if ($role === 'super_admin'): ?>
                    <article class="stat-card">
                        <div class="stat-top">
                            <span class="stat-label">Staff accounts</span>
                            <span class="stat-icon">♙</span>
                        </div>
                        <div class="stat-number">
                            <?= number_format($stats['staff']) ?>
                        </div>
                        <div class="stat-foot">Registered CMS user accounts</div>
                    </article>
                <?php endif; ?>

            </div>
        </section>

        <div class="dashboard-grid">

            <section class="panel" aria-labelledby="recentHeading">
                <div class="panel-header">
                    <div>
                        <h2 id="recentHeading">Recent notices</h2>
                        <p>Latest notice records by last update.</p>
                    </div>

                    <a
                        class="text-link"
                        href="<?= adminH(url('admin/notices.php')) ?>"
                    >Manage notices &rarr;</a>
                </div>

                <?php if ($recentNotices): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Notice</th>
                                    <th>Status</th>
                                    <th>Last updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentNotices as $notice): ?>
                                    <tr>
                                        <td>
                                            <div class="notice-title">
                                                <?= adminH($notice['title']) ?>
                                            </div>
                                            <span class="notice-category">
                                                <?= adminH($notice['category']) ?>
                                            </span>

                                            <?php if ((int) $notice['is_urgent'] === 1): ?>
                                                <div class="urgent-label">
                                                    Urgent notice
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="status <?= adminH($notice['status']) ?>">
                                                <?= adminH($notice['status']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= adminH(
                                                date(
                                                    'd M Y',
                                                    strtotime($notice['updated_at'])
                                                )
                                            ) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <strong>No notices to display</strong>
                        <p>Notice records will appear here after they are created.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel" aria-labelledby="actionsHeading">
                <div class="panel-header">
                    <div>
                        <h2 id="actionsHeading">Quick actions</h2>
                        <p>Go directly to a content area.</p>
                    </div>
                </div>

                <div class="quick-actions">

                    <a
                        class="action-link"
                        href="<?= adminH(url('admin/notices.php')) ?>"
                    >
                        <span class="action-icon">▤</span>
                        <span class="action-copy">
                            <strong>Notices &amp; circulars</strong>
                            <span>Manage announcements and urgent notices</span>
                        </span>
                        <span class="action-arrow">&rarr;</span>
                    </a>

                    <a
                        class="action-link"
                        href="<?= adminH(url('admin/messages.php')) ?>"
                    >
                        <span class="action-icon gold">▣</span>
                        <span class="action-copy">
                            <strong>Leadership messages</strong>
                            <span>Update the President's and Principal's messages</span>
                        </span>
                        <span class="action-arrow">&rarr;</span>
                    </a>

                    <a
                        class="action-link"
                        href="<?= adminH(url('admin/events.php')) ?>"
                    >
                        <span class="action-icon green">▦</span>
                        <span class="action-copy">
                            <strong>Manage events</strong>
                            <span>Maintain the institutional event listing</span>
                        </span>
                        <span class="action-arrow">&rarr;</span>
                    </a>

                    <a
                        class="action-link"
                        href="<?= adminH(url('admin/gallery.php')) ?>"
                    >
                        <span class="action-icon purple">▧</span>
                        <span class="action-copy">
                            <strong>Photo gallery</strong>
                            <span>Manage photographs and image descriptions</span>
                        </span>
                        <span class="action-arrow">&rarr;</span>
                    </a>

                    <?php if ($role === 'super_admin'): ?>
                        <a
                            class="action-link"
                            href="<?= adminH(url('admin/users.php')) ?>"
                        >
                            <span class="action-icon">♙</span>
                            <span class="action-copy">
                                <strong>Staff &amp; permissions</strong>
                                <span>Manage accounts and assigned roles</span>
                            </span>
                            <span class="action-arrow">&rarr;</span>
                        </a>
                    <?php endif; ?>

                </div>

                <div class="role-note">
                    <strong>Signed in as <?= adminH($roleLabel) ?></strong>
                    <p>
                        Access to protected actions must also be checked
                        by the server on each individual management page.
                    </p>
                </div>
            </section>

        </div>

        <footer class="footer">
            <span>BIST CMS &middot; Bengal Institute of Science &amp; Technology</span>
            <span>Authorized institutional access only</span>
        </footer>

    </div>
</main>

<script>
(function () {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('menuToggle');
    const overlay = document.getElementById('sidebarOverlay');

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
        const isOpen = sidebar.classList.toggle('open');

        overlay.classList.toggle('show', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });
})();
</script>

</body>
</html>
