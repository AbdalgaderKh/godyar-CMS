<?php
// frontend/views/partials/featured_videos.php

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    return;
}

try {
    $stmt = $pdo->query("
        SELECT id, title, video_url, description, is_active, created_at
        FROM featured_videos
        WHERE is_active = 1
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $videos = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $videos = [];
}

if (!$videos) {
    return;
}
?>

<section class="my-4">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 mb-0">
      <svg class="gdy-icon ms-1 text-danger" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
      فيديوهات مميزة
    </h2>
    <span class="small text-muted">لقطات مختارة بعناية من جوديـار</span>
  </div>

  <div class="row g-3">
    <?php foreach ($videos as $v): ?>
      <?php
        $title = $v['title'] ?? '';
        $url   = $v['video_url'] ?? '';
        $desc  = $v['description'] ?? '';
        $thumb = gdy_video_thumbnail($url);

        // شارة نوع المنصة
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $platform = 'منصة أخرى';
        $badgeClass = 'bg-secondary';
        if (stripos($host, 'youtube.com') !== false || stripos($host, 'youtu.be') !== false) {
            $platform = 'YouTube';
            $badgeClass = 'bg-danger';
        } elseif (stripos($host, 'facebook.com') !== false) {
            $platform = 'Facebook';
            $badgeClass = 'bg-primary';
        } elseif (stripos($host, 'twitter.com') !== false || stripos($host, 'x.com') !== false) {
            $platform = 'X';
            $badgeClass = 'bg-dark';
        }
      ?>
      <div class="col-12 col-md-6 col-lg-4">
        <article class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="position-relative" style="padding-top:56%;">
            <?php if ($thumb): ?>
              <img src="<?= h($thumb) ?>" alt="<?= h($title) ?>"
                   class="position-absolute top-0 start-0 w-100 h-100"
                   style="object-fit:cover;">
            <?php else: ?>
              <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark d-flex align-items-center justify-content-center text-light">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </div>
            <?php endif; ?>

            <span class="badge <?= $badgeClass ?> position-absolute top-2 start-2 rounded-pill px-2 py-1 small">
              <?= h($platform) ?>
            </span>

            <div class="position-absolute top-50 start-50 translate-middle bg-white bg-opacity-75 rounded-circle p-2">
              <svg class="gdy-icon text-danger" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
          </div>
          <div class="card-body p-2 p-md-3">
            <h3 class="h6 mb-1 text-truncate" title="<?= h($title) ?>"><?= h($title) ?></h3>
            <?php if ($desc): ?>
              <p class="small text-muted mb-2" style="max-height:3.2em;overflow:hidden;">
                <?= h($desc) ?>
              </p>
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center">
              <a href="<?= h($url) ?>" target="_blank" rel="noopener"
                 class="btn btn-sm btn-outline-danger rounded-pill">
                <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تشغيل الفيديو
              </a>
              <span class="small text-muted">
                <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <?= h(date('Y-m-d', strtotime((string)$v['created_at'] ?? 'now'))) ?>
              </span>
            </div>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</section>
