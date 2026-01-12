<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/lang.php';

$lang = function_exists('gdy_lang') ? (string)gdy_lang() : (string)($_SESSION['lang'] ?? 'ar');
$lang = strtolower($lang);
if (!in_array($lang, ['ar','en','fr'], true)) {
    $lang = 'ar';
}
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';

// -----------------------------------------------------------------------------
// Admin Theme (Accent preset)
// -----------------------------------------------------------------------------
// Saved in DB settings as: admin.theme = blue|red|green|brown
// Applied as an attribute on <html lang="ar" dir="rtl"> so CSS can switch variables safely.
// IMPORTANT: settings_get() is defined only inside admin/settings pages.
// So we provide a safe fallback query here so theme works everywhere.

if (!function_exists('gdy_admin_setting')) {
    function gdy_admin_setting(string $key, string $default = ''): string {
        // Prefer settings_get if available
        if (function_exists('settings_get')) {
            try { return (string)settings_get($key, $default); } catch (\Throwable $e) {}
        }

        // Fallback: query DB directly
        try {
            $pdo = null;
            if (class_exists('\Godyar\DB') && method_exists('\Godyar\DB', 'pdo')) {
                $pdo = \Godyar\DB::pdo();
            } elseif (function_exists('gdy_pdo_safe')) {
                $pdo = gdy_pdo_safe();
            }
            if ($pdo instanceof \PDO) {
                $stmt = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key`=? LIMIT 1");
                $stmt->execute([$key]);
                $v = $stmt->fetchColumn();
                return ($v === false || $v === null) ? $default : (string)$v;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $default;
    }
}

$adminTheme = strtolower(trim(gdy_admin_setting('admin.theme', 'blue')));
if (!in_array($adminTheme, ['blue','red','green','brown'], true)) {
    $adminTheme = 'blue';
}


if (!isset($pageTitle)) {
    $pageTitle = __('dashboard', [], 'لوحة التحكم');
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// ✅ مسارات ديناميكية (يدعم تركيب الموقع داخل الجذر أو داخل مجلد)
$___base  = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';
$___admin = $___base . '/admin';

// ✅ Bootstrap CSS/JS (محلي إن وُجد، وإلا CDN) + RTL/LTR حسب اللغة
$root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);

$cssFileName = ($dir === 'rtl') ? 'bootstrap.rtl.min.css' : 'bootstrap.min.css';
$jsFileName  = 'bootstrap.bundle.min.js';

$localCssFile = rtrim($root, '/\\') . '/assets/vendor/bootstrap/css/' . $cssFileName;
$localJsFile  = rtrim($root, '/\\') . '/assets/vendor/bootstrap/js/' . $jsFileName;

$bootstrapCss = is_file($localCssFile)
  ? ($___base . '/assets/vendor/bootstrap/css/' . $cssFileName)
  : '/assets/vendor/bootstrap/css/' . $cssFileName;

$bootstrapJs = is_file($localJsFile)
  ? ($___base . '/assets/vendor/bootstrap/js/' . $jsFileName)
  : '/assets/vendor/bootstrap/js/' . $jsFileName;


// Ensure admin UI CSS is loaded (even when pages include header.php directly).
if (!isset($pageHead)) { $pageHead = ''; }
if (strpos((string)$pageHead, 'admin-ui.css') === false) {
    // Cache-bust to avoid old CSS being stuck after updates
    $adminUiLocal = rtrim($root, '/\\') . '/admin/assets/css/admin-ui.css';
    $adminUiVer   = is_file($adminUiLocal) ? (string)filemtime($adminUiLocal) : (string)time();
    $pageHead = '<link rel="stylesheet" href="' . $___admin . '/assets/css/admin-ui.css?v=' . $adminUiVer . '">' . "\n" . $pageHead;
}

?>
<!doctype html>
<html lang="<?= h($lang) ?>" dir="<?= h($dir) ?>" data-admin-theme="<?= h($adminTheme) ?>">
<head>
    <meta charset="utf-8">
    <title><?= h($pageTitle) ?> — <?= h(__('dashboard', [], 'لوحة التحكم')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Perf: preconnect/dns-prefetch for third-party -->
    <!-- Favicon (avoid /favicon.ico 404 + cacheable icons) -->
    <?php
	      // If system settings provide favicon, prefer it; otherwise use bundled icons.
	      // IMPORTANT: default to empty string to avoid preg_match(null, ...)
	      $fav = '';
	      if (isset($siteSettings) && is_array($siteSettings) && isset($siteSettings['raw']) && is_array($siteSettings['raw'])) {
	          $fav = (string)($siteSettings['raw']['site.favicon'] ?? $siteSettings['raw']['favicon'] ?? '');
	      }
	      $fav = trim((string)$fav);
	      if ($fav !== '') {
          // normalize to absolute
          if (!preg_match('~^(https?:)?//~', $fav)) {
              $fav = $___base . '/' . ltrim($fav, '/');
          }
      } else {
          $fav = $___base . '/assets/images/icons/favicon.ico';
      }
      $fav16  = $___base . '/assets/images/icons/favicon-16x16.png';
      $fav32  = $___base . '/assets/images/icons/favicon-32x32.png';
      $apple  = $___base . '/assets/images/icons/apple-touch-icon.png';
    ?>
    <link rel="icon" href="<?= h($fav) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= h($fav16) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= h($fav32) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= h($apple) ?>">


    <script>
      window.GODYAR_BASE_URL = <?= json_encode($___base, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.GDY_ADMIN_URL   = <?= json_encode($___admin, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <link rel="stylesheet" href="<?= h($bootstrapCss) ?>">

    <!-- Font Awesome (non-render-blocking) -->
    <noscript></noscript>

<style>
        html, body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top, var(--gdy-bg), var(--gdy-bg) 55%, var(--gdy-bg) 100%);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--gdy-text);
            overflow-x: hidden;
        }

        .gdy-admin-page {
            padding: 1.25rem 1.5rem;
        }
    </style>
<?php if (!empty($pageHead)) { echo $pageHead; } ?>
</head>
<body>

<?php // Global CSRF token for admin AJAX (Saved Filters, etc.) ?>
<?php if (function_exists('csrf_token')): ?>
  <input type="hidden" name="csrf_token" id="gdyGlobalCsrfToken" value="<?= h(csrf_token()) ?>" style="display:none">
<?php elseif (function_exists('generate_csrf_token')): ?>
  <input type="hidden" name="csrf_token" id="gdyGlobalCsrfToken" value="<?= h(generate_csrf_token()) ?>" style="display:none">
<?php endif; ?>

<div class="gdy-admin-mobilebar d-lg-none">
  <button type="button" class="gdy-admin-menu-btn" id="gdyAdminMenuBtn" aria-label="القائمة">
    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
  </button>
</div>
<div class="gdy-admin-backdrop" id="gdyAdminBackdrop" hidden></div>

<?php if (!defined('GDY_ADMIN_BOOTSTRAP_JS_LOADED')): define('GDY_ADMIN_BOOTSTRAP_JS_LOADED', true); ?>
<script src="<?= h($bootstrapJs) ?>"></script>
<?php endif; ?>
