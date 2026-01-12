<?php require_once __DIR__ . '/../layout/header.php'; ?>

require_once __DIR__ . '/../_admin_guard.php';
<?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
<main class="admin-content container-fluid py-4 gdy-admin-page">
  <h2 class="h5 mb-4"><?= h(__('t_44f313f183', 'تقارير')) ?></h2>
  <div class="card p-3"><?= h(__('t_c552e0f7ab', 'محتوى تقارير هنا')) ?></div>
</main>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
