<?php
// frontend/index.php
// نقطة الدخول لواجهة الموقع (الصفحة الرئيسية)

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// لو تم استدعاء هذا الملف مباشرة (بدون public index)
// نتأكد من تحميل البوتستراب
if (!defined('ROOT_PATH')) {
    $bootstrapPath = __DIR__ . '/../includes/bootstrap.php';
    if (is_file($bootstrapPath)) {
        require_once $bootstrapPath;
    }
}

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// قراءة هوية الموقع من الإعدادات إن توفرت
$siteName    = 'Godyar News';
$siteTagline = 'منصة إخبارية متكاملة';

if (function_exists('settings_get')) {
    $siteName    = settings_get('site.name', $siteName);
    $siteTagline = settings_get('site.desc', $siteTagline);
} elseif (isset($GLOBALS['site_settings']) && is_array($GLOBALS['site_settings'])) {
    $siteName    = $GLOBALS['site_settings']['site_name']    ?? $siteName;
    $siteTagline = $GLOBALS['site_settings']['site_tagline'] ?? $siteTagline;
}

// يمكن استخدامه لاحقاً إن احتجت
$pageTitle = $siteName;

// مسارات ملفات الهيدر/الفوتر/المحتوى
$headerFile = __DIR__ . '/views/partials/header.php';
$footerFile = __DIR__ . '/views/partials/footer.php';
$homeFile   = __DIR__ . '/home.php';

// =================== الهيدر ===================
if (is_file($headerFile)) {
    // الهيدر يستخدم $siteName و $siteTagline و $baseUrl إن وُجدت
    require $headerFile;
} else {
    // هيدر بديل بسيط في حال عدم وجود الهيدر الاحترافي
    ?>
    <!doctype html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <title><?= h($pageTitle) ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
    </head>
    <body class="bg-light text-dark">
    <main class="container my-4">
    <?php
}

// =================== محتوى الصفحة الرئيسية ===================
if (is_file($homeFile)) {
    require $homeFile;   // هذا الملف يطبع <div class="row ..."> فقط (بدون <html> إلخ)
} else {
    ?>
    <div class="alert alert-warning mt-3">
        ملف <code>frontend/home.php</code> غير موجود.
    </div>
    <?php
}

// =================== الفوتر ===================
if (is_file($footerFile)) {
    require $footerFile;
} else {
    // إغلاق العلامات في حال الهيدر البديل
    ?>
    </main>
    <footer class="border-top mt-4 py-3 text-center small text-muted">
        &copy; <?= date('Y') ?> <?= h($siteName) ?>
    </footer>
    </body>
    </html>
    <?php
}
