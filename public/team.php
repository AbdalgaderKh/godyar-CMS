<?php
declare(strict_types=1);

// /godyar/public/team.php
require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$members = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("
            SELECT id, name, position, photo, bio, social_twitter, social_facebook, social_instagram, social_linkedin
            FROM team_members
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        @error_log('[Front Team] ' . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title>فريق العمل - <?= h(env('SITE_NAME','Godyar News')) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/godyar/assets/css/bootstrap.min.css">
</head>
<body>
<div class="container py-4">
  <h1 class="h3 mb-4">فريق العمل</h1>
  <div class="row g-3">
    <?php if (empty($members)): ?>
      <p class="text-muted">لم يتم إضافة أعضاء فريق العمل بعد.</p>
    <?php else: ?>
      <?php foreach ($members as $m): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card h-100 text-center">
            <div class="card-body">
              <div class="mb-2">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                     style="width:72px;height:72px;background:#e5e7eb;overflow:hidden;">
                  <?php if (!empty($m['photo'])): ?>
                    <img src="<?= h($m['photo']) ?>" alt="<?= h($m['name']) ?>"
                         style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    <span class="fw-bold"><?= mb_substr($m['name'],0,1,'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <h2 class="h6 mb-1"><?= h($m['name']) ?></h2>
              <div class="small text-muted mb-2"><?= h($m['position']) ?></div>
              <?php if (!empty($m['bio'])): ?>
                <p class="small"><?= nl2br(h($m['bio'])) ?></p>
              <?php endif; ?>
            </div>
            <div class="card-footer small">
              <?php if (!empty($m['social_twitter'])): ?>
                <a href="<?= h($m['social_twitter']) ?>" target="_blank" class="me-1">X</a>
              <?php endif; ?>
              <?php if (!empty($m['social_facebook'])): ?>
                <a href="<?= h($m['social_facebook']) ?>" target="_blank" class="me-1">F</a>
              <?php endif; ?>
              <?php if (!empty($m['social_instagram'])): ?>
                <a href="<?= h($m['social_instagram']) ?>" target="_blank" class="me-1">IG</a>
              <?php endif; ?>
              <?php if (!empty($m['social_linkedin'])): ?>
                <a href="<?= h($m['social_linkedin']) ?>" target="_blank" class="me-1">IN</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
