<?php
declare(strict_types=1);


require_once __DIR__ . '/../../_admin_guard.php';
// admin/system/cache/index.php

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'system_cache';
$pageTitle   = 'إدارة الكاش';

if (!Auth::isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$flash = null;

$cacheInfo = [
    'enabled' => false,
    'driver'  => 'file',
    'path'    => '',
];

// معلومات عن الكاش + اختبار بسيط
if (class_exists('Cache')) {
    // تجربة كتابة/قراءة للتأكد من عمل الكاش
    try {
        Cache::put('_admin_cache_test', 'ok', 60);
        $val = Cache::get('_admin_cache_test', null);
        $cacheInfo['enabled'] = ($val === 'ok');
        Cache::forget('_admin_cache_test');
    } catch (Throwable $e) {
        $cacheInfo['enabled'] = false;
        @error_log('[Godyar Cache Test] ' . $e->getMessage());
    }

    // محاولة معرفة المسار من الكلاس
    try {
        if (method_exists('Cache', 'getPath')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $cacheInfo['path'] = (string) Cache::getPath();
        } else {
            $ref = new ReflectionClass('Cache');
            if ($ref->hasProperty('cachePath')) {
                $prop = $ref->getProperty('cachePath');
                $prop->setAccessible(true);
                $cacheInfo['path'] = (string) $prop->getValue();
            }
        }
    } catch (Throwable $e) {
        @error_log('[Godyar Cache Path] ' . $e->getMessage());
    }

    // لو بقي المسار فارغاً، نستخدم المسار الشائع عندك
    if ($cacheInfo['path'] === '') {
        $cacheInfo['path'] = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__,3)) . '/cache';
    }
}

// زر مسح الكاش
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['flush'])
    && class_exists('Cache')
) {
    try {
        Cache::flush();
        $flash = 'تم مسح ملفات الكاش بنجاح.';
    } catch (Throwable $e) {
        $flash = 'حدث خطأ أثناء مسح الكاش.';
        @error_log('[Godyar Cache Flush] ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../../layout/header.php';
require_once __DIR__ . '/../../layout/sidebar.php';
?>

<style>
:root{
    /* نفس "التصميم الموحد للعرض" المستخدم في الصفحات الأخرى */
    --gdy-shell-max: min(880px, 100vw - 360px);
}

html, body{
    overflow-x: hidden;
    background: #020617;
    color: #e5e7eb;
}

/* الحاوية العامة للمحتوى */
.admin-content{
    max-width: var(--gdy-shell-max);
    width: 100%;
    margin: 0 auto;
}

/* تقليل الفراغ العمودي */
.admin-content.container-fluid.py-4{
    padding-top: 0.75rem !important;
    padding-bottom: 1rem !important;
}

/* رأس الصفحة */
.gdy-page-header{
    padding: .85rem 1rem;
    margin-bottom: .9rem;
    border-radius: 1rem;
    background: radial-gradient(circle at top, #020617 0%, #020617 55%, #020617 100%);
    border: 1px solid rgba(148,163,184,0.35);
    box-shadow: 0 8px 20px rgba(15,23,42,0.85);
}

/* كروت زجاجية */
.gdy-glass-card{
    background: rgba(15,23,42,0.96);
    border-radius: 16px;
    border: 1px solid rgba(31,41,55,0.9);
    color: #e5e7eb;
}

/* عناوين صغيرة */
.gdy-muted{
    color: #9ca3af;
    font-size: .85rem;
}

/* زر مسح الكاش */
.gdy-danger-outline{
    border-radius: 999px;
}

/* استجابة الشاشات الصغيرة */
@media (max-width: 992px){
    :root{
        --gdy-shell-max: 100vw;
    }
}
</style>

<div class="admin-content container-fluid py-4">

  <div class="gdy-page-header mb-3 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
    <div>
      <h1 class="h4 mb-1 text-white">إدارة الكاش</h1>
      <p class="mb-0 gdy-muted">
        عرض حالة نظام الكاش ومسح الملفات المؤقتة عند الحاجة.
      </p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-info py-2">
      <?= h($flash) ?>
    </div>
  <?php endif; ?>

  <div class="card gdy-glass-card mb-3">
    <div class="card-body">
      <?php if (!class_exists('Cache')): ?>
        <p class="mb-0" style="color:#fca5a5;">
          كلاس <code>Cache</code> غير محمّل. تأكد من وجود الملف <code>includes/Cache.php</code> واستدعائه من <code>bootstrap.php</code>.
        </p>
      <?php else: ?>
        <dl class="row mb-0">
          <dt class="col-sm-3 col-md-2 mb-2">الحالة</dt>
          <dd class="col-sm-9 col-md-10 mb-2">
            <span class="badge <?= $cacheInfo['enabled'] ? 'bg-success' : 'bg-warning text-dark' ?>">
              <?= $cacheInfo['enabled'] ? 'يعمل بشكل سليم' : 'غير مفعّل أو غير مستقر' ?>
            </span>
          </dd>

          <dt class="col-sm-3 col-md-2 mb-2">المحرّك</dt>
          <dd class="col-sm-9 col-md-10 mb-2">
            <code><?= h($cacheInfo['driver']) ?></code>
          </dd>

          <dt class="col-sm-3 col-md-2 mb-0">المسار</dt>
          <dd class="col-sm-9 col-md-10 mb-0">
            <code><?= h($cacheInfo['path']) ?></code>
          </dd>
        </dl>
      <?php endif; ?>
    </div>
  </div>

  <?php if (class_exists('Cache')): ?>
    <form method="post" class="card gdy-glass-card">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
        <div class="mb-3 mb-md-0">
          <h2 class="h6 mb-1 text-white">مسح الكاش</h2>
          <p class="mb-0 gdy-muted">
            في حال وجود تغييرات لا تظهر مباشرة، يمكنك مسح ملفات الكاش لإجبار النظام على إعادة توليدها.
          </p>
        </div>
        <div>
          <button type="submit"
                  name="flush"
                  value="1"
                  class="btn btn-outline-danger gdy-danger-outline"
                  data-confirm='هل أنت متأكد من مسح الكاش؟'>
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> مسح الكاش الآن
          </button>
        </div>
      </div>
    </form>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
