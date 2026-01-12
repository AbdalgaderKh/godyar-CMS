<?php
require_once __DIR__ . '/_settings_guard.php';
require_once __DIR__ . '/_settings_meta.php';
settings_apply_context();
require_once __DIR__ . '/../layout/app_start.php';

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('verify_csrf')) { verify_csrf(); }

    try {
        $enabled = !empty($_POST['cache_enabled']) ? '1' : '0';
        $ttl = (int)($_POST['cache_ttl'] ?? 300);
        if ($ttl <= 0) $ttl = 300;

        settings_save([
            'cache.enabled' => $enabled,
            'cache.ttl'     => (string)$ttl,
        ]);

        $notice = __('t_2b91fb1389', 'تم حفظ إعدادات الكاش بنجاح.');
    } catch (Throwable $e) {
        $error = __('t_4fa410044f', 'حدث خطأ أثناء الحفظ.');
        @error_log('[settings_cache] ' . $e->getMessage());
    }
}

$cache_enabled = settings_get('cache.enabled', '0') === '1';
$cache_ttl     = (int)settings_get('cache.ttl', '300');
?>

<div class="row g-3">
    <div class="col-md-3">
      <?php include __DIR__ . '/_settings_nav.php'; ?>
    </div>

    <div class="col-md-9">
      <div class="card p-4">
<?php if ($notice): ?>
          <div class="alert alert-success"><?= h($notice) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <?php endif; ?>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="cache_enabled" name="cache_enabled" <?= $cache_enabled ? 'checked' : '' ?>>
            <label class="form-check-label" for="cache_enabled"><?= h(__('t_11832df349', 'تفعيل الكاش')) ?></label>
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_85278cd78a', 'مدة الكاش (TTL بالثواني)')) ?></label>
            <input class="form-control" type="number" name="cache_ttl" value="<?= h((string)$cache_ttl) ?>">
            <div class="form-text"><?= h(__('t_c1220e1e8c', 'مثال: 300 = 5 دقائق.')) ?></div>
          </div>

          <button class="btn btn-primary"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
        </form>
      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
