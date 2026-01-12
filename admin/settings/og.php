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
        $enabled = isset($_POST['og_enabled']) ? '1' : '0';
        $mode = in_array(($_POST['og_mode'] ?? 'dynamic'), ['dynamic','static'], true) ? (string)$_POST['og_mode'] : 'dynamic';
        $engine = in_array(($_POST['og_engine'] ?? 'auto'), ['auto','imagick','gd'], true)
            ? (string)$_POST['og_engine']
            : 'auto';
        $arabic_mode = in_array(($_POST['og_arabic_mode'] ?? 'auto'), ['auto','shape','static'], true) ? (string)$_POST['og_arabic_mode'] : 'auto';

        // Basic hex color sanitization (#RRGGBB)
        $hex = function($v, $def) {
            $v = trim((string)$v);
            if ($v === '') return $def;
            if ($v[0] !== '#') $v = '#' . $v;
            return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper($v) : $def;
        };

        settings_save([
            'og.enabled'        => $enabled,
            'og.mode'           => $mode,
            'og.engine'         => $engine,
            'og.default_image'  => trim((string)($_POST['og_default_image'] ?? '')),
            'og.template_image' => trim((string)($_POST['og_template_image'] ?? '')),
            'og.logo_image'     => trim((string)($_POST['og_logo_image'] ?? '')),

            'og.bg_color'       => $hex($_POST['og_bg_color'] ?? '', '#F5F5F5'),
            'og.text_color'     => $hex($_POST['og_text_color'] ?? '', '#141414'),
            'og.muted_color'    => $hex($_POST['og_muted_color'] ?? '', '#4B5563'),
            'og.accent_color'   => $hex($_POST['og_accent_color'] ?? '', '#111827'),

            'og.site_name'      => trim((string)($_POST['og_site_name'] ?? '')),
            'og.tagline'        => trim((string)($_POST['og_tagline'] ?? '')),
            'og.arabic_mode'    => $arabic_mode,
        ]);

        $notice = __('t_90e2dfc8e1', 'تم حفظ إعدادات OG بنجاح.');
    } catch (Throwable $e) {
        $error = __('t_54f61c6dd4', 'تعذر حفظ الإعدادات. حاول مرة أخرى.');
        @error_log('[settings_og] ' . $e->getMessage());
    }
}

// Load values
$og_enabled       = settings_get('og.enabled', '1') === '1';
$og_mode          = settings_get('og.mode', 'dynamic');
$og_engine        = settings_get('og.engine', 'auto');
$og_arabic_mode   = settings_get('og.arabic_mode', 'auto');
$og_default_image = settings_get('og.default_image', 'assets/images/og-default.png');
$og_template_image= settings_get('og.template_image', '');
$og_logo_image    = settings_get('og.logo_image', '');

$og_bg_color      = settings_get('og.bg_color', '#F5F5F5');
$og_text_color    = settings_get('og.text_color', '#141414');
$og_muted_color   = settings_get('og.muted_color', '#4B5563');
$og_accent_color  = settings_get('og.accent_color', '#111827');

$og_site_name     = settings_get('og.site_name', '');
$og_tagline       = settings_get('og.tagline', '');

$previewTitle = __('t_1cbd0d2d9a', 'مثال عنوان خبر لاختبار صورة المشاركة');
$base = function_exists('gdy_base_url') ? rtrim((string)gdy_base_url(), '/') : '';
$previewUrl = ($base ? $base : '') . '/og.php?title=' . rawurlencode($previewTitle);
?>

