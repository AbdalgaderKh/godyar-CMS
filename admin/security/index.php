<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/Auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// يسمح فقط للمدير بالدخول إلى مركز الأمان
Auth::requireAdmin('../login.php');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pageTitle   = __('t_82bacb8bca', 'مركز الأمان');
$currentPage = 'system_health'; // أو أي اسم قائمة يناسب السايدبار

$headerPath  = __DIR__ . '/../layout/header.php';
$sidebarPath = __DIR__ . '/../layout/sidebar.php';
$footerPath  = __DIR__ . '/../layout/footer.php';

if (is_file($headerPath)) {
    require $headerPath;
}
if (is_file($sidebarPath)) {
    require $sidebarPath;
}
?>
<div class="admin-content container-fluid py-4">
  <h1 class="h4 mb-3 text-white"><?= h(__('t_73c5b74931', 'مركز الأمان في Godyar')) ?></h1>

  <div class="alert alert-info">
    <?= h(__('t_131af90b58', 'هذه الصفحة مخصصة لفحص إعدادات الأمان العامة للنظام.')) ?>
    <br>
    <small class="text-light-50">
      <?= h(__('t_c8a1d5ae64', 'النسخة الحالية مبسطة، ويمكن تطويرها لاحقًا لإظهار المزيد من التفاصيل (صلاحيات الملفات، إعدادات PHP، جداول حساسة… إلخ).')) ?>
    </small>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card bg-dark border-secondary">
        <div class="card-header border-secondary text-white">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_afd4e6edb4', 'إعدادات الجلسات والكويكز')) ?>
        </div>
        <div class="card-body small text-light">
          <ul class="mb-0">
            <li><?= h(__('t_16fe54ab5a', 'استخدام')) ?> <code>httponly</code> <?= h(__('t_c04d9209c0', 'في كعكات الجلسة:')) ?> <strong><?= h(__('t_918499f2af', 'مفعل')) ?></strong></li>
            <li><?= h(__('t_16fe54ab5a', 'استخدام')) ?> <code>samesite=Lax</code>: <strong><?= h(__('t_918499f2af', 'مفعل')) ?></strong></li>
            <li><?= h(__('t_00a6b7090a', 'الاتصال عبر HTTPS (إن وجد):')) ?> <strong><?= !empty($_SERVER['HTTPS']) ? __('t_e1dadf4c7c', 'نعم') : __('t_b27ea934ef', 'لا') ?></strong></li>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card bg-dark border-secondary">
        <div class="card-header border-secondary text-white">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_84d1dd88a6', 'إعدادات PHP المهمة')) ?>
        </div>
        <div class="card-body small text-light">
          <ul class="mb-0">
            <li><?= h(__('t_1425cbc31c', 'الإصدار:')) ?> <strong><?= h(PHP_VERSION) ?></strong></li>
            <li>display_errors: <strong><?= ini_get('display_errors') ? 'ON' : 'OFF' ?></strong></li>
            <li>log_errors: <strong><?= ini_get('log_errors') ? 'ON' : 'OFF' ?></strong></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
if (is_file($footerPath)) {
    require $footerPath;
}
