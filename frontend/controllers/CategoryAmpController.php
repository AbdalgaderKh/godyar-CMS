<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$slug = $_GET['slug'] ?? null; if (!$slug){ http_response_code(404); exit; }
$page = max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;
$st=$pdo->prepare("SELECT * FROM categories WHERE slug=:s AND is_active=1 LIMIT 1");
$st->execute([':s'=>$slug]); $category=$st->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$category){ http_response_code(404); exit; }
$lim=(int)$perPage; $off=(int)$offset;
$sql="SELECT slug,title,excerpt,featured_image,publish_at FROM news WHERE status='published' AND category_id=:cid ORDER BY publish_at DESC LIMIT $lim OFFSET $off";
$st=$pdo->prepare($sql); $st->execute([':cid'=>(int)$category['id']]); $items=$st->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/../views/category_amp.php';
