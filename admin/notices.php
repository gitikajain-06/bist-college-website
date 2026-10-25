
<?php
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/includes/auth.php';
verifyCurrentAdmin($pdo);

$admin = currentAdmin();
$role = $admin['role'] ?? '';
$adminId = (int) ($admin['id'] ?? 0);

$canPublish = in_array($role, [
    'super_admin',
    'administrator',
    'publisher'
], true);

$canDelete = in_array($role, [
    'super_admin',
    'administrator'
], true);

$canManageAll = in_array($role, [
    'super_admin',
    'administrator',
    'publisher'
], true);

function bistNoticeH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bistNoticeDate($value): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTimeImmutable(
            (string) $value,
            new DateTimeZone('Asia/Kolkata')
        ))->format('d M Y, h:i A');
    } catch (Throwable $e) {
        return '—';
    }
}

function bistNoticeSlug(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string) $slug, '-');

    return $slug !== '' ? substr($slug, 0, 240) : 'notice';
}

function bistNoticeRedirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

$baseUrl = url('admin/notices.php');
$successMessage = '';
$errorMessage = '';
$editingNotice = null;

$categories = [
    'General',
    'Academic',
    'Examination',
    'Admission',
    'Scholarship',
    'Event',
    'Urgent'
];

$form = [
    'title' => '',
    'slug' => '',
    'category' => 'General',
    'summary' => '',
    'document_path' => '',
    'status' => 'draft',
    'is_urgent' => 0,
    'expires_at' => ''
];

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyAdminCsrfToken($_POST['csrf_token'] ?? null);

    $action = (string) ($_POST['action'] ?? '');

    try {
        /*
        |------------------------------------------------------------------
        | Create or update notice
        |------------------------------------------------------------------
        */
        if ($action === 'save') {
            $noticeId = filter_var(
                $_POST['notice_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            $noticeId = $noticeId ? (int) $noticeId : 0;

            $form = [
                'title' => trim((string) ($_POST['title'] ?? '')),
                'slug' => trim((string) ($_POST['slug'] ?? '')),
                'category' => trim((string) ($_POST['category'] ?? 'General')),
                'summary' => trim((string) ($_POST['summary'] ?? '')),
                'document_path' => trim((string) ($_POST['document_path'] ?? '')),
                'status' => trim((string) ($_POST['status'] ?? 'draft')),
                'is_urgent' => isset($_POST['is_urgent']) ? 1 : 0,
                'expires_at' => trim((string) ($_POST['expires_at'] ?? ''))
            ];

            if ($form['title'] === '') {
                throw new RuntimeException('Please enter a notice title.');
            }

            if (mb_strlen($form['title']) > 255) {
                throw new RuntimeException('The title cannot exceed 255 characters.');
            }

            if (!in_array($form['category'], $categories, true)) {
                $form['category'] = 'General';
            }

            if (!in_array($form['status'], ['draft', 'pending', 'published', 'archived'], true)) {
                $form['status'] = 'draft';
            }

            if (!$canPublish && $form['status'] === 'published') {
                $form['status'] = 'pending';
            }

            if (!$canPublish && $form['status'] === 'archived') {
                $form['status'] = 'draft';
            }

            if (mb_strlen($form['summary']) > 10000) {
                throw new RuntimeException('The summary is too long.');
            }

            if (mb_strlen($form['document_path']) > 500) {
                throw new RuntimeException('The document link cannot exceed 500 characters.');
            }

            if (
                $form['document_path'] !== '' &&
                preg_match('/[\x00-\x1F\x7F]/', $form['document_path'])
            ) {
                throw new RuntimeException('The document link contains invalid characters.');
            }

            $expiresAt = null;

            if ($form['expires_at'] !== '') {
                $expiry = DateTimeImmutable::createFromFormat(
                    '!Y-m-d\TH:i',
                    $form['expires_at'],
                    new DateTimeZone('Asia/Kolkata')
                );

                if (!$expiry) {
                    throw new RuntimeException('Please enter a valid expiry date.');
                }

                $expiresAt = $expiry->format('Y-m-d H:i:s');
            }

            $existing = null;

            if ($noticeId > 0) {
                $stmt = $pdo->prepare(
                    'SELECT id, created_by, status, published_at
                     FROM notices
                     WHERE id = ?
                     LIMIT 1'
                );
                $stmt->execute([$noticeId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    throw new RuntimeException('The selected notice was not found.');
                }

                if (
                    !$canManageAll &&
                    (int) $existing['created_by'] !== $adminId
                ) {
                    throw new RuntimeException(
                        'You can only edit notices created by your account.'
                    );
                }

                if (
                    !$canPublish &&
                    $existing['status'] === 'published'
                ) {
                    throw new RuntimeException(
                        'You do not have permission to edit a published notice.'
                    );
                }
            }

            $baseSlug = $form['slug'] !== ''
                ? bistNoticeSlug($form['slug'])
                : bistNoticeSlug($form['title']);

            $slug = $baseSlug;
            $suffix = 2;

            while (true) {
                $check = $pdo->prepare(
                    'SELECT id FROM notices WHERE slug = ? LIMIT 1'
                );
                $check->execute([$slug]);
                $slugRow = $check->fetch(PDO::FETCH_ASSOC);

                if (!$slugRow || (int) $slugRow['id'] === $noticeId) {
                    break;
                }

                $slug = substr($baseSlug, 0, 225) . '-' . $suffix;
                $suffix++;
            }

            $publishedAt = null;

            if ($form['status'] === 'published') {
                $publishedAt = (
                    $existing &&
                    $existing['status'] === 'published' &&
                    !empty($existing['published_at'])
                )
                    ? $existing['published_at']
                    : date('Y-m-d H:i:s');
            }

            if ($noticeId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE notices
                     SET title = ?,
                         slug = ?,
                         category = ?,
                         summary = ?,
                         document_path = ?,
                         status = ?,
                         is_urgent = ?,
                         expires_at = ?,
                         published_at = ?
                     WHERE id = ?'
                );

                $stmt->execute([
                    $form['title'],
                    $slug,
                    $form['category'],
                    $form['summary'] !== '' ? $form['summary'] : null,
                    $form['document_path'] !== '' ? $form['document_path'] : null,
                    $form['status'],
                    $form['is_urgent'],
                    $expiresAt,
                    $publishedAt,
                    $noticeId
                ]);

                bistNoticeRedirect(
                    $baseUrl . '?edited=1'
                );
            }

            $stmt = $pdo->prepare(
                'INSERT INTO notices
                    (title, slug, category, summary, document_path,
                     status, is_urgent, expires_at, published_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $form['title'],
                $slug,
                $form['category'],
                $form['summary'] !== '' ? $form['summary'] : null,
                $form['document_path'] !== '' ? $form['document_path'] : null,
                $form['status'],
                $form['is_urgent'],
                $expiresAt,
                $publishedAt,
                $adminId
            ]);

            bistNoticeRedirect($baseUrl . '?created=1');
        }

        /*
        |------------------------------------------------------------------
        | Change notice status
        |------------------------------------------------------------------
        */
        if ($action === 'change_status') {
            $noticeId = (int) ($_POST['notice_id'] ?? 0);
            $newStatus = (string) ($_POST['new_status'] ?? '');

            if (!$canPublish) {
                throw new RuntimeException(
                    'You do not have permission to change publication status.'
                );
            }

            if (!in_array($newStatus, ['draft', 'pending', 'published', 'archived'], true)) {
                throw new RuntimeException('Invalid notice status.');
            }

            $stmt = $pdo->prepare(
                'SELECT id, status, created_by
                 FROM notices
                 WHERE id = ?
                 LIMIT 1'
            );
            $stmt->execute([$noticeId]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                throw new RuntimeException('Notice not found.');
            }

            if (
                !$canManageAll &&
                (int) $target['created_by'] !== $adminId
            ) {
                throw new RuntimeException('You cannot manage this notice.');
            }

            $publishedAt = $newStatus === 'published'
                ? date('Y-m-d H:i:s')
                : null;

            if ($newStatus === 'published' && $target['status'] === 'published') {
                $stmt = $pdo->prepare(
                    'SELECT published_at FROM notices WHERE id = ?'
                );
                $stmt->execute([$noticeId]);
                $publishedAt = $stmt->fetchColumn() ?: date('Y-m-d H:i:s');
            }

            $stmt = $pdo->prepare(
                'UPDATE notices
                 SET status = ?, published_at = ?
                 WHERE id = ?'
            );
            $stmt->execute([$newStatus, $publishedAt, $noticeId]);

            bistNoticeRedirect($baseUrl . '?status_updated=1');
        }

        /*
        |------------------------------------------------------------------
        | Delete notice
        |------------------------------------------------------------------
        */
        if ($action === 'delete') {
            if (!$canDelete) {
                throw new RuntimeException(
                    'Only the Super Admin or Administrator can delete notices.'
                );
            }

            $noticeId = (int) ($_POST['notice_id'] ?? 0);

            $stmt = $pdo->prepare('DELETE FROM notices WHERE id = ?');
            $stmt->execute([$noticeId]);

            bistNoticeRedirect($baseUrl . '?deleted=1');
        }

        throw new RuntimeException('Unknown action.');
    } catch (PDOException $e) {
        error_log('BIST notice database error: ' . $e->getMessage());
        $errorMessage = 'The notice could not be saved because of a database error. Please check the details and try again.';
    } catch (RuntimeException $e) {
        $errorMessage = $e->getMessage();
    } catch (Throwable $e) {
        error_log('BIST notice error: ' . $e->getMessage());
        $errorMessage = 'Something went wrong. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| PAGE MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_GET['created'])) {
    $successMessage = 'Notice created successfully.';
} elseif (isset($_GET['edited'])) {
    $successMessage = 'Notice updated successfully.';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Notice deleted successfully.';
} elseif (isset($_GET['status_updated'])) {
    $successMessage = 'Notice status updated successfully.';
}

/*
|--------------------------------------------------------------------------
| LOAD NOTICE FOR EDITING
|--------------------------------------------------------------------------
*/

$editId = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT);

if ($editId && !$errorMessage) {
    $stmt = $pdo->prepare(
        'SELECT * FROM notices WHERE id = ? LIMIT 1'
    );
    $stmt->execute([(int) $editId]);
    $editingNotice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editingNotice) {
        $errorMessage = 'The notice you selected does not exist.';
    } elseif (
        !$canManageAll &&
        (int) $editingNotice['created_by'] !== $adminId
    ) {
        $errorMessage = 'You do not have permission to edit this notice.';
        $editingNotice = null;
    } elseif (
        !$canPublish &&
        $editingNotice['status'] === 'published'
    ) {
        $errorMessage = 'You cannot edit a published notice.';
        $editingNotice = null;
    } else {
        $form = [
            'title' => $editingNotice['title'] ?? '',
            'slug' => $editingNotice['slug'] ?? '',
            'category' => $editingNotice['category'] ?? 'General',
            'summary' => $editingNotice['summary'] ?? '',
            'document_path' => $editingNotice['document_path'] ?? '',
            'status' => $editingNotice['status'] ?? 'draft',
            'is_urgent' => (int) ($editingNotice['is_urgent'] ?? 0),
            'expires_at' => !empty($editingNotice['expires_at'])
                ? date(
                    'Y-m-d\TH:i',
                    strtotime($editingNotice['expires_at'])
                )
                : ''
        ];
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS AND NOTICE LIST
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$categoryFilter = (string) ($_GET['category'] ?? '');

$allowedStatuses = ['draft', 'pending', 'published', 'archived'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if (!in_array($categoryFilter, $categories, true)) {
    $categoryFilter = '';
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(n.title LIKE ? OR n.summary LIKE ? OR n.slug LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}

if ($statusFilter !== '') {
    $where[] = 'n.status = ?';
    $params[] = $statusFilter;
}

if ($categoryFilter !== '') {
    $where[] = 'n.category = ?';
    $params[] = $categoryFilter;
}

if (!$canManageAll) {
    $where[] = 'n.created_by = ?';
    $params[] = $adminId;
}

$sql = 'SELECT n.*, u.name AS author_name
        FROM notices n
        LEFT JOIN users u ON u.id = n.created_by';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY
          CASE WHEN n.is_urgent = 1 THEN 0 ELSE 1 END,
          n.display_order ASC,
          n.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countSql = 'SELECT
    COUNT(*) AS total,
    SUM(status = "published") AS published,
    SUM(status = "draft") AS drafts,
    SUM(status = "pending") AS pending,
    SUM(is_urgent = 1 AND status = "published") AS urgent
    FROM notices';

$countParams = [];

if (!$canManageAll) {
    $countSql .= ' WHERE created_by = ?';
    $countParams[] = $adminId;
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$stats = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stats = array_merge([
    'total' => 0,
    'published' => 0,
    'drafts' => 0,
    'pending' => 0,
    'urgent' => 0
], $stats);

$csrf = adminCsrfToken();
$isEditing = $editingNotice !== null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notice Management | BIST Admin</title>
    <style>
        :root {
            --navy: #0B2E59;
            --blue: #1E5AA8;
            --blue-soft: #EAF1FB;
            --gold: #F4B400;
            --page: #F4F7FB;
            --white: #FFFFFF;
            --text: #1B2B43;
            --muted: #718096;
            --border: #E4EAF2;
            --green: #16845B;
            --red: #C83D4D;
            --orange: #B66A00;
            --sidebar-width: 258px;
            --radius: 14px;
            --shadow: 0 4px 18px rgba(15, 35, 65, .045);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--page);
            color: var(--text);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.55;
        }

        a {
            color: inherit;
        }

        button, input, select, textarea {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        .sidebar {
            position: fixed;
            z-index: 1000;
            inset: 0 auto 0 0;
            width: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            background: var(--white);
            border-right: 1px solid var(--border);
            transition: transform .25s ease;
        }

        .sidebar-brand {
            min-height: 82px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--navy);
            color: #fff;
            font-size: 23px;
            font-weight: 800;
        }

        .brand-text strong, .brand-text span {
            display: block;
        }

        .brand-text strong {
            color: var(--navy);
            font-size: 16px;
            letter-spacing: 1px;
        }

        .brand-text span {
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .sidebar-section {
            padding: 24px 22px 9px;
            color: #94A0B2;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
        }

        .sidebar-nav {
            padding: 0 12px;
        }

        .nav-link {
            min-height: 45px;
            display: flex;
            align-items: center;
            gap: 13px;
            margin: 4px 0;
            padding: 0 14px;
            border-radius: 9px;
            color: #59677B;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background .2s, color .2s;
        }

        .nav-link:hover {
            background: #F2F6FC;
            color: var(--blue);
        }

        .nav-link.active {
            background: var(--blue-soft);
            color: var(--blue);
            box-shadow: inset 3px 0 0 var(--blue);
        }

        .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar-bottom {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: auto 15px 18px;
            padding: 14px 12px;
            border: 1px solid var(--border);
            border-radius: 11px;
        }

        .status-dot {
            width: 9px;
            height: 9px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #1BA66A;
        }

        .sidebar-bottom strong, .sidebar-bottom small {
            display: block;
        }

        .sidebar-bottom strong {
            font-size: 12px;
        }

        .sidebar-bottom small {
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .main {
            min-height: 100vh;
            margin-left: var(--sidebar-width);
            transition: margin-left .25s ease;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 500;
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 30px;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        .topbar-left, .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .icon-button {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: #fff;
            color: var(--navy);
            font-size: 21px;
        }

        .icon-button:hover {
            background: var(--blue-soft);
            border-color: #C9D9EF;
        }

        .topbar-title {
            margin: 0;
            color: var(--navy);
            font-size: 17px;
            font-weight: 750;
        }

        .topbar-subtitle {
            margin-top: 2px;
            color: var(--muted);
            font-size: 11px;
        }

        .clock {
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .admin-chip {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 7px 11px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: #fff;
        }

        .admin-avatar {
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--blue-soft);
            color: var(--blue);
            font-weight: 800;
        }

        .admin-details strong, .admin-details span {
            display: block;
        }

        .admin-details strong {
            max-width: 145px;
            overflow: hidden;
            color: var(--text);
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-details span {
            margin-top: 2px;
            color: var(--muted);
            font-size: 10px;
            text-transform: capitalize;
        }

        .content {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 28px 30px 36px;
        }

        .page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }

        .eyebrow {
            margin-bottom: 7px;
            color: var(--blue);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .page-heading h1 {
            margin: 0;
            color: var(--navy);
            font-size: clamp(24px, 3vw, 31px);
            line-height: 1.2;
            letter-spacing: -.7px;
        }

        .page-heading p {
            max-width: 650px;
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .button {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 15px;
            border: 1px solid transparent;
            border-radius: 9px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 750;
            transition: .2s ease;
        }

        .button-primary {
            background: var(--blue);
            color: #fff;
        }

        .button-primary:hover {
            background: var(--navy);
            transform: translateY(-1px);
        }

        .button-secondary {
            background: #fff;
            border-color: var(--border);
            color: var(--text);
        }

        .button-secondary:hover {
            background: #F5F8FC;
        }

        .button-danger {
            background: #FFF0F1;
            border-color: #F7D8DC;
            color: var(--red);
        }

        .button-small {
            min-height: 32px;
            padding: 0 10px;
            font-size: 11px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 24px;
        }

        .stat-card {
            position: relative;
            min-width: 0;
            padding: 19px 20px;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .stat-card::after {
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: var(--blue);
            content: "";
        }

        .stat-card:nth-child(2)::after { background: var(--green); }
        .stat-card:nth-child(3)::after { background: var(--orange); }
        .stat-card:nth-child(4)::after { background: var(--red); }

        .stat-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .stat-value {
            margin-top: 9px;
            color: var(--navy);
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }

        .stat-caption {
            margin-top: 8px;
            color: #8A96A8;
            font-size: 10px;
        }

        .workspace {
            display: flex;
            flex-direction: column;
            gap: 23px;
            min-width: 0;
        }

        .panel {
            min-width: 0;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            padding: 21px 23px;
            border-bottom: 1px solid var(--border);
        }

        .panel-title {
            margin: 0;
            color: var(--navy);
            font-size: 16px;
            font-weight: 800;
        }

        .panel-description {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .panel-body {
            padding: 23px;
        }

        .section-number {
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 10px;
            background: var(--blue-soft);
            color: var(--blue);
            font-weight: 800;
        }

        .panel-heading-left {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 19px;
        }

        .field {
            min-width: 0;
        }

        .field-full {
            grid-column: 1 / -1;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: #33445C;
            font-size: 12px;
            font-weight: 750;
        }

        .required {
            color: var(--red);
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            min-height: 43px;
            padding: 10px 12px;
            border: 1px solid #DCE4EE;
            border-radius: 9px;
            outline: none;
            background: #fff;
            color: var(--text);
            font-size: 12px;
            transition: border-color .2s, box-shadow .2s;
        }

        .field textarea {
            min-height: 125px;
            resize: vertical;
            line-height: 1.65;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(30, 90, 168, .10);
        }

        .field-help {
            margin-top: 6px;
            color: #8995A6;
            font-size: 10px;
        }

        .checkbox-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #FAFBFD;
        }

        .checkbox-row input {
            width: 16px;
            height: 16px;
            margin-top: 2px;
            accent-color: var(--blue);
        }

        .checkbox-row strong {
            display: block;
            color: var(--text);
            font-size: 12px;
        }

        .checkbox-row small {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 10px;
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .form-actions-right {
            display: flex;
            gap: 9px;
            flex-wrap: wrap;
        }

        .notice-count {
            display: inline-flex;
            align-items: center;
            min-height: 27px;
            padding: 0 10px;
            border-radius: 7px;
            background: #F0F4FA;
            color: #5F6D81;
            font-size: 11px;
            font-weight: 750;
            white-space: nowrap;
        }

        .filter-form {
            display: grid;
            grid-template-columns: minmax(180px, 1.7fr) repeat(2, minmax(140px, .8fr)) auto auto;
            gap: 10px;
            padding: 18px 23px;
            border-bottom: 1px solid var(--border);
            background: #FCFDFE;
        }

        .filter-form input,
        .filter-form select {
            width: 100%;
            min-width: 0;
            min-height: 40px;
            padding: 9px 11px;
            border: 1px solid #DCE4EE;
            border-radius: 8px;
            outline: none;
            background: #fff;
            font-size: 11px;
        }

        .filter-form input:focus,
        .filter-form select:focus {
            border-color: var(--blue);
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .notice-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .notice-table th {
            padding: 13px 17px;
            background: #F8FAFD;
            color: #78869A;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .6px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .notice-table td {
            padding: 17px;
            border-top: 1px solid #EDF1F6;
            color: #536177;
            font-size: 11px;
            vertical-align: top;
        }

        .notice-table tbody tr:hover {
            background: #FCFDFF;
        }

        .notice-title {
            min-width: 200px;
            max-width: 390px;
            color: var(--navy);
            font-size: 12px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .notice-meta {
            margin-top: 5px;
            color: #8A96A8;
            font-size: 10px;
            overflow-wrap: anywhere;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge-published {
            background: #E8F7EF;
            color: #16754E;
        }

        .badge-draft {
            background: #EEF2F7;
            color: #627086;
        }

        .badge-pending {
            background: #FFF5DF;
            color: #996100;
        }

        .badge-archived {
            background: #F0EAFB;
            color: #7150A8;
        }

        .badge-urgent {
            margin-top: 7px;
            background: #FFF0F1;
            color: #BE3446;
        }

        .row-actions {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
            min-width: 150px;
        }

        .inline-form {
            display: inline-flex;
            margin: 0;
        }

        .select-action {
            min-height: 32px;
            max-width: 130px;
            padding: 4px 7px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: #fff;
            color: var(--text);
            font-size: 10px;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            display: grid;
            place-items: center;
            margin: 0 auto 15px;
            border-radius: 16px;
            background: var(--blue-soft);
            color: var(--blue);
            font-size: 26px;
        }

        .empty-state h3 {
            margin: 0;
            color: var(--navy);
            font-size: 15px;
        }

        .empty-state p {
            margin: 7px 0 18px;
            color: var(--muted);
            font-size: 12px;
        }

        .alert {
            margin-bottom: 20px;
            padding: 13px 16px;
            border: 1px solid;
            border-radius: 10px;
            font-size: 12px;
        }

        .alert-success {
            border-color: #BDE7CF;
            background: #EFFAF4;
            color: #176B47;
        }

        .alert-error {
            border-color: #F3C9CE;
            background: #FFF3F4;
            color: #A52E3E;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 20px 30px;
            border-top: 1px solid var(--border);
            background: #fff;
            color: var(--muted);
            font-size: 10px;
        }

        .overlay {
            display: none;
        }

        body.sidebar-collapsed .sidebar {
            transform: translateX(-100%);
        }

        body.sidebar-collapsed .main {
            margin-left: 0;
        }

        @media (max-width: 1150px) {
            .filter-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-form .search-field {
                grid-column: 1 / -1;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .clock {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 12px 0 35px rgba(15, 35, 65, .12);
            }

            .main {
                margin-left: 0;
            }

            body.mobile-sidebar-open .sidebar {
                transform: translateX(0);
            }

            body.mobile-sidebar-open .overlay {
                position: fixed;
                inset: 0;
                z-index: 900;
                display: block;
                background: rgba(15, 30, 50, .42);
            }

            .topbar {
                min-height: 66px;
                padding: 11px 14px;
            }

            .topbar-subtitle,
            .admin-details {
                display: none;
            }

            .content {
                padding: 22px 14px 28px;
            }

            .page-heading {
                flex-direction: column;
                align-items: stretch;
            }

            .page-heading > .button {
                align-self: flex-start;
            }

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-value {
                font-size: 24px;
            }

            .panel-header,
            .panel-body {
                padding: 17px;
            }

            .filter-form {
                grid-template-columns: 1fr;
                padding: 15px;
            }

            .filter-form .search-field {
                grid-column: auto;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .field-full {
                grid-column: auto;
            }

            .footer {
                padding: 18px 14px;
            }
        }

        @media (max-width: 420px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .stat-card {
                padding: 13px;
            }

            .stat-label {
                font-size: 10px;
            }

            .stat-value {
                font-size: 22px;
            }

            .form-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .form-actions-right {
                width: 100%;
            }

            .form-actions-right .button {
                flex: 1;
            }
        }
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">B</div>
        <div class="brand-text">
            <strong>BIST</strong>
            <span>Administration Portal</span>
        </div>
    </div>

    <div class="sidebar-section">WORKSPACE</div>

    <nav class="sidebar-nav">
        <a class="nav-link"
           href="<?= bistNoticeH(url('admin/dashboard.php')) ?>">
            <span class="nav-icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <a class="nav-link active"
           href="<?= bistNoticeH(url('admin/notices.php')) ?>">
            <span class="nav-icon">▤</span>
            <span>Notice Management</span>
        </a>

        <a class="nav-link"
           href="<?= bistNoticeH(url('admin/messages.php')) ?>">
            <span class="nav-icon">✉</span>
            <span>Messages</span>
        </a>

        <a class="nav-link"
           href="<?= bistNoticeH(url('admin/events.php')) ?>">
            <span class="nav-icon">▦</span>
            <span>Events</span>
        </a>

        <a class="nav-link"
           href="<?= bistNoticeH(url('admin/gallery.php')) ?>">
            <span class="nav-icon">▧</span>
            <span>Gallery</span>
        </a>

        <?php if ($role === 'super_admin'): ?>
            <div class="sidebar-section">ADMINISTRATION</div>
            <a class="nav-link"
               href="<?= bistNoticeH(url('admin/users.php')) ?>">
                <span class="nav-icon">♙</span>
                <span>User Management</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-bottom">
        <span class="status-dot"></span>
        <div>
            <strong>Administration active</strong>
            <small>BIST content management</small>
        </div>
    </div>
</aside>

<div class="overlay" id="sidebarOverlay"></div>

<div class="main" id="mainContent">
    <header class="topbar">
        <div class="topbar-left">
            <button
                type="button"
                class="icon-button"
                id="menuToggle"
                aria-label="Toggle sidebar"
                aria-controls="sidebar"
                aria-expanded="true"
            >☰</button>

            <div>
                <h2 class="topbar-title">Notice Management</h2>
                <div class="topbar-subtitle">Manage institutional announcements</div>
            </div>
        </div>

        <div class="topbar-right">
            <div class="clock" id="liveClock">Loading Indian time…</div>

            <div class="admin-chip">
                <div class="admin-avatar">
                    <?= bistNoticeH(
                        strtoupper(substr((string) ($admin['name'] ?? 'A'), 0, 1))
                    ) ?>
                </div>
                <div class="admin-details">
                    <strong><?= bistNoticeH($admin['name'] ?? 'Administrator') ?></strong>
                    <span><?= bistNoticeH(str_replace('_', ' ', $role)) ?></span>
                </div>
            </div>
        </div>
    </header>

    <main class="content">
        <section class="page-heading">
            <div>
                <div class="eyebrow">Content management / Notices</div>
                <h1>Notice Workspace</h1>
                <p>Create, organise and publish official college announcements from one clean workspace.</p>
            </div>

            <a class="button button-primary"
               href="<?= bistNoticeH($baseUrl) ?>#notice-form">
                <span>＋</span> Create New Notice
            </a>
        </section>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success" role="status">
                ✓ <?= bistNoticeH($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-error" role="alert">
                <?= bistNoticeH($errorMessage) ?>
            </div>
        <?php endif; ?>

        <section class="stats-grid" aria-label="Notice statistics">
            <div class="stat-card">
                <div class="stat-label">Total Notices</div>
                <div class="stat-value"><?= (int) $stats['total'] ?></div>
                <div class="stat-caption">Notices available in your workspace</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Published</div>
                <div class="stat-value"><?= (int) $stats['published'] ?></div>
                <div class="stat-caption">Currently marked as published</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Drafts & Pending</div>
                <div class="stat-value">
                    <?= (int) $stats['drafts'] + (int) $stats['pending'] ?>
                </div>
                <div class="stat-caption">Not yet published</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Urgent Published</div>
                <div class="stat-value"><?= (int) $stats['urgent'] ?></div>
                <div class="stat-caption">Published notices flagged urgent</div>
            </div>
        </section>

        <div class="workspace">

            <!-- CREATE / EDIT NOTICE -->
            <section class="panel" id="notice-form">
                <div class="panel-header">
                    <div class="panel-heading-left">
                        <div class="section-number">01</div>
                        <div>
                            <h2 class="panel-title">
                                <?= $isEditing ? 'Edit Notice' : 'Create a Notice' ?>
                            </h2>
                            <p class="panel-description">
                                Enter the notice details below. Fields marked with * are required.
                            </p>
                        </div>
                    </div>

                    <?php if ($isEditing): ?>
                        <a class="button button-secondary button-small"
                           href="<?= bistNoticeH($baseUrl) ?>">
                            Cancel Editing
                        </a>
                    <?php endif; ?>
                </div>

                <div class="panel-body">
                    <form method="post"
                          action="<?= bistNoticeH($baseUrl) ?>#notice-form"
                          autocomplete="off">
                        <input type="hidden" name="csrf_token"
                               value="<?= bistNoticeH($csrf) ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="notice_id"
                               value="<?= $isEditing ? (int) $editingNotice['id'] : 0 ?>">

                        <div class="form-grid">
                            <div class="field field-full">
                                <label for="title">
                                    Notice Title <span class="required">*</span>
                                </label>
                                <input
                                    id="title"
                                    name="title"
                                    type="text"
                                    maxlength="255"
                                    required
                                    placeholder="e.g. Semester Examination Schedule"
                                    value="<?= bistNoticeH($form['title']) ?>"
                                >
                                <div class="field-help">
                                    Use a clear, concise title that students can understand immediately.
                                </div>
                            </div>

                            <div class="field">
                                <label for="category">Notice Category</label>
                                <select id="category" name="category">
                                    <?php foreach ($categories as $category): ?>
                                        <option
                                            value="<?= bistNoticeH($category) ?>"
                                            <?= $form['category'] === $category ? 'selected' : '' ?>
                                        >
                                            <?= bistNoticeH($category) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label for="slug">Notice Slug <span style="color:#8995A6;font-weight:400">(optional)</span></label>
                                <input
                                    id="slug"
                                    name="slug"
                                    type="text"
                                    maxlength="280"
                                    placeholder="Automatically generated from title"
                                    value="<?= bistNoticeH($form['slug']) ?>"
                                >
                                <div class="field-help">
                                    Used to identify the notice in a URL. Duplicate slugs are made unique automatically.
                                </div>
                            </div>

                            <div class="field field-full">
                                <label for="summary">Notice Description / Summary</label>
                                <textarea
                                    id="summary"
                                    name="summary"
                                    maxlength="10000"
                                    placeholder="Write the notice details, important instructions, dates or information for students..."
                                ><?= bistNoticeH($form['summary']) ?></textarea>
                            </div>

                            <div class="field field-full">
                                <label for="document_path">Document Link <span style="color:#8995A6;font-weight:400">(optional)</span></label>
                                <input
                                    id="document_path"
                                    name="document_path"
                                    type="text"
                                    maxlength="500"
                                    placeholder="https://example.com/notice.pdf or uploads/notices/notice.pdf"
                                    value="<?= bistNoticeH($form['document_path']) ?>"
                                >
                                <div class="field-help">
                                    Enter an existing document URL or relative file path. This field does not upload a file.
                                </div>
                            </div>

                            <div class="field">
                                <label for="status">Publication Status</label>
                                <select id="status" name="status">
                                    <option value="draft"
                                        <?= $form['status'] === 'draft' ? 'selected' : '' ?>>
                                        Draft
                                    </option>
                                    <option value="pending"
                                        <?= $form['status'] === 'pending' ? 'selected' : '' ?>>
                                        Pending Review
                                    </option>

                                    <?php if ($canPublish): ?>
                                        <option value="published"
                                            <?= $form['status'] === 'published' ? 'selected' : '' ?>>
                                            Published
                                        </option>
                                        <option value="archived"
                                            <?= $form['status'] === 'archived' ? 'selected' : '' ?>>
                                            Archived
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <div class="field-help">
                                    <?php if ($canPublish): ?>
                                        You can publish notices directly from this form.
                                    <?php else: ?>
                                        Your notices can be saved as drafts or submitted for review.
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="field">
                                <label for="expires_at">Expiry Date & Time</label>
                                <input
                                    id="expires_at"
                                    name="expires_at"
                                    type="datetime-local"
                                    value="<?= bistNoticeH($form['expires_at']) ?>"
                                >
                                <div class="field-help">
                                    Optional. Your public notices page must hide expired notices.
                                </div>
                            </div>

                            <div class="field field-full">
                                <label>Notice Priority</label>
                                <label class="checkbox-row" for="is_urgent">
                                    <input
                                        id="is_urgent"
                                        name="is_urgent"
                                        type="checkbox"
                                        value="1"
                                        <?= (int) $form['is_urgent'] === 1 ? 'checked' : '' ?>
                                    >
                                    <span>
                                        <strong>Mark as an urgent notice</strong>
                                        <small>Urgent notices are prioritised in this workspace. Your public page must also support the urgent flag.</small>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <div class="field-help">
                                Changes are saved to the BIST notices database.
                            </div>

                            <div class="form-actions-right">
                                <?php if ($isEditing): ?>
                                    <a class="button button-secondary"
                                       href="<?= bistNoticeH($baseUrl) ?>">
                                        Cancel
                                    </a>
                                <?php else: ?>
                                    <button type="reset" class="button button-secondary">
                                        Reset Form
                                    </button>
                                <?php endif; ?>

                                <button type="submit" class="button button-primary">
                                    <?= $isEditing ? 'Save Changes' : 'Save Notice' ?>
                                    <span>→</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <!-- NOTICE LIBRARY -->
            <section class="panel" id="notice-library">
                <div class="panel-header">
                    <div class="panel-heading-left">
                        <div class="section-number">02</div>
                        <div>
                            <h2 class="panel-title">Notice Library</h2>
                            <p class="panel-description">
                                Search, filter, edit and manage existing notices.
                            </p>
                        </div>
                    </div>

                    <span class="notice-count">
                        <?= count($notices) ?> result<?= count($notices) === 1 ? '' : 's' ?>
                    </span>
                </div>

                <form class="filter-form" method="get"
                      action="<?= bistNoticeH($baseUrl) ?>">
                    <input
                        class="search-field"
                        type="search"
                        name="q"
                        value="<?= bistNoticeH($search) ?>"
                        placeholder="Search title, description or slug..."
                        aria-label="Search notices"
                    >

                    <select name="status" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <?php foreach ($allowedStatuses as $statusOption): ?>
                            <option value="<?= bistNoticeH($statusOption) ?>"
                                <?= $statusFilter === $statusOption ? 'selected' : '' ?>>
                                <?= bistNoticeH(ucfirst($statusOption)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="category" aria-label="Filter by category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= bistNoticeH($category) ?>"
                                <?= $categoryFilter === $category ? 'selected' : '' ?>>
                                <?= bistNoticeH($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="button button-primary">
                        Search
                    </button>

                    <a class="button button-secondary"
                       href="<?= bistNoticeH($baseUrl) ?>#notice-library">
                        Clear
                    </a>
                </form>

                <?php if (!$notices): ?>
                    <div class="empty-state">
                        <div class="empty-icon">▤</div>
                        <h3>No notices found</h3>
                        <p>
                            <?= $search !== '' || $statusFilter !== '' || $categoryFilter !== ''
                                ? 'Try changing your search or filters.'
                                : 'Create your first notice using the form above.' ?>
                        </p>
                        <?php if ($search === '' && $statusFilter === '' && $categoryFilter === ''): ?>
                            <a class="button button-primary"
                               href="<?= bistNoticeH($baseUrl) ?>#notice-form">
                                ＋ Create First Notice
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="notice-table">
                            <thead>
                                <tr>
                                    <th>Notice</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($notices as $notice): ?>
                                <?php
                                    $noticeStatus = (string) $notice['status'];
                                    $badgeClass = 'badge-' . $noticeStatus;
                                ?>
                                <tr>
                                    <td>
                                        <div class="notice-title">
                                            <?= bistNoticeH($notice['title']) ?>
                                        </div>

                                        <div class="notice-meta">
                                            /<?= bistNoticeH($notice['slug']) ?>
                                        </div>

                                        <?php if ((int) $notice['is_urgent'] === 1): ?>
                                            <div>
                                                <span class="badge badge-urgent">
                                                    ! Urgent
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="notice-meta">
                                            By <?= bistNoticeH($notice['author_name'] ?: 'Unknown') ?>
                                        </div>
                                    </td>

                                    <td><?= bistNoticeH($notice['category']) ?></td>

                                    <td>
                                        <span class="badge <?= bistNoticeH($badgeClass) ?>">
                                            <?= bistNoticeH(ucfirst($noticeStatus)) ?>
                                        </span>

                                        <?php if ($noticeStatus === 'published' && !empty($notice['published_at'])): ?>
                                            <div class="notice-meta">
                                                <?= bistNoticeH(bistNoticeDate($notice['published_at'])) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($notice['expires_at'])): ?>
                                            <div class="notice-meta">
                                                Expires <?= bistNoticeH(bistNoticeDate($notice['expires_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= bistNoticeH(bistNoticeDate($notice['created_at'])) ?>
                                    </td>

                                    <td>
                                        <div class="row-actions">
                                            <?php
                                                $canEditThis = $canManageAll
                                                    || (int) $notice['created_by'] === $adminId;

                                                if (
                                                    !$canPublish &&
                                                    $noticeStatus === 'published'
                                                ) {
                                                    $canEditThis = false;
                                                }
                                            ?>

                                            <?php if ($canEditThis): ?>
                                                <a class="button button-secondary button-small"
                                                   href="<?= bistNoticeH($baseUrl) ?>?edit=<?= (int) $notice['id'] ?>#notice-form">
                                                    Edit
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($canPublish): ?>
                                                <form class="inline-form" method="post"
                                                      action="<?= bistNoticeH($baseUrl) ?>#notice-library">
                                                    <input type="hidden" name="csrf_token"
                                                           value="<?= bistNoticeH($csrf) ?>">
                                                    <input type="hidden" name="action"
                                                           value="change_status">
                                                    <input type="hidden" name="notice_id"
                                                           value="<?= (int) $notice['id'] ?>">

                                                    <select class="select-action"
                                                            name="new_status"
                                                            aria-label="Change status for <?= bistNoticeH($notice['title']) ?>"
                                                            onchange="if(confirm('Apply this status to the selected notice?')) this.form.submit(); else this.value='<?= bistNoticeH($noticeStatus) ?>';">
                                                        <?php foreach ($allowedStatuses as $statusOption): ?>
                                                            <option value="<?= bistNoticeH($statusOption) ?>"
                                                                <?= $noticeStatus === $statusOption ? 'selected' : '' ?>>
                                                                <?= bistNoticeH(ucfirst($statusOption)) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($canDelete): ?>
                                                <form class="inline-form" method="post"
                                                      action="<?= bistNoticeH($baseUrl) ?>#notice-library"
                                                      onsubmit="return confirm('Permanently delete this notice? This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token"
                                                           value="<?= bistNoticeH($csrf) ?>">
                                                    <input type="hidden" name="action"
                                                           value="delete">
                                                    <input type="hidden" name="notice_id"
                                                           value="<?= (int) $notice['id'] ?>">

                                                    <button type="submit"
                                                            class="button button-danger button-small">
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <footer class="footer">
        <span>© <?= date('Y') ?> Bengal Institute of Science &amp; Technology</span>
        <span>BIST Administration Portal · India Standard Time</span>
    </footer>
</div>

<script>
(() => {
    const toggle = document.getElementById('menuToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const clock = document.getElementById('liveClock');

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function updateExpanded() {
        if (!toggle) return;

        const open = isMobile()
            ? document.body.classList.contains('mobile-sidebar-open')
            : !document.body.classList.contains('sidebar-collapsed');

        toggle.setAttribute('aria-expanded', String(open));
    }

    if (toggle && overlay) {
        toggle.addEventListener('click', () => {
            if (isMobile()) {
                document.body.classList.toggle('mobile-sidebar-open');
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }

            updateExpanded();
        });

        overlay.addEventListener('click', () => {
            document.body.classList.remove('mobile-sidebar-open');
            updateExpanded();
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                document.body.classList.remove('mobile-sidebar-open');
                updateExpanded();
            }
        });

        window.addEventListener('resize', () => {
            document.body.classList.remove('mobile-sidebar-open');
            updateExpanded();
        });

        updateExpanded();
    }

    function updateClock() {
        if (!clock) return;

        const now = new Date();

        const time = new Intl.DateTimeFormat('en-IN', {
            timeZone: 'Asia/Kolkata',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        }).format(now);

        const date = new Intl.DateTimeFormat('en-IN', {
            timeZone: 'Asia/Kolkata',
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        }).format(now);

        clock.textContent = date + ' · ' + time;
    }

    updateClock();
    window.setInterval(updateClock, 1000);
})();
</script>

</body>
</html>
