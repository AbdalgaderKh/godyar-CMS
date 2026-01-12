<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('j')) {
    function j($v): string {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo j(['ok' => false, 'error' => 'NO_DB']);
    exit;
}

$newsId = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
$type   = isset($_POST['type']) ? trim((string)$_POST['type']) : '';

$allowedTypes = ['like','important','love','angry'];

if ($newsId <= 0 || $type === '' || !in_array($type, $allowedTypes, true)) {
    echo j(['ok' => false, 'error' => 'INVALID_DATA']);
    exit;
}

// نتحقق من وجود جدول التفاعلات
try {
    $check = $pdo->query("SHOW TABLES LIKE 'news_reactions'");
    if (!$check || !$check->fetchColumn()) {
        echo j(['ok' => false, 'error' => 'TABLE_MISSING']);
        exit;
    }
} catch (Throwable $e) {
    @error_log('[Godyar Reaction] check table: ' . $e->getMessage());
    echo j(['ok' => false, 'error' => 'EXCEPTION']);
    exit;
}

$ip  = $_SERVER['REMOTE_ADDR'] ?? null;
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? null;

try {
    $stmt = $pdo->prepare("INSERT INTO news_reactions (news_id, type, ip_address, user_agent) VALUES (:nid, :type, :ip, :ua)");
    $stmt->execute([
        ':nid'  => $newsId,
        ':type' => $type,
        ':ip'   => $ip,
        ':ua'   => $ua,
    ]);

    // نجلب إحصائيات بسيطة لهذا الخبر
    $counts = [];
    $st2 = $pdo->prepare("SELECT type, COUNT(*) AS c FROM news_reactions WHERE news_id = :nid GROUP BY type");
    $st2->execute([':nid' => $newsId]);
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['type']] = (int)$row['c'];
    }

    echo j(['ok' => true, 'counts' => $counts]);
} catch (Throwable $e) {
    @error_log('[Godyar Reaction] insert: ' . $e->getMessage());
    echo j(['ok' => false, 'error' => 'EXCEPTION']);
}
