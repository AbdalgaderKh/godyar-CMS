<?php
// /frontend/views/partials/header.php

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * قراءة حالة العضو من الجلسة
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$currentUser = $_SESSION['user'] ?? null;

// دعم متوافق مع مفاتيح الجلسة القديمة (بعض الصفحات تستخدم user_id / user_name / user_email)
if (!is_array($currentUser) && !empty($_SESSION['user_id'])) {
    $currentUser = [
        'id'       => (int)$_SESSION['user_id'],
        'username' => $_SESSION['user_name'] ?? null,
        'email'    => $_SESSION['user_email'] ?? null,
        'role'     => $_SESSION['user_role'] ?? 'user',
    ];
    // حفظ الشكل الموحد حتى لا يتكرر
    $_SESSION['user'] = $currentUser;
}


if (!isset($isLoggedIn)) {
    $isLoggedIn = is_array($currentUser) && !empty($currentUser['id']);
}

if (!isset($isAdmin)) {
    $role    = $currentUser['role'] ?? '';
    $isAdmin = $isLoggedIn && in_array($role, ['admin','superadmin','manager'], true);
}

/**
 * جلب إعدادات الموقع من HomeController (جدول settings)
 */
$siteSettings = [];
if (class_exists('HomeController')) {
    try {
        $siteSettings = HomeController::getSiteSettings();
        if (!is_array($siteSettings)) {
            $siteSettings = [];
        }
    } catch (Throwable $e) {
        $siteSettings = [];
    }
}


// Fallback: بعض الصفحات (مثل elections.php / صفحات قديمة) لا تمر عبر HomeController.
// لضمان أن الثيم/الشعار/الأسماء تعمل في كل الصفحات، نحاول تحميل settings مباشرة من DB بشكل آمن.
if (empty($siteSettings)) {
    try {
        $rootPath = dirname(__DIR__, 3); // project root
        $dbHelper = $rootPath . '/includes/db.php';
        $settingsHelper = $rootPath . '/includes/site_settings.php';
        if (is_file($dbHelper)) { require_once $dbHelper; }
        if (is_file($settingsHelper)) { require_once $settingsHelper; }
        if (function_exists('gdy_pdo_safe') && function_exists('gdy_load_settings')) {
            $pdo = gdy_pdo_safe();
            $siteSettings = gdy_load_settings($pdo);
            if (!is_array($siteSettings)) { $siteSettings = []; }
        }
    } catch (Throwable $e) {
        // ignore
        $siteSettings = [];
    }
}

// المتغيرات الأساسية مع أولوية لما يمرره السكربت ثم الإعدادات ثم الافتراضي
$siteName    = $siteName    ?? ($siteSettings['site_name']    ?? 'Godyar News');
$siteTagline = $siteTagline ?? ($siteSettings['site_tagline'] ?? __('منصة إخبارية متكاملة'));
$siteLogo    = $siteLogo    ?? ($siteSettings['site_logo']    ?? '');

// Front preset (Default vs Custom) - ensures Default palette is used sitewide
$rawSettings = (isset($siteSettings['raw']) && is_array($siteSettings['raw'])) ? $siteSettings['raw'] : [];
$frontPreset = (string)($siteSettings['front_preset'] ?? $siteSettings['settings.front_preset'] ?? ($rawSettings['front_preset'] ?? '') ?? ($rawSettings['settings.front_preset'] ?? ''));
$frontPreset = strtolower(trim($frontPreset)) ?: 'default';
$primaryColor = $primaryColor ?? ($siteSettings['primary_color'] ?? ($siteSettings['theme_primary'] ?? '#111111'));
// If preset is NOT custom, force the new Default palette (beige + gold + black)
if ($frontPreset !== 'custom') {
    $primaryColor = '#111111';
    $primaryDark  = '#000000';
    $primaryRgb   = '17,17,17';
}

// Primary dark (optional from settings). If not provided, compute a darker shade.
if (!isset($primaryDark) || $primaryDark === '' || $primaryDark === null) {
    $primaryDark = (string)($siteSettings['primary_dark'] ?? ($siteSettings['theme_primary_dark'] ?? ''));
    if ($primaryDark === '') {
        $hex = ltrim((string)$primaryColor, '#');
        if (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $r = max(0, hexdec(substr($hex, 0, 2)) - 40);
            $g = max(0, hexdec(substr($hex, 2, 2)) - 40);
            $b = max(0, hexdec(substr($hex, 4, 2)) - 40);
            $primaryDark = sprintf('#%02x%02x%02x', $r, $g, $b);
        } else {
            $primaryDark = 'var(--primary-dark)';
        }
    }
}

// Primary RGB (for rgba() usage in CSS). Used by front themes.
$primaryRgb = '214, 157, 16';
try {
    $hex = ltrim((string)$primaryColor, '#');
    if (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $primaryRgb = $r . ', ' . $g . ', ' . $b;
    }
} catch (Throwable $e) {
    $primaryRgb = '214, 157, 16';
}

$themeClass   = $themeClass   ?? 'theme-default';


// Header background image (from settings)
$headerBgEnabled = (($siteSettings['theme_header_bg_enabled'] ?? '0') === '1');
$headerBgSource  = (string)($siteSettings['theme_header_bg_source'] ?? 'upload');
$headerBgUrl     = trim((string)($siteSettings['theme_header_bg_url'] ?? ''));
$headerBgImage   = trim((string)($siteSettings['theme_header_bg_image'] ?? ''));

$headerBg = '';
if ($headerBgEnabled) {
    if ($headerBgSource === 'upload' && $headerBgImage !== '') {
        $headerBg = preg_match('~^https?://~i', $headerBgImage) ? $headerBgImage : (rtrim($baseUrl, '/') . '/' . ltrim($headerBgImage, '/'));
    } elseif ($headerBgSource === 'url' && $headerBgUrl !== '') {
        $headerBg = preg_match('~^https?://~i', $headerBgUrl) ? $headerBgUrl : (rtrim($baseUrl, '/') . '/' . ltrim($headerBgUrl, '/'));
    } elseif ($headerBgImage !== '') {
        $headerBg = preg_match('~^https?://~i', $headerBgImage) ? $headerBgImage : (rtrim($baseUrl, '/') . '/' . ltrim($headerBgImage, '/'));
    }
}

$searchPlaceholder = $searchPlaceholder ?? __('ابحث عن خبر أو موضوع...');
$headerCategories  = $headerCategories  ?? [];
$isLoggedIn        = $isLoggedIn        ?? false;
$isAdmin           = $isAdmin           ?? false;

// ضبط baseUrl
if (isset($baseUrl) && $baseUrl !== '') {
    $baseUrl = rtrim($baseUrl, '/');
} elseif (function_exists('base_url')) {
    $baseUrl = rtrim(base_url(), '/');
} else {
    $baseUrl = '';
}
$baseUrl = preg_replace('#/frontend/controllers$#', '', $baseUrl);

// تحديد اللغة الحالية
$_gdyLang = function_exists('gdy_lang') ? (string)gdy_lang() : (isset($GLOBALS['lang']) ? (string)$GLOBALS['lang'] : 'ar');
$_gdyLang = trim($_gdyLang, '/');
if ($_gdyLang === '') { $_gdyLang = 'ar'; }

