<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/system/health/index.php — صحة النظام

require_once __DIR__ . '/../../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'system_health';
$pageTitle   = __('t_63163058e0', 'صحة النظام');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// التحقق من تسجيل الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../../login.php');
            exit;
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Godyar Health] Auth error: '.$e->getMessage());
    header('Location: ../../login.php');
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

// ========================
// تجميع معلومات الفحص
// ========================
$checks = [
    'php_version'   => PHP_VERSION,
    'php_sapi'      => PHP_SAPI,
    'os'            => php_uname('s') . ' ' . php_uname('r'),
    'timezone'      => date_default_timezone_get(),
    'now'           => date('Y-m-d H:i:s'),
    'db_ok'         => false,
    'db_driver'     => null,
    'db_server'     => null,
    'cache_ok'      => false,
    'cache_driver'  => 'none',
];

$dbError = null;

// قاعدة البيانات
if ($pdo instanceof PDO) {
    try {
        $pdo->query("SELECT 1");
        $checks['db_ok']     = true;
        $checks['db_driver'] = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $checks['db_server'] = (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
} else {
    $dbError = __('t_d3618334c1', 'لم يتم تهيئة اتصال قاعدة البيانات.');
}

// الكاش
if (class_exists('Cache')) {
    $checks['cache_driver'] = 'file';
    try {
        Cache::put('_health_test', 'ok', 60);
        $val = Cache::get('_health_test');
        $checks['cache_ok'] = ($val === 'ok');
        Cache::forget('_health_test');
    } catch (Throwable $e) {
        $checks['cache_ok'] = false;
    }
}

// إعدادات PHP المهمة
$phpIni = [
    'memory_limit'       => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize'=> ini_get('upload_max_filesize'),
    'post_max_size'      => ini_get('post_max_size'),
    'display_errors'     => ini_get('display_errors'),
];

// معلومات البيئة (ENV)
$appEnv   = 'production';
$appDebug = false;
$appUrl   = '';

if (function_exists('env')) {
    $appEnv   = (string)env('APP_ENV', $appEnv);
    $appDebug = (bool)env('APP_DEBUG', false);
    $appUrl   = (string)env('APP_URL', '');
} else {
    $appEnv   = getenv('APP_ENV') ?: $appEnv;
    $appDebug = (bool)getenv('APP_DEBUG');
    $appUrl   = getenv('APP_URL') ?: '';
}

// فحص بعض المجلدات المهمة (إن وُجدت)
$rootPath = realpath(__DIR__ . '/../../../') ?: dirname(__DIR__, 3);
$fsChecks = [];

$dirsToCheck = [
    'cache'   => $rootPath . '/cache',
    'uploads' => $rootPath . '/uploads',
    'logs'    => $rootPath . '/logs',
];

foreach ($dirsToCheck as $label => $path) {
    if (is_dir($path)) {
        $fsChecks[] = [
            'label'    => $label,
            'path'     => $path,
            'exists'   => true,
            'writable' => is_writable($path),
        ];
    } else {
        $fsChecks[] = [
            'label'    => $label,
            'path'     => $path,
            'exists'   => false,
            'writable' => false,
        ];
    }
}

// مجلد النظام المؤقت
$fsChecks[] = [
    'label'    => 'tmp',
    'path'     => sys_get_temp_dir(),
    'exists'   => is_dir(sys_get_temp_dir()),
    'writable' => is_writable(sys_get_temp_dir()),
];

// تقييم عام لصحة النظام
$score = 0;
if (version_compare(PHP_VERSION, '8.0.0', '>=')) $score += 40;
if ($checks['db_ok'])                               $score += 40;
if ($checks['cache_ok'])                            $score += 20;

$overallStatus = 'medium';
$overallLabel  = __('t_1418425392', 'متوسط');
$overallClass  = 'bg-warning';
if ($score >= 90) {
    $overallStatus = 'good';
    $overallLabel  = __('t_06cbf01c51', 'ممتاز');
    $overallClass  = 'bg-success';
} elseif ($score <= 40) {
    $overallStatus = 'bad';
    $overallLabel  = __('t_7078d21ad4', 'بحاجة إلى تدخل');
    $overallClass  = 'bg-danger';
}

require_once __DIR__ . '/../../layout/header.php';
require_once __DIR__ . '/../../layout/sidebar.php';
?>

<style>
:root{
    --gdy-shell-max: 900px;
}

/* منع التمرير الأفقي وضبط الخلفية */
html, body{
    overflow-x: hidden;
    background:#020617;
    color:#e5e7eb;
}

.admin-content.container-fluid.py-4{
    padding-top:.75rem !important;
    padding-bottom:1rem !important;
}

/* غلاف داخلي يضمن أن المحتوى لا يخرج عن نطاق العرض */
.gdy-layout-wrap{
    width:100%;
    max-width:var(--gdy-shell-max);
    margin:0 auto;
}

/* رأس الصفحة */
.gdy-page-header{
    padding:.9rem 1.1rem .8rem;
    margin-bottom:.9rem;
    border-radius:1rem;
    background:radial-gradient(circle at top,#020617 0%,#020617 55%,#020617 100%);
    border:1px solid rgba(148,163,184,0.35);
    box-shadow:0 8px 20px rgba(15,23,42,0.85);
}
.gdy-page-header h1{
    margin:0 0 .15rem;
    color:#f9fafb;
}
.gdy-page-header p{
    margin:0;
    font-size:.85rem;
    color:#9ca3af;
}

/* كرت عام */
.glass-card{
    background:rgba(15,23,42,0.96);
    border-radius:16px;
    border:1px solid #1f2937;
}
.glass-card .card-title{
    font-size:.95rem;
}

/* كرت التقييم العام */
.gdy-health-summary{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.75rem;
}
.gdy-health-score{
    font-size:1.6rem;
    font-weight:700;
}
.gdy-health-meter{
    width:100%;
    height:.4rem;
    border-radius:999px;
    background:#0f172a;
    overflow:hidden;
}
.gdy-health-meter-fill{
    height:100%;
    background:linear-gradient(90deg,#22c55e,#0ea5e9);
}

/* شارة status */
.badge-health{
    border-radius:999px;
    padding:.2rem .7rem;
    font-size:.75rem;
}

/* جدول صغير للخصائص */
.table-health{
    color:#e5e7eb;
    font-size:.82rem;
}
.table-health th,
.table-health td{
    padding:.25rem .5rem;
    border-color:#111827 !important;
}
.table-health th{
    width:45%;
    color:#9ca3af;
    font-weight:500;
}

/* شارات الملفات */
.badge-ok{
    background-color:#16a34a;
}
.badge-warn{
    background-color:#eab308;
    color:#111827;
}
.badge-bad{
    background-color:#dc2626;
}

/* استجابة للجوال */
@media (max-width: 991.98px){
    :root{
        --gdy-shell-max:100%;
    }
    .gdy-layout-wrap{
        padding-inline:.5rem;
    }
}
</style>

<div class="admin-content.container-fluid.py-4">
  <div class="gdy-layout-wrap">

    <!-- رأس الصفحة -->
    <div class="gdy-page-header mb-3">
      <h1 class="h4"><?= h(__('t_63163058e0', 'صحة النظام')) ?></h1>
      <p><?= h(__('t_57f02b95e4', 'فحص سريع لأهم مكوّنات البيئة: PHP، قاعدة البيانات، الكاش، إعدادات الخادم.')) ?></p>
    </div>

    <!-- التقييم العام -->
    <div class="card glass-card border-0 shadow-sm mb-3">
      <div class="card-body gdy-health-summary">
        <div>
          <div class="mb-1 small text-muted"><?= h(__('t_7a148c6e4b', 'التقييم العام')) ?></div>
          <span class="badge badge-health <?= h($overallClass) ?>"><?= h($overallLabel) ?></span>
          <div class="mt-2 gdy-health-meter">
            <div class="gdy-health-meter-fill" style="width: <?= max(10, min(100, $score)) ?>%;"></div>
          </div>
        </div>
        <div class="text-end">
          <div class="gdy-health-score"><?= (int)$score ?>%</div>
          <div class="small text-muted"><?= h(__('t_717d8ac6b3', 'مبني على PHP + قاعدة البيانات + الكاش')) ?></div>
        </div>
      </div>
    </div>

    <!-- الكروت الرئيسية: PHP / DB / Cache -->
    <div class="row g-3 mb-3">
      <!-- PHP -->
      <div class="col-md-4">
        <div class="card glass-card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title text-white">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> PHP
            </h5>
            <table class="table table-sm table-borderless table-health mb-1">
              <tr>
                <th><?= h(__('t_8c0c06316b', 'الإصدار')) ?></th>
                <td><?= h($checks['php_version']) ?></td>
              </tr>
              <tr>
                <th><?= h(__('t_0cc940b3e8', 'وضع التشغيل (SAPI)')) ?></th>
                <td><?= h($checks['php_sapi']) ?></td>
              </tr>
              <tr>
                <th><?= h(__('t_1cdc416592', 'النظام')) ?></th>
                <td><?= h($checks['os']) ?></td>
              </tr>
            </table>
            <small class="text-muted">الوقت الحالي: <?= h($checks['now']) ?> (<?= h($checks['timezone']) ?>)</small>
          </div>
        </div>
      </div>

      <!-- قاعدة البيانات -->
      <div class="col-md-4">
        <div class="card glass-card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title text-white">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_fa678d5458', 'قاعدة البيانات')) ?>
            </h5>
            <?php if ($checks['db_ok']): ?>
              <span class="badge badge-health bg-success mb-2"><?= h(__('t_be7ec211f4', 'متصل')) ?></span>
            <?php else: ?>
              <span class="badge badge-health bg-danger mb-2"><?= h(__('t_dbf2299ec8', 'فشل الاتصال')) ?></span>
            <?php endif; ?>

            <table class="table table-sm table-borderless table-health mb-1">
              <tr>
                <th><?= h(__('t_e1be0fd308', 'المحرك')) ?></th>
                <td><?= h($checks['db_driver'] ?: __('t_6b5e6d57ba', 'غير معروف')) ?></td>
              </tr>
              <tr>
                <th><?= h(__('t_646ca15d4e', 'نسخة الخادم')) ?></th>
                <td><?= h($checks['db_server'] ?: '-') ?></td>
              </tr>
            </table>

            <?php if (!$checks['db_ok'] && $dbError): ?>
              <p class="small text-warning mb-0">الخطأ: <?= h($dbError) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- الكاش -->
      <div class="col-md-4">
        <div class="card glass-card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title.text-white">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_a58f1e6aeb', 'نظام الكاش')) ?>
            </h5>
            <table class="table table-sm.table-borderless.table-health mb-1">
              <tr>
                <th>Driver</th>
                <td><?= h($checks['cache_driver']) ?></td>
              </tr>
            </table>
            <?php if ($checks['cache_ok']): ?>
              <span class="badge badge-health bg-success"><?= h(__('t_69bf6c3ec9', 'الكاش يعمل بشكل سليم')) ?></span>
            <?php else: ?>
              <span class="badge badge-health bg-warning text-dark"><?= h(__('t_e23c9778a3', 'الكاش غير مفعّل أو غير مستقر')) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- تفاصيل أخرى: إعدادات PHP + البيئة + الملفات -->
    <div class="row g-3">
      <!-- إعدادات PHP + البيئة -->
      <div class="col-lg-6">
        <div class="card glass-card border-0 shadow-sm mb-3">
          <div class="card-body">
            <h5 class="card-title text-white">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_84d1dd88a6', 'إعدادات PHP المهمة')) ?>
            </h5>
            <table class="table table-sm table-borderless table-health mb-0">
              <tr>
                <th>memory_limit</th>
                <td><?= h($phpIni['memory_limit']) ?></td>
              </tr>
              <tr>
                <th>max_execution_time</th>
                <td><?= h($phpIni['max_execution_time']) ?> ثانية</td>
              </tr>
              <tr>
                <th>upload_max_filesize</th>
                <td><?= h($phpIni['upload_max_filesize']) ?></td>
              </tr>
              <tr>
                <th>post_max_size</th>
                <td><?= h($phpIni['post_max_size']) ?></td>
              </tr>
              <tr>
                <th>display_errors</th>
                <td><?= h($phpIni['display_errors']) ?></td>
              </tr>
            </table>
          </div>
        </div>

        <div class="card glass-card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title text-white">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_1f71f9dd0d', 'معلومات البيئة')) ?>
            </h5>
            <table class="table table-sm table-borderless table-health mb-0">
              <tr>
                <th>APP_ENV</th>
                <td><?= h($appEnv) ?></td>
              </tr>
              <tr>
                <th>APP_DEBUG</th>
                <td><?= $appDebug ? 'ON' : 'OFF' ?></td>
              </tr>
              <tr>
                <th>APP_URL</th>
                <td><?= $appUrl !== '' ? h($appUrl) : '-' ?></td>
              </tr>
              <tr>
                <th>HTTPS</th>
                <td><?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? __('t_918499f2af', 'مفعل') : __('t_60dfc10f77', 'غير مفعل') ?></td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      <!-- فحص الملفات والصلاحيات -->
      <div class="col-lg-6">
        <div class="card glass-card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title text-white">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_6870d42f69', 'الملفات والصلاحيات')) ?>
            </h5>
            <table class="table table-sm table-borderless table-health mb-0">
              <thead>
              <tr>
                <th><?= h(__('t_3cc8aa6d79', 'المجلد')) ?></th>
                <th><?= h(__('t_4f3d289eb3', 'المسار')) ?></th>
                <th class="text-center"><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
              </tr>
              </thead>
              <tbody>
              <?php foreach ($fsChecks as $fs): ?>
                  <tr>
                    <td><?= h($fs['label']) ?></td>
                    <td><small><?= h($fs['path']) ?></small></td>
                    <td class="text-center">
                      <?php if (!$fs['exists']): ?>
                        <span class="badge badge-bad"><?= h(__('t_bf016f0ee1', 'غير موجود')) ?></span>
                      <?php elseif ($fs['writable']): ?>
                        <span class="badge badge-ok"><?= h(__('t_66602b5188', 'قابل للكتابة')) ?></span>
                      <?php else: ?>
                        <span class="badge badge-warn"><?= h(__('t_10ae42e443', 'غير قابل للكتابة')) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /gdy-layout-wrap -->
</div><!-- /admin-content -->

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
