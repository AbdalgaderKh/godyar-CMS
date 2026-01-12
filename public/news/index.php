<?php
declare(strict_types=1);

// /godyar/public/news/index.php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/front_ads.php';

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$items = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("
            SELECT id, title, slug, excerpt, image_path, published_at
            FROM news
            WHERE status = 'published'
            ORDER BY published_at DESC
            LIMIT 20
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        @error_log('[Front News Index] ' . $e->getMessage());
    }
}

// هنا يفترض أنك لديك هيدر القالب الأمامي
// include __DIR__ . '/../layout/header.php';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title>الأخبار - <?= h(env('SITE_NAME','Godyar News')) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/godyar/assets/css/bootstrap.min.css">
</head>
<body>

<div class="container py-4">
  <div class="row">
    <div class="col-md-8">
      <h1 class="h3 mb-3">آخر الأخبار</h1>

      <?php if (empty($items)): ?>
        <p class="text-muted">لا توجد أخبار منشورة حالياً.</p>
      <?php else: ?>
        <?php foreach ($items as $news): ?>
          <article class="card mb-3">
            <div class="card-body d-flex gap-3">
              <?php if (!empty($news['image_path'])): ?>
                <div style="width:120px;flex-shrink:0;">
                  <img src="<?= h($news['image_path']) ?>" alt="<?= h($news['title']) ?>"
                       style="width:100%;height:auto;object-fit:cover;">
                </div>
              <?php endif; ?>

              <div>
                <h2 class="h5">
                  <a href="view.php?slug=<?= urlencode($news['slug']) ?>" class="text-decoration-none">
                    <?= h($news['title']) ?>
                  </a>
                </h2>
                <div class="text-muted small mb-2"><?= h($news['published_at']) ?></div>
                <p class="mb-0"><?= nl2br(h($news['excerpt'])) ?></p>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="col-md-4">
      <h2 class="h6 mb-3">إعلانات</h2>
      <?php godyar_render_ads('sidebar_top', 2); ?>
    </div>
  </div>
</div>

</body>
</html>