// ✅ ملاحظة مهمة: لا نضيف بادئة اللغة على روابط الملفات الحقيقية (assets/images/uploads)
// لأن طلبات الملفات الثابتة لا تمر دائماً على PHP وبالتالي قد تسبب 404.
// لذلك نفصل بين:
//  - $rootUrl: الجذر (للملفات الثابتة + الروابط العامة مثل login/logout)
//  - $navBaseUrl: جذر الموقع مع بادئة اللغة (للصفحات/الروابط)
// بعض إعدادات base_url() تعيد الرابط مع /ar، لذلك نزيله من الجذر إن وُجد.
$rootUrl = $baseUrl;
if ($rootUrl !== '' && preg_match('#/' . preg_quote($_gdyLang, '#') . '$#i', $rootUrl)) {
    $rootUrl = preg_replace('#/' . preg_quote($_gdyLang, '#') . '$#i', '', $rootUrl);
}
// اجعل $baseUrl دائماً هو الجذر (بدون /ar) لتفادي 404 في assets و login
$baseUrl = $rootUrl;

$navBaseUrl = rtrim($rootUrl, '/') . '/' . $_gdyLang;
if ($rootUrl === '') { $navBaseUrl = '/' . $_gdyLang; }

/**
 * تحميل التصنيفات للهيدر
 */
if (empty($headerCategories)) {
    try {
        $pdo = gdy_pdo_safe();

        if ($pdo instanceof \PDO) {
            $catNameColumn = 'name';
            $columns = [];

            try {
                $colStmt = $pdo->query("SHOW COLUMNS FROM categories");
                $columns = $colStmt->fetchAll(\PDO::FETCH_COLUMN, 0);
            } catch (\Throwable $e) {
                // تجاهل
            }

            if (!in_array('name', $columns, true)) {
                if (in_array('category_name', $columns, true)) {
                    $catNameColumn = 'category_name';
                } elseif (in_array('cat_name', $columns, true)) {
                    $catNameColumn = 'cat_name';
                } elseif (in_array('title', $columns, true)) {
                    $catNameColumn = 'title';
                } else {
                    $catNameColumn = null;
                }
            }

            $hasParentColumn = in_array('parent_id', $columns, true);

            if ($catNameColumn !== null) {
                if ($hasParentColumn) {
                    $sql = "SELECT id, {$catNameColumn} AS name, slug, parent_id FROM categories ORDER BY parent_id IS NULL DESC, parent_id ASC, id ASC";
                } else {
                    $sql = "SELECT id, {$catNameColumn} AS name, slug FROM categories ORDER BY id ASC";
                }
            } else {
                if ($hasParentColumn) {
                    $sql = "SELECT id, slug, parent_id FROM categories ORDER BY parent_id IS NULL DESC, parent_id ASC, id ASC";
                } else {
                    $sql = "SELECT id, slug FROM categories ORDER BY id ASC";
                }
            }

            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (is_array($rows) && !empty($rows)) {
                $headerCategories = $rows;
            }
        }
    } catch (\Throwable $e) {
        $headerCategories = $headerCategories ?? [];
    }
}
?>
<?php
  // اتجاه الصفحة يجب أن يتبع اللغة المختارة فعليًا (وليس قيمة عامة قد لا تتغير).
  $gdyLang = function_exists('gdy_lang') ? (string)gdy_lang() : 'ar';
  $gdyLang2 = strtolower(substr($gdyLang, 0, 2));
  $gdyPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
  $gdyIsRtl = in_array($gdyLang2, ['ar','fa','ur','he'], true) || (bool)preg_match('~^/ar(/|$)~', $gdyPath);
?>
<!DOCTYPE html>
<html lang="<?= h($gdyLang) ?>" dir="<?= $gdyIsRtl ? 'rtl' : 'ltr' ?>" data-theme="light" class="no-js">
<head>
  <meta charset="utf-8">
  <script>
    (function(){
      try{
        var t = localStorage.getItem('gdy_theme');
        if(t === 'dark' || t === 'light'){
          document.documentElement.setAttribute('data-theme', t);
        } /* prefers-color-scheme disabled: default light unless user chose */
      }catch(e){}
      document.documentElement.classList.remove('no-js');
      document.documentElement.classList.add('js');
    })();
  </script>

  <?php
    // SEO: يمكن للصفحات تمرير $pageSeo = ['title'=>..., 'description'=>..., 'image'=>..., 'url'=>..., 'type'=>..., 'published_time'=>..., 'modified_time'=>..., 'author'=>..., 'jsonld'=>...]
    $seoTitle = h($siteName . ' - ' . $siteTagline);
    $seoDesc  = h($siteTagline);

    $seoImage = '';
    $seoUrl   = '';
    $seoType  = 'website';
    $seoPublished = '';
    $seoModified  = '';
    $seoAuthor    = '';
    $seoJsonLd    = '';

    if (isset($pageSeo) && is_array($pageSeo)) {
      if (!empty($pageSeo['title']))       $seoTitle = h((string)$pageSeo['title']);
      if (!empty($pageSeo['description'])) $seoDesc  = h((string)$pageSeo['description']);
      if (!empty($pageSeo['image']))       $seoImage = (string)$pageSeo['image'];
      if (!empty($pageSeo['url']))         $seoUrl   = (string)$pageSeo['url'];
      if (!empty($pageSeo['type']))        $seoType  = (string)$pageSeo['type'];
      if (!empty($pageSeo['published_time'])) $seoPublished = (string)$pageSeo['published_time'];
      if (!empty($pageSeo['modified_time']))  $seoModified  = (string)$pageSeo['modified_time'];
      if (!empty($pageSeo['author']))         $seoAuthor    = (string)$pageSeo['author'];
      if (!empty($pageSeo['jsonld']))         $seoJsonLd    = (string)$pageSeo['jsonld'];
    }
    // Canonical URL: إذا لم يتم تمريره من الصفحة، نبنيه من الطلب الحالي مع حذف بارامترات التتبع/الفرز
    if ($seoUrl === '' && isset($rootUrl) && $rootUrl !== '') {
      $reqUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
      $path = parse_url($reqUri, PHP_URL_PATH) ?: '/';
      $query = (string)(parse_url($reqUri, PHP_URL_QUERY) ?? '');
      $q = [];
      if ($query !== '') { parse_str($query, $q); }

      // إزالة بارامترات تسبب تكرار المحتوى
      $drop = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','fbclid','gclid','msclkid','igshid','ref','ref_src','sort','period'];
      foreach ($drop as $k) { if (isset($q[$k])) unset($q[$k]); }

      // page=1 لا نحتاجه في canonical
      if (isset($q['page']) && (int)$q['page'] <= 1) unset($q['page']);

      $qs = http_build_query($q);
      $seoUrl = rtrim($rootUrl, '/') . $path . ($qs !== '' ? ('?' . $qs) : '');
    }

    // Robots/meta keywords من الإعدادات إن وُجدت
    $robotsMeta = (string)($siteSettings['seo.robots'] ?? 'index,follow');
    $metaKeywords = (string)($siteSettings['seo.meta_keywords'] ?? '');

  ?>
  <title><?= $seoTitle ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="<?= $seoDesc ?>">
  <meta name="robots" content="<?= h($robotsMeta) ?>">
  <?php if ($metaKeywords !== ''): ?><meta name="keywords" content="<?= h($metaKeywords) ?>"><?php endif; ?>

  
  <link rel="alternate" type="application/rss+xml" title="RSS" href="<?= h(rtrim($rootUrl,'/')) ?>/rss.xml">
  <link rel="sitemap" type="application/xml" href="<?= h(rtrim($rootUrl,'/')) ?>/sitemap.xml">
