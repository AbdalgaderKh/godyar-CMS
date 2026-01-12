<?php include __DIR__ . '/partials/header.php'; ?>
<h1 class="h4 mb-3">أرشيف اليوم</h1>
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
