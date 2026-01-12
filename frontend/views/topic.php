<?php
// /frontend/views/topic.php

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$tag   = $tag   ?? [];
$meta  = $meta  ?? ['intro'=>'','cover_path'=>''];
$best  = $best  ?? [];
$latest= $latest?? [];
$items = $items ?? [];
$page  = (int)($page ?? 1);
$pages = (int)($pages ?? 1);

$baseUrl = $baseUrl ?? (function_exists('base_url') ? rtrim((string)base_url(), '/') : '');

$name = (string)($tag['name'] ?? '');
$slug = (string)($tag['slug'] ?? '');
$intro = trim((string)($meta['intro'] ?? ''));
$cover = trim((string)($meta['cover_path'] ?? ''));

$coverUrl = '';
if ($cover !== '') {
    $coverUrl = preg_match('~^https?://~i', $cover) ? $cover : ($baseUrl . '/' . ltrim($cover, '/'));
}

?>
<main class="container py-4">
  <div class="card border-0 shadow-sm mb-4 overflow-hidden" style="border-radius:18px;">
    <?php if ($coverUrl): ?>
      <div style="height:180px; background:url('<?= h($coverUrl) ?>') center/cover no-repeat;"></div>
    <?php else: ?>
      <div style="height:140px; background:linear-gradient(135deg, rgba(14,165,233,.18), rgba(99,102,241,.12));"></div>
    <?php endif; ?>
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <div class="text-muted small mb-1"><?= h(__('الموضوع')) ?></div>
          <h1 class="h4 mb-0">#<?= h($name) ?></h1>
        </div>
        <a class="btn btn-outline-light" href="<?= h($baseUrl . '/tag/' . rawurlencode($slug ?: $name)) ?>">
          <?= h(__('عرض الوسم الكلاسيكي')) ?>
        </a>
      </div>

      <?php if ($intro !== ''): ?>
        <p class="text-muted mt-3 mb-0" style="max-width:900px;"><?= nl2br(h($intro)) ?></p>
      <?php else: ?>
        <p class="text-muted mt-3 mb-0" style="max-width:900px;"><?= h(__('أحدث وأهم المقالات المتعلقة بهذا الموضوع.')) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($best)): ?>
    <section class="mb-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="h6 mb-0"><?= h(__('الأكثر قراءة في هذا الموضوع')) ?></h2>
      </div>
      <div class="row g-3">
        <?php foreach ($best as $it): 
          $nid = (int)($it['id'] ?? 0);
          $title = (string)($it['title'] ?? '');
          $slugN = (string)($it['slug'] ?? '');
          $newsUrl = $nid > 0 ? ($baseUrl . ($slugN !== '' ? '/news/' . $slugN : '/news/id/' . $nid)) : '#';
          $img = (string)($it['image'] ?? '');
          $imgUrl = $img ? ($baseUrl . '/img.php?src=' . rawurlencode($img) . '&w=560') : '';
        ?>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="card h-100 text-decoration-none border-0 shadow-sm" href="<?= h($newsUrl) ?>" style="border-radius:16px;">
            <?php if ($imgUrl): ?>
              <img class="card-img-top" src="<?= h($imgUrl) ?>" alt="<?= h($title) ?>" style="height:160px; object-fit:cover;">
            <?php endif; ?>
            <div class="card-body">
              <h3 class="h6 mb-0"><?= h($title) ?></h3>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="mb-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
      <h2 class="h6 mb-0"><?= h(__('أحدث المقالات')) ?></h2>
      <div class="text-muted small"><?= h(__('صفحة')) ?> <?= (int)$page ?> / <?= (int)$pages ?></div>
    </div>

    <div class="row g-3">
      <?php foreach ($items as $it):
        $nid = (int)($it['id'] ?? 0);
        $title = (string)($it['title'] ?? '');
        $slugN = (string)($it['slug'] ?? '');
        $excerpt = (string)($it['excerpt'] ?? '');
        $newsUrl = $nid > 0 ? ($baseUrl . ($slugN !== '' ? '/news/' . $slugN : '/news/id/' . $nid)) : '#';
        $img = (string)($it['image'] ?? '');
        $imgUrl = $img ? ($baseUrl . '/img.php?src=' . rawurlencode($img) . '&w=560') : '';
      ?>
      <div class="col-12 col-md-6 col-lg-4">
        <a class="card h-100 text-decoration-none border-0 shadow-sm" href="<?= h($newsUrl) ?>" style="border-radius:16px;">
          <?php if ($imgUrl): ?>
            <img class="card-img-top" src="<?= h($imgUrl) ?>" alt="<?= h($title) ?>" style="height:160px; object-fit:cover;">
          <?php endif; ?>
          <div class="card-body">
            <h3 class="h6 mb-1"><?= h($title) ?></h3>
            <?php if ($excerpt): ?>
              <p class="text-muted small mb-0"><?= h(mb_substr($excerpt, 0, 160, 'UTF-8')) ?>…</p>
            <?php endif; ?>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <?php
            $base = $baseUrl . '/topic/' . rawurlencode($slug ?: $name);
            $prev = max(1, $page - 1);
            $next = min($pages, $page + 1);
          ?>
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h($base . '/page/' . $prev) ?>"><?= h(__('السابق')) ?></a>
          </li>
          <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h($base . '/page/' . $next) ?>"><?= h(__('التالي')) ?></a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </section>
</main>
