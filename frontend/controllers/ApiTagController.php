<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
$slug = $_GET['slug'] ?? null;
if (!$slug) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing slug']); exit; }
try {
  $st=$pdo->prepare("SELECT id,name,slug FROM tags WHERE slug=:s LIMIT 1");
  $st->execute([':s'=>$slug]); $tag=$st->fetch(PDO::FETCH_ASSOC);
  if (!$tag) { http_response_code(404); echo json_encode(['ok'=>false]); exit; }
  $lim=min(50,max(1,(int)($_GET['limit']??12)));
  $sql="SELECT n.slug,n.title,n.excerpt,n.featured_image,n.publish_at
       FROM news n INNER JOIN news_tags nt ON nt.news_id=n.id
       WHERE nt.tag_id=".(int)$tag['id']." AND n.status='published'
       ORDER BY n.publish_at DESC
       LIMIT $lim";
  $items=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'tag'=>$tag,'items'=> $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e){ error_log('API_TAG: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'internal']); }
