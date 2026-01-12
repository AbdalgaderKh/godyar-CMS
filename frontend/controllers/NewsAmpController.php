<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
if (!$slug) { 
    http_response_code(404); 
    exit('Not found'); 
}

$news = null;
try {
    $st = $pdo->prepare("SELECT * FROM news WHERE slug=:s AND status='published' LIMIT 1");
    $st->execute([':s'=>$slug]);
    $news = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { 
    error_log('NEWS_DETAIL: '.$e->getMessage()); 
}

if (!$news) { 
    http_response_code(404); 
    exit('Not found'); 
}

// زيادة عدد المشاهدات
try {
    $updateStmt = $pdo->prepare("UPDATE news SET views = COALESCE(views, 0) + 1 WHERE slug = :slug");
    $updateStmt->execute([':slug' => $slug]);
} catch (Throwable $e) {
    error_log('NEWS_VIEWS_UPDATE: ' . $e->getMessage());
}

// السماح للإضافات بالتعامل مع البيانات قبل العرض
if (function_exists('g_do_hook')) {
    g_do_hook('frontend_news_before_render', $news, $pdo);
}

$site = class_exists('Settings') ? (Settings::get('site_title') ?? 'Godyar') : 'Godyar';
$canonical = '/news/id/' . (int)($news['id'] ?? 0);

require __DIR__ . '/../views/news_detail.php';