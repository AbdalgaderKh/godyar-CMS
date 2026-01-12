<?php
declare(strict_types=1);

// /godyar/frontend/controllers/TagController.php

require_once __DIR__ . '/../../includes/bootstrap.php';

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// slug من الرابط: /tag/slug
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
if ($slug === '') {
    header("HTTP/1.1 404 Not Found");
    echo 'الوسم غير موجود';
    exit;
}

$tag   = null;
$items = [];
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;
$total   = 0;
$pages   = 1;

try {
    if ($pdo instanceof PDO) {
        // البيانات الأساسية للوسم
        $stmt = $pdo->prepare("SELECT id, name, slug, description FROM tags WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $tag = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$tag) {
            header("HTTP/1.1 404 Not Found");
            echo 'الوسم غير موجود';
            exit;
        }

        // إجمالي الأخبار تحت هذا الوسم
        $cnt = $pdo->prepare("
            SELECT COUNT(*)
            FROM news n
            INNER JOIN news_tags nt ON nt.news_id = n.id
            WHERE nt.tag_id = :tid
              AND n.status = 'published'
        ");
        $cnt->execute([':tid' => (int)$tag['id']]);
        $total = (int)$cnt->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));

        // قائمة الأخبار
        $sql = "SELECT n.id,
                       n.slug,
                       n.featured_image,
                       n.title,
                       n.excerpt,
                       n.publish_at
                FROM news n
                INNER JOIN news_tags nt ON nt.news_id = n.id
                WHERE nt.tag_id = :tid
                  AND n.status = 'published'
                ORDER BY n.publish_at DESC, n.id DESC
                LIMIT :limit OFFSET :offset";
        $st = $pdo->prepare($sql);
        $st->bindValue(':tid', (int)$tag['id'], PDO::PARAM_INT);
        $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    @error_log('[TagController] ' . $e->getMessage());
    $tag   = null;
    $items = [];
}

if (!$tag) {
    header("HTTP/1.1 404 Not Found");
    echo 'الوسم غير موجود';
    exit;
}

// SEO + عنوان ووصف الصفحة
$tagName        = (string)($tag['name'] ?? '');
$tagSlug        = (string)($tag['slug'] ?? '');
$tagDescription = (string)($tag['description'] ?? '');
if ($tagDescription === '') {
    $tagDescription = "الأخبار المرتبطة بالوسم {$tagName}.";
}

// baseUrl
$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
if ($baseUrl === '') {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $scheme . '://' . $host;
}

$canonicalUrl = $baseUrl . '/tag/' . rawurlencode($tagSlug !== '' ? $tagSlug : $tagName);
if ($page > 1) {
    $canonicalUrl .= '?page=' . (int)$page;
}

$homeUrl = rtrim($baseUrl, '/') . '/';

// OG image ديناميكي للوسم
$ogImage = $baseUrl . '/og_image.php?type=tag&title=' . rawurlencode($tagName);

$pageSeo = [
    'title' => 'الوسم: ' . $tagName,
    'description' => $tagDescription,
    'url' => $canonicalUrl,
    'type' => 'website',
    'image' => $ogImage,
    'jsonld' => json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type'=>'ListItem','position'=>1,'name'=>'الرئيسية','item'=>$homeUrl],
            ['@type'=>'ListItem','position'=>2,'name'=>'وسم: ' . $tagName,'item'=>$canonicalUrl],
        ],
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
];

// تضمين الهيدر + الـ view + الفوتر
$header = __DIR__ . '/../templates/header.php';
$footer = __DIR__ . '/../templates/footer.php';
$view   = __DIR__ . '/../views/tag.php';

if (is_file($header)) require $header;

if (is_file($view)) {
    require $view;
} else {
    echo "View not found: " . h($view);
}

if (is_file($footer)) require $footer;
