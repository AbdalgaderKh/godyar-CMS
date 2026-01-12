<?php

require_once __DIR__ . '/../_admin_guard.php';
// admin/plugins/settings.php

require_once __DIR__ . '/../../includes/bootstrap.php';

$currentPage = 'plugins';
$pageTitle   = __('t_82f4198472', 'إعدادات الإضافة');

$slug = $_GET['slug'] ?? '';
$slug = preg_replace('~[^A-Za-z0-9_\-]~', '', $slug); // تنظيف بسيط

$pluginsDir = dirname(__DIR__, 2) . '/plugins';
$pluginDir  = $pluginsDir . '/' . $slug;

$error = null;
if ($slug === '') {
    $error = __('t_26ec6b26e2', 'لم يتم تحديد إضافة صحيحة.');
} elseif (!is_dir($pluginDir)) {
    $error = __('t_97c99cec40', 'مجلد الإضافة غير موجود: ') . $slug;
}

// مسار ملف الإعدادات داخل مجلد الإضافة
$settingsFile = $pluginDir . '/settings.json';

// قراءة الإعدادات الحالية
$settings = [];
if (!$error && is_file($settingsFile)) {
    $json = @file_get_contents($settingsFile);
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $settings = $decoded;
    }
}

// معالجة الحفظ
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['enabled_on_front']  = !empty($_POST['enabled_on_front']);
    $settings['items_limit']       = (int)($_POST['items_limit'] ?? 5);
    $settings['title_override_ar'] = trim($_POST['title_override_ar'] ?? '');

    @file_put_contents(
        $settingsFile,
        json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    header('Location: settings.php?slug=' . urlencode($slug) . '&saved=1');
    exit;
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<div class="admin-content">
  <div class="gdy-admin-page">
    <div class="container-xxl" style="max-width:1000px;">
  <div class="gdy-page-header d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 text-white mb-1">
        إعدادات الإضافة: <?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>
      </h1>
      <p class="text-muted mb-0"><?= h(__('t_3b8df8cd4f', 'تحكم بالإعدادات الخاصة بهذه الإضافة.')) ?></p>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger mb-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php elseif (!empty($_GET['saved'])): ?>
    <div class="alert alert-success mb-3"><?= h(__('t_71974f421e', 'تم حفظ الإعدادات بنجاح.')) ?></div>
  <?php endif; ?>

  <?php if (!$error): ?>
    <div class="card glass-card gdy-card mb-3">
      <div class="card-body">
        <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">


          <div class="mb-3">
            <label class="form-label"><?= h(__('t_43fdee9c45', 'تفعيل الإضافة في الواجهة')) ?></label>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="enabled_on_front"
                     id="enabled_on_front"
                     <?= !empty($settings['enabled_on_front']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="enabled_on_front"><?= h(__('t_4759637ebc', 'مفعّل')) ?></label>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_aa641933cd', 'عدد العناصر المعروضة')) ?></label>
            <input type="number" name="items_limit" class="form-control"
                   value="<?= (int)($settings['items_limit'] ?? 5) ?>" min="1" max="50">
          </div>

          <div class="mb-3">
            <label class="form-label"><?= h(__('t_5a217e1a0d', 'عنوان مخصص بالعربية (اختياري)')) ?></label>
            <input type="text" name="title_override_ar" class="form-control"
                   value="<?= htmlspecialchars($settings['title_override_ar'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <button type="submit" class="btn btn-primary"><?= h(__('t_32be3bade9', 'حفظ الإعدادات')) ?></button>
          <a href="index.php" class="btn btn-secondary"><?= h(__('t_59e91d21d2', 'عودة لقائمة الإضافات')) ?></a>
        </form>
      </div>
    </div>
  <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
