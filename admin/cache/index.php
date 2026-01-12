<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
$currentPage = 'system_cache';
$pageTitle   = 'إدارة الكاش';

require_once __DIR__ . '/../../_admin_boot.php';

$cacheSupported = class_exists('Cache');
$cacheMessage   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cacheSupported) {
    // لو عندك verify_csrf() فعّله هنا
    if (function_exists('verify_csrf') && !verify_csrf()) {
        $cacheMessage = 'فشل التحقق الأمني، يرجى إعادة المحاولة.';
    } else {
        try {
            Cache::flush();
            $cacheMessage = 'تم مسح الكاش بنجاح.';
        } catch (Throwable $e) {
            $cacheMessage = 'حدث خطأ أثناء مسح الكاش.';
            @error_log('[Godyar system/cache] ' . $e->getMessage());
        }
    }
}

?>

<div class="admin-content container-fluid py-4">
  <div class="mb-3">
    <h1 class="h3 mb-1">الكاش</h1>
    <p class="text-muted mb-0">إدارة ذاكرة الكاش الخاصة بلوحة التحكم والموقع.</p>
  </div>

  <div class="card glass-card mb-3">
    <div class="card-header">
      <h2 class="h6 mb-0"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> مسح الكاش</h2>
    </div>
    <div class="card-body">
      <?php if (!$cacheSupported): ?>
        <p class="text-danger mb-0">كلاس Cache غير موجود، يرجى التأكد من ملف <code>includes/cache.php</code>.</p>
      <?php else: ?>
        <?php if ($cacheMessage): ?>
          <div class="alert alert-info py-2"><?= h($cacheMessage) ?></div>
        <?php endif; ?>

        <p class="mb-2">
          هذا الإجراء سيحذف كل ملفات الكاش المخزّنة في <code>storage/cache</code> داخل مجلد جويار.
        </p>

        <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

          <?php if (function_exists('csrf_field')) { csrf_field(); } ?>
          <button type="submit" class="btn btn-danger" data-confirm='تأكيد مسح الكاش بالكامل؟'>
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> مسح الكاش الآن
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
