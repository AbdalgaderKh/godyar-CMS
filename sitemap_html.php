<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=900');

$pdo  = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
$base = function_exists('gdy_base_url') ? rtrim((string)gdy_base_url(), '/') : '';
if ($base === '') {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
  $base = $scheme . '://' . $host;
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$siteName = 'Sitemap';
try {
  if (function_exists('HomeController::getSiteSettings')) {
    // not reliable in CLI, ignore
  }
} catch (Throwable $e) {}

$items = [
  'home' => [],
  'categories' => [],
  'pages' => [],
  'news' => [],
];

$items['home'][] = ['title' => 'الرئيسية', 'url' => $base . '/'];

if ($pdo) {
  try {
    // categories
    $hasDeleted = false;
    $hasSlug = false;
    $q = $pdo->query("SHOW COLUMNS FROM categories");
    $cols = $q ? ($q->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($cols as $c) {
      $f = strtolower((string)($c['Field'] ?? ''));
      if ($f === 'deleted_at') $hasDeleted = true;
      if ($f === 'slug') $hasSlug = true;
    }
    $where = "1=1";
    if ($hasDeleted) $where .= " AND deleted_at IS NULL";
    $sql = "SELECT id, " . ($hasSlug ? "slug," : "'' as slug,") . " name FROM categories WHERE {$where} ORDER BY name ASC";
    $stmt = $pdo->query($sql);
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($rows as $r) {
      $id = (int)($r['id'] ?? 0);
      $name = trim((string)($r['name'] ?? ''));
      $slug = trim((string)($r['slug'] ?? ''));
      $url = $slug !== '' ? ($base . '/category/' . rawurlencode($slug)) : ($base . '/category/id/' . $id);
      if ($name !== '') $items['categories'][] = ['title' => $name, 'url' => $url];
    }

    // pages (static pages)
    $hasDeleted = false;
    $hasSlug = false;
    $q = $pdo->query("SHOW COLUMNS FROM pages");
    $cols = $q ? ($q->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($cols as $c) {
      $f = strtolower((string)($c['Field'] ?? ''));
      if ($f === 'deleted_at') $hasDeleted = true;
      if ($f === 'slug') $hasSlug = true;
    }
    $where = "status='published'";
    if ($hasDeleted) $where .= " AND deleted_at IS NULL";
    $sql = "SELECT id, " . ($hasSlug ? "slug," : "'' as slug,") . " title FROM pages WHERE {$where} ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($rows as $r) {
      $id = (int)($r['id'] ?? 0);
      $title = trim((string)($r['title'] ?? ''));
      $slug = trim((string)($r['slug'] ?? ''));
      $url = $slug !== '' ? ($base . '/page/' . rawurlencode($slug)) : ($base . '/page/id/' . $id);
      if ($title !== '') $items['pages'][] = ['title' => $title, 'url' => $url];
    }

    // news - last 500 (keep it light)
    $hasDeleted = false;
    $hasSlug = false;
    $dateCol = '';
    $q = $pdo->query("SHOW COLUMNS FROM news");
    $cols = $q ? ($q->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($cols as $c) {
      $f = strtolower((string)($c['Field'] ?? ''));
      if ($f === 'deleted_at') $hasDeleted = true;
      if ($f === 'slug') $hasSlug = true;
      if (in_array($f, ['published_at','created_at','created_on','date','publish_date'], true) && $dateCol === '') {
        $dateCol = $f;
      }
    }
    $where = "status='published'";
    if ($hasDeleted) $where .= " AND deleted_at IS NULL";
    $dateExpr = $dateCol !== '' ? $dateCol : 'created_at';
    $sql = "SELECT id, " . ($hasSlug ? "slug," : "'' as slug,") . " title, {$dateExpr} as dt
            FROM news WHERE {$where}
            ORDER BY {$dateExpr} DESC
            LIMIT 500";
    $stmt = $pdo->query($sql);
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($rows as $r) {
      $id = (int)($r['id'] ?? 0);
      $title = trim((string)($r['title'] ?? ''));
      $slug = trim((string)($r['slug'] ?? ''));
      $url = $slug !== '' ? ($base . '/news/' . rawurlencode($slug)) : ($base . '/news/id/' . $id);
      if ($title !== '') $items['news'][] = ['title' => $title, 'url' => $url];
    }

  } catch (Throwable $e) {
    @error_log('[sitemap_html] ' . $e->getMessage());
  }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>خريطة الموقع</title>
  <meta name="description" content="خريطة الموقع: روابط الأقسام والصفحات والأخبار.">
  <link rel="canonical" href="<?= h(function_exists('gdy_clean_url') ? gdy_clean_url(($base . '/sitemap')) : ($base . '/sitemap')) ?>">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#fff;color:#111}
    .wrap{max-width:980px;margin:0 auto;padding:24px}
    h1{margin:0 0 10px;font-size:28px}
    .note{color:#555;margin:0 0 18px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
    .card{border:1px solid #e7e7e7;border-radius:14px;padding:14px}
    .card h2{margin:0 0 10px;font-size:18px}
    ul{margin:0;padding:0 18px}
    li{margin:6px 0}
    a{color:#0b66c3;text-decoration:none}
    a:hover{text-decoration:underline}
    .top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:18px}
    .top a{font-weight:600}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <h1>خريطة الموقع</h1>
      <a href="<?= h($base . '/sitemap.xml') ?>">ملف Sitemap.xml</a>
    </div>
    <p class="note">هذه صفحة HTML مخصصة للزوار والبوتات لتسهيل اكتشاف الروابط. يتم تحديثها تلقائيًا.</p>

    <div class="grid">
      <div class="card">
        <h2>روابط أساسية</h2>
        <ul>
          <?php foreach ($items['home'] as $it): ?>
            <li><a href="<?= h($it['url']) ?>"><?= h($it['title']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="<?= h($base . '/rss.xml') ?>">RSS</a></li>
        </ul>
      </div>

      <div class="card">
        <h2>الأقسام</h2>
        <ul>
          <?php foreach ($items['categories'] as $it): ?>
            <li><a href="<?= h($it['url']) ?>"><?= h($it['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="card">
        <h2>الصفحات</h2>
        <ul>
          <?php foreach ($items['pages'] as $it): ?>
            <li><a href="<?= h($it['url']) ?>"><?= h($it['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="card">
        <h2>آخر الأخبار (500)</h2>
        <ul>
          <?php foreach ($items['news'] as $it): ?>
            <li><a href="<?= h($it['url']) ?>"><?= h($it['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</body>
</html>
