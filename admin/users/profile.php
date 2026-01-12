<?php require_once __DIR__ . '/../layout/header.php'; ?>

require_once __DIR__ . '/../_admin_guard.php';
<?php require_once __DIR__ . '/../layout/sidebar.php'; ?>

<style>
:root{
    /* نضغط عرض محتوى صفحة إدارة المستخدمين ليكون مريحاً بجانب السايدبار */
    --gdy-shell-max: min(880px, 100vw - 360px);
}

html, body{
    overflow-x: hidden;
    background: #020617;
    color: #e5e7eb;
}

.admin-content{
    max-width: var(--gdy-shell-max);
    width: 100%;
    margin: 0 auto;
}

/* تقليل الفراغ العمودي الافتراضي داخل صفحات الإدارة */
.admin-content.container-fluid.py-4{
    padding-top: 0.75rem !important;
    padding-bottom: 1rem !important;
}

/* توحيد مسافة رأس الصفحة */
.gdy-page-header{
    margin-bottom: 0.75rem;
}
</style>

<div class="admin-content container-fluid py-4">
  <div class="gdy-page-header mb-3">
    <h1 class="h4 mb-2 text-white"><?= h(__('t_e9c893ddaa', 'الملف الشخصي')) ?></h1>
    <p class="text-muted mb-0 small"><?= h(__('t_f7190750d4', 'من هنا يمكنك عرض وتعديل إعدادات ملفك الشخصي.')) ?></p>
  </div>

  <div class="card glass-card gdy-card" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
    <div class="card-body">
      <p class="mb-0"><?= h(__('t_f7190750d4', 'من هنا يمكنك عرض وتعديل إعدادات ملفك الشخصي.')) ?></p>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
