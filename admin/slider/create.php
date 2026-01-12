<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/slider/create.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'slider';
$pageTitle   = __('t_8e17c0aac0', 'إضافة شريحة سلايدر');

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$errors = [];
$data   = [
    'title'      => '',
    'subtitle'   => '',
    'image_path' => '',
    'link_url'   => '',
    'is_active'  => 1,
    'sort_order' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $v) {
        if (isset($_POST[$k])) {
            $data[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k];
        }
    }
    $data['is_active']  = isset($_POST['is_active']) ? 1 : 0;
    $data['sort_order'] = (int)($data['sort_order'] ?? 0);

    if ($data['title'] === '') {
        $errors['title'] = __('t_318fc376b7', 'العنوان مطلوب.');
    }

    if (empty($errors) && $pdo instanceof PDO) {
        try {
            $sql = "
                INSERT INTO slider (title, subtitle, image_path, link_url, is_active, sort_order, created_at)
                VALUES (:title, :subtitle, :image_path, :link_url, :is_active, :sort_order, NOW())
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title'      => $data['title'],
                ':subtitle'   => $data['subtitle'],
                ':image_path' => $data['image_path'],
                ':link_url'   => $data['link_url'],
                ':is_active'  => $data['is_active'],
                ':sort_order' => $data['sort_order'],
            ]);
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $errors['general'] = __('t_dee8218bc9', 'حدث خطأ أثناء حفظ الشريحة.');
            @error_log('[Godyar Slider Create] insert: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<style>
:root{
  /* نضغط عرض محتوى السلايدر ليكون مريحاً بجانب السايدبار */
  --gdy-shell-max: min(880px, 100vw - 360px);
}

.admin-content{
  max-width: var(--gdy-shell-max);
  margin: 0 auto;
}

/* تقليل الفراغ العمودي داخل صفحة السلايدر */
.admin-content.container-fluid.py-4{
  padding-top:0.75rem !important;
  padding-bottom:1rem !important;
}

.gdy-page-header{
  margin-bottom:0.75rem;
}

.gdy-card{
  border-radius:1.25rem;
  border:1px solid rgba(148,163,184,0.25);
}

/* تحسين رأس الجدول في صفحة القائمة */
.table thead th{
  background:#020617;
  border-bottom-color:rgba(148,163,184,0.4);
}
</style>

<div class="admin-content container-fluid py-4">
  <div class="admin-content gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div>
      <h1 class="h4 mb-1 text-white"><?= h(__('t_5d1adeeb8d', 'إضافة شريحة جديدة')) ?></h1>
      <p class="mb-0" style="color:#e5e7eb;"><?= h(__('t_a0f6af2e31', 'شريحة تظهر في السلايدر في الصفحة الرئيسية.')) ?></p>
    </div>
    <div class="mt-3 mt-md-0">
      <a href="index.php" class="btn btn-outline-light">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_19ae074cbf', 'العودة للقائمة')) ?>
      </a>
    </div>
  </div>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger py-2"><?= h($errors['general']) ?></div>
  <?php endif; ?>

  <form method="post" class="card glass-card gdy-card" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label"><?= h(__('t_aa7e2e97ea', 'العنوان *')) ?></label>
          <input type="text" name="title" class="form-control" value="<?= h($data['title']) ?>">
          <?php if (!empty($errors['title'])): ?>
            <div class="text-danger small mt-1"><?= h($errors['title']) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= h(__('t_d31bde862c', 'النص الفرعي')) ?></label>
          <input type="text" name="subtitle" class="form-control" value="<?= h($data['subtitle']) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label"><?= h(__('t_324c759f42', 'مسار الصورة')) ?></label>
          <div class="input-group">
          <input type="text" id="slider_image_path" name="image_path" class="form-control"
                 placeholder="/uploads/slider/slide1.jpg"
                 value="<?= h($data['image_path']) ?>">
          <button type="button" class="btn btn-outline-secondary" data-action="open-media-modal" data-target="slider_image_path">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_443b526a45', 'اختيار من الوسائط')) ?>
          </button>
        </div>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= h(__('t_72ce2dd33e', 'الرابط عند الضغط')) ?></label>
          <input type="text" name="link_url" class="form-control"
                 placeholder="https://example.com"
                 value="<?= h($data['link_url']) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label d-block"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                   <?= $data['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active"><?= h(__('t_74cdedb351', 'مفعّلة في السلايدر')) ?></label>
          </div>
        </div>

        <div class="col-md-3">
          <label class="form-label"><?= h(__('t_ddda59289a', 'الترتيب')) ?></label>
          <input type="number" name="sort_order" class="form-control"
                 value="<?= (int)$data['sort_order'] ?>">
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
      <button type="submit" class="btn btn-primary">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_915ff03e02', 'حفظ الشريحة')) ?>
      </button>
    </div>
  </form>
</div>

<script>
  function godyarOpenMediaModal(fieldId) {
    var modalEl = document.getElementById('godyarMediaModal');
    if (!modalEl) return;
    var iframe = modalEl.querySelector('iframe');
    if (iframe) {
      iframe.src = '../media/picker.php?field=' + encodeURIComponent(fieldId);
    }
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  function godyarSelectMedia(fieldId, url) {
    var input = document.getElementById(fieldId);
    if (input) {
      input.value = url;
      var event = new Event('change', { bubbles: true });
      input.dispatchEvent(event);
    }
    if (window.bootstrap) {
      var modalEl = document.getElementById('godyarMediaModal');
      if (modalEl) {
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
    }
  }
</script>

<div class="modal fade" id="godyarMediaModal" tabindex="-1" aria-labelledby="godyarMediaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="background:#020617;color:#e5e7eb;">
      <div class="modal-header border-secondary">
        <h5 class="modal-title" id="godyarMediaModalLabel"><?= h(__('t_b8c98cdb48', 'اختيار ملف من مكتبة الوسائط')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h(__('t_9932cca009', 'إغلاق')) ?>"></button>
      </div>
      <div class="modal-body p-0">
        <iframe src="../media/picker.php" style="width:100%;height:520px;border:0;"></iframe>
      </div>
    </div>
  </div>
</div>


<?php require_once __DIR__ . '/../layout/footer.php'; ?>
