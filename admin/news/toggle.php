<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
$BASE_DIR = dirname(__DIR__, 2);
// bootstrap loaded by _admin_guard.php; keep require_once to avoid redeclare fatals
require_once $BASE_DIR . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

header('Content-Type: application/json; charset=utf-8');

// CSRF
$csrf = (string)($_POST['csrf_token'] ?? '');
if (function_exists('verify_csrf_token') && !verify_csrf_token($csrf)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'CSRF failed']);
    exit;
}

$role = $_SESSION['user']['role'] ?? 'guest';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'forbidden']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'missing id']);
    exit;
}

$pdo = $pdo ?? null;
if ($pdo instanceof PDO) {
    // toggle: published <-> draft
    $st = $pdo->prepare("UPDATE news SET status = IF(status='published','draft','published') WHERE id=?");
    $ok = $st->execute([$id]);
    echo json_encode(['ok' => (bool)$ok]);
    exit;
}

echo json_encode(['ok' => true]);
