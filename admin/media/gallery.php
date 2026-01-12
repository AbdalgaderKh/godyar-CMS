<?php require_once __DIR__ . '/../layout/header.php'; ?>

require_once __DIR__ . '/../_admin_guard.php';
<?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
<style>
html, body{
    overflow-x:hidden;
    background:#020617;
    color:#e5e7eb;
}
.admin-content{
    max-width: 1100px;
    margin: 0 auto;
    padding: 1.5rem 1.25rem;
}
.gdy-page-header h1{
    font-weight:600;
}
.gdy-card{
    background:rgba(15,23,42,0.9);
    border-radius:1rem;
    border:1px solid rgba(148,163,184,0.3);
    padding:1.5rem;
}
</style>
<div class="admin-content">
  <div class="gdy-page-header mb-4">
    <h1 class="h4 mb-1"><?= h(__('t_adefc961fd', 'المعرض')) ?></h1>
    <p class="text-muted mb-0 small"><?= h(__('t_3b25f57bf1', 'إدارة صور المعرض وعرضها بطريقة منظمة.')) ?></p>
  </div>
  <div class="gdy-card">
    <?= h(__('t_8f5a2d188e', 'محتوى المعرض هنا')) ?>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