<link rel="canonical" href="<?= h($seoUrl) ?>">

  <?php
    // Preload images مهمة (LCP) إذا مررتها الصفحة عبر $pagePreloadImages
    $pagePreloadImages = (isset($pagePreloadImages) && is_array($pagePreloadImages)) ? $pagePreloadImages : [];
    $pagePreloadImages = array_values(array_filter(array_unique(array_map('trim', $pagePreloadImages))));
    foreach ($pagePreloadImages as $idx => $imgHref) {
      if ($imgHref === '') continue;
      $fp = ($idx === 0) ? 'high' : 'low';
      echo '<link rel="preload" as="image" href="' . h($imgHref) . '" fetchpriority="' . $fp . '">' . "\n";
    }
  ?>


  <?php
    // OpenGraph / Twitter (يظهر عند مشاركة الرابط)
    $ogTitle = $seoTitle;
    $ogDesc  = $seoDesc;
    $ogUrl   = $seoUrl !== '' ? $seoUrl : '';
    $ogImage = $seoImage !== '' ? $seoImage : '';
  ?>
  <meta property="og:type" content="<?= h($seoType) ?>">
  <meta property="og:title" content="<?= $ogTitle ?>">
  <meta property="og:description" content="<?= $ogDesc ?>">
  <?php if ($ogUrl !== ''): ?><meta property="og:url" content="<?= h($ogUrl) ?>"><?php endif; ?>
  <?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= h($ogImage) ?>"><?php endif; ?>

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $ogTitle ?>">
  <meta name="twitter:description" content="<?= $ogDesc ?>">
  <?php if ($ogImage !== ''): ?><meta name="twitter:image" content="<?= h($ogImage) ?>"><?php endif; ?>

  <?php if ($seoPublished !== ''): ?><meta property="article:published_time" content="<?= h($seoPublished) ?>"><?php endif; ?>
  <?php if ($seoModified !== ''): ?><meta property="article:modified_time" content="<?= h($seoModified) ?>"><?php endif; ?>
  <?php if ($seoAuthor !== ''): ?><meta property="article:author" content="<?= h($seoAuthor) ?>"><?php endif; ?>

  <?php if ($seoJsonLd !== ''): ?>
    <script type="application/ld+json"><?= $seoJsonLd ?></script>
  <?php endif; ?>
  <?php if (!gdy_is_rtl()): ?>
    <link rel="stylesheet" href="<?= h(rtrim((string)($baseUrl ?? ''), '/')) ?>/assets/css/ltr.css">
  <?php endif; ?>

    <link rel="stylesheet" href="<?= h(rtrim((string)($baseUrl ?? ''), '/')) ?>/assets/css/ui-enhancements.css?v=20260107_8">
    <link rel="stylesheet" href="<?= h(rtrim((string)($baseUrl ?? ''), '/')) ?>/assets/css/pwa.css?v=20260107_8">

    <!-- Theme Core (Front): يجعل تغيير الثيم ينعكس على كل عناصر الواجهة بشكل احترافي -->
    <link rel="stylesheet" href="<?= h(rtrim((string)($baseUrl ?? ''), '/')) ?>/assets/css/themes/theme-core.css?v=20260107_8">

    <?php
      // Front-end theme stylesheet (optional)
      // ✅ مهم جداً: لا نحقن --primary في :root إذا كان ملف الثيم موجوداً، حتى لا يطغى اللون الافتراضي.
      // Keys supported: frontend_theme (المطلوب) + settings.frontend_theme + theme.front + theme_front
      $rawSettings = (isset($siteSettings['raw']) && is_array($siteSettings['raw'])) ? $siteSettings['raw'] : [];
      $themeFront = (string)(
        $siteSettings['frontend_theme']
          ?? $siteSettings['settings.frontend_theme']
          ?? ($rawSettings['frontend_theme'] ?? '')
          ?? $siteSettings['theme_front']
          ?? ($rawSettings['theme.front'] ?? '')
          ?? ($siteSettings['theme.front'] ?? 'default')
      );

      $themeFront = strtolower(trim($themeFront)) ?: 'default';
      // Allow values like theme-red
      $themeFront = preg_replace('/^theme-/', '', $themeFront);
      $themeFront = preg_replace('/[^a-z0-9_-]/', '', $themeFront) ?: 'default';

      $hasThemeCss = false;
      if ($themeFront !== 'default') {
        $themeCssDisk = dirname(__DIR__, 3) . '/assets/css/themes/theme-' . $themeFront . '.css';
        if (is_file($themeCssDisk)) {
          $hasThemeCss = true;
          $themeCssHref = rtrim((string)($rootUrl ?? ''), '/') . '/assets/css/themes/theme-' . $themeFront . '.css';
          $v = (string)@filemtime($themeCssDisk);
          echo '<link rel="stylesheet" href="' . h($themeCssHref) . ($v !== '' ? ('?v=' . h($v)) : '') . '">' . "\n";
        }
      }
    ?>

  <?php if (!empty($siteSettings['extra_head_code'])): ?>
    <?= $siteSettings['extra_head_code'] . "\n" ?>
  <?php endif; ?>

  <style>
    :root {
<?php if (empty($hasThemeCss)): ?>
      /* لا يوجد ملف ثيم => استخدم ألوان الإعدادات كـ fallback */
      --primary: <?= h($primaryColor) ?>;
      --primary-rgb: <?= h($primaryRgb) ?>;
      --primary-light: <?= h($primaryColor) ?>22;
      --primary-dark: <?= h($primaryDark) ?>;
<?php endif; ?>

      /* اجعل الخلفيات/التدرجات تتبع الثيم المختار */
      --bg-page: var(--page-bg);
      --bg-header: var(--header-bg);
      --bg-footer: var(--footer-bg);

      /* Card tokens تأتي من theme-core.css / theme-*.css */

      --text-main: #0f172a;
      --text-muted: #64748b;
      --radius-lg: 18px;
      --shadow-soft: 0 16px 30px rgba(15,23,42,0.12);
      --shadow-hover: 0 20px 40px rgba(15,23,42,0.18);

      /* ✅ يُضبط تلقائياً عبر JS لمنع دخول المحتوى تحت الهيدر */
      --header-h: 0px;

      /* حجم الشعار في الهيدر (يمكن تعديله عبر CSS إضافي من الإعدادات) */
      --logo-size: 64px;
    }

    html.theme-dark {
      --bg-page: #0b1220;
      --card-gradient: linear-gradient(135deg, #0b1220 0%, #0f172a 55%, #0b1220 100%);
      --card-border: rgba(148,163,184,.22);
      --text-main: #e5e7eb;
      --text-muted: #94a3b8;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; scroll-padding-top: 0px; /* header is not sticky */ }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Tajawal", sans-serif;
      background: var(--bg-page);
      color: var(--text-main);
      line-height: 1.6;
      overflow-x: hidden;
    }

    a { color: inherit; text-decoration: none; transition: all 0.3s ease; }
    a:hover { text-decoration: none; }

    .container {
      width: 100%;
      max-width: 1180px;
      margin: 0 auto;
      padding: 0 16px;
    }

    /* ===== الهيدر الداكن (مع وضوح عالي للكتابة) ===== */
    .site-header{
      position: relative;
      top: auto;
      z-index: 10;

      /* ✅ هيدر خفيف (Tint) يتبع لون الثيم + وضوح نص عالي */
      --on-primary: #111827;
      --on-primary-muted: rgba(17,24,39,.62);

      --header-chip-bg: rgba(255,255,255,.92);
      --header-border: rgba(var(--primary-rgb), .18);
      --header-shadow: 0 10px 24px rgba(15,23,42,.07);

      --header-bg: linear-gradient(
        180deg,
        rgba(var(--primary-rgb), .18),
        rgba(255,251,242, .94)
      );

      background: var(--header-bg);
      color: var(--on-primary);

      border-bottom: 1px solid var(--header-border);
      box-shadow: var(--header-shadow);

      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);

      will-change: transform;
    }

