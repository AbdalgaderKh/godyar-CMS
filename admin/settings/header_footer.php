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
            'advanced.extra_head' => (string)($_POST['extra_head_code'] ?? ''),
            'advanced.extra_body' => (string)($_POST['extra_body_code'] ?? ''),
        ]);
        $notice = __('t_f6425ed4f7', 'تم حفظ أكواد الهيدر/الفوتر بنجاح.');
    } catch (Throwable $e) {
        $error = __('t_4fa410044f', 'حدث خطأ أثناء الحفظ.');
        @error_log('[settings_header_footer] ' . $e->getMessage());
    }
}

$extra_head_code = settings_get('advanced.extra_head', '');
$extra_body_code = settings_get('advanced.extra_body', '');
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
            <label class="form-label"><?= h(__('t_709326ebec', 'كود إضافي داخل &lt;head&gt;')) ?></label>
            <textarea class="form-control" rows="7" name="extra_head_code"><?= h($extra_head_code) ?></textarea>
            <div class="form-text"><?= h(__('t_c9593b4c69', 'مثال: أكواد التحقق، ميتا، خطوط…')) ?></div>
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_f02f35b274', 'كود إضافي قبل &lt;/body&gt;')) ?></label>
            <textarea class="form-control" rows="7" name="extra_body_code"><?= h($extra_body_code) ?></textarea>
            <div class="form-text"><?= h(__('t_22e2d23204', 'مثال: سكربتات، تتبع، شات…')) ?></div>
          </div>

          <button class="btn btn-primary"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
        </form>
      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
