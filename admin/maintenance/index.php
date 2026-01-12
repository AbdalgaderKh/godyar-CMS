<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
// admin/maintenance/index.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'maintenance';
$pageTitle   = __('t_f96c99c4d8', 'وضع الصيانة');

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$flagFile = GODYAR_ROOT . '/maintenance.flag';
$flash    = null;

// معالجة POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode  = (string)($_POST['mode'] ?? '');
    $msg   = trim((string)($_POST['message'] ?? ''));

    try {
        if ($mode === 'enable') {
            $content = $msg === '' ? 'Site is under maintenance' : $msg;
            @file_put_contents($flagFile, $content);
            $flash = __('t_d4c9797619', 'تم تفعيل وضع الصيانة. سيظهر للزوار صفحة الصيانة.');
        } elseif ($mode === 'disable') {
            if (is_file($flagFile)) {
                @unlink($flagFile);
            }
            $flash = __('t_ec96ec5ee6', 'تم إلغاء وضع الصيانة. الموقع الآن متاح للزوار.');
        }
    } catch (Throwable $e) {
        $flash = __('t_bff9e6efda', 'حدث خطأ أثناء تحديث حالة الصيانة.');
        @error_log('[Godyar Maintenance] ' . $e->getMessage());
    }
}

$isMaintenance = is_file($flagFile);
$currentMsg    = '';
$lastUpdated   = '';
if ($isMaintenance) {
    $currentMsg = (string)@file_get_contents($flagFile);
    $ts = @filemtime($flagFile);
    if ($ts) {
        $lastUpdated = date('Y-m-d H:i', (int)$ts);
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
/* التصميم الموحد للعرض - منع التمدد خارج الشاشة + عدم التداخل مع السايدبار */
html, body { overflow-x: hidden; }

@media (min-width: 992px) {
  /* نفس عرض القائمة الجانبية */
  .admin-content.gdy-page { margin-right: 260px !important; }
}

/* غلاف الصفحة */
.admin-content.gdy-page {
  background: linear-gradient(135deg, #0f172a 0%, #020617 100%);
  min-height: 100vh;
  color: #e5e7eb;
}

/* احتواء المحتوى وتوسيطه */
.admin-content.gdy-page .gdy-shell{
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.5rem 1rem 2rem;
}

/* رأس الصفحة */
.gdy-header {
  background: linear-gradient(135deg, #0ea5e9, #0369a1);
  color: #fff;
  padding: 1.25rem 1.5rem;
  border-radius: 1rem;
  box-shadow: 0 12px 30px rgba(15,23,42,.55);
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}
.gdy-header h1{
  margin: 0 0 .25rem;
  font-size: 1.25rem;
  font-weight: 800;
}
.gdy-header p{
  margin: 0;
  font-size: .9rem;
  opacity: .92;
}

/* كارد زجاجي */
.gdy-card {
  background: rgba(15,23,42,.92);
  border: 1px solid rgba(148,163,184,.35);
  border-radius: 1rem;
  box-shadow: 0 18px 45px rgba(2,6,23,.65);
  overflow: hidden;
}
.gdy-card .card-body{ padding: 1.25rem; }

/* نصوص */
.gdy-muted { color: #9ca3af; }
.gdy-kbd {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-size: .85em;
  background: rgba(2,6,23,.85);
  border: 1px solid rgba(148,163,184,.25);
  padding: .15rem .45rem;
  border-radius: .5rem;
  color: #e5e7eb;
}

/* الحقول */
.form-control, .form-select {
  background: rgba(2,6,23,.75);
  border-color: rgba(148,163,184,.35);
  color: #e5e7eb;
  border-radius: .75rem;
}
.form-control:focus, .form-select:focus {
  background: rgba(2,6,23,.85);
  border-color: #0ea5e9;
  box-shadow: 0 0 0 .15rem rgba(14,165,233,.25);
  color: #e5e7eb;
}

/* شارة الحالة */
.gdy-status {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .35rem .75rem;
  border-radius: 999px;
  border: 1px solid rgba(148,163,184,.35);
  background: rgba(2,6,23,.55);
  font-weight: 700;
  font-size: .85rem;
}
.gdy-status .dot{
  width: 9px; height: 9px; border-radius: 999px;
  background: #22c55e;
  box-shadow: 0 0 0 4px rgba(34,197,94,.15);
}
.gdy-status.is-on .dot{
  background: #f59e0b;
  box-shadow: 0 0 0 4px rgba(245,158,11,.18);
}

/* أزرار */
.gdy-btn {
  border-radius: .8rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: .45rem;
}
.gdy-actions { display:flex; flex-wrap:wrap; gap:.5rem; }

/* صندوق معلومات */
.gdy-info-box{
  border-radius: .9rem;
  border: 1px dashed rgba(148,163,184,.4);
  background: rgba(2,6,23,.55);
  padding: .9rem 1rem;
}

/* تجاوب */
@media (max-width: 767.98px){
  .admin-content.gdy-page .gdy-shell{ padding: 1rem .75rem 1.5rem; }
  .gdy-header{ padding: 1rem 1.1rem; }
}
</style>

<div class="admin-content gdy-page">
  <div class="gdy-shell">

    <div class="gdy-header">
      <div>
        <h1><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_f96c99c4d8', 'وضع الصيانة')) ?></h1>
        <p><?= h(__('t_6e83122fb0', 'تفعيل أو إلغاء صفحة الصيانة في واجهة الموقع عبر ملف')) ?> <span class="gdy-kbd">maintenance.flag</span>.</p>
      </div>
      <div class="gdy-actions">
        <a href="index.php" class="btn btn-outline-light btn-sm gdy-btn">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_7cf7b105b4', 'العودة')) ?>
        </a>
        <button type="button" class="btn btn-outline-info btn-sm gdy-btn" id="copy-flag-path"
                data-path="<?= h($flagFile) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_1e713e7334', 'نسخ مسار الملف')) ?>
        </button>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-info py-2 mb-3">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($flash) ?>
      </div>
    <?php endif; ?>

    <div class="gdy-card">
      <div class="card-body">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <div class="gdy-status <?= $isMaintenance ? 'is-on' : '' ?>">
              <span class="dot"></span>
              <span><?= $isMaintenance ? __('t_1cdcf83f2f', 'وضع الصيانة: مفعّل') : __('t_eb89a60edd', 'وضع الصيانة: غير مفعّل') ?></span>
            </div>
            <?php if ($lastUpdated): ?>
              <div class="small gdy-muted mt-1">
                <?= h(__('t_e89d048a83', 'آخر تحديث:')) ?> <span class="gdy-kbd"><?= h($lastUpdated) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <div class="small gdy-muted">
            <?= h(__('t_faa6688e17', 'المسار:')) ?> <span class="gdy-kbd"><?= h($flagFile) ?></span>
          </div>
        </div>

        <?php if ($isMaintenance): ?>
          <div class="mb-3">
            <label class="form-label"><?= h(__('t_4886822396', 'الرسالة الحالية المعروضة في صفحة الصيانة')) ?></label>
            <div class="gdy-info-box small">
              <?= nl2br(h($currentMsg)) ?>
            </div>
          </div>
        <?php endif; ?>

        <form method="post" id="maintenance-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_5c0b35918f', 'رسالة الصيانة المخصصة (اختياري)')) ?></label>
            <textarea name="message" class="form-control" rows="3"
                      placeholder="<?= h(__('t_132ae9a19f', 'سنعود قريباً...')) ?>"><?= h($currentMsg) ?></textarea>
            <div class="form-text gdy-muted">
              <?= h(__('t_ba08fbec23', 'اكتب رسالة مختصرة وواضحة للزوار (يمكن تركها فارغة لاستخدام الرسالة الافتراضية).')) ?>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <button type="submit" name="mode" value="enable" class="btn btn-warning text-dark gdy-btn">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_11f2db3133', 'تفعيل وضع الصيانة')) ?>
            </button>
            <button type="submit" name="mode" value="disable" class="btn btn-success gdy-btn" id="disable-btn">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_92c406424e', 'إلغاء وضع الصيانة')) ?>
            </button>
          </div>
        </form>

        <div class="mt-3 gdy-info-box">
          <div class="small">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h(__('t_afde1943c7', 'تأكد أن سكربت الواجهة (index.php أو router) يتحقق من وجود الملف')) ?>
            <span class="gdy-kbd">maintenance.flag</span> <?= h(__('t_a5a2271c5b', 'ويحوّل الزائر إلى')) ?>
            <span class="gdy-kbd">/godyar/public/maintenance.php</span>.
          </div>
        </div>

      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // نسخ مسار الملف (للإدارة)
  var copyBtn = document.getElementById('copy-flag-path');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var path = this.getAttribute('data-path') || '';
      if (!path) return;
      navigator.clipboard.writeText(path).then(() => {
        var old = this.innerHTML;
        this.innerHTML = '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تم النسخ';
        this.classList.remove('btn-outline-info');
        this.classList.add('btn-success');
        setTimeout(() => {
          this.innerHTML = old;
          this.classList.add('btn-outline-info');
          this.classList.remove('btn-success');
        }, 1600);
      });
    });
  }

  // تأكيد قبل إلغاء وضع الصيانة
  var disableBtn = document.getElementById('disable-btn');
  if (disableBtn) {
    disableBtn.addEventListener('click', function (e) {
      if (!confirm('هل تريد إلغاء وضع الصيانة وفتح الموقع للزوار؟')) {
        e.preventDefault();
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
