<?php
declare(strict_types=1);

// /track.php — endpoint خفيف لتسجيل الزيارات من المتصفح
// الهدف: ضمان احتساب الزيارات حتى في حال كان هناك كاش/ CDN يمنع تشغيل PHP لصفحات HTML.

require_once __DIR__ . '/includes/bootstrap.php';

// منع الكاش
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// قراءة JSON (أو form-data)
$payload = [];
try {
    $raw = (string)file_get_contents('php://input');
    if ($raw !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
} catch (Throwable $e) {
    $payload = [];
}

if (empty($payload)) {
    $payload = $_POST ?? [];
    if (!is_array($payload)) $payload = [];
}

$pageRaw = (string)($payload['page'] ?? 'other');
// صفحة آمنة (حروف/أرقام/underscore فقط)
$page = preg_replace('/[^a-z0-9_]/i', '', $pageRaw);
if ($page === '') $page = 'other';

$newsId = null;
if (isset($payload['news_id'])) {
    $newsId = (int)$payload['news_id'];
    if ($newsId <= 0) $newsId = null;
}

// referrer الحقيقي (document.referrer)؛ إن لم يصل نستخدم HTTP_REFERER كاحتياط
$ref = (string)($payload['referrer'] ?? '');
if ($ref === '') {
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
}

// نفس منطق منع التكرار (10 دقائق)
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$key = 'visit_' . $page . '_' . (string)($newsId ?? 0);
if (isset($_SESSION[$key]) && time() - (int)$_SESSION[$key] < 600) {
    echo json_encode(['ok' => true, 'skipped' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
$_SESSION[$key] = time();

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No DB'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

try {
    // لو الأعمدة الحديثة موجودة نستعملها، وإلا fallback
    $hasOs = function_exists('db_column_exists') ? db_column_exists($pdo, 'visits', 'os') : false;
    $hasBr = function_exists('db_column_exists') ? db_column_exists($pdo, 'visits', 'browser') : false;
    $hasDv = function_exists('db_column_exists') ? db_column_exists($pdo, 'visits', 'device') : false;

    if ($hasOs && $hasBr && $hasDv) {
        $stmt = $pdo->prepare("INSERT INTO visits (page,news_id,source,referrer,user_ip,user_agent,os,browser,device) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $page,
            $newsId,
            gdy_classify_source($ref),
            $ref,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $ua,
            function_exists('gdy_parse_os') ? gdy_parse_os($ua) : null,
            function_exists('gdy_parse_browser') ? gdy_parse_browser($ua) : null,
            function_exists('gdy_parse_device') ? gdy_parse_device($ua) : null,
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO visits (page,news_id,source,referrer,user_ip,user_agent) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $page,
            $newsId,
            gdy_classify_source($ref),
            $ref,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $ua,
        ]);
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    @error_log('[track.php] insert error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Insert failed'], JSON_UNESCAPED_UNICODE);
}
