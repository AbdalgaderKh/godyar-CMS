<?php
declare(strict_types=1);

// /godyar/public/page.php?slug=about — عرض صفحة ثابتة من جدول pages

require_once __DIR__ . '/../includes/bootstrap.php';

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = gdy_pdo_safe();
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';

$pageColumns = [];
$page        = null;

if ($pdo instanceof PDO && $slug !== '') {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM pages");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pageColumns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        @error_log('[Front Page] SHOW COLUMNS pages: ' . $e->getMessage());
    }

    $sql = "SELECT * FROM pages WHERE slug = :slug";
    if (isset($pageColumns['status'])) {
        $sql .= " AND status = 'published'";
    }
    if (isset($pageColumns['is_published'])) {
        $sql .= " AND is_published = 1";
    }
    $sql .= " LIMIT 1";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        @error_log('[Front Page] select: ' . $e->getMessage());
    }
}

if (!$page) {
    http_response_code(404);
}

$siteName = (string)env('SITE_NAME', 'Godyar News');

$title   = $page['title']   ?? 'الصفحة غير موجودة';
$content = $page['content'] ?? ($page['body'] ?? '');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title><?= h($title) ?> - <?= h($siteName) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap -->
  <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    .page-hero {
      background: linear-gradient(135deg, #0891b2, #06b6d4);
      color: #fff;
      padding: 28px 0;
      margin-bottom: 20px;
    }
    .page-hero h1 {
      font-size: 1.7rem;
      margin: 0;
    }
    .page-card {
      border-radius: 18px;
      border: none;
      box-shadow: 0 10px 24px rgba(15,23,42,.1);
      background-color: #ffffff;
    }
    .page-content {
      font-size: 1rem;
      line-height: 1.8;
    }
    .page-content img {
      max-width: 100%;
      height: auto;
    }
  </style>
</head>
<body>

<header class="page-hero">
  <div class="container">
    <h1><?= h($title) ?></h1>
  </div>
</header>

<main class="py-3">
  <div class="container">
    <?php if (!$page): ?>
      <div class="alert alert-warning text-center">
        عذراً، لم يتم العثور على هذه الصفحة.
      </div>
    <?php else: ?>
      <article class="page-card p-3 p-md-4">
        <div class="page-content">
          <?= $content // محتوى موثوق من لوحة التحكم ?>
        </div>
      </article>
    <?php endif; ?>
  </div>
</main>

<footer class="py-4 text-center text-muted small">
  &copy; <?= date('Y') ?> <?= h($siteName) ?> . جميع الحقوق محفوظة.
</footer>

</body>
</html>
