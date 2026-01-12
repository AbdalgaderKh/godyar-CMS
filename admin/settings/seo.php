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
            'seo.meta_title'       => trim((string)($_POST['meta_title'] ?? '')),
            'seo.meta_description' => trim((string)($_POST['meta_description'] ?? '')),
            'seo.meta_keywords'    => trim((string)($_POST['meta_keywords'] ?? '')),
            'seo.og_image'         => trim((string)($_POST['og_image'] ?? '')),
            'seo.robots'           => trim((string)($_POST['robots'] ?? 'index,follow')),
            'seo.canonical'        => trim((string)($_POST['canonical'] ?? '')),
            // IndexNow
            'seo.indexnow_enabled'     => isset($_POST['indexnow_enabled']) ? '1' : '0',
            'seo.indexnow_key'         => trim((string)($_POST['indexnow_key'] ?? '')),
            'seo.indexnow_endpoint'    => trim((string)($_POST['indexnow_endpoint'] ?? 'https://api.indexnow.org/indexnow')),
            'seo.indexnow_key_location'=> trim((string)($_POST['indexnow_key_location'] ?? '')),
        ]);
        $notice = __('t_04cc9e8d8b', 'تم حفظ إعدادات SEO بنجاح.');
    } catch (Throwable $e) {
        $error = __('t_4fa410044f', 'حدث خطأ أثناء الحفظ.');
        @error_log('[settings_seo] ' . $e->getMessage());
    }
}

$meta_title       = settings_get('seo.meta_title', '');
$meta_description = settings_get('seo.meta_description', '');
$meta_keywords    = settings_get('seo.meta_keywords', '');
$og_image         = settings_get('seo.og_image', '');
$robots           = settings_get('seo.robots', 'index,follow');
$canonical        = settings_get('seo.canonical', '');
$indexnow_enabled = (string)settings_get('seo.indexnow_enabled', '0');
$indexnow_key     = (string)settings_get('seo.indexnow_key', '');
$indexnow_endpoint= (string)settings_get('seo.indexnow_endpoint', 'https://api.indexnow.org/indexnow');
$indexnow_key_location = (string)settings_get('seo.indexnow_key_location', '');
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
            <label class="form-label"><?= h(__('t_79dc36c355', 'Meta Title (عنوان افتراضي)')) ?></label>
            <input class="form-control" name="meta_title" value="<?= h($meta_title) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_eff8e0d67a', 'Meta Description (وصف افتراضي)')) ?></label>
            <textarea class="form-control" rows="3" name="meta_description"><?= h($meta_description) ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_95481e62d6', 'Meta Keywords (اختياري)')) ?></label>
            <input class="form-control" name="meta_keywords" value="<?= h($meta_keywords) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_32769a7187', 'OG Image (رابط صورة مشاركة)')) ?></label>
            <input class="form-control" name="og_image" value="<?= h($og_image) ?>">
            <div class="form-text"><?= h(__('t_f19157842d', 'يمكن استخدام مسار من مكتبة الوسائط أو رابط مباشر.')) ?></div>
          </div>

          <div class="row">
            <div class="col-md-7 mb-3">
              <label class="form-label"><?= h(__('t_7d3e93eac6', 'Robots')) ?></label>
              <input class="form-control" name="robots" value="<?= h($robots) ?>">
              <div class="form-text"><?= h(__('t_a8399bb5d7', 'مثال: index,follow أو noindex,nofollow')) ?></div>
            </div>
            <div class="col-md-5 mb-3">
              <label class="form-label"><?= h(__('t_33a60bd5bd', 'Canonical (اختياري)')) ?></label>
              <input class="form-control" name="canonical" value="<?= h($canonical) ?>">
            </div>
          </div>

          
          <hr class="my-4">

          <h6 class="mb-3"><?= h(__('t_idxnow_001', 'IndexNow (إشعار محركات البحث عند نشر خبر)')) ?></h6>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="indexnow_enabled" name="indexnow_enabled" value="1" <?= ($indexnow_enabled === '1' ? 'checked' : '') ?>>
            <label class="form-check-label" for="indexnow_enabled"><?= h(__('t_idxnow_002', 'تفعيل IndexNow')) ?></label>
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_idxnow_003', 'IndexNow Key')) ?></label>
            <input class="form-control" name="indexnow_key" value="<?= h($indexnow_key) ?>" placeholder="ضع المفتاح هنا">
            <div class="form-text"><?= h(__('t_idxnow_004', 'سيتم نشر المفتاح تلقائياً على الرابط: /indexnow-key.txt')) ?></div>
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_idxnow_005', 'Key Location (اختياري)')) ?></label>
            <input class="form-control" name="indexnow_key_location" value="<?= h($indexnow_key_location) ?>" placeholder="<?= h(rtrim((string)base_url(), '/')) ?>/indexnow-key.txt">
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_idxnow_006', 'Endpoint (اختياري)')) ?></label>
            <input class="form-control" name="indexnow_endpoint" value="<?= h($indexnow_endpoint) ?>" placeholder="https://api.indexnow.org/indexnow">
          </div>

<button class="btn btn-primary"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
        </form>
      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
