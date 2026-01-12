<?php include __DIR__ . '/partials/header.php'; ?>
<?php
// Expect $author, $items
$postsCount = $postsCount ?? (is_array($items) ? count($items) : 0);
?>
<section class="mb-4">
  <div class="d-flex align-items-center gap-3">
    <?php if (!empty($author['avatar'])): ?>
      <img src="<?= htmlspecialchars($author['avatar']) ?>" alt="" width="72" height="72" style="border-radius:50%;object-fit:cover">
    <?php endif; ?>
    <div>
      <h1 class="h4 mb-1"><?= htmlspecialchars($author['name'] ?? '') ?></h1>
      <?php if (!empty($author['bio'])): ?><p class="text-muted mb-0"><?= htmlspecialchars($author['bio']) ?></p><?php endif; ?>
      <div class="text-muted small">عدد المقالات: <?= (int)$postsCount ?></div>
    </div>
  </div>
</section>
<div class="row g-3">
<?php foreach (($items ?? []) as $n): ?>
  <div class="col-12 col-md-6 col-lg-4">
	    <?php $prefix = rtrim($baseUrl ?? '', '/'); ?>
	    <a class="card h-100 text-decoration-none" href="<?= htmlspecialchars($prefix . '/news/id/' . (int)($n['id'] ?? 0)) ?>">
	      <?php if (!empty($n['featured_image'])): ?><img class="card-img-top" src="<?= htmlspecialchars($prefix . '/img.php?src=' . rawurlencode((string)$n['featured_image']) . '&w=600') ?>" alt=""><?php endif; ?>
      <div class="card-body">
        <h3 class="h6"><?= htmlspecialchars($n['title']) ?></h3>
        <?php if (!empty($n['excerpt'])): ?><p class="text-muted small mb-0"><?= htmlspecialchars($n['excerpt']) ?></p><?php endif; ?>
      </div>
    </a>
  </div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