<div class="row">
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

        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-1"><?= h(__('t_9f44d9ef4b', 'إعدادات صورة المشاركة (OG Image)')) ?></h5>
            <div class="text-muted small"><?= h(__('t_7df69c7d85', 'تتحكم في الصورة الافتراضية التي تظهر عند مشاركة روابط الموقع على واتساب/تويتر/فيسبوك وغيرها.')) ?></div>
          </div>
          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= h($previewUrl) ?>">
            <?= h(__('t_2b5c257af1', 'معاينة')) ?>
          </a>
        </div>

        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="og_enabled" name="og_enabled" <?= $og_enabled ? 'checked' : '' ?>>
          <label class="form-check-label" for="og_enabled"><?= h(__('t_3aa3309f64', 'تفعيل مولّد OG الديناميكي')) ?></label>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label"><?= h(__('t_67d67e57b0', 'الوضع')) ?></label>
            <select class="form-select" name="og_mode">
              <option value="dynamic" <?= $og_mode==='dynamic'?'selected':'' ?>><?= h(__('t_12df5d6c0a', 'ديناميكي (og.php)')) ?></option>
              <option value="static"  <?= $og_mode==='static'?'selected':'' ?>><?= h(__('t_5ad56d1a68', 'ثابت (صورة محددة)')) ?></option>
            </select>
            <div class="form-text"><?= h(__('t_4c104db2fd', 'الديناميكي يولّد صورة تلقائيًا حسب عنوان الصفحة. الثابت يستخدم صورة واحدة دائمًا.')) ?></div>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label"><?= h(__('t_1b330b4d71', 'محرك التوليد')) ?></label>
            <select class="form-select" name="og_engine">
              <option value="auto"    <?= $og_engine==='auto'?'selected':'' ?>><?= h(__('t_8b930a5d0f', 'تلقائي (Imagick ثم GD)')) ?></option>
              <option value="imagick" <?= $og_engine==='imagick'?'selected':'' ?>><?= h(__('t_2c9a9f3d10', 'Imagick (الأفضل للعربي إذا Pango متوفر)')) ?></option>
              <option value="gd"      <?= $og_engine==='gd'?'selected':'' ?>><?= h(__('t_0c3a9aa4f1', 'GD (متوافق دائمًا)')) ?></option>
            </select>
            <div class="form-text">
              <?php
                $hasImagick = extension_loaded('imagick') && class_exists('Imagick');
                $hasPango = false;
                if ($hasImagick) {
                  try {
                    $fmts = Imagick::queryFormats();
                    foreach ($fmts as $f) {
                      if (strtoupper((string)$f) === 'PANGO') { $hasPango = true; break; }
                    }
                  } catch (Throwable $e) { $hasPango = false; }
                }
              ?>
              <?= h(__('t_5f8f6c2dd3', 'الحالة:')) ?>
              <?= $hasImagick ? 'Imagick ✅' : 'Imagick ❌' ?>
              •
              <?= $hasPango ? 'Pango ✅' : 'Pango ❌' ?>
            </div>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label"><?= h(__('t_4f7c7cc2d1', 'التعامل مع العناوين العربية')) ?></label>
            <select class="form-select" name="og_arabic_mode">
              <option value="auto"   <?= $og_arabic_mode==='auto'?'selected':'' ?>><?= h(__('t_8b930a5d0f', 'تلقائي (الأفضل)')) ?></option>
              <option value="shape"  <?= $og_arabic_mode==='shape'?'selected':'' ?>><?= h(__('t_9c8c0c2d93', 'تشكيل عربي عبر GD (قد يعتمد على الخط)')) ?></option>
              <option value="static" <?= $og_arabic_mode==='static'?'selected':'' ?>><?= h(__('t_9d2b5d4c2f', 'استخدم الصورة الثابتة للعربي')) ?></option>
            </select>
            <div class="form-text"><?= h(__('t_2fbbd66f2c', 'بعض الاستضافات لا تدعم تشكيل العربية في صور GD بشكل ممتاز؛ هذا الخيار يعطيك تحكم.')) ?></div>
          </div>
        </div>

        <hr class="my-3">

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label"><?= h(__('t_69b1dc21f6', 'لون الخلفية')) ?></label>
            <input class="form-control" name="og_bg_color" value="<?= h($og_bg_color) ?>" placeholder="#F5F5F5">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label"><?= h(__('t_85d77e9b85', 'لون النص')) ?></label>
            <input class="form-control" name="og_text_color" value="<?= h($og_text_color) ?>" placeholder="#141414">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label"><?= h(__('t_05c3d7f9b3', 'لون ثانوي (Tagline)')) ?></label>
            <input class="form-control" name="og_muted_color" value="<?= h($og_muted_color) ?>" placeholder="#4B5563">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label"><?= h(__('t_0824132c2b', 'لون Accent (شريط/تفصيل)')) ?></label>
            <input class="form-control" name="og_accent_color" value="<?= h($og_accent_color) ?>" placeholder="#111827">
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label"><?= h(__('t_88822c6fcb', 'صورة خلفية قالب (اختياري)')) ?></label>
            <input class="form-control" name="og_template_image" value="<?= h($og_template_image) ?>" placeholder="assets/images/og-bg.png">
            <div class="form-text"><?= h(__('t_6f6d8d8d25', 'مسار محلي داخل الموقع (لا نحمّل صور خارجية لأسباب أمنية).')) ?></div>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label"><?= h(__('t_3ac646c7b2', 'Logo (اختياري)')) ?></label>
            <input class="form-control" name="og_logo_image" value="<?= h($og_logo_image) ?>" placeholder="assets/images/logo.png">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label"><?= h(__('t_7b418dd5ed', 'الصورة الثابتة الافتراضية')) ?></label>
          <input class="form-control" name="og_default_image" value="<?= h($og_default_image) ?>" placeholder="assets/images/og-default.png">
          <div class="form-text"><?= h(__('t_7f79204b9b', 'تستخدم كبديل إذا تعذر التوليد أو في وضع "ثابت".')) ?></div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label"><?= h(__('t_16967b1b18', 'اسم الموقع (اختياري)')) ?></label>
            <input class="form-control" name="og_site_name" value="<?= h($og_site_name) ?>">
            <div class="form-text"><?= h(__('t_8c0b4e2f26', 'إن تركته فارغًا سيستخدم site_name من الإعدادات العامة.')) ?></div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label"><?= h(__('t_04de2c3d96', 'وصف/شعار قصير (اختياري)')) ?></label>
            <input class="form-control" name="og_tagline" value="<?= h($og_tagline) ?>">
            <div class="form-text"><?= h(__('t_7e3ea92f5a', 'إن تركته فارغًا سيستخدم site_tagline إن وجد.')) ?></div>
          </div>
        </div>

        <button class="btn btn-primary"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