.site-header.has-bg{
  /* Overlay خفيف يحافظ على القراءة مع صورة الهيدر بدون تحويله لداكن */
  background-image: linear-gradient(180deg, rgba(255,255,255,.22), rgba(255,255,255,.48)), var(--gdy-header-bg);
  background-size: cover;
  background-position: center;
}

    /* ✅ بديل للمتصفحات التي لا تدعم blur */
    @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
      .site-header{ background: var(--bg-header); }
    }

    /* ===== Header layout (منظم/احترافي) ===== */
.header-inner{
  display:flex;
  direction: inherit;
  align-items:center;
  justify-content:space-between;
  gap:14px;
  padding:12px 0;
  flex-wrap:nowrap;
  min-width:0;
}

/* ✅ عكس ترتيب الهيدر تلقائياً حسب اتجاه اللغة:
   - RTL: الشعار يمين + الأدوات يسار
   - LTR: الشعار يسار + الأدوات يمين */
html[dir="rtl"] .site-header .header-inner{ flex-direction: row; }

.hdr-utils{
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:nowrap;
  min-width:0;
}

/* على الشاشات المتوسطة/الصغيرة نسمح بالالتفاف حتى لا يحدث تكدس */
@media (max-width: 992px){
  .header-inner{ flex-wrap: wrap; }
  .hdr-utils{ width:auto; flex-wrap:wrap; }
  html[dir="rtl"] .hdr-utils{ justify-content:flex-start; } /* أقصى اليسار */
  html[dir="ltr"] .hdr-utils{ justify-content:flex-end; }  /* أقصى اليمين */
}
.brand-block {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 0;
      transition: transform 0.2s ease;
    }
    .brand-block:hover { transform: translateY(-1px); }

