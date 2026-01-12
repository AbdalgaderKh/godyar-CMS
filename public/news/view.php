<?php
declare(strict_types=1);

// /godyar/public/news/view.php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/front_ads.php';

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';

$news = null;
if ($pdo instanceof PDO) {
    try {
        if ($slug !== '') {
            $stmt = $pdo->prepare("SELECT * FROM news WHERE slug = :slug AND status='published' LIMIT 1");
            $stmt->execute([':slug' => $slug]);
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id AND status='published' LIMIT 1");
            $stmt->execute([':id' => $id]);
        }
        $news = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    } catch (Throwable $e) {
        @error_log('[Front News View] ' . $e->getMessage());
    }
}

if (!$news) {
    http_response_code(404);
    echo 'الخبر غير موجود.';
    exit;
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title><?= h($news['title']) ?> - <?= h(env('SITE_NAME','Godyar News')) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/godyar/assets/css/bootstrap.min.css">
</head>
<body>
<div class="container py-4">
  <div class="row">
    <div class="col-md-8">
      <article>
        <h1 class="h3 mb-2"><?= h($news['title']) ?></h1>
        <div class="text-muted small mb-3"><?= h($news['published_at'] ?: $news['created_at']) ?></div>

        <?php if (!empty($news['image_path'])): ?>
          <div class="mb-3">
            <img src="<?= h($news['image_path']) ?>" alt="<?= h($news['title']) ?>"
                 class="img-fluid rounded">
          </div>
        <?php endif; ?>

        <?php if (!empty($news['excerpt'])): ?>
          <p class="lead"><?= nl2br(h($news['excerpt'])) ?></p>
        <?php endif; ?>

        <div style="white-space:pre-wrap;word-wrap:break-word;">
          <?= nl2br(h($news['body'])) ?>
        </div>
      </article>
    </div>
    <div class="col-md-4">
      <h2 class="h6 mb-3">إعلانات</h2>
      <?php godyar_render_ads('article_sidebar', 2); ?>
    </div>
  </div>
</div>
</body>
</html>
