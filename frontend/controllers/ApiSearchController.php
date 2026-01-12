<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
$q = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1)); $perPage = max(1,min(50,(int)($_GET['limit'] ?? 12))); $offset = ($page-1)*$perPage;
if ($q === '') { echo json_encode(['ok'=>true,'items'=>[]]); exit; }
try {
  $like = "%$q%";
  $cnt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE status='published' AND (title LIKE :q OR excerpt LIKE :q OR content LIKE :q)");
  $cnt->execute([':q'=>$like]);
  $total = (int)$cnt->fetchColumn();
  $lim=(int)$perPage; $off=(int)$offset;
  $sql = "SELECT slug,title,excerpt,featured_image,publish_at FROM news WHERE status='published' AND (title LIKE :q OR excerpt LIKE :q OR content LIKE :q) ORDER BY publish_at DESC LIMIT $lim OFFSET $off";
  $st = $pdo->prepare($sql);
  $st->execute([':q'=>$like]);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'total'=>$total,'items'=>$items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { error_log('API_SEARCH: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false]); }