/* RTL: اجعل الشعار (اللوجو + النص) داخل البلوك بمحاذاة يمين، واللوجو في أقصى يمين البلوك */
html[dir="rtl"] .site-header .brand-block{ flex-direction: row-reverse; }
html[dir="rtl"] .site-header .brand-text{ text-align: right; }

    
    .brand-logo {
      width: var(--logo-size);
      height: var(--logo-size);
      border-radius: 999px;
      padding: 3px; /* سماكة الحلقة */
	      /* الحلقة تتبع لون الثيم (بدون ألوان متنافرة) */
	      background: conic-gradient(
	        from 180deg,
	        var(--primary),
	        var(--primary-dark),
	        var(--primary),
	        var(--primary-dark),
	        var(--primary)
	      );
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--on-primary, #0f172a);
      font-size: 1.45rem;
	      box-shadow:
	        0 0 0 1px rgba(255,255,255,0.16),
	        0 10px 24px rgba(var(--primary-rgb), 0.22);
      overflow: hidden;
      flex-shrink: 0;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
      position: relative;
    }

    /* خلفية داخلية داكنة (حتى لو لم توجد صورة) */
    .brand-logo::after {
      content: '';
      position: absolute;
      inset: 3px;
      border-radius: 999px;
	      background: radial-gradient(circle at 30% 20%, rgba(var(--primary-rgb), 0.22), rgba(255,255,255,1) 70%);
      z-index: 0;
    }

    .brand-logo img,
    .brand-logo i{
      position: relative;
      z-index: 1;
    }

    .brand-logo img {
      width: 100%;
      height: 100%;
      border-radius: 999px;
      object-fit: cover;
      display: block;
    }

    .brand-logo:hover {
      transform: translateY(-1px) scale(1.02);
      box-shadow:
        0 0 0 1px rgba(255,255,255,0.18),
        0 14px 32px rgba(var(--primary-rgb), 0.28);
    }

    @media (max-width:575.98px){
      :root{ --logo-size: 54px; }
    }


    .brand-text { text-align: end; min-width: 0; }
    .brand-title {
      font-size: 1.06rem;
      font-weight: 900;
      letter-spacing: .2px;
      color: var(--on-primary, #0f172a);
      line-height: 1.05;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 46ch;
    }
    .brand-subtitle {
      font-size: .80rem;
      color: var(--on-primary-muted, rgba(15,23,42,.72));
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 64ch;
    }

    .hdr-utils{display:flex;align-items:center;gap:10px;flex-wrap:nowrap;justify-content:flex-end;}

    @media (max-width: 992px){.hdr-utils{ flex-wrap: wrap; }
      .brand-title,.brand-subtitle{ max-width: 100%; }
    }
    .hdr-dropdown{position:relative;}
    .hdr-dd-btn{
      padding: 6px 11px;
      border-radius: 999px;
      /* المطلوب: خلفية بيضاء + نص/أيقونة بنفس لون الاستايل */
      background: rgba(255,255,255,.96);
      border: 1px solid rgba(var(--primary-rgb), .22);
      color: var(--primary) !important;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      font-weight: 900;
      font-size: .78rem;
      transition: .2s ease;
      white-space: nowrap;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }

    .hdr-dd-btn .gdy-icon,
    .hdr-dd-btn span{ color: var(--primary) !important; }

    .hdr-theme-btn{min-width:44px;justify-content:center;padding:10px 12px}
    .hdr-theme-btn .gdy-icon{margin:0}
    .hdr-dd-btn:hover{
      background: #ffffff;
      border-color: rgba(var(--primary-rgb), .45);
      transform: translateY(-1px);
      box-shadow: 0 10px 22px rgba(var(--primary-rgb),0.18);
    }
    .hdr-dd-btn .chev{width:14px;height:14px; opacity: 1}

    .hdr-dd-btn .avatar-mini{
      width: 26px;
      height: 26px;
      border-radius: 50%;
      object-fit: cover;
      border: 1px solid rgba(var(--primary-rgb), .22);
      background: rgba(var(--primary-rgb), .06);
      flex-shrink: 0;
    }

    .hdr-dd-menu{
      position: absolute;
      top: calc(100% + 8px);
      inset-inline-start: 0;
      min-width: 220px;
      background: rgba(255,255,255,.98);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(var(--primary-rgb), .20);
      border-radius: 14px;
      padding: 6px;
      box-shadow: 0 18px 40px rgba(15,23,42,0.18);
      display: none;
      z-index: 1000;
    }
    .hdr-dropdown.open .hdr-dd-menu{display:block;}

    .hdr-dd-menu a{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      padding: 9px 10px;
      border-radius: 12px;
      color: #0f172a;
      text-decoration:none;
      font-weight: 700;
      font-size: .82rem;
      border: 1px solid transparent;
    }
    .hdr-dd-menu a:hover{
      background: rgba(var(--primary-rgb),0.08);
      border-color: rgba(var(--primary-rgb),0.18);
    }
    .hdr-dd-menu a.active{
      background: rgba(var(--primary-rgb),0.14);
      border-color: rgba(var(--primary-rgb),0.35);
    }
    .hdr-dd-sep{
      height: 1px;
      background: rgba(var(--primary-rgb),0.14);
      margin: 6px 6px;
    }


    /* ===== شريط التصنيفات + البحث (استعادة تنسيق احترافي) ===== */
    .header-secondary{
      border-top: 1px solid var(--header-border, rgba(255,255,255,.18));
      padding: 10px 0 12px;
      background: var(--header-secondary-bg, rgba(255,255,255,.08));
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    .site-header.has-bg .header-secondary{
      background: var(--header-secondary-bg, rgba(255,255,255,.08));
    }
    .header-secondary-inner{
      display:flex;
      align-items:center;
      gap:12px;
      padding: 12px;
      border-radius: 18px;
      background: rgba(255,255,255,.58);
      border: 1px solid rgba(var(--primary-rgb), .12);
      box-shadow: 0 16px 36px rgba(15,23,42,.06);
      flex-wrap: nowrap;
      min-width: 0;
    }

    .cats-toggle{
      display:none;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      width:100%;
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid var(--header-border, rgba(255,255,255,.20));
      background: var(--header-chip-bg, rgba(255,255,255,.12));
      color: var(--on-primary, #ffffff);
      font-weight: 900;
    }
    .cats-toggle .chev{ transition: transform .2s ease; }
    .cats-toggle[aria-expanded="true"] .chev{ transform: rotate(180deg); }

    .cats-nav{
      display:flex;
      flex-wrap: nowrap;
      align-items:center;
      gap:8px;
      font-size: .84rem;
      min-width: 0;
      overflow-x: auto;
      overflow-y: hidden;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
      padding: 2px;
      max-width: 100%;
    }
    .cats-nav::-webkit-scrollbar{ display:none; }
    .cats-item{ position:relative; }
        .cats-link{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 7px 12px;
      border-radius: 999px;
      /* المطلوب: خلفية بيضاء + نص/أيقونة بنفس لون الثيم */
      background: rgba(255,255,255,.96);
      border: 1px solid rgba(var(--primary-rgb), .22);
      color: var(--primary) !important;
      font-weight: 800;
      transition: .2s ease;
      white-space: nowrap;
    }
    .cats-link span{ color: var(--primary) !important; }
    .cats-link i{ opacity: .95; color: var(--primary) !important; }
    /* طلب: إزالة أيقونة الفئة من الهيدر */
    .cats-nav .cats-link i{ display:none !important; }
    .cats-link:hover{
      background: #ffffff;
      border-color: rgba(var(--primary-rgb), .45);
      transform: translateY(-1px);
      box-shadow: 0 10px 22px rgba(var(--primary-rgb),0.18);
    }
    .cats-link:active{ transform: translateY(0); }

    /* Submenu for children categories */
    .cats-submenu{
      position:absolute;
      top: calc(100% + 10px);
      inset-inline-end: 0;
      min-width: 220px;
      background: rgba(255,255,255,.94);
      border: 1px solid rgba(var(--primary-rgb), .20);
      border-radius: 14px;
      padding: 6px;
      box-shadow: 0 18px 40px rgba(0,0,0,0.22);
      display:none;
      z-index: 1100;
    }
    .cats-item.has-children:hover > .cats-submenu,
    .cats-item.has-children:focus-within > .cats-submenu{ display:block; }
    .cats-submenu .cats-item{ display:block; }
        .cats-submenu .cats-link{
      width:100%;
      justify-content: space-between;
      padding: 9px 10px;
      border-radius: 12px;
      background: #ffffff;
      border: 1px solid rgba(var(--primary-rgb), .16);
      font-weight: 700;
      font-size: .84rem;
      color: var(--primary) !important;
    }
    .cats-submenu .cats-link:hover{
      background: rgba(var(--primary-rgb), .08);
      border-color: rgba(var(--primary-rgb), .28);
      transform:none;
      box-shadow:none;
    }

    .header-search{
      margin-inline-start: auto;
      position: relative;
      min-width: 220px;
      max-width: 320px;
      flex: 1;
    }
    .header-search input{
      width:100%;
      padding: 10px 38px 10px 12px;
      border-radius: 999px;
      border: 1px solid rgba(var(--primary-rgb), .18);
      background: rgba(255,255,255,.98);
      color: #111827;
      font-size: .90rem;
      transition:.2s ease;
    }
    .header-search input::placeholder{ color: rgba(17,24,39,0.55); }
    .header-search input:focus{
      outline:none;
      border-color: rgba(var(--primary-rgb), .45);
      box-shadow: 0 0 0 3px rgba(255,255,255,0.14);
    }
    .header-search-icon{
      position:absolute;
      inset-inline-end: 12px;
      top:50%;
      transform: translateY(-50%);
      color: var(--on-primary-muted, rgba(17,24,39,0.55));
      font-size: .86rem;
      pointer-events:none;
    }
    .header-search:focus-within .header-search-icon{ color: rgba(var(--primary-rgb),0.90); }

    @media (max-width: 768px){
      .header-inner{ flex-direction: column; align-items: stretch; }
      
      .header-secondary-inner{ flex-direction: column; align-items: stretch; }
      .cats-toggle{ display:flex; }
      /* على الجوال: فئات بشكل شرائح أفقية (Facebook-like tabs) */
      .cats-nav{ display:none; width:100%; flex-direction: row; flex-wrap: nowrap; align-items: center; overflow-x: auto; overflow-y: hidden; padding: 8px 2px; gap: 8px; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
      .cats-nav::-webkit-scrollbar{ display:none; }
      .cats-nav.open{ display:flex; }
      .cats-item{ width:auto; flex: 0 0 auto; }
      .cats-link{ width:auto; justify-content: center; border-radius: 999px; }
      .cats-submenu{ position: static; display: none; margin-top: 8px; }
      .cats-item.has-children:hover > .cats-submenu,
      .cats-item.has-children:focus-within > .cats-submenu{ display:block; }
      .header-search{ width:100%; max-width: none; margin-inline-start: 0; }
    }


      /* ===== Theme force overrides (keeps site coherent even when some pages have inline CSS) ===== */
      body[class*="theme-"] .section-title,
      body[class*="theme-"] .godyar-home-section-title,
      body[class*="theme-"] .gdy-home-section-title,
      body[class*="theme-"] .hm-section-title,
      body[class*="theme-"] .hm-block-title,
      body[class*="theme-"] .gdy-block-title {
        color: var(--primary) !important;
      }

      /* Article / card badges: white bg + themed text for clarity */
      body[class*="theme-"] .hm-card-badge,
      body[class*="theme-"] .hm-opinion-article-badge,
      body[class*="theme-"] .gdy-sidecard-badge,
      body[class*="theme-"] .gdy-home-badge,
      body[class*="theme-"] .gdy-card-badge,
      body[class*="theme-"] .badge {
        background: #ffffff !important;
        color: var(--primary) !important;
        border: 1px solid rgba(var(--primary-rgb), .25) !important;
      }

      /* Author avatar frame + author labels */
      body[class*="theme-"] .article-author-avatar,
      body[class*="theme-"] .gdy-author-avatar,
      body[class*="theme-"] .opinion-author-avatar,
      body[class*="theme-"] .hm-opinion-author-avatar,
      body[class*="theme-"] .gdy-opinion-author-avatar {
        border-color: rgba(var(--primary-rgb), .9) !important;
      }
      body[class*="theme-"] .article-author-page-title,
      body[class*="theme-"] .opinion-author-role,
      body[class*="theme-"] .hm-opinion-author-role {
        color: var(--primary) !important;
      }

      /* Newsletter highlight */
      body[class*="theme-"] .hm-newsletter {
        border-color: rgba(var(--primary-rgb), .55) !important;
      }
      body[class*="theme-"] .hm-newsletter button,
      body[class*="theme-"] .hm-newsletter .btn,
      body[class*="theme-"] .hm-newsletter-form button {
        background: var(--primary) !important;
        border-color: var(--primary) !important;
        color: #ffffff !important;
      }


  </style>

<!-- PWA -->
<?php
  $__gdyLang = $_gdyLang ?? (function_exists('gdy_lang') ? gdy_lang() : 'ar');
$__gdyLang = is_string($__gdyLang) ? $__gdyLang : 'ar';

// base_url() عادة يرجع مثل: https://example.com/ar أو https://example.com/godyar/ar
// نحتاج "مسار التطبيق" بدون جزء اللغة حتى لا تصبح الأصول /ar/assets أو /ar/manifest.php
$__gdyBase = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';

$__gdyBaseParts = @parse_url($__gdyBase);
$__gdyPath = isset($__gdyBaseParts['path']) ? rtrim((string)$__gdyBaseParts['path'], '/') : '';
// احذف جزء اللغة النهائي (/ar أو /en) إن وجد
if ($__gdyLang && $__gdyPath !== '' && substr($__gdyPath, - (strlen($__gdyLang) + 1)) === '/' . $__gdyLang) {
    $__gdyAppPath = substr($__gdyPath, 0, - (strlen($__gdyLang) + 1));
} else {
    $__gdyAppPath = $__gdyPath;
}
$__gdyAppPath = rtrim($__gdyAppPath, '/'); // قد تصبح ''

// روابط PWA/Assets يجب أن تكون من جذر التطبيق (بدون /ar)
$__gdyBasePath = $__gdyAppPath; // path فقط (بدون دومين)

$__gdyManifestUrl = ($__gdyBasePath === '' ? '' : $__gdyBasePath) . '/manifest.webmanifest?lang=' . rawurlencode($__gdyLang);
$__gdySwUrl       = ($__gdyBasePath === '' ? '' : $__gdyBasePath) . '/sw.js';
?>
<link rel="manifest" href="<?= h($__gdyManifestUrl) ?>">
<meta name="theme-color" content="#0b1220">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

<link rel="apple-touch-icon" sizes="180x180" href="<?= h($__gdyBasePath) ?>/assets/images/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= h($__gdyBasePath) ?>/assets/images/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= h($__gdyBasePath) ?>/assets/images/icons/favicon-16x16.png">
<link rel="icon" href="<?= h($__gdyBasePath) ?>/assets/images/icons/favicon.ico">
<link rel="shortcut icon" href="<?= h($__gdyBasePath) ?>/assets/images/icons/favicon.ico">
<link rel="icon" type="image/png" sizes="192x192" href="<?= h($__gdyBasePath) ?>/assets/images/icons/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="<?= h($__gdyBasePath) ?>/assets/images/icons/icon-512.png">

<script nonce="<?= h($cspNonce ?? '') ?>">
  window.GDY_BASE = <?= json_encode($__gdyBasePath, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  window.GDY_SW_URL = <?= json_encode($__gdySwUrl, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
</script>
</head>
<!-- HEADER_NON_STICKY_MARKER: non-sticky header 2025-12-14 -->
<?php
  // Ensure the global selected front theme class is always present on <body>
  // even if some controllers set a different theme class (e.g. theme-ocean).
    $rawSettings = (isset($siteSettings['raw']) && is_array($siteSettings['raw'])) ? $siteSettings['raw'] : [];
  
  // Keys supported: frontend_theme (المطلوب) + settings.frontend_theme + frontendTheme (camel) + theme.front (legacy)
  $themeFront = (string)(
    ($siteSettings['frontend_theme'] ?? null)
    ?? ($siteSettings['settings.frontend_theme'] ?? null)
    ?? ($siteSettings['frontendTheme'] ?? null)
    ?? ($rawSettings['frontend_theme'] ?? null)
    ?? ($rawSettings['settings.frontend_theme'] ?? null)
    ?? ($siteSettings['theme_front'] ?? null)
    ?? ($rawSettings['theme.front'] ?? null)
    ?? ($siteSettings['theme.front'] ?? 'default')
  );
  $themeFront = strtolower(trim($themeFront)) ?: 'default';
  $themeFront = preg_replace('/^theme-/', '', $themeFront);
  if (str_starts_with($themeFront, 'theme-')) { $themeFront = substr($themeFront, 6); }
  $themeFront = preg_replace('/[^a-z0-9_-]/', '', $themeFront) ?: 'default';
  $themeFrontClass = 'theme-' . $themeFront;
  if (strpos(' ' . (string)$themeClass . ' ', ' ' . $themeFrontClass . ' ') === false) {
    $themeClass = trim(((string)$themeClass) . ' ' . $themeFrontClass);
  }
?>
<body class="<?= h($themeClass) ?>" data-auth="<?= !empty($currentUser) ? '1' : '0' ?>" data-user-id="<?= (int)($currentUser['id'] ?? 0) ?>">

<?php $hdrClass = $headerBg ? ' has-bg' : ''; ?>
<?php $hdrStyle = $headerBg ? ' style="--gdy-header-bg:url(\'' . h($headerBg) . '\');"' : ''; ?>
<header class="site-header<?= $hdrClass ?>"<?= $hdrStyle ?>>
  <div class="container">
    <div class="header-inner">
      <a href="<?= h($navBaseUrl) ?>" class="brand-block">
        <div class="brand-logo">
          <?php if ($siteLogo): ?>
            <img src="<?= h($siteLogo) ?>" alt="<?= h($siteName) ?>">
          <?php else: ?>
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
          <?php endif; ?>
        </div>
        <div class="brand-text">
          <div class="brand-title"><?= h($siteName) ?></div>
          <div class="brand-subtitle"><?= h($siteTagline) ?></div>
        </div>
      </a>

      <div class="hdr-utils">
          <button type="button" class="hdr-dd-btn hdr-search-btn" id="gdyMobileSearchBtn" title="<?= h(__('search')) ?>" aria-label="<?= h(__('search')) ?>">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg>
          </button>
          <div class="hdr-dropdown hdr-lang" id="gdyLangDd">
            <button type="button" class="hdr-dd-btn" aria-haspopup="menu" aria-expanded="false" title="Language">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#globe"></use></svg>
              <span><?= strtoupper(gdy_lang()) ?></span>
              <svg class="gdy-icon chev" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
            </button>
            <div class="hdr-dd-menu" role="menu" aria-label="Language">
              <a role="menuitem" href="<?= h(gdy_lang_url('ar')) ?>" class="<?= gdy_lang()==='ar' ? 'active' : '' ?>"><span>AR</span><span>العربية</span></a>
              <a role="menuitem" href="<?= h(gdy_lang_url('en')) ?>" class="<?= gdy_lang()==='en' ? 'active' : '' ?>"><span>EN</span><span>English</span></a>
              <a role="menuitem" href="<?= h(gdy_lang_url('fr')) ?>" class="<?= gdy_lang()==='fr' ? 'active' : '' ?>"><span>FR</span><span>Français</span></a>
            </div>
          </div>

          <button type="button" class="hdr-dd-btn hdr-theme-btn" id="gdyThemeToggle" title="الوضع الليلي" aria-pressed="false">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#moon"></use></svg>
          </button>

          <div class="hdr-dropdown hdr-user" id="gdyUserDd">
            <?php if (!$isLoggedIn): ?>
              <button type="button" class="hdr-dd-btn" aria-haspopup="menu" aria-expanded="false">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                <span><?= h(__('الحساب')) ?></span>
                <svg class="gdy-icon chev" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
              </button>
              <div class="hdr-dd-menu" role="menu" aria-label="Account">
                <a role="menuitem" href="<?= h($baseUrl) ?>/login"><span><?= h(__('تسجيل الدخول')) ?></span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#login"></use></svg></a>
                <a role="menuitem" href="<?= h($baseUrl) ?>/register"><span><?= h(__('إنشاء حساب')) ?></span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg></a>
              </div>
            <?php else: ?>
              <?php $uLabel = (string)($currentUser['display_name'] ?? ($currentUser['username'] ?? ($currentUser['email'] ?? __('حسابي')))); ?>
              <button type="button" class="hdr-dd-btn" aria-haspopup="menu" aria-expanded="false" title="<?= h($uLabel) ?>">
                <?php if (!empty($currentUser['avatar'])): ?>
                  <img class="avatar-mini" src="<?= h($baseUrl . '/' . ltrim((string)$currentUser['avatar'], '/')) ?>" alt="avatar">
                <?php else: ?>
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                <?php endif; ?>
                <span><?= h($uLabel) ?></span>
                <svg class="gdy-icon chev" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
              </button>
              <div class="hdr-dd-menu" role="menu" aria-label="Account">
                <a role="menuitem" href="<?= h($baseUrl) ?>/profile"><span><?= h(__('الملف الشخصي')) ?></span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg></a>
                <?php if ($isAdmin): ?>
                  <a role="menuitem" href="<?= h($baseUrl) ?>/admin/"><span><?= h(__('لوحة التحكم')) ?></span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#settings"></use></svg></a>
                <?php endif; ?>
                <div class="hdr-dd-sep"></div>
                <a role="menuitem" href="<?= h($baseUrl) ?>/logout"><span><?= h(__('تسجيل الخروج')) ?></span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#logout"></use></svg></a>
              </div>
            <?php endif; ?>
          </div>
        </div>
    </div>

    <div class="header-secondary">
      <div class="container">
        <div class="header-secondary-inner">
          <button type="button" class="cats-toggle" id="gdyCatsToggle" aria-expanded="false" aria-controls="gdyCatsNav">
            <span class="gdy-inline-flex-gap"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#tag"></use></svg><span><?= h(__('الفئات')) ?></span></span>
            <svg class="gdy-icon chev" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg>
          </button>
          <nav id="gdyCatsNav" class="cats-nav" aria-label="أقسام الموقع">
          <a href="<?= h($navBaseUrl) ?>" class="cats-link cats-link--home">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg>
            <span><?= h(__('الرئيسية')) ?></span>
          </a>

          <a href="<?= h($baseUrl) ?>/elections.php" class="cats-link">
            <span><?= h(__('الانتخابات')) ?></span>
          </a>

          <?php if (!empty($headerCategories)): ?>
            <?php
              // بناء شجرة للتصنيفات (رئيسية + فرعية)
              $headerCategoriesTree = $headerCategoriesTree ?? null;

              // ✅ استثناء أقسام من الظهور في هيدر الموقع (مثال: /category/20 المكرر)
              // يمكن تعديل القائمة لاحقاً بسهولة.
              $excludeHeaderCategoryIds = [20];

              if ($headerCategoriesTree === null) {
                  $byId = [];
                  foreach ($headerCategories as $row) {
                      $id = (int)($row['id'] ?? 0);
                      if ($id <= 0) { continue; }
                      if (in_array($id, $excludeHeaderCategoryIds, true)) { continue; }
                      $row['children'] = [];
                      $byId[$id] = $row;
                  }

                  foreach ($byId as $id => &$row) {
                      $pid = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
                      if ($pid > 0 && isset($byId[$pid])) {
                          $byId[$pid]['children'][] =& $row;
                      }
                  }
                  unset($row);

                  $headerCategoriesTree = [];
                  foreach ($byId as $id => $row) {
                      $pid = isset($row['parent_id']) ? (int)$row['parent_id'] : 0;
                      if ($pid === 0) {
                          $headerCategoriesTree[] = $row;
                      }
                  }
              }

              // كلاسات مميزة لكل قسم حسب slug
              $specialCatStyles = [
                  'opinion'         => 'cats-link--opinion',
                  'opinion-writers' => 'cats-link--opinion',
                  'book-opinion'    => 'cats-link--opinion',
                  'videos'          => 'cats-link--videos',
                  'video'           => 'cats-link--videos',
                  'video-news'      => 'cats-link--videos',
                  'sports'          => 'cats-link--sports',
                  'sport'           => 'cats-link--sports',
                  'football'        => 'cats-link--sports',
                  'business'        => 'cats-link--business',
                  'economy'         => 'cats-link--business',
                  'tech'            => 'cats-link--tech',
                  'technology'      => 'cats-link--tech',
                  'culture'         => 'cats-link--culture',
              ];

              // تتبّع العناصر التي تم عرضها لمنع التكرار (مثلاً قسم يظهر مرتين بسبب slug فارغ/متعدد)
              $renderedHeaderCats = [];

              // المسار الحالي لاستخدامه في تمييز القسم النشط في الهيدر
              $gdyHeaderPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
              $gdyHeaderPath = rtrim($gdyHeaderPath, '/');
              if ($gdyHeaderPath === '') { $gdyHeaderPath = '/'; }


              $renderHeaderCat = function (array $cat) use (&$renderHeaderCat, &$renderedHeaderCats, $navBaseUrl, $rootUrl, $specialCatStyles, $excludeHeaderCategoryIds, $gdyHeaderPath) {
                  $catId = (int)($cat['id'] ?? 0);
                  if ($catId > 0 && in_array($catId, $excludeHeaderCategoryIds, true)) {
                      return;
                  }
                  $catSlug = trim((string)($cat['slug'] ?? ''));
                  if ($catSlug === '') {
                      $catSlug = (string)($cat['id'] ?? '');
                  }
                  $catName = $cat['name'] ?? 'قسم';
                  $hasChildren = !empty($cat['children']);

                  $cls  = 'cats-link';
                  $icon = 'fa-regular fa-newspaper';

                  // تحديد ما إذا كان هذا رابط "الرأي / كتّاب الرأي"
                  $isOpinionLink = in_array($catSlug, ['opinion', 'opinion-writers', 'book-opinion'], true);

                  // مفتاح فريد لمنع تكرار نفس الرابط/القسم
                  $uniqueKey = $isOpinionLink ? 'opinion-link' : ('cat:' . $catSlug);
                  if (isset($renderedHeaderCats[$uniqueKey])) {
                      return;
                  }
                  $renderedHeaderCats[$uniqueKey] = true;

                  foreach ($specialCatStyles as $slugKey => $className) {
                      if ($catSlug === $slugKey) {
                          $cls .= ' ' . $className;
                          if (strpos($className, 'opinion') !== false) {
                              $icon = 'fa-solid fa-feather';
                          } elseif (strpos($className, 'videos') !== false) {
                              $icon = 'fa-solid fa-video';
                          } elseif (strpos($className, 'sports') !== false) {
                              $icon = 'fa-solid fa-futbol';
                          } elseif (strpos($className, 'business') !== false) {
                              $icon = 'fa-solid fa-coins';
                          } elseif (strpos($className, 'tech') !== false) {
                              $icon = 'fa-solid fa-microchip';
                          } elseif (strpos($className, 'culture') !== false) {
                              $icon = 'fa-solid fa-masks-theater';
                          }
                      }
                  }

                  // ضبط اسم القسم لرابط "كتّاب الرأي"
                  if ($isOpinionLink) {
                      $catName = 'كتّاب الرأي';
                  }

                  // مسار الرابط: الأقسام العادية → /category/{slug}
                  // قسم "الرأي" → ينقل إلى بلوك كتّاب الرأي في الصفحة الرئيسية
                  if ($isOpinionLink) {
                      $href = rtrim($rootUrl, '/') . '/opinion_author.php';
                  } else {
                      $href = rtrim($navBaseUrl, '/') . '/category/' . rawurlencode($catSlug);
                  }
                  // تمييز القسم النشط في الهيدر
                  $isActive = false;
                  if ($isOpinionLink) {
                      $isActive = (bool)preg_match('~(?:^|/)opinion_author\.php$~i', $gdyHeaderPath);
                  } else {
                      $normalizedPath = rtrim($gdyHeaderPath, '/');
                      if ($normalizedPath === '') { $normalizedPath = '/'; }
                      $catSlugSafe = preg_quote($catSlug, '~');
                      $isActive = (bool)preg_match('~/(?:[a-z]{2}/)?category/' . $catSlugSafe . '$~i', $normalizedPath);
                  }

                  $clsAttr = trim($cls . ($isActive ? ' is-active' : ''));
                  $ariaCurrent = $isActive ? 'aria-current="page"' : '';

              ?>
                <div class="cats-item <?= $hasChildren ? 'has-children' : '' ?>">
                  <a href="<?= h($href) ?>" class="<?= h($clsAttr) ?>" <?= $ariaCurrent ?>>
<span><?= h(__((string)$catName)) ?></span>
                  </a>
                  <?php if ($hasChildren): ?>
                    <div class="cats-submenu">
                      <?php foreach ($cat['children'] as $child): ?>
                        <?php $renderHeaderCat($child); ?>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php
              };
            ?>

            <?php foreach ($headerCategoriesTree as $catRoot): ?>
              <?php $renderHeaderCat($catRoot); ?>
            <?php endforeach; ?>

          <?php else: ?>
            <a href="<?= h($navBaseUrl) ?>/category/general-news" class="cats-link">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
              <span><?= h(__('أخبار عامة')) ?></span>
            </a>
            <a href="<?= h($navBaseUrl) ?>/category/politics" class="cats-link">
              
              <span><?= h(__('سياسة')) ?></span>
            </a>
            <a href="<?= h($navBaseUrl) ?>/category/business" class="cats-link cats-link--business">
              
              <span><?= h(__('اقتصاد')) ?></span>
            </a>
            <a href="<?= h($navBaseUrl) ?>/category/sports" class="cats-link cats-link--sports">
              
              <span><?= h(__('رياضة')) ?></span>
            </a>
          <?php endif; ?>
          </nav>

          <form class="header-search" action="<?= h($navBaseUrl) ?>/search" method="get">
            <input type="search" placeholder="<?= h($searchPlaceholder) ?>" name="q">
            <span class="header-search-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg></span>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    // منطق إخفاء/إظهار زر الانتخابات + ضبط ارتفاع الهيدر تلقائياً
    document.addEventListener('DOMContentLoaded', function () {


      // ✅ ضبط --header-h لمنع دخول المحتوى/السكرول تحت الهيدر
      function setHeaderH(){
        var header = document.querySelector('.site-header');
        if (!header) return;
        document.documentElement.style.setProperty('--header-h', header.offsetHeight + 'px');
      }
      setHeaderH();
      window.addEventListener('resize', setHeaderH);
    

      // ✅ عرض/إخفاء قائمة الفئات على الجوال
      var catsBtn = document.getElementById('gdyCatsToggle');
      var catsNav = document.getElementById('gdyCatsNav');
      if (catsBtn && catsNav) {
        function setCats(open){
          catsNav.classList.toggle('open', !!open);
          catsBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        catsBtn.addEventListener('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          setCats(!catsNav.classList.contains('open'));
        });
        // افتحها افتراضياً على الشاشات الصغيرة
        if (window.matchMedia('(max-width: 920px)').matches) {
          setCats(true);
        }
        document.addEventListener('click', function(e){
          if(!catsNav.contains(e.target) && !catsBtn.contains(e.target) && window.matchMedia('(max-width: 920px)').matches){
            // اتركها مفتوحة في الجوال (Facebook-like tabs)
          }
        });
      }

      // ✅ Dark mode toggle
      var themeBtn = document.getElementById('gdyThemeToggle');
      if(themeBtn){
        var KEY = 'gdy_theme';
        function applyTheme(v){
          var dark = (v === 'dark');
          document.documentElement.classList.toggle('theme-dark', dark);
          themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
          var useEl = themeBtn.querySelector('use');
          if(useEl){
            var id = dark ? 'sun' : 'moon';
            var href = '/assets/icons/gdy-icons.svg#' + id;
            useEl.setAttribute('href', href);
            useEl.setAttribute('xlink:href', href);
          }
        }
          try{
            document.dispatchEvent(new CustomEvent('gdy:theme', { detail: { dark: dark } }));
          }catch(e){}

        var saved = null;
        try{ saved = localStorage.getItem(KEY); }catch(e){}
        if(saved === 'dark' || saved === 'light'){
          applyTheme(saved);
        }
        themeBtn.addEventListener('click', function(){
          var isDark = document.documentElement.classList.contains('theme-dark');
          var next = isDark ? 'light' : 'dark';
          try{ localStorage.setItem(KEY, next); }catch(e){}
          applyTheme(next);
        });
      }
});
  </script>

<script>
(function(){
  function setupDd(id, closeOthers){
    const root = document.getElementById(id);
    if(!root) return;
    const btn = root.querySelector('.hdr-dd-btn');
    if(!btn) return;

    function isOpen(){ return root.classList.contains('open'); }
    function setOpen(v){
      if(closeOthers) closeOthers(id);
      root.classList.toggle('open', v);
      btn.setAttribute('aria-expanded', v ? 'true' : 'false');
    }

    btn.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      setOpen(!isOpen());
    });

    document.addEventListener('click', function(e){
      if(!root.contains(e.target)) setOpen(false);
    });

    document.addEventListener('keydown', function(e){
      if(e.key === 'Escape') setOpen(false);
    });
  }

  function closeOthers(exceptId){
    ['gdyLangDd','gdyUserDd'].forEach(function(id){
      if(id === exceptId) return;
      const r = document.getElementById(id);
      if(!r) return;
      const b = r.querySelector('.hdr-dd-btn');
      r.classList.remove('open');
      if(b) b.setAttribute('aria-expanded','false');
    });
  }

  setupDd('gdyLangDd', closeOthers);
  setupDd('gdyUserDd', closeOthers);
})();
</script>

</header>

<main id="mainContent">
  <div class="page-shell">
    <div class="container">