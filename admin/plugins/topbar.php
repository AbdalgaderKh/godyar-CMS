<?php

require_once __DIR__ . '/../_admin_guard.php';
// admin/plugins/topbar.php
// إعدادات إضافة Top Bar

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';

use Godyar\Auth;

Auth::requirePermission('manage_plugins'); // نفس صلاحية إدارة الإضافات

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pluginDir  = __DIR__ . '/../../plugins/TopBar';
$configFile = $pluginDir . '/config.json';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

function topbar_load_config(string $configFile): array
{
    $defaults = [
        'bar_enabled'   => true,
        'message'       => __('t_bd24e08a3b', 'مرحبًا بك في موقعنا! 👋'),
        'bg_color'      => '#111827',
        'text_color'    => '#ffffff',
        'position'      => 'fixed',
        'closable'      => true,
        'show_on_paths' => '*',
    ];

    if (!is_file($configFile)) {
        return $defaults;
    }

    $json = @file_get_contents($configFile);
    if (!is_string($json) || $json === '') {
        return $defaults;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $defaults;
    }

    return array_merge($defaults, $data);
}

function topbar_save_config(string $configFile, array $cfg): bool
{
    $json = json_encode(
        $cfg,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
    if ($json === false) {
        return false;
    }

    if (!is_dir(dirname($configFile))) {
        @mkdir(dirname($configFile), 0775, true);
    }

    return @file_put_contents($configFile, $json) !== false;
}

$cfg = topbar_load_config($configFile);

// معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!function_exists('verify_csrf') || !verify_csrf()) {
            throw new Exception(__('t_fbbc004136', 'رمز الحماية (CSRF) غير صالح، يرجى إعادة المحاولة.'));
        }

        $barEnabled = isset($_POST['bar_enabled']);
        $position   = $_POST['position'] ?? 'fixed';
        if (!in_array($position, ['fixed','static'], true)) {
            $position = 'fixed';
        }

        $message = (string)($_POST['message'] ?? '');
        if ($message === '') {
            $message = __('t_bd24e08a3b', 'مرحبًا بك في موقعنا! 👋');
        }

        $bgColor   = trim((string)($_POST['bg_color'] ?? '#111827'));
        $textColor = trim((string)($_POST['text_color'] ?? '#ffffff'));

        $closable = isset($_POST['closable']);

        $showOnPaths = trim((string)($_POST['show_on_paths'] ?? '*'));
        if ($showOnPaths === '') {
            $showOnPaths = '*';
        }

        $cfg = [
            'bar_enabled'   => $barEnabled,
            'message'       => $message,
            'bg_color'      => $bgColor,
            'text_color'    => $textColor,
            'position'      => $position,
            'closable'      => $closable,
            'show_on_paths' => $showOnPaths,
        ];

        if (!topbar_save_config($configFile, $cfg)) {
            throw new Exception(__('t_32b4205165', 'تعذر حفظ إعدادات الإضافة (تحقق من أذونات المجلد plugins/TopBar).'));
        }

        $_SESSION['flash_success'] = __('t_fe258b98f8', 'تم حفظ إعدادات الشريط العلوي بنجاح.');
        header('Location: topbar.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
        header('Location: topbar.php');
        exit;
    }
}

