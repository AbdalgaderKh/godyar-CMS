<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/system/index.php — مركز النظام (صحة / كاش / سجلات / صيانة)

require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

// لتفعيل تمييز العنصر في القائمة الجانبية
$currentPage = 'system';
$pageTitle   = __('t_4c2c39a341', 'مركز النظام');

// ------------------------------
// 1) التحقق من تسجيل الدخول
// ------------------------------
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Godyar System] Auth check error: ' . $e->getMessage());
    if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
        header('Location: ../login.php');
        exit;
    }
}

// ------------------------------
// 2) تهيئة PDO
// ------------------------------
$pdo = gdy_pdo_safe();

if (!$pdo instanceof PDO) {
    // DB is required for this page.
    header('Location: index.php?dberror=1');
    exit;
}

// ------------------------------
// 3) Helpers
// ------------------------------
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function bool_to_status(bool $v): array {
    return $v
        ? ['badge' => 'success', 'label' => __('t_aaaa712e44', 'سليم')]
        : ['badge' => 'danger',  'label' => __('t_608f639707', 'غير سليم')];
}

// تلخيص details
if (!function_exists('format_details_short')) {
    function format_details_short(?string $details): string {
        if ($details === null || $details === '') {
            return '';
        }

        $decoded = json_decode($details, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $parts = [];
            foreach ($decoded as $k => $v) {
                if (is_scalar($v)) {
                    $parts[] = h($k) . ': ' . h((string)$v);
                } else {
                    $parts[] = h($k) . ': ' . h(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            }
            $str = implode(' | ', $parts);
        } else {
            $str = $details;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($str, 'UTF-8') > 160) {
                $str = mb_substr($str, 0, 160, 'UTF-8') . '...';
            }
        } else {
            if (strlen($str) > 160) {
                $str = substr($str, 0, 160) . '...';
            }
        }

        return h($str);
    }
}

// ------------------------------
// 4) حالة التبويب + فلاش الرسائل
// ------------------------------
$allowedTabs = ['health', 'cache', 'logs', 'maintenance'];
$tab = $_GET['tab'] ?? 'health';
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'health';
}

$flashSuccess = null;
$flashError   = null;

// مسار ملف الصيانة
$maintenanceFlag = GODYAR_ROOT . '/storage/maintenance.flag';
$maintenanceEnabled = is_file($maintenanceFlag);

// ------------------------------
// 5) POST actions (flush cache / maintenance)
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // تحقق CSRF لو الدوال موجودة
    if (function_exists('verify_csrf')) {
        try {
            if (!verify_csrf()) {
                $flashError = __('t_0f296c4fe0', 'فشل التحقق الأمني، يرجى إعادة المحاولة.');
                $action = ''; // لا تنفّذ أي شيء
            }
        } catch (Throwable $e) {
            @error_log('[Godyar System] verify_csrf error: ' . $e->getMessage());
        }
    }

    if ($action === 'flush_cache') {
        if (!class_exists('Cache')) {
            $flashError = __('t_a706887eaa', 'نظام الكاش غير مُحمّل (Cache class غير موجودة).');
        } else {
            try {
                Cache::flush();
                $flashSuccess = __('t_9d56c40acf', 'تم مسح الكاش بالكامل بنجاح.');
            } catch (Throwable $e) {
                $flashError = __('t_46daa0a94a', 'تعذر مسح الكاش: ') . $e->getMessage();
            }
        }
        $tab = 'cache';
    }

    if ($action === 'toggle_maintenance') {
        $enable = ($_POST['enable'] ?? '') === '1';
        try {
            if ($enable && !$maintenanceEnabled) {
                // تفعيل وضع الصيانة
                $dir = dirname($maintenanceFlag);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                @file_put_contents($maintenanceFlag, date('c'));
                $maintenanceEnabled = true;
                $flashSuccess = __('t_e1cd3f66b0', 'تم تفعيل وضع الصيانة.');

            } elseif (!$enable && $maintenanceEnabled) {
                // إلغاء وضع الصيانة + مسح الكاش (تكامل مباشر مع Cache)
                @unlink($maintenanceFlag);
                $maintenanceEnabled = false;

                if (class_exists('Cache')) {
                    try {
                        Cache::flush();
                        $flashSuccess = __('t_c5f14e13a0', 'تم إلغاء وضع الصيانة ومسح الكاش.');
                    } catch (Throwable $e) {
                        $flashSuccess = __('t_6bd9335ece', 'تم إلغاء وضع الصيانة، لكن حدث خطأ أثناء مسح الكاش.');
                        @error_log('[Godyar System] maintenance flush error: ' . $e->getMessage());
                    }
                } else {
                    $flashSuccess = __('t_150fe51a82', 'تم إلغاء وضع الصيانة.');
                }
            }
        } catch (Throwable $e) {
            $flashError = __('t_e69b789620', 'تعذر تغيير وضع الصيانة: ') . $e->getMessage();
        }
        $tab = 'maintenance';
    }
}

