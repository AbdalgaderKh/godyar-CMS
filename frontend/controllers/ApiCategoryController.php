<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
$slug = $_GET['slug'] ?? null;
if (!$slug) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing slug']); exit; }
try {
  $st=$pdo->prepare("SELECT id,name,slug FROM categories WHERE slug=:s AND is_active=1 LIMIT 1");
  $st->execute([':s'=>$slug]); $cat=$st->fetch(PDO::FETCH_ASSOC);
  if (!$cat) { http_response_code(404); echo json_encode(['ok'=>false]); exit; }
  $lim=min(50,max(1,(int)($_GET['limit']??12)));
  $sql="SELECT slug,title,excerpt,featured_image,publish_at FROM news WHERE status='published' AND category_id=".(int)$cat['id']." ORDER BY publish_at DESC LIMIT $lim";
  $items=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'category'=>$cat,'items'=> $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e){ error_log('API_CAT: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'internal']); }
