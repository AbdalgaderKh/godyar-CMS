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
        $mode = (($_POST['layout_sidebar_mode'] ?? 'visible') === 'hidden') ? 'hidden' : 'visible';
        settings_save(['layout.sidebar_mode' => $mode]);
        $notice = __('t_abef82929a', 'تم حفظ إعدادات سايدبار الواجهة بنجاح.');
    } catch (Throwable $e) {
        $error = __('t_4fa410044f', 'حدث خطأ أثناء الحفظ.');
        @error_log('[settings_frontend_sidebar] ' . $e->getMessage());
    }
}

$layout_sidebar_mode = settings_get('layout.sidebar_mode', 'visible');
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

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_acf5e83818', 'حالة القائمة الجانبية للواجهة (للزوار)')) ?></label>
            <select class="form-select" name="layout_sidebar_mode">
              <option value="visible" <?= $layout_sidebar_mode === 'visible' ? 'selected' : '' ?>><?= h(__('t_bc6a03bbaa', 'ظاهرة')) ?></option>
              <option value="hidden"  <?= $layout_sidebar_mode === 'hidden'  ? 'selected' : '' ?>><?= h(__('t_ad0a598276', 'مخفية')) ?></option>
            </select>
            <div class="form-text"><?= h(__('t_bfd5336c62', 'هذا يخص سايدبار الزوار في الواجهة، وليس سايدبار لوحة التحكم.')) ?></div>
          </div>

          <button class="btn btn-primary"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
        </form>
      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
