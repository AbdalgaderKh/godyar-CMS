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
        settings_save([
            'site.locale'   => trim((string)($_POST['site_locale'] ?? 'ar')),
            'site.timezone' => trim((string)($_POST['site_timezone'] ?? 'Asia/Riyadh')),
        ]);
        $notice = __('t_9fa83b6bf3', 'تم حفظ إعدادات الوقت واللغة بنجاح.');
    } catch (Throwable $e) {
        $error = __('t_4fa410044f', 'حدث خطأ أثناء الحفظ.');
        @error_log('[settings_time] ' . $e->getMessage());
    }
}

$site_locale = settings_get('site.locale', 'ar');
$site_tz     = settings_get('site.timezone', 'Asia/Riyadh');
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

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_ed98f0bcd9', 'اللغة (Locale)')) ?></label>
              <input class="form-control" name="site_locale" value="<?= h($site_locale) ?>">
              <div class="form-text"><?= h(__('t_bc96ee5c34', 'مثال: ar / en')) ?></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_f8c42a0043', 'المنطقة الزمنية (Timezone)')) ?></label>
              <input class="form-control" name="site_timezone" value="<?= h($site_tz) ?>">
              <div class="form-text"><?= h(__('t_6cc9daf7c4', 'مثال: Asia/Riyadh')) ?></div>
            </div>
          </div>

          <button class="btn btn-primary"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
        </form>
      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
