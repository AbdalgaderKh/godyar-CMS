<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/users/view.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'users';
$pageTitle   = __('t_ed2a06b28b', 'تفاصيل مستخدم');

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

$user = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @error_log('[Godyar Users View] ' . $e->getMessage());
}

if (!$user) {
    header('Location: index.php');
    exit;
}

$roleLabel = [
    'superadmin' => __('t_83965e88ca', 'مشرف عام'),
    'admin'      => __('t_bd4c63dd36', 'مدير'),
    'editor'     => __('t_81807f1484', 'محرر'),
    'author'     => __('t_99cbece3bc', 'كاتب'),
    'user'       => __('t_b1ed56cfd0', 'عضو')
][$user['role']] ?? $user['role'];

$statusLabel = [
    'active'   => __('t_8caaf95380', 'نشط'),
    'inactive' => __('t_1e0f5f1adc', 'غير نشط'),
    'banned'   => __('t_e59b95cb50', 'محظور'),
][$user['status']] ?? $user['status'];

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

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
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div>
      <h1 class="h4 mb-1 text-white"><?= h($user['username']) ?></h1>
      <p class="mb-0" style="color:#e5e7eb;"><?= h(__('t_f3f6edf49f', 'تفاصيل المستخدم وصلاحياته.')) ?></p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
      <a href="edit.php?id=<?= (int)$user['id'] ?>" class="btn btn-primary">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_759fdc242e', 'تعديل')) ?>
      </a>
      <a href="index.php" class="btn btn-outline-light">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_19ae074cbf', 'العودة للقائمة')) ?>
      </a>
    </div>
  </div>

  <div class="card glass-card gdy-card" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-md-3"><?= h(__('t_2e8b171b46', 'الاسم')) ?></dt>
        <dd class="col-md-9"><?= h($user['name']) ?></dd>

        <dt class="col-md-3"><?= h(__('t_2436aacc18', 'البريد الإلكتروني')) ?></dt>
        <dd class="col-md-9"><?= h($user['email']) ?></dd>

        <dt class="col-md-3"><?= h(__('t_1647921065', 'الدور')) ?></dt>
        <dd class="col-md-9"><span class="badge bg-info"><?= h($roleLabel) ?></span></dd>

        <dt class="col-md-3"><?= h(__('t_1253eb5642', 'الحالة')) ?></dt>
        <dd class="col-md-9">
          <?php
            $class = $user['status']==='active'?'bg-success':($user['status']==='banned'?'bg-danger':'bg-secondary');
          ?>
          <span class="badge <?= $class ?>"><?= h($statusLabel) ?></span>
        </dd>

        <dt class="col-md-3"><?= h(__('t_a180402e1a', 'آخر تسجيل دخول')) ?></dt>
        <dd class="col-md-9"><?= h($user['last_login_at'] ?? '') ?> — IP: <?= h($user['last_login_ip'] ?? '-') ?></dd>

        <dt class="col-md-3"><?= h(__('t_d4ef3a02e7', 'تاريخ الإنشاء')) ?></dt>
        <dd class="col-md-9"><?= h($user['created_at'] ?? '') ?></dd>

        <dt class="col-md-3"><?= h(__('t_4041e7805b', 'آخر تحديث')) ?></dt>
        <dd class="col-md-9"><?= h($user['updated_at'] ?? '') ?></dd>
      </dl>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
