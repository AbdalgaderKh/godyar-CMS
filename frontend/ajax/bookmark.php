<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$user = $_SESSION['user'] ?? null;
if (!$user || empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'AUTH_REQUIRED'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'NO_DB'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$newsId = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
$action = $_POST['action'] ?? 'toggle';

if ($newsId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'INVALID_NEWS'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // نتأكد أن جدول user_bookmarks موجود
    $check = $pdo->query("SHOW TABLES LIKE 'user_bookmarks'");
    if (!$check || !$check->fetchColumn()) {
        echo json_encode(['ok' => false, 'error' => 'TABLE_MISSING'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $userId = (int)$user['id'];

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_bookmarks (user_id, news_id) VALUES (:uid, :nid)");
        $stmt->execute([':uid' => $userId, ':nid' => $newsId]);
        echo json_encode(['ok' => true, 'status' => 'added'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'remove') {
        $stmt = $pdo->prepare("DELETE FROM user_bookmarks WHERE user_id = :uid AND news_id = :nid");
        $stmt->execute([':uid' => $userId, ':nid' => $newsId]);
        echo json_encode(['ok' => true, 'status' => 'removed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // toggle
    $stmt = $pdo->prepare("SELECT id FROM user_bookmarks WHERE user_id = :uid AND news_id = :nid LIMIT 1");
    $stmt->execute([':uid' => $userId, ':nid' => $newsId]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $stmt = $pdo->prepare("DELETE FROM user_bookmarks WHERE id = :id");
        $stmt->execute([':id' => (int)$exists]);
        echo json_encode(['ok' => true, 'status' => 'removed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_bookmarks (user_id, news_id) VALUES (:uid, :nid)");
        $stmt->execute([':uid' => $userId, ':nid' => $newsId]);
        echo json_encode(['ok' => true, 'status' => 'added'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} catch (Throwable $e) {
    @error_log('[Godyar Bookmark] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'EXCEPTION'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
