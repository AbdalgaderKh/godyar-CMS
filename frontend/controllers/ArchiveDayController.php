<?php
// عرض أخطاء مؤقتًا أثناء التشخيص (احذف لاحقًا)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/bootstrap.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') {
    http_response_code(404);
    exit('Category slug is required.');
}

// إعدادات عامة (قوائم/سوشيال/وصف الفوتر..)
$settings = [];
try {
    if (class_exists('Settings')) {
        $settings = Settings::getAll();
    } else {
        $pdo = db();
        if ($pdo instanceof PDO) {
            $st = $pdo->query("SELECT `key`,`value` FROM settings");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $settings[$r['key']] = $r['value'];
        }
    }
} catch (Throwable $e) { error_log('CAT_SETTINGS: '.$e->getMessage()); }
$site_title    = $settings['site_title'] ?? 'Godyar';
$site_name     = $settings['site_name'] ?? $site_title;
$footer_about  = $settings['footer_about'] ?? '';
$main_menu     = json_decode($settings['menu_main']   ?? '[]', true) ?: [];
$footer_links  = json_decode($settings['menu_footer'] ?? '[]', true) ?: [];
$social_links  = json_decode($settings['social_links']?? '[]', true) ?: [];

// جلب بيانات التصنيف
$pdo = db();
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection not available.');
}

try {
    $st = $pdo->prepare("SELECT id, name, slug, description 
                         FROM categories 
                         WHERE slug = :slug AND (is_active = 1 OR is_active IS NULL) 
                         LIMIT 1");
    $st->execute([':slug' => $slug]);
    $category = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('CAT_FETCH: '.$e->getMessage());
    $category = null;
}

if (!$category) {
    http_response_code(404);
    exit('التصنيف غير موجود.');
}

// ترقيم الصفحات الآمن
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$total = 0;
$items = [];

try {
    // إجمالي المقالات
    $cnt = $pdo->prepare("SELECT COUNT(*) 
                          FROM news 
                          WHERE status='published' AND category_id = :cid");
    $cnt->execute([':cid' => (int)$category['id']]);
    $total = (int)$cnt->fetchColumn();

    // جلب المقالات (LIMIT/OFFSET ثابتان رقميًا لتفادي emulated prepares)
    $lim = (int)$perPage;
    $off = (int)$offset;
    $sql = "SELECT id, slug, title, excerpt, featured_image, publish_at
            FROM news
            WHERE status='published' AND category_id = :cid
            ORDER BY publish_at DESC
            LIMIT $lim OFFSET $off";
    $qs = $pdo->prepare($sql);
    $qs->execute([':cid' => (int)$category['id']]);
    $items = $qs->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('CAT_NEWS: '.$e->getMessage());
    $items = [];
}

$pages = max(1, (int)ceil($total / $perPage));

// SEO
$pageTitle       = $category['name'] . ' | ' . $site_title;
$pageDescription = !empty($category['description']) ? $category['description'] : ($settings['site_description'] ?? $site_title);
$canonical       = '/godyar/category/' . rawurlencode($category['slug']);

// اعرض الواجهة
$view = __DIR__ . '/../views/category.php';
if (is_file($view)) {
    require $view;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Category: ".h($category['name'])."\n";
    foreach ($items as $n) echo "- ".h($n['title'])."\n";
}