// ------------------------------
// 6) فحص الكاش + إحصاءات
// ------------------------------
$cacheConfig = [
    'enabled' => false,
    'driver'  => (string)env('CACHE_DRIVER', 'file'),
    'path'    => (string)env('CACHE_PATH', 'storage/cache'),
    'ttl'     => (int)env('CACHE_TTL', 300),
];

$enabledRaw = strtolower((string)env('CACHE_ENABLED', 'false'));
$cacheConfig['enabled'] = in_array($enabledRaw, ['1','true','yes','on'], true);

$cacheSelfTest = [
    'run'     => false,
    'ok'      => false,
    'message' => '',
];

if (class_exists('Cache') && $cacheConfig['enabled']) {
    $cacheSelfTest['run'] = true;
    try {
        $keyBase = 'system_health_test_';
        if (function_exists('random_bytes')) {
            $key = $keyBase . bin2hex(random_bytes(4));
        } else {
            $key = $keyBase . mt_rand(1000, 9999);
        }

        $payload = ['timestamp' => time(), 'rand' => mt_rand()];
        Cache::put($key, $payload, 10);
        $val = Cache::get($key);

        if (is_array($val) && isset($val['timestamp'])) {
            $cacheSelfTest['ok']      = true;
            $cacheSelfTest['message'] = __('t_7b3b16c250', 'كتابة/قراءة الكاش تعمل بشكل سليم.');
        } else {
            $cacheSelfTest['ok']      = false;
            $cacheSelfTest['message'] = __('t_9659eea4f0', 'لم يتمكن النظام من قراءة القيمة المخزنة في الكاش.');
        }
    } catch (Throwable $e) {
        $cacheSelfTest['ok']      = false;
        $cacheSelfTest['message'] = __('t_d6b8e612a1', 'استثناء أثناء فحص الكاش: ') . $e->getMessage();
    }
}

// إحصاءات ملفات الكاش (عدد الملفات + الحجم التقريبي)
$cacheStats = [
    'files' => 0,
    'size'  => 0,
];

if ($cacheConfig['driver'] === 'file') {
    $cacheDir = rtrim(GODYAR_ROOT . '/' . trim($cacheConfig['path'], '/'), '/');
    if (is_dir($cacheDir)) {
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $cacheStats['files']++;
                    $cacheStats['size'] += (int)$file->getSize();
                }
            }
        } catch (Throwable $e) {
            @error_log('[Godyar System] cache stats: ' . $e->getMessage());
        }
    }
}

// ------------------------------
// 7) فحص صحة النظام (health)
// ------------------------------
$healthChecks = [];

// إصدار PHP
$phpVersion = PHP_VERSION;
$phpOk      = version_compare(PHP_VERSION, '8.0.0', '>=');
$healthChecks[] = [
    'label'   => __('t_365ffd0e8f', 'إصدار PHP'),
    'value'   => $phpVersion,
    'status'  => $phpOk ? 'ok' : 'warn',
    'message' => $phpOk
        ? __('t_e360355ebe', 'إصدار مناسب لتشغيل النظام.')
        : __('t_164e5c7368', 'يُفضّل التحديث إلى PHP 8.0 أو أعلى لتحسين الأداء والأمان.'),
];

