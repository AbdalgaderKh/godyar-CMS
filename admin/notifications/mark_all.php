<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

if (function_exists('verify_csrf')) {
  verify_csrf('csrf_token');
}

try {
  $pdo = \Godyar\DB::pdo();
} catch (\Throwable $e) {
  $pdo = null;
}

$uid = (int)($_SESSION['user']['id'] ?? 0);

if ($pdo instanceof \PDO && $uid > 0) {
  try {
    $chk = $pdo->query("SHOW TABLES LIKE 'admin_notifications'");
    $has = $chk && $chk->fetchColumn();
    if ($has) {
      $stmt = $pdo->prepare("UPDATE admin_notifications SET is_read=1 WHERE is_read=0 AND (user_id IS NULL OR user_id=:uid)");
      $stmt->execute(['uid'=>$uid]);
    }
  } catch (\Throwable $e) {
    // ignore
  }
}

$ref = (string)($_SERVER['HTTP_REFERER'] ?? '/admin/notifications/index.php');
header('Location: ' . $ref);
exit;
