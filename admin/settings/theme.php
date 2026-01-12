<?php
require_once __DIR__ . '/_settings_guard.php';
require_once __DIR__ . '/_settings_meta.php';
settings_apply_context();
require_once __DIR__ . '/../layout/app_start.php';

// Upload helper (header background image)
require_once __DIR__ . '/../../includes/utilities/upload.php';

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('verify_csrf')) { verify_csrf(); }

    try {
        // Admin panel theme preset
        $adminTheme = strtolower(trim((string)($_POST['admin_theme'] ?? 'blue')));
        if (!in_array($adminTheme, ['blue','red','green','brown'], true)) {
            $adminTheme = 'blue';
        }

        // -----------------------------
        // Front-end (site) theme presets
        // -----------------------------
        $frontPreset = strtolower(trim((string)($_POST['front_preset'] ?? 'custom')));
        if ($frontPreset === 'aljazeera') { $frontPreset = 'default'; }

        $frontPresetMap = [
            // Readable, moderate palette (Primary used heavily in UI)
            'blue'  => ['primary' => '#0ea5e9', 'accent' => '#22c55e'],
            'red'   => ['primary' => '#ef4444', 'accent' => '#0ea5e9'],
            'green' => ['primary' => '#16a34a', 'accent' => '#0ea5e9'],
            'brown' => ['primary' => '#a16207', 'accent' => '#0ea5e9'],
            'default' => ['primary' => '#111111', 'accent' => '#111111'],
        ];

        // Reset to default if requested
        if (!empty($_POST['reset_front_defaults'])) {
            $frontPreset = 'default';
            $_POST['front_theme']    = 'default';
            $_POST['primary_color']  = '#111111';
            $_POST['primary_dark']   = '#000000';
            $_POST['accent_color']   = '#111111';
        }

        // Base theme settings
        $pairs = [
            'admin.theme'        => $adminTheme,
            'frontend_theme'     => trim((string)($_POST['front_theme'] ?? 'default')),
            'theme.front'        => trim((string)($_POST['front_theme'] ?? 'default')),
            'theme.primary'      => trim((string)($_POST['primary_color'] ?? '#111111')),
            'theme.primary_dark' => trim((string)($_POST['primary_dark'] ?? '')),
            'theme.accent'       => trim((string)($_POST['accent_color'] ?? '#111111')),
            'theme.header_style' => trim((string)($_POST['header_style'] ?? 'dark')),
            'theme.footer_style' => trim((string)($_POST['footer_style'] ?? 'dark')),
            'theme.container'    => trim((string)($_POST['container_width'] ?? 'boxed')),

            'blocks.trending'     => !empty($_POST['block_trending']) ? '1' : '0',
            'blocks.editors_pick' => !empty($_POST['block_editors_pick']) ? '1' : '0',
            'blocks.videos'       => !empty($_POST['block_videos']) ? '1' : '0',
            'blocks.newsletter'   => !empty($_POST['block_newsletter']) ? '1' : '0',

            // Header background (new)
            'theme.header_bg_enabled' => !empty($_POST['header_bg_enabled']) ? '1' : '0',
            'theme.header_bg_source'  => in_array((string)($_POST['header_bg_source'] ?? 'upload'), ['upload','url'], true)
                                        ? (string)($_POST['header_bg_source'] ?? 'upload') : 'upload',
            'theme.header_bg_url'     => trim((string)($_POST['header_bg_url'] ?? '')),
        ];

        // Apply preset (overrides pickers) if selected
        if (isset($frontPresetMap[$frontPreset])) {
            $pairs['theme.primary'] = $frontPresetMap[$frontPreset]['primary'];
            $pairs['theme.accent']  = $frontPresetMap[$frontPreset]['accent'];
        }

        // Normalize theme.front values for the current available theme CSS files
        // (Files live under: /assets/css/themes/theme-{name}.css)
        $pairs['theme.front'] = strtolower(trim((string)$pairs['theme.front']));
        if (!in_array($pairs['theme.front'], ['default','light','beige','red','blue','green','brown'], true)) {
            // Keep as-is if user has a custom theme, but sanitize
            $pairs['theme.front'] = preg_replace('/[^a-z0-9_-]/', '', (string)$pairs['theme.front']);
            if ($pairs['theme.front'] === '') {
                $pairs['theme.front'] = 'default';
            }
        }

        // Validate colors
        foreach (['theme.primary','theme.primary_dark','theme.accent'] as $ck) {
            $val = (string)($pairs[$ck] ?? '');
            if ($val === '') continue;
            if (!preg_match('/^#[0-9a-f]{6}$/i', $val)) {
                // Ignore invalid input
                unset($pairs[$ck]);
            }
        }

        // If primary_dark is missing, compute a darker shade
        if (empty($pairs['theme.primary_dark']) && !empty($pairs['theme.primary'])) {
            $hex = ltrim((string)$pairs['theme.primary'], '#');
            if (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
                $r = max(0, hexdec(substr($hex, 0, 2)) - 40);
                $g = max(0, hexdec(substr($hex, 2, 2)) - 40);
                $b = max(0, hexdec(substr($hex, 4, 2)) - 40);
                $pairs['theme.primary_dark'] = sprintf('#%02x%02x%02x', $r, $g, $b);
            }
        }

        // Remove stored image if requested
        if (!empty($_POST['header_bg_remove'])) {
            $pairs['theme.header_bg_image'] = '';
        }

        // Upload new image (takes precedence)
        $uploaded = null;
        if (!empty($_FILES['header_bg_file']['name'] ?? '')) {
            $uploaded = \Godyar\Util\Upload::image('header_bg_file', '/uploads/settings', 8);
            if ($uploaded) {
                // Ensure we store with leading slash for consistent URL building
                $pairs['theme.header_bg_image'] = ($uploaded[0] === '/') ? $uploaded : ('/' . ltrim($uploaded, '/'));
                $pairs['theme.header_bg_source'] = 'upload';
            }
        }

        // If source is URL and provided, clear stored image (optional, for clarity)
        if (($pairs['theme.header_bg_source'] ?? '') === 'url' && ($pairs['theme.header_bg_url'] ?? '') !== '') {
            if (!$uploaded) {
                $pairs['theme.header_bg_image'] = '';
            }
        }

        settings_save($pairs);
        $notice = __('t_36a9b31463', 'تم حفظ إعدادات المظهر بنجاح.');
    } catch (Throwable $e) {
        $error = __('t_4fa410044f', 'حدث خطأ أثناء الحفظ.');
        @error_log('[settings_theme] ' . $e->getMessage());
    }
}