// امتدادات مطلوبة
$requiredExt = [
    'pdo'        => 'PDO',
    'pdo_mysql'  => 'pdo_mysql',
    'mbstring'   => 'mbstring',
    'json'       => 'json',
];

foreach ($requiredExt as $ext => $label) {
    $loaded = extension_loaded($ext);
    $healthChecks[] = [
        'label'   => __('t_b2a1c4856b', 'امتداد ') . $label,
        'value'   => $loaded ? __('t_1e1c2a6534', 'محمّل') : __('t_bf016f0ee1', 'غير موجود'),
        'status'  => $loaded ? 'ok' : 'fail',
        'message' => $loaded
            ? __('t_ee5c57566d', 'الامتداد متوفر.')
            : __('t_340a2c92fa', 'الامتداد غير متوفر، قد يسبب مشاكل في بعض الوظائف.'),
    ];
}

// اتصال قاعدة البيانات
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetchColumn();
        $healthChecks[] = [
            'label'   => __('t_a93008bd5c', 'اتصال قاعدة البيانات'),
            'value'   => __('t_655b17dd81', 'ناجح'),
            'status'  => 'ok',
            'message' => __('t_84d1f66549', 'تم الاتصال بقاعدة البيانات بنجاح.'),
        ];
    } catch (Throwable $e) {
        $healthChecks[] = [
            'label'   => __('t_a93008bd5c', 'اتصال قاعدة البيانات'),
            'value'   => __('t_a838e35c4d', 'فشل'),
            'status'  => 'fail',
            'message' => $e->getMessage(),
        ];
    }
} else {
    $healthChecks[] = [
        'label'   => __('t_a93008bd5c', 'اتصال قاعدة البيانات'),
        'value'   => __('t_7c51c50e59', 'غير مهيأ'),
        'status'  => 'fail',
        'message' => __('t_2f1d06ae61', 'لم يتم تهيئة الـ PDO في bootstrap.'),
    ];
}

// صلاحيات مجلدات التخزين
$pathsToCheck = [
    'storage/'            => GODYAR_ROOT . '/storage',
    'storage/cache/'      => GODYAR_ROOT . '/' . trim($cacheConfig['path'], '/'),
    'storage/sessions/'   => GODYAR_ROOT . '/storage/sessions',
];

foreach ($pathsToCheck as $label => $path) {
    $exists  = is_dir($path);
    $writable= $exists && is_writable($path);
    $status  = $exists && $writable ? 'ok' : ($exists ? 'warn' : 'fail');

    $msg = !$exists
        ? __('t_a4314ff552', 'المجلد غير موجود.')
        : ($writable ? __('t_5fadb5e2c3', 'المجلد موجود وقابل للكتابة.') : __('t_f64d07a499', 'المجلد موجود لكنه غير قابل للكتابة.'));

    $healthChecks[] = [
        'label'   => __('t_14508ee81c', 'مجلد ') . $label,
        'value'   => $exists ? ($writable ? __('t_d7fe42c8a3', 'جاهز') : __('t_f1d51b3d6a', 'مقروء فقط')) : __('t_f7d65e7b9c', 'مفقود'),
        'status'  => $status,
        'message' => $msg,
    ];
}

// حالة الكاش كجزء من health
$cacheStatusLabel = $cacheConfig['enabled'] ? __('t_4759637ebc', 'مفعّل') : __('t_4c64abcbc3', 'غير مفعّل');
$healthChecks[] = [
    'label'   => __('t_a58f1e6aeb', 'نظام الكاش'),
    'value'   => $cacheStatusLabel . __('t_eaa3562b99', ' / السائق: ') . $cacheConfig['driver'],
    'status'  => ($cacheConfig['enabled'] && $cacheSelfTest['ok']) ? 'ok' : 'warn',
    'message' => $cacheSelfTest['run']
        ? ($cacheSelfTest['message'] ?: __('t_bf8548dd3a', 'تم تنفيذ فحص الكاش.'))
        : __('t_da185070aa', 'يمكن تفعيل الكاش من ملف env (المتغيّر CACHE_ENABLED).'),
];

// ------------------------------
// 8) سجلات النظام (logs tab)
// ------------------------------
$adminLogs = [];
$adminLogsError = null;
$adminLogsTableExists = false;

