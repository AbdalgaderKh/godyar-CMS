<?php
declare(strict_types=1);


require_once __DIR__ . '/../../_admin_guard.php';
$currentPage = 'system_maintenance';
$pageTitle   = __('t_f96c99c4d8', 'وضع الصيانة');

require_once __DIR__ . '/../../_admin_boot.php';

$flagFile = GODYAR_ROOT . '/storage/maintenance.flag';
$isOn     = is_file($flagFile);
$message  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('verify_csrf') && !verify_csrf()) {
        $message = __('t_0f296c4fe0', 'فشل التحقق الأمني، يرجى إعادة المحاولة.');
    } else {
        $mode = $_POST['mode'] ?? '';
        if ($mode === 'on') {
            @mkdir(dirname($flagFile), 0775, true);
            @file_put_contents($flagFile, date('Y-m-d H:i:s') . ' maintenance on');
            $isOn    = true;
            $message = __('t_e1cd3f66b0', 'تم تفعيل وضع الصيانة.');
        } elseif ($mode === 'off') {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            $isOn    = false;
            $message = __('t_150fe51a82', 'تم إلغاء وضع الصيانة.');
        }
    }
}
?>

<div class="admin-content container-fluid py-4">
  <div class="mb-3">
    <h1 class="h3 mb-1"><?= h(__('t_f96c99c4d8', 'وضع الصيانة')) ?></h1>
    <p class="text-muted mb-0">
      <?= h(__('t_53e754fbde', 'عند تفعيل وضع الصيانة، يتم تحويل الزوار إلى صفحة صيانة جميلة في الواجهة الأمامية،
      بينما يبقى الوصول إلى لوحة التحكم متاحاً للمشرفين.')) ?>
    </p>
  </div>

  <div class="card glass-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="h6 mb-0"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_640a46691d', 'حالة النظام')) ?></h2>
      <span class="badge <?= $isOn ? 'bg-danger' : 'bg-success' ?>">
        <?= $isOn ? __('t_75f027a006', 'وضع الصيانة مفعل') : __('t_6a8de9d74a', 'الموقع يعمل بشكل طبيعي') ?>
      </span>
    </div>
    <div class="card-body">
      <?php if ($message): ?>
        <div class="alert alert-info py-2"><?= h($message) ?></div>
      <?php endif; ?>

      <p class="mb-3">
        <?= h(__('t_6625f2da4c', 'تأكد من أن صفحة')) ?> <code>public/maintenance.php</code> <?= h(__('t_482bde0257', 'موجودة وتعرض رسالة مناسبة للزوار.')) ?>
      </p>

      <form method="post" action="" class="d-flex gap-2">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <?php if (function_exists('csrf_field')) { csrf_field(); } ?>

        <?php if ($isOn): ?>
          <input type="hidden" name="mode" value="off">
          <button type="submit" class="btn btn-success">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_92c406424e', 'إلغاء وضع الصيانة')) ?>
          </button>
        <?php else: ?>
          <input type="hidden" name="mode" value="on">
          <button type="submit" class="btn btn-danger" data-confirm='سيتم تفعيل وضع الصيانة، متابعة؟'>
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_11f2db3133', 'تفعيل وضع الصيانة')) ?>
          </button>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
