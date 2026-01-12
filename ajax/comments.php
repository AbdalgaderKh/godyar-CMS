<?php
declare(strict_types=1);

/**
 * godyar ajax/comments.php
 * - Supports threaded replies via parent_id (0 = top-level)
 * - Works with the `comments` table used by admin/comments/index.php
 *
 * Required columns (minimum):
 *  id, news_id, parent_id, name, email, body, status, created_at
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

header('Content-Type: application/json; charset=utf-8');

function gdy_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$debug = isset($_GET['debug']) && ($_GET['debug'] === '1' || $_GET['debug'] === 'true');

try {
    /** @var PDO $pdo */
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('pdo_missing');
    }
    // Safer defaults
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null], 500);
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'list');

// ---- Identify user/session (support local + OAuth sessions)
$u = (isset($_SESSION['user']) && is_array($_SESSION['user'])) ? $_SESSION['user'] : [];
$userId = (int)($_SESSION['user_id'] ?? ($u['id'] ?? 0));

$email = trim((string)(
    $_SESSION['user_email']
    ?? $_SESSION['email']
    ?? ($u['email'] ?? '')
));

$name = trim((string)(
    $_SESSION['user_name']
    ?? ($u['display_name'] ?? ($u['username'] ?? ''))
));

// Consider "logged in" if we have a user id OR a known email OR legacy flag
$isLogged = ($userId > 0) || ($email !== '') || !empty($_SESSION['is_member_logged']) || !empty($u);

// Try to hydrate from DB if logged but missing fields
if ($isLogged && ($name === '' || $email === '' || $userId === 0)) {
    try {
        // Best-effort: match by id first, else by email
        if ($userId > 0) {
            $st = $pdo->prepare("SELECT id, username, display_name, email FROM users WHERE id = :id LIMIT 1");
            $st->execute([':id' => $userId]);
        } elseif ($email !== '') {
            $st = $pdo->prepare("SELECT id, username, display_name, email FROM users WHERE email = :em LIMIT 1");
            $st->execute([':em' => $email]);
        } else {
            $st = null;
        }
        if ($st) {
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            if ($userId === 0 && !empty($row['id'])) $userId = (int)$row['id'];
            if ($name === '') $name = trim((string)($row['display_name'] ?? $row['username'] ?? ''));
            if ($email === '') $email = trim((string)($row['email'] ?? ''));
        }
    } catch (Throwable $e) {
        // ignore
    }
}

$me = $isLogged ? ['id' => $userId, 'name' => $name, 'email' => $email] : null;

// ---- Optional CSRF check (doesn't break if token isn't present)
function gdy_check_csrf(): void {
    $sess = $_SESSION['csrf_token'] ?? '';
    $post = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($sess !== '' || $post !== '') {
        if (!$sess || !$post || !hash_equals((string)$sess, (string)$post)) {
            gdy_out(['ok' => false, 'error' => 'csrf', 'msg' => 'انتهت الجلسة. حدّث الصفحة ثم حاول مرة أخرى.'], 403);
        }
    }
}

// ---- Diagnostics
if ($action === 'diag') {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM comments")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        gdy_out([
            'ok' => true,
            'table' => 'comments',
            'columns' => $cols,
            'me' => $me,
            'is_logged' => $isLogged,
        ]);
    } catch (Throwable $e) {
        gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null, 'me' => $me], 500);
    }
}

// ---- List
if ($action === 'list') {
    $newsId = (int)($_GET['news_id'] ?? 0);
    if ($newsId <= 0) {
        gdy_out(['ok' => true, 'comments' => [], 'me' => $me]);
    }

    try {
        // Include parent_id for replies
        $st = $pdo->prepare(
            "SELECT id, news_id, parent_id, name, body, created_at
             FROM comments
             WHERE news_id = :nid AND status = 'approved'
             ORDER BY id ASC
             LIMIT 500"
        );
        $st->execute([':nid' => $newsId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        gdy_out(['ok' => true, 'comments' => $rows, 'me' => $me]);
    } catch (Throwable $e) {
        gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null], 500);
    }
}

// ---- Add (supports replies via parent_id)
if ($action === 'add') {
    gdy_check_csrf();

    $newsId = (int)($_POST['news_id'] ?? 0);
    $parentId = (int)($_POST['parent_id'] ?? 0);
    $body = trim((string)($_POST['body'] ?? ''));

    if ($newsId <= 0) {
        gdy_out(['ok' => false, 'error' => 'validation', 'msg' => 'معرّف الخبر غير صحيح.'], 400);
    }
    if ($body === '') {
        gdy_out(['ok' => false, 'error' => 'validation', 'msg' => 'نص التعليق مطلوب.'], 400);
    }

    // If replying, validate parent exists & belongs to same news
    if ($parentId > 0) {
        try {
            $st = $pdo->prepare("SELECT id FROM comments WHERE id = :pid AND news_id = :nid LIMIT 1");
            $st->execute([':pid' => $parentId, ':nid' => $newsId]);
            if (!$st->fetchColumn()) {
                gdy_out(['ok' => false, 'error' => 'validation', 'msg' => 'التعليق الذي تحاول الرد عليه غير موجود.'], 400);
            }
        } catch (Throwable $e) {
            gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null], 500);
        }
    }

    // Guest requires name/email
    if (!$isLogged) {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        if ($name === '' || $email === '') {
            gdy_out(['ok' => false, 'error' => 'name_email_required', 'msg' => 'الاسم والبريد الإلكتروني مطلوبان.'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            gdy_out(['ok' => false, 'error' => 'email_invalid', 'msg' => 'البريد الإلكتروني غير صالح.'], 400);
        }
    } else {
        // Logged in: infer name/email if needed
        if ($name === '') {
            if ($email !== '' && strpos($email, '@') !== false) {
                $name = explode('@', $email)[0];
            } else {
                $name = 'Member';
            }
        }
        if ($email === '') $email = 'member@local';
    }

    $status = $isLogged ? 'approved' : 'pending';
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    try {
        $st = $pdo->prepare(
            "INSERT INTO comments (news_id, parent_id, name, email, body, status, ip, user_agent)
             VALUES (:nid, :pid, :name, :email, :body, :status, :ip, :ua)"
        );
        $st->execute([
            ':nid' => $newsId,
            ':pid' => $parentId,
            ':name' => $name,
            ':email' => $email,
            ':body' => $body,
            ':status' => $status,
            ':ip' => $ip,
            ':ua' => $ua,
        ]);

        gdy_out([
            'ok' => true,
            'id' => (int)$pdo->lastInsertId(),
            'status' => $status,
        ]);
    } catch (Throwable $e) {
        gdy_out(['ok' => false, 'error' => 'db', 'detail' => $debug ? $e->getMessage() : null], 500);
    }
}

gdy_out(['ok' => false, 'error' => 'bad_action'], 400);