if ($tab === 'logs' && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
        if ($stmt && $stmt->fetch()) {
            $adminLogsTableExists = true;
            $stmt2 = $pdo->query("
                SELECT id, user_id, action, entity_type, entity_id, details, created_at
                FROM admin_logs
                ORDER BY id DESC
                LIMIT 100
            ");
            $adminLogs = $stmt2 ? $stmt2->fetchAll(PDO::FETCH_ASSOC) : [];
        }
    } catch (Throwable $e) {
        $adminLogsError = $e->getMessage();
    }
}

// tail لملف error_log
$errorLogPath = (string)env('GODYAR_ERROR_LOG', ini_get('error_log'));
$errorLogLines = [];
if ($tab === 'logs' && $errorLogPath && is_file($errorLogPath) && is_readable($errorLogPath)) {
    try {
        $fp = @fopen($errorLogPath, 'rb');
        if ($fp) {
            $bufferSize = 8192;
            $pos   = -1;
            $lines = '';
            $lineCount = 0;
            $maxLines = 80;

            fseek($fp, 0, SEEK_END);
            $fileSize = ftell($fp);

            while (-$pos < $fileSize && $lineCount < $maxLines) {
                $seek = max($pos - $bufferSize, -$fileSize);
                fseek($fp, $seek, SEEK_END);
                $chunk = fread($fp, -$seek);
                $lines = $chunk . $lines;
                $pos   = $seek;
                $lineCount = substr_count($lines, "\n");
            }
            fclose($fp);

            $errorLogLines = explode("\n", trim($lines));
        }
    } catch (Throwable $e) {
        @error_log('[Godyar System] error_log tail error: ' . $e->getMessage());
    }
}

