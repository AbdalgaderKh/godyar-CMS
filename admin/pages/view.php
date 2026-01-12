<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/pages/view.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'pages';
$pageTitle   = __('t_b09bebc756', 'عرض صفحة');

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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0 || !$pdo instanceof PDO) {
    header('Location: index.php');
    exit;
}

$row = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @error_log('[Godyar Pages View] ' . $e->getMessage());
}

if (!$row) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<div class="admin-content container-fluid py-4">
  <div class="gdy-layout-wrap">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div>
      <h1 class="h4 mb-1 text-white"><?= h($row['title']) ?></h1>
      <p class="mb-0" style="color:#e5e7eb;"><?= h(__('t_4c123df2ef', 'عرض تفاصيل الصفحة الثابتة كما ستظهر للزوار.')) ?></p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
      <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-primary">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_759fdc242e', 'تعديل')) ?>
      </a>
      <a href="index.php" class="btn btn-outline-light">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_19ae074cbf', 'العودة للقائمة')) ?>
      </a>
    </div>
  </div>

  <div class="card glass-card mb-3" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-md-3">Slug</dt>
        <dd class="col-md-9"><?= h($row['slug']) ?></dd>

        <dt class="col-md-3"><?= h(__('t_1253eb5642', 'الحالة')) ?></dt>
        <dd class="col-md-9">
          <span class="badge <?= $row['status']==='published'?'bg-success':'bg-secondary' ?>">
            <?= $row['status']==='published'?__('t_c67d973434', 'منشورة'):__('t_2401f018ed', 'مسودّة') ?>
          </span>
        </dd>

        <dt class="col-md-3"><?= h(__('t_d4ef3a02e7', 'تاريخ الإنشاء')) ?></dt>
        <dd class="col-md-9"><?= h($row['created_at']) ?></dd>

        <dt class="col-md-3"><?= h(__('t_4041e7805b', 'آخر تحديث')) ?></dt>
        <dd class="col-md-9"><?= h($row['updated_at'] ?? '') ?></dd>
      </dl>
    </div>
  </div>

  <div class="card glass-card gdy-card" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
    <div class="card-header" style="background:#020617;border-bottom:1px solid #1f2937;">
      <h2 class="h6 mb-0"><?= h(__('t_e261adf643', 'محتوى الصفحة')) ?></h2>
    </div>
    <div class="card-body">
      <div style="white-space:pre-wrap;word-wrap:break-word;">
        <?= nl2br(h($row['content'])) ?>
      </div>
    </div>
  </div>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