$front_theme     = settings_get('frontend_theme', settings_get('theme.front', 'default'));
$admin_theme     = strtolower(trim((string)settings_get('admin.theme', 'blue')));
if (!in_array($admin_theme, ['blue','red','green','brown'], true)) {
    $admin_theme = 'blue';
}
$primary_color   = settings_get('theme.primary', '#111111');
$primary_dark    = settings_get('theme.primary_dark', '');
$accent_color    = settings_get('theme.accent', '#111111');
$header_style    = settings_get('theme.header_style', 'dark');
$footer_style    = settings_get('theme.footer_style', 'dark');
$container_width = settings_get('theme.container', 'boxed');

$block_trending     = settings_get('blocks.trending', '1') === '1';
$block_editors_pick = settings_get('blocks.editors_pick', '1') === '1';
$block_videos       = settings_get('blocks.videos', '1') === '1';
$block_newsletter   = settings_get('blocks.newsletter', '1') === '1';

$header_bg_enabled = settings_get('theme.header_bg_enabled', '0') === '1';
$header_bg_source  = (string)settings_get('theme.header_bg_source', 'upload');
$header_bg_url     = (string)settings_get('theme.header_bg_url', '');
$header_bg_image   = (string)settings_get('theme.header_bg_image', '');

// Guess current preset for UI
$front_preset = 'custom';
$presetGuess = [
    'default' => ['primary' => '#111111', 'accent' => '#111111'],
    'blue'  => ['primary' => '#0ea5e9', 'accent' => '#22c55e'],
    'red'   => ['primary' => '#ef4444', 'accent' => '#0ea5e9'],
    'green' => ['primary' => '#16a34a', 'accent' => '#0ea5e9'],
    'brown' => ['primary' => '#a16207', 'accent' => '#0ea5e9'],
];
foreach ($presetGuess as $k => $v) {
    if (strtolower((string)$primary_color) === strtolower($v['primary']) && strtolower((string)$accent_color) === strtolower($v['accent'])) {
        $front_preset = $k;
        break;
    }
}

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

        <form method="post" enctype="multipart/form-data">
          <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <?php endif; ?>

          <h6 class="mb-2"><?= h(__('t_admin_theme_title', 'مظهر لوحة التحكم')) ?></h6>
          <div class="alert alert-info py-2" style="font-size:.92rem;">
            <ul class="mb-0" style="padding-inline-start:1.1rem;">
              <li><?= h(__('t_admin_theme_tip1', 'الاعتدال في استخدام الألوان: تجنب الفوضى البصرية.')) ?></li>
              <li><?= h(__('t_admin_theme_tip2', 'ألوان متناسقة: استخدم ألوانًا متجانسة على مستوى اللوحة.')) ?></li>
              <li><?= h(__('t_admin_theme_tip3', 'سهولة القراءة: تأكد أن النص واضح على الخلفيات الملونة.')) ?></li>
              <li><?= h(__('t_admin_theme_tip4', 'تجربة المستخدم: اجعل اللون يخدم المحتوى ولا يشتّت الانتباه.')) ?></li>
            </ul>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_admin_theme_label', 'ستايل لوحة التحكم')) ?></label>
              <select class="form-select" name="admin_theme" id="admin_theme">
                <option value="blue"  <?= $admin_theme === 'blue'  ? 'selected' : '' ?>><?= h(__('t_admin_theme_blue', 'أزرق - Blue')) ?></option>
                <option value="red"   <?= $admin_theme === 'red'   ? 'selected' : '' ?>><?= h(__('t_admin_theme_red', 'أحمر - Red')) ?></option>
                <option value="green" <?= $admin_theme === 'green' ? 'selected' : '' ?>><?= h(__('t_admin_theme_green', 'أخضر - Green')) ?></option>
                <option value="brown" <?= $admin_theme === 'brown' ? 'selected' : '' ?>><?= h(__('t_admin_theme_brown', 'بني - Brown')) ?></option>
              </select>
              <div class="form-text"><?= h(__('t_admin_theme_note', 'يؤثر على لون الهيدر وأزرار (Primary) داخل لوحة التحكم فقط.')) ?></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_admin_theme_preview', 'معاينة')) ?></label>
              <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge bg-primary" style="border-radius:999px;">Primary</span>
                <span class="badge" style="background:var(--gdy-accent);color:var(--gdy-accent-on);border-radius:999px;">Accent</span>
                <span class="badge" style="background:var(--gdy-accent-2);color:var(--gdy-accent-on);border-radius:999px;">Accent 2</span>
              </div>
              <div class="form-text" id="admin_theme_preview_note"><?= h(__('t_admin_theme_preview_note', 'المعاينة فورية في هذه الصفحة. بعد الحفظ سيتم تطبيقها على جميع صفحات لوحة التحكم.')) ?></div>
            </div>
          </div>

          <hr>

          <h6 class="mb-2"><?= h(__('t_front_theme_title', 'مظهر الواجهة')) ?></h6>
          <div class="alert alert-info py-2" style="font-size:.92rem;">
            <?= h(__('t_front_theme_note', 'هذه الإعدادات تؤثر على قالب الواجهة (الموقع) وليس لوحة التحكم.')) ?>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_front_theme_layout', 'ستايل الواجهة')) ?></label>
              <select class="form-select" name="front_theme" id="front_theme">
                <option value="default" <?= $front_theme === 'default' ? 'selected' : '' ?>>Default</option>
                <option value="light" <?= $front_theme === 'light' ? 'selected' : '' ?>>light</option>
                <option value="beige" <?= $front_theme === 'beige' ? 'selected' : '' ?>>beige</option>
                <option value="red" <?= $front_theme === 'red' ? 'selected' : '' ?>><?= h(__('t_admin_theme_red', 'أحمر - Red')) ?></option>
                <option value="blue" <?= $front_theme === 'blue' ? 'selected' : '' ?>><?= h(__('t_admin_theme_blue', 'أزرق - Blue')) ?></option>
                <option value="green" <?= $front_theme === 'green' ? 'selected' : '' ?>><?= h(__('t_admin_theme_green', 'أخضر - Green')) ?></option>
                <option value="brown" <?= $front_theme === 'brown' ? 'selected' : '' ?>><?= h(__('t_admin_theme_brown', 'بني - Brown')) ?></option>
              </select>
              <div class="form-text"><?= h(__('t_front_theme_files', 'يتم تحميل ملف CSS من: /assets/css/themes/theme-{name}.css (مثل: theme-red.css / theme-blue.css ...).')) ?></div>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_bc7df01a60', 'عرض المحتوى')) ?></label>
              <select class="form-select" name="container_width">
                <option value="boxed" <?= $container_width === 'boxed' ? 'selected' : '' ?>><?= h(__('t_1018afa3c4', 'مربع (Boxed)')) ?></option>
                <option value="full" <?= $container_width === 'full' ? 'selected' : '' ?>><?= h(__('t_acc87fcc65', 'كامل العرض')) ?></option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_front_color_preset', 'لون الهوية (Preset)')) ?></label>
              <select class="form-select" name="front_preset" id="front_preset">
                <option value="custom" <?= $front_preset === 'custom' ? 'selected' : '' ?>><?= h(__('t_preset_custom', 'مخصص (Custom)')) ?></option>
                <option value="default" <?= $front_preset === 'default' ? 'selected' : '' ?>>Default</option>
                <option value="blue"  <?= $front_preset === 'blue' ? 'selected' : '' ?>><?= h(__('t_admin_theme_blue', 'أزرق - Blue')) ?></option>
                <option value="red"   <?= $front_preset === 'red' ? 'selected' : '' ?>><?= h(__('t_admin_theme_red', 'أحمر - Red')) ?></option>
                <option value="green" <?= $front_preset === 'green' ? 'selected' : '' ?>><?= h(__('t_admin_theme_green', 'أخضر - Green')) ?></option>
                <option value="brown" <?= $front_preset === 'brown' ? 'selected' : '' ?>><?= h(__('t_admin_theme_brown', 'بني - Brown')) ?></option>
              </select>
              <div class="form-text"><?= h(__('t_front_color_preset_note', 'اختر Preset لتطبيق ألوان متناسقة فوراً، أو اختر Custom لاستخدام منتقي الألوان بالأسفل.')) ?></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_0d4de28c50', 'معاينة')) ?></label>
              <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge" id="front_preview_primary" style="background:<?= h($primary_color) ?>;border-radius:999px;">Primary</span>
                <span class="badge" id="front_preview_accent" style="background:<?= h($accent_color) ?>;border-radius:999px;">Accent</span>
              </div>
              <div class="form-text"><?= h(__('t_front_preview_note', 'بعد الحفظ افتح الموقع أو حدّث الصفحة (Ctrl+F5) لرؤية التغيير.')) ?></div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label"><?= h(__('t_a71da15dca', 'اللون الأساسي')) ?></label>
              <input type="color" class="form-control form-control-color" name="primary_color" id="primary_color" value="<?= h($primary_color) ?>">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label"><?= h(__('t_dac6ead9fa', 'لون التمييز')) ?></label>
              <input type="color" class="form-control form-control-color" name="accent_color" id="accent_color" value="<?= h($accent_color) ?>">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label"><?= h(__('t_primary_dark', 'اللون الداكن (اختياري)')) ?></label>
              <input type="color" class="form-control form-control-color" name="primary_dark" id="primary_dark" value="<?= h($primary_dark ?: $primary_color) ?>">
              <div class="form-text"><?= h(__('t_primary_dark_note', 'إن تركته مناسباً سيُحسب تلقائياً.')) ?></div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_8851fd9015', 'ستايل الهيدر')) ?></label>
              <select class="form-select" name="header_style">
                <option value="dark" <?= $header_style === 'dark' ? 'selected' : '' ?>><?= h(__('t_31546428bd', 'داكن')) ?></option>
                <option value="light" <?= $header_style === 'light' ? 'selected' : '' ?>><?= h(__('t_f9d987e7c7', 'فاتح')) ?></option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_018b082610', 'ستايل الفوتر')) ?></label>
              <select class="form-select" name="footer_style">
                <option value="dark" <?= $footer_style === 'dark' ? 'selected' : '' ?>><?= h(__('t_31546428bd', 'داكن')) ?></option>
                <option value="light" <?= $footer_style === 'light' ? 'selected' : '' ?>><?= h(__('t_f9d987e7c7', 'فاتح')) ?></option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <h6 class="mb-2"><?= h(__('t_hdrbg_title', 'خلفية الهيدر')) ?></h6>
              <div class="alert alert-info py-2" style="font-size:.92rem;">
                <?= h(__('t_hdrbg_help', 'يمكنك اختيار صورة لخلفية الهيدر (رفع من الكمبيوتر أو رابط مباشر).')) ?>
              </div>
            </div>

            <div class="col-md-4 mb-3">
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="header_bg_enabled" id="header_bg_enabled" <?= $header_bg_enabled ? 'checked' : '' ?>>
                <label class="form-check-label" for="header_bg_enabled"><?= h(__('t_hdrbg_enable', 'تفعيل')) ?></label>
              </div>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label"><?= h(__('t_hdrbg_source', 'المصدر')) ?></label>
              <select class="form-select" name="header_bg_source">
                <option value="upload" <?= $header_bg_source === 'upload' ? 'selected' : '' ?>><?= h(__('t_hdrbg_upload', 'رفع من الكمبيوتر')) ?></option>
                <option value="url" <?= $header_bg_source === 'url' ? 'selected' : '' ?>><?= h(__('t_hdrbg_url', 'رابط مباشر')) ?></option>
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label"><?= h(__('t_hdrbg_url_label', 'رابط الصورة')) ?></label>
              <input class="form-control" name="header_bg_url" value="<?= h($header_bg_url) ?>" placeholder="https://...">
              <div class="form-text"><?= h(__('t_hdrbg_url_note', 'يُستخدم عند اختيار (رابط مباشر).')) ?></div>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_hdrbg_upload_label', 'رفع صورة')) ?></label>
              <input class="form-control" type="file" name="header_bg_file" accept="image/*">
              <div class="form-text"><?= h(__('t_hdrbg_upload_note', 'PNG/JPG/WebP — يُفضّل أبعاد كبيرة لتظهر بجودة.')) ?></div>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label"><?= h(__('t_hdrbg_current', 'الصورة الحالية')) ?></label>
              <?php
                $base = '';
                if (function_exists('base_url')) { $base = rtrim((string)base_url(), '/'); }
                elseif (defined('BASE_URL')) { $base = rtrim((string)BASE_URL, '/'); }
                $preview = '';
                if ($header_bg_image !== '') {
                    $preview = preg_match('~^https?://~i', $header_bg_image)
                        ? $header_bg_image
                        : ($base . '/' . ltrim($header_bg_image, '/'));
                } elseif ($header_bg_url !== '') {
                    $preview = $header_bg_url;
                }
              ?>
              <?php if ($preview): ?>
                <div class="d-flex gap-3 align-items-center flex-wrap">
                  <img src="<?= h($preview) ?>" alt="header-bg" style="width:160px;height:64px;object-fit:cover;border-radius:10px;border:1px solid rgba(0,0,0,.1);">
                  <div>
                    <div class="form-check mb-2">
                      <input class="form-check-input" type="checkbox" name="header_bg_remove" id="header_bg_remove">
                      <label class="form-check-label" for="header_bg_remove"><?= h(__('t_hdrbg_remove', 'إزالة الصورة المحفوظة')) ?></label>
                    </div>
                    <div class="text-muted" style="font-size:.85rem;direction:ltr;max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                      <?= h($preview) ?>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="text-muted"><?= h(__('t_hdrbg_none', 'لا توجد صورة حالياً.')) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <hr>

          <h6 class="mb-2"><?= h(__('t_0875a474ec', 'بلوكات الواجهة')) ?></h6>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="block_trending" id="block_trending" <?= $block_trending ? 'checked' : '' ?>>
            <label class="form-check-label" for="block_trending"><?= h(__('t_73730b440c', 'إظهار الأخبار المتداولة')) ?></label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="block_editors_pick" id="block_editors_pick" <?= $block_editors_pick ? 'checked' : '' ?>>
            <label class="form-check-label" for="block_editors_pick"><?= h(__('t_d9513dffd6', 'إظهار اختيار المحرر')) ?></label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="block_videos" id="block_videos" <?= $block_videos ? 'checked' : '' ?>>
            <label class="form-check-label" for="block_videos"><?= h(__('t_2c1c71442e', 'إظهار بلوك الفيديوهات')) ?></label>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="block_newsletter" id="block_newsletter" <?= $block_newsletter ? 'checked' : '' ?>>
            <label class="form-check-label" for="block_newsletter"><?= h(__('t_8236301d93', 'إظهار النشرة البريدية')) ?></label>
          </div>

          <button class="btn btn-outline-secondary me-2" name="reset_front_defaults" value="1" data-confirm='سيتم استعادة مظهر الواجهة إلى الوضع الافتراضي (الجزيرة). هل تريد المتابعة؟'><?= h(__('t_reset_front_defaults', 'استعادة الافتراضي')) ?></button>
          <button class="btn btn-primary"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
        </form>

        <script>
          (function(){
            // Live preview for Admin theme (no save required)
            var sel = document.getElementById('admin_theme');
            if (!sel) return;
            var root = document.documentElement;
            var saved = sel.value;

            function applyTheme(val){
              if (!val) return;
              root.setAttribute('data-admin-theme', val);
            }

            // Apply initial value (in case header sets something else)
            applyTheme(saved);

            sel.addEventListener('change', function(){
              applyTheme(sel.value);
            });

            // Live preview for Front-end (colors + preset)
            var fp = document.getElementById('front_preset');
            var pc = document.getElementById('primary_color');
            var ac = document.getElementById('accent_color');
            var pd = document.getElementById('primary_dark');
            var b1 = document.getElementById('front_preview_primary');
            var b2 = document.getElementById('front_preview_accent');

            var presets = {
              default: {primary:'#111111', accent:'#111111'},
              blue:  {primary:'#0ea5e9', accent:'#22c55e'},
              red:   {primary:'#ef4444', accent:'#0ea5e9'},
              green: {primary:'#16a34a', accent:'#0ea5e9'},
              brown: {primary:'#a16207', accent:'#0ea5e9'}
            };

            function updateBadges(){
              if (b1 && pc) b1.style.background = pc.value;
              if (b2 && ac) b2.style.background = ac.value;
            }

            function computeDark(hex){
              try {
                if (!hex || hex.charAt(0) !== '#' || hex.length !== 7) return '';
                var r = Math.max(0, parseInt(hex.substr(1,2), 16) - 40);
                var g = Math.max(0, parseInt(hex.substr(3,2), 16) - 40);
                var b = Math.max(0, parseInt(hex.substr(5,2), 16) - 40);
                return '#' + [r,g,b].map(function(n){ return n.toString(16).padStart(2,'0'); }).join('');
              } catch(e){ return ''; }
            }

            if (fp) {
              fp.addEventListener('change', function(){
                var key = (fp.value || '').toLowerCase();
                if (key in presets && pc && ac) {
                  pc.value = presets[key].primary;
                  ac.value = presets[key].accent;
                  if (pd) pd.value = computeDark(pc.value) || pc.value;
                  updateBadges();
                }
              });
            }

            // Keep "Preset" in sync with "Front theme" (unless user chose Custom)
            var ft = document.getElementById('front_theme');
            var themeToPreset = { 'default':'default', 'red':'red', 'blue':'blue', 'green':'green', 'brown':'brown' };

            function dispatchChange(el){
              try { el.dispatchEvent(new Event('change')); }
              catch(e){
                var evt = document.createEvent('HTMLEvents');
                evt.initEvent('change', true, false);
                el.dispatchEvent(evt);
              }
            }

            function syncPresetWithTheme(){
              if (!ft || !fp) return;
              var t = (ft.value || '').toLowerCase();
              var want = themeToPreset[t] || '';
              if (!want) return;
              if ((fp.value || '').toLowerCase() === 'custom') return; // respect custom
              if ((fp.value || '').toLowerCase() !== want) {
                fp.value = want;
                dispatchChange(fp);
              }
            }

            if (ft && fp) {
              ft.addEventListener('change', syncPresetWithTheme);
              // Initial sync on load
              syncPresetWithTheme();
            }

            function onManualChange(){
              // If user edits colors manually, set preset to custom
              if (fp) fp.value = 'custom';
              if (pd && pc) {
                // Keep primary_dark reasonable unless user changed it
                if (!pd.dataset.userChanged) {
                  pd.value = computeDark(pc.value) || pc.value;
                }
              }
              updateBadges();
            }

            if (pc) pc.addEventListener('input', onManualChange);
            if (ac) ac.addEventListener('input', onManualChange);
            if (pd) {
              pd.addEventListener('input', function(){ pd.dataset.userChanged = '1'; updateBadges(); });
            }

            updateBadges();
          })();
        </script>
      </div>
    </div>
  </div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