// ------------------------------
// 9) الواجهة — تضمين الهيدر والقائمة الجانبية
// ------------------------------
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<div class="admin-content container-fluid py-4">

  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div>
      <h1 class="h3 mb-1"><?= h(__('t_4c2c39a341', 'مركز النظام')) ?></h1>
      <p class="text-muted mb-0 small">
        <?= h(__('t_2d324516c7', 'لوحة لمراقبة صحة النظام، إدارة الكاش، الاطلاع على السجلات، والتحكم في وضع الصيانة.')) ?>
      </p>
    </div>
  </div>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success py-2"><?= h($flashSuccess) ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="alert alert-danger py-2"><?= h($flashError) ?></div>
  <?php endif; ?>

  <!-- تبويبات النظام -->
  <ul class="nav nav-pills mb-3 system-tabs">
    <li class="nav-item">
      <a class="nav-link <?= $tab === 'health' ? 'active' : '' ?>" href="?tab=health">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_63163058e0', 'صحة النظام')) ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tab === 'cache' ? 'active' : '' ?>" href="?tab=cache">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_a10e27b470', 'الكاش')) ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tab === 'logs' ? 'active' : '' ?>" href="?tab=logs">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_b66a827841', 'السجلات')) ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tab === 'maintenance' ? 'active' : '' ?>" href="?tab=maintenance">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_f96c99c4d8', 'وضع الصيانة')) ?>
      </a>
    </li>
  </ul>

  <?php if ($tab === 'health'): ?>
    <!-- تبويب صحة النظام -->
    <div class="row g-3">
      <?php
        $okCount = count(array_filter($healthChecks, fn($c) => $c['status'] === 'ok'));
        $warnCount = count(array_filter($healthChecks, fn($c) => $c['status'] === 'warn'));
        $failCount = count(array_filter($healthChecks, fn($c) => $c['status'] === 'fail'));
      ?>
      <div class="col-md-4">
        <div class="card glass-card shadow-sm border-0">
          <div class="card-body">
            <h5 class="card-title mb-3"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_6d5c9702b3', 'ملخص سريع')) ?></h5>
            <ul class="list-unstyled mb-0 small">
              <li class="mb-1">
                <span class="badge bg-success me-1"><?= $okCount ?></span> <?= h(__('t_921d6d0ec7', 'عناصر سليمة')) ?>
              </li>
              <li class="mb-1">
                <span class="badge bg-warning text-dark me-1"><?= $warnCount ?></span> <?= h(__('t_b349024108', 'تحذيرات')) ?>
              </li>
              <li class="mb-1">
                <span class="badge bg-danger me-1"><?= $failCount ?></span> <?= h(__('t_ccf97a7e4a', 'مشاكل تحتاج تدخل')) ?>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="card glass-card shadow-sm border-0">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th><?= h(__('t_c4cf241e6b', 'العنصر')) ?></th>
                    <th><?= h(__('t_931f803bd8', 'القيمة')) ?></th>
                    <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                    <th><?= h(__('t_3c4208fe6a', 'ملاحظات')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($healthChecks as $check): ?>
                    <tr>
                      <td><strong><?= h($check['label']) ?></strong></td>
                      <td><small><?= h((string)$check['value']) ?></small></td>
                      <td>
                        <?php if ($check['status'] === 'ok'): ?>
                          <span class="badge bg-success"><?= h(__('t_aaaa712e44', 'سليم')) ?></span>
                        <?php elseif ($check['status'] === 'warn'): ?>
                          <span class="badge bg-warning text-dark"><?= h(__('t_b34a41530b', 'تحذير')) ?></span>
                        <?php else: ?>
                          <span class="badge bg-danger"><?= h(__('t_8c96e0d00d', 'مشكلة')) ?></span>
                        <?php endif; ?>
                      </td>
                      <td><small class="text-muted"><?= h($check['message']) ?></small></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

  <?php elseif ($tab === 'cache'): ?>
    <!-- تبويب الكاش -->
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card glass-card shadow-sm border-0">
          <div class="card-body">
            <h5 class="card-title mb-3"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_cd55756f04', 'إعدادات الكاش')) ?></h5>
            <dl class="row small mb-0">
              <dt class="col-4"><?= h(__('t_1253eb5642', 'الحالة')) ?></dt>
              <dd class="col-8">
                <?php
                  $st = bool_to_status($cacheConfig['enabled']);
                ?>
                <span class="badge bg-<?= $st['badge'] ?>"><?= $st['label'] ?></span>
              </dd>

              <dt class="col-4"><?= h(__('t_6bf9aa1887', 'السائق (Driver)')) ?></dt>
              <dd class="col-8"><?= h($cacheConfig['driver']) ?></dd>

              <dt class="col-4"><?= h(__('t_4f3d289eb3', 'المسار')) ?></dt>
              <dd class="col-8"><code><?= h($cacheConfig['path']) ?></code></dd>

              <dt class="col-4"><?= h(__('t_58c27f48ec', 'TTL الافتراضي')) ?></dt>
              <dd class="col-8"><?= (int)$cacheConfig['ttl'] ?> ثانية</dd>

              <dt class="col-4"><?= h(__('t_1c74d47709', 'ملفات الكاش')) ?></dt>
              <dd class="col-8">
                <?= (int)$cacheStats['files'] ?> ملف
                <small class="text-muted">(≈ <?= round($cacheStats['size'] / 1024, 1) ?> كيلوبايت)</small>
              </dd>

              <dt class="col-4"><?= h(__('t_0f1c748e44', 'اختبار الكاش')) ?></dt>
              <dd class="col-8">
                <?php if (!$cacheConfig['enabled']): ?>
                  <span class="badge bg-warning text-dark"><?= h(__('t_f08d8e9f9b', 'الكاش غير مفعّل')) ?></span>
                  <div class="small text-muted mt-1">
                    <?= h(__('t_d0dba25f1e', 'فعّل CACHE_ENABLED في ملف البيئة للاستفادة من الأداء.')) ?>
                  </div>
                <?php elseif (!$cacheSelfTest['run']): ?>
                  <span class="badge bg-secondary"><?= h(__('t_169a5da88f', 'لم يتم الفحص')) ?></span>
                <?php elseif ($cacheSelfTest['ok']): ?>
                  <span class="badge bg-success"><?= h(__('t_655b17dd81', 'ناجح')) ?></span>
                  <div class="small text-muted mt-1"><?= h($cacheSelfTest['message']) ?></div>
                <?php else: ?>
                  <span class="badge bg-danger"><?= h(__('t_a838e35c4d', 'فشل')) ?></span>
                  <div class="small text-muted mt-1"><?= h($cacheSelfTest['message']) ?></div>
                <?php endif; ?>
              </dd>
            </dl>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card glass-card shadow-sm border-0">
          <div class="card-body">
            <h5 class="card-title mb-3"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_736b931c7c', 'إدارة الكاش')) ?></h5>
            <p class="small text-muted">
              <?= h(__('t_ddd3d2ec83', 'يمكنك مسح الكاش بالكامل لإجبار النظام على إعادة تحميل الإعدادات والبيانات من قاعدة البيانات.')) ?>
            </p>
            <form method="post" data-confirm='هل أنت متأكد من مسح الكاش بالكامل؟'>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

              <input type="hidden" name="action" value="flush_cache">
              <?php if (function_exists('csrf_field')) { csrf_field(); } ?>
              <button type="submit" class="btn btn-danger">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_53081a962d', 'مسح الكاش الآن')) ?>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

  <?php elseif ($tab === 'logs'): ?>
    <!-- تبويب السجلات -->
    <div class="row g-3">
      <div class="col-md-7">
        <div class="card glass-card shadow-sm border-0">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title h6 mb-0">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_d5b113d588', 'admin_logs (آخر 100 سجل)')) ?>
            </h5>
          </div>
          <div class="card-body p-0">
            <?php if ($adminLogsError): ?>
              <p class="text-danger small p-3 mb-0"><?= h($adminLogsError) ?></p>
            <?php elseif (!$adminLogsTableExists): ?>
              <p class="text-muted small p-3 mb-0">
                <?= h(__('t_64b2ab32d2', 'جدول')) ?> <code>admin_logs</code> <?= h(__('t_48ba74b806', 'غير موجود. تأكد من تشغيل سكربت إنشاء الجداول.')) ?>
              </p>
            <?php elseif (empty($adminLogs)): ?>
              <p class="text-muted small p-3 mb-0"><?= h(__('t_6255ed95d0', 'لا توجد سجلات حتى الآن.')) ?></p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle text-center">
                  <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <th><?= h(__('t_8456f22b47', 'التاريخ')) ?></th>
                      <th><?= h(__('t_457bd90fa1', 'الحدث')) ?></th>
                      <th><?= h(__('t_92ad641491', 'المستخدم')) ?></th>
                      <th><?= h(__('t_cc0478a85c', 'تفاصيل')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($adminLogs as $log): ?>
                      <tr>
                        <td><?= (int)$log['id'] ?></td>
                        <td><small><?= h($log['created_at']) ?></small></td>
                        <td><code class="small"><?= h($log['action']) ?></code></td>
                        <td>
                          <?php if ($log['user_id']): ?>
                            <small>#<?= (int)$log['user_id'] ?></small>
                          <?php else: ?>
                            <span class="text-muted small"><?= h(__('t_cd09c30d57', 'غير محدد')) ?></span>
                          <?php endif; ?>
                        </td>
                        <td style="max-width: 260px;"><small><?= format_details_short($log['details'] ?? '') ?></small></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-md-5">
        <div class="card glass-card shadow-sm border-0">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title h6 mb-0">
              <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_70b4ac3c65', 'ملف أخطاء PHP')) ?>
            </h5>
            <span class="small text-muted"><?= h(basename($errorLogPath)) ?></span>
          </div>
          <div class="card-body">
            <?php if (!$errorLogPath || !is_file($errorLogPath)): ?>
              <p class="small text-muted mb-0">
                <?= h(__('t_27231c932a', 'لم يتم العثور على ملف الأخطاء. يمكنك تحديده عبر')) ?>
                <code>GODYAR_ERROR_LOG</code> <?= h(__('t_ab6e943213', 'في ملف البيئة.')) ?>
              </p>
            <?php elseif (empty($errorLogLines)): ?>
              <p class="small text-muted mb-0"><?= h(__('t_9daab3604d', 'لا توجد أسطر لعرضها أو الملف فارغ.')) ?></p>
            <?php else: ?>
              <pre class="small mb-0" style="max-height: 360px; overflow:auto; background:rgba(15,23,42,.85); color:#e5e7eb; border-radius:8px; padding:10px;">
<?php foreach ($errorLogLines as $line) {
    echo h($line) . "\n";
} ?>
              </pre>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  <?php elseif ($tab === 'maintenance'): ?>
    <!-- تبويب وضع الصيانة -->
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card glass-card shadow-sm border-0">
          <div class="card-body">
            <h5 class="card-title mb-3"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_f96c99c4d8', 'وضع الصيانة')) ?></h5>

            <p class="small text-muted">
              <?= h(__('t_4aff301ed8', 'عند تفعيل وضع الصيانة، يمكن أن تقوم الواجهة الأمامية بعرض صفحة "الموقع تحت الصيانة"
              مع إبقاء لوحة التحكم متاحة للمسؤولين.')) ?>
            </p>

            <p class="mt-2">
              الحالة الحالية:
              <?php if ($maintenanceEnabled): ?>
                <span class="badge bg-warning text-dark"><?= h(__('t_918499f2af', 'مفعل')) ?></span>
              <?php else: ?>
                <span class="badge bg-success"><?= h(__('t_60dfc10f77', 'غير مفعل')) ?></span>
              <?php endif; ?>
            </p>

            <form method="post" class="mt-3"
                  data-confirm='هل أنت متأكد من تغيير حالة وضع الصيانة؟'>
              <input type="hidden" name="action" value="toggle_maintenance">
              <input type="hidden" name="enable" value="<?= $maintenanceEnabled ? '0' : '1' ?>">
              <?php if (function_exists('csrf_field')) { csrf_field(); } ?>

              <?php if ($maintenanceEnabled): ?>
                <button type="submit" class="btn btn-success">
                  <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_1ab11f3d9d', 'إيقاف وضع الصيانة')) ?>
                </button>
                <div class="small text-muted mt-2">
                  <?= h(__('t_a35b25300b', 'عند الإيقاف سيتم')) ?> <strong><?= h(__('t_2caa9c447f', 'مسح الكاش')) ?></strong> <?= h(__('t_5c1edd5e9d', 'تلقائياً لضمان تحديث كل شيء.')) ?>
                </div>
              <?php else: ?>
                <button type="submit" class="btn btn-warning text-dark">
                  <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_11f2db3133', 'تفعيل وضع الصيانة')) ?>
                </button>
                <div class="small text-muted mt-2">
                  <?= h(__('t_a051961618', 'تحتاج إضافة فحص ملف')) ?> <code>storage/maintenance.flag</code> <?= h(__('t_e51e911768', 'في الواجهة الأمامية لعرض صفحة الصيانة.')) ?>
                </div>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card glass-card shadow-sm border-0">
          <div class="card-body">
            <h5 class="card-title mb-3"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_ca1a330b25', 'ملاحظات فنية')) ?></h5>
            <ul class="small text-muted mb-0">
              <li><?= h(__('t_452f8963f9', 'هذا التبويب لا يغيّر الكود الأمامي بنفسه، بل يضع/يحذف ملف علامة في')) ?>
                <code>storage/maintenance.flag</code>.
              </li>
              <li><?= h(__('t_eb4935de3b', 'يمكنك في صفحة index الرئيسية للموقع أن تتحقق من وجود هذا الملف لعرض صفحة "الموقع تحت الصيانة".')) ?></li>
              <li><?= h(__('t_86825d1b4e', 'عند الخروج من وضع الصيانة، يتم استدعاء')) ?> <code>Cache::flush()</code> <?= h(__('t_6d7725f09e', 'لمسح الكاش بالكامل.')) ?></li>
            </ul>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>
</div>

<style>
.system-tabs .nav-link {
  border-radius: 999px;
}
.system-tabs .nav-link.active {
  background: linear-gradient(135deg, #06b6d4, #0ea5e9);
  box-shadow: 0 10px 25px rgba(8, 47, 73, .35);
}
.glass-card {
  background: radial-gradient(circle at top left, rgba(45, 212, 191, 0.08), rgba(15,23,42,0.92));
  border: 1px solid rgba(148, 163, 184, 0.35) !important;
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}
</style>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