?>
<div class="admin-content">
  <div class="admin-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1><?= h(__('t_22959e596d', 'إعدادات الشريط العلوي (Top Bar)')) ?></h1>
      <p class="text-muted mb-0">
        <?= h(__('t_dad4b96645', 'من هنا يمكنك التحكم في الشريط العلوي الذي يظهر أعلى الموقع للزوار.')) ?>
      </p>
    </div>
    <div>
      <a href="index.php" class="btn btn-secondary btn-sm">
        <?= h(__('t_96f6dd0bdb', 'رجوع إلى قائمة الإضافات')) ?>
      </a>
    </div>
  </div>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success mt-3"><?= h($flashSuccess) ?></div>
  <?php endif; ?>

  <?php if ($flashError): ?>
    <div class="alert alert-danger mt-3"><?= h($flashError) ?></div>
  <?php endif; ?>

  <div class="card mt-3 mb-4">
    <div class="card-header">
      <h2 class="card-title mb-0"><?= h(__('t_46ce4c91ac', 'الإعدادات العامة')) ?></h2>
    </div>
    <div class="card-body">
      <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <?php if (function_exists('csrf_field')) { csrf_field(); } ?>

        <div class="mb-3 form-check">
          <input
            type="checkbox"
            class="form-check-input"
            id="bar_enabled"
            name="bar_enabled"
            <?= !empty($cfg['bar_enabled']) ? 'checked' : '' ?>
          >
          <label class="form-check-label" for="bar_enabled">
            <?= h(__('t_3244c7d67f', 'تفعيل الشريط العلوي')) ?>
          </label>
        </div>

        <div class="mb-3">
          <label for="message" class="form-label"><?= h(__('t_6a3c8bac29', 'نص الرسالة')) ?></label>
          <textarea
            class="form-control"
            id="message"
            name="message"
            rows="3"
          ><?= h((string)$cfg['message']) ?></textarea>
          <div class="form-text">
            <?= h(__('t_e07ee0dbfd', 'يمكنك استخدام HTML بسيط (مثل &lt;strong&gt; أو &lt;a&gt;).')) ?>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="bg_color" class="form-label"><?= h(__('t_b51bfb65a6', 'لون الخلفية')) ?></label>
            <input
              type="text"
              class="form-control"
              id="bg_color"
              name="bg_color"
              value="<?= h((string)$cfg['bg_color']) ?>"
            >
            <div class="form-text"><?= h(__('t_2bff231ee6', 'مثال: #111827')) ?></div>
          </div>

          <div class="col-md-4 mb-3">
            <label for="text_color" class="form-label"><?= h(__('t_b11ae536ee', 'لون النص')) ?></label>
            <input
              type="text"
              class="form-control"
              id="text_color"
              name="text_color"
              value="<?= h((string)$cfg['text_color']) ?>"
            >
            <div class="form-text"><?= h(__('t_3051a8f964', 'مثال: #ffffff')) ?></div>
          </div>

          <div class="col-md-4 mb-3">
            <label for="position" class="form-label"><?= h(__('t_b799144cc2', 'الموضع')) ?></label>
            <select class="form-select" id="position" name="position">
              <option value="fixed" <?= ($cfg['position'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>
                <?= h(__('t_b4e12701fe', 'ثابت أعلى الصفحة (يبقى أثناء التمرير)')) ?>
              </option>
              <option value="static" <?= ($cfg['position'] ?? 'fixed') === 'static' ? 'selected' : '' ?>>
                <?= h(__('t_b6bdca3e6f', 'عادي (ضمن محتوى الصفحة)')) ?>
              </option>
            </select>
          </div>
        </div>

        <div class="mb-3 form-check">
          <input
            type="checkbox"
            class="form-check-input"
            id="closable"
            name="closable"
            <?= !empty($cfg['closable']) ? 'checked' : '' ?>
          >
          <label class="form-check-label" for="closable">
            <?= h(__('t_02a32c7ac3', 'السماح للزائر بإغلاق الشريط (يتم تذكر الإغلاق في المتصفح)')) ?>
          </label>
        </div>

        <div class="mb-3">
          <label for="show_on_paths" class="form-label"><?= h(__('t_c2950b8de4', 'إظهار الشريط في المسارات')) ?></label>
          <input
            type="text"
            class="form-control"
            id="show_on_paths"
            name="show_on_paths"
            value="<?= h((string)$cfg['show_on_paths']) ?>"
          >
          <div class="form-text">
            <strong>*</strong> <?= h(__('t_d514c8253b', '= كل الصفحات. أو أدخل قائمة مسارات مفصولة بفواصل،
            مثال:')) ?> <code>/godyar/, /godyar/index.php?page=home</code>
          </div>
        </div>

        <button type="submit" class="btn btn-primary"><?= h(__('t_32be3bade9', 'حفظ الإعدادات')) ?></button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
