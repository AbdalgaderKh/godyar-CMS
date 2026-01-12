<?php
declare(strict_types=1);

/**
 * Godyar Front Controller (Homepage)
 * أي زيارة للجذر / أو /index.php تمر من هنا
 */

// تحميل البوتستراب العام (الاتصال بقاعدة البيانات + الجلسات + إلخ)
require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

// سنحاول تحميل جميع الإعدادات من جدول settings
$rawSettings  = [];
$siteSettings = [];

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT `key`, `value` FROM `settings`");
        if ($stmt) {
            // ترجع مصفوفة بالشكل ['site.name' => 'xxx', 'site.desc' => 'yyy', ...]
            $rawSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        }
    } catch (Throwable $e) {
        @error_log('[front.index] settings load error: ' . $e->getMessage());
        $rawSettings = [];
    }
}

/**
 * تجهيز مصفوفة إعدادات سهلة الاستخدام لباقي الملفات
 * (الهيدر، السايدبار، الكنترولرات...)
 */
$siteSettings = [
    // هوية الموقع
    'site_name'    => $rawSettings['site.name']    ?? 'Godyar News',
    'site_desc'    => $rawSettings['site.desc']    ?? 'منصة إخبارية متكاملة',
    'site_logo'    => $rawSettings['site.logo']    ?? '',
    'site_email'   => $rawSettings['site.email']   ?? '',
    'site_phone'   => $rawSettings['site.phone']   ?? '',
    'site_address' => $rawSettings['site.address'] ?? '',
    'site_url'     => $rawSettings['site.url']     ?? '',

    // لغة / توقيت
    'site_locale'   => $rawSettings['site.locale']   ?? 'ar',
    'site_timezone' => $rawSettings['site.timezone'] ?? 'Asia/Riyadh',

    // ألوان/ثيم
    'theme_primary'      => $rawSettings['theme.primary']      ?? '#111111',
    // لو مستقبلاً حفظت قيمة theme.primary_dark في settings ستُقرأ، وإلا يستخدم الافتراضي
    'theme_primary_dark' => $rawSettings['theme.primary_dark'] ?? '#0369a1',
    'theme_accent'       => $rawSettings['theme.accent']       ?? '#111111',
    'theme_front'        => $rawSettings['theme.front']        ?? 'default',
    'theme_header_style' => $rawSettings['theme.header_style'] ?? 'dark',
    'theme_footer_style' => $rawSettings['theme.footer_style'] ?? 'dark',
    'theme_container'    => $rawSettings['theme.container']    ?? 'boxed',

    // إعداد السايدبار (نقطة مهمّة لمشكلتك)
    // القيمة في قاعدة البيانات: layout.sidebar_mode => visible / hidden
    'layout_sidebar_mode' => $rawSettings['layout.sidebar_mode'] ?? 'visible',
];

// نشر الإعدادات في متغير عام ليستخدمه أي ملف (الهيدر / السايدبار / الكنترولرات...)
$GLOBALS['site_settings'] = $siteSettings;

/**
 * متغيرات مريحة يمكن للهيدر أن يستخدمها مباشرة
 * (header.php عندك أصلاً يقرأ $siteName, $siteTagline, $siteLogo, $primaryColor, $primaryDark, $themeClass)
 */
$siteName     = $siteSettings['site_name'];
$siteTagline  = $siteSettings['site_desc'];
$siteLogo     = $siteSettings['site_logo'];
$primaryColor = $siteSettings['theme_primary'];
$primaryDark  = $siteSettings['theme_primary_dark'];
$themeClass   = 'theme-' . ($siteSettings['theme_front'] ?: 'default');

// تأمين baseUrl عالمي (لمن يحتاجه في القوالب)
if (!isset($GLOBALS['baseUrl'])) {
    if (function_exists('base_url')) {
        $GLOBALS['baseUrl'] = rtrim(base_url(), '/');
    } else {
        $GLOBALS['baseUrl'] = '';
    }
}

// استدعاء الكنترولر الخاص بالصفحة الرئيسية (يحضر البيانات فقط)
require_once __DIR__ . '/frontend/controllers/HomeController.php';

// استدعاء واجهة الفرونت إند التي تطبع الهيدر + المحتوى + الفوتر
require_once __DIR__ . '/frontend/index.php';

exit;
