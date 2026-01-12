<?php
// /godyar/frontend/views/tag.php

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// تجهيز baseUrl
if (!isset($baseUrl)) {
    $baseUrl = function_exists('base_url') ? rtrim(base_url(), '/') : '';
}

$tag   = $tag   ?? [];
$items = $items ?? [];
$pages = $pages ?? 1;
$page  = $page  ?? 1;

$tagName        = $tag['name'] ?? '';
$tagDescription = $tag['description'] ?? "الأخبار المرتبطة بهذا الوسم.";
?>

<div class="container py-4">

  <header class="mb-3">
    <h1 class="h4 mb-1">الوسم: <?= h($tagName) ?></h1>
    <?php if ($tagDescription): ?>
      <p class="text-muted mb-0"><?= h($tagDescription) ?></p>
    <?php endif; ?>
  </header>

  <?php if (!empty($items)): ?>
    <div class="row g-3">
      <?php foreach ($items as $n): ?>
        <?php
          $slug    = $n['slug'] ?? '';
          $title   = $n['title'] ?? '';
          $excerpt = $n['excerpt'] ?? '';
          $img     = $n['featured_image'] ?? '';
          $nid = (int)($n['id'] ?? 0);
          $newsUrl = $nid > 0 ? (rtrim($baseUrl,'/') . '/news/id/' . $nid) : '#';
          $imgUrl  = $img ? $baseUrl . '/img.php?src=' . rawurlencode($img) . '&w=400' : '';
        ?>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="card h-100 text-decoration-none" href="<?= h($newsUrl) ?>">
            <?php if ($imgUrl): ?>
              <img class="card-img-top"
                   src="<?= h($imgUrl) ?>"
                   alt="<?= h($title) ?>">
            <?php endif; ?>
            <div class="card-body">
              <h3 class="h6 mb-1"><?= h($title) ?></h3>
              <?php if ($excerpt): ?>
                <p class="text-muted small mb-0">
                  <?= h(mb_substr($excerpt, 0, 160, 'UTF-8')) ?><?= (mb_strlen($excerpt, 'UTF-8') > 160 ? '…' : '') ?>
                </p>
              <?php endif; ?>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (($pages ?? 1) > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <?php
              $pageUrl = $baseUrl . '/tag/' . rawurlencode($tag['slug'] ?? '') . '?page=' . $p;
            ?>
            <li class="page-item <?= $p == $page ? 'active' : ''; ?>">
              <a class="page-link" href="<?= h($pageUrl) ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>

  <?php else: ?>
    <div class="alert alert-info">لا توجد مقالات تحت هذا الوسم حالياً.</div>
  <?php endif; ?>

</div>
