<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE);
  exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q, 'UTF-8') < 2) {
  echo json_encode(['ok'=>true,'items'=>[]], JSON_UNESCAPED_UNICODE);
  exit;
}

$like = '%' . $q . '%';

try {
  // Keep query conservative to work across different schemas
  $sql = "SELECT id, title, slug
          FROM news
          WHERE (title LIKE :q OR slug LIKE :q)
          ORDER BY id DESC
          LIMIT 8";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':q' => $like]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $items = [];
  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    if ($id <= 0) continue;
    $slug = (string)($r['slug'] ?? '');
    // Router-safe URL (works even if slug is empty)
    $url = '/news/id/' . $id . ($slug !== '' ? ('/' . rawurlencode($slug)) : '');
    $items[] = [
      'id' => $id,
      'title' => (string)($r['title'] ?? ''),
      'url' => $url,
    ];
  }

  echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  @error_log('[InternalLinks] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error'], JSON_UNESCAPED_UNICODE);
}
