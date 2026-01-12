<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/slider/index.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'slider';
$pageTitle   = __('t_eafc27904f', 'إدارة السلايدر');

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$rows = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("
            SELECT id, title, subtitle, image_path, link_url, is_active, sort_order, created_at
            FROM slider
            ORDER BY sort_order ASC, id DESC
            LIMIT 100
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        @error_log('[Godyar Slider Index] ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<style>
:root{
  /* نضغط عرض محتوى السلايدر ليكون مريحاً بجانب السايدبار */
  --gdy-shell-max: min(880px, 100vw - 360px);
}

.admin-content{
  max-width: var(--gdy-shell-max);
  margin: 0 auto;
}

/* تقليل الفراغ العمودي داخل صفحة السلايدر */
.admin-content.container-fluid.py-4{
  padding-top:0.75rem !important;
  padding-bottom:1rem !important;
}

.gdy-page-header{
  margin-bottom:0.75rem;
}

.gdy-card{
  border-radius:1.25rem;
  border:1px solid rgba(148,163,184,0.25);
}

/* تحسين رأس الجدول في صفحة القائمة */
.table thead th{
  background:#020617;
  border-bottom-color:rgba(148,163,184,0.4);
}
</style>

<div class="admin-content container-fluid py-4">
  <div class="admin-content gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div>
      <h1 class="h4 mb-1 text-white"><?= h(__('t_58a041f8da', 'السلايدر')) ?></h1>
      <p class="mb-0" style="color:#e5e7eb;"><?= h(__('t_06aa1b65da', 'إدارة شرائح العرض في الصفحة الرئيسية.')) ?></p>
    </div>
    <div class="mt-3 mt-md-0">
      <a href="create.php" class="btn btn-primary">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_5d1adeeb8d', 'إضافة شريحة جديدة')) ?>
      </a>
    </div>
  </div>

  <div class="card glass-card gdy-card" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
    <div class="card-body p-0">
      <?php if (empty($rows)): ?>
        <p class="p-3 mb-0" style="color:#9ca3af;"><?= h(__('t_305bcf385b', 'لا توجد شرائح حالياً.')) ?></p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle text-center" style="color:#e5e7eb;">
            <thead style="background:#020617;">
              <tr>
                <th>#</th>
                <th><?= h(__('t_6dc6588082', 'العنوان')) ?></th>
                <th><?= h(__('t_d31bde862c', 'النص الفرعي')) ?></th>
                <th><?= h(__('t_59df47722a', 'الصورة')) ?></th>
                <th><?= h(__('t_615e66bc1b', 'الرابط')) ?></th>
                <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                <th><?= h(__('t_ddda59289a', 'الترتيب')) ?></th>
                <th><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><small><?= (int)$row['id'] ?></small></td>
                  <td class="text-start"><small><?= h($row['title']) ?></small></td>
                  <td><small><?= h($row['subtitle']) ?></small></td>
                  <td>
                    <?php if (!empty($row['image_path'])): ?>
                      <small><?= h($row['image_path']) ?></small>
                    <?php else: ?>
                      <span class="text-muted small"><?= h(__('t_9d7155f3e3', 'لا يوجد')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($row['link_url'])): ?>
                      <a href="<?= h($row['link_url']) ?>" target="_blank" class="small text-info"><?= h(__('t_b42b9ec2e8', 'فتح')) ?></a>
                    <?php else: ?>
                      <span class="text-muted small"><?= h(__('t_9d7155f3e3', 'لا يوجد')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge <?= !empty($row['is_active']) ? 'bg-success' : 'bg-secondary' ?>">
                      <?= !empty($row['is_active']) ? __('t_641298ecec', 'مفعّلة') : __('t_ad0a598276', 'مخفية') ?>
                    </span>
                  </td>
                  <td><small><?= (int)$row['sort_order'] ?></small></td>
                  <td>
                    <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary">
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
