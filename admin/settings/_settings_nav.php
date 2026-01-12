<?php
require_once __DIR__ . '/_settings_meta.php';

$current = settings_current_file();
$pages = $GLOBALS['__SETTINGS_PAGES'] ?? [];
?>
<div class="card">
  <div class="list-group list-group-flush">
    <?php foreach ($pages as $file => $meta): ?>
      <?php
        $isActive = ($current === (string)$file);
        $title = (string)($meta['title'] ?? $file);
        $icon  = (string)($meta['icon'] ?? '');
      ?>
      <a
        href="<?= h((string)$file) ?>"
        class="list-group-item d-flex justify-content-between align-items-center <?= $isActive ? 'active fw-semibold' : '' ?>"
        <?= $isActive ? 'aria-current="page"' : '' ?>
      >
        <span class="d-inline-flex align-items-center gap-2">
          <span aria-hidden="true"><?= h($icon) ?></span>
          <span><?= h($title) ?></span>
        </span>
        <?php if ($isActive): ?><span class="badge bg-light text-dark"><?= h(__('t_804237edcf', 'الحالي')) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
