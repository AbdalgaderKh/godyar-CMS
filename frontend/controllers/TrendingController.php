<?php
declare(strict_types=1);

// /godyar/frontend/controllers/TrendingController.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/TemplateEngine.php';

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// ============= تحميل الإعدادات من جدول settings =============
$settings = [];
try {
    if ($pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
        foreach ($stmt as $row) {
            $settings[$row['key']] = $row['value'];
        }
    }
} catch (Throwable $e) {
    @error_log('[Trending] settings load error: ' . $e->getMessage());
}

function setting(array $settings, string $key, $default = ''): string {
    return isset($settings[$key]) && $settings[$key] !== ''
        ? (string)$settings[$key]
        : (string)$default;
}

function setting_int(array $settings, string $key, int $default, int $min, int $max): int {
    if (!isset($settings[$key]) || $settings[$key] === '') {
        return $default;
    }
    $v = (int)$settings[$key];
    if ($v < $min) $v = $min;
    if ($v > $max) $v = $max;
    return $v;
}

// قيم افتراضية لو ما في إعدادات
$siteName     = setting($settings, 'site_name', 'Godyar News');
$siteTagline  = setting($settings, 'site_tagline', 'منصة إخبارية متكاملة');
$siteLogo     = setting($settings, 'site_logo', '');
$primaryColor = setting($settings, 'primary_color', '#0ea5e9');

// نصوص واجهة قابلة للتعديل من صفحة الإعدادات
$searchPlaceholder        = setting($settings, 'search_placeholder', __('ابحث عن خبر أو موضوع...'));
$carbonBadgeText          = setting($settings, 'carbon_badge_text', 'نلتزم بالمساعدة على تقليل انبعاث الكربون في بنيتنا التقنية.');

// إعدادات ميزات الواجهة
$showCarbonBadge     = setting($settings, 'show_carbon_badge',     '1') === '1';

// حساب لون داكن من اللون الأساسي للاستخدام في التدرجات
$primaryHex = ltrim($primaryColor, '#');
if (strlen($primaryHex) === 6) {
    $r = max(0, hexdec(substr($primaryHex, 0, 2)) - 30);
    $g = max(0, hexdec(substr($primaryHex, 2, 2)) - 30);
    $b = max(0, hexdec(substr($primaryHex, 4, 2)) - 30);
    $primaryDark = sprintf('#%02x%02x%02x', $r, $g, $b);
} else {
    $primaryDark = '#0369a1';
}

// ثيم الواجهة (يؤثر على بعض الألوان العامة)
$frontendTheme = setting($settings, 'frontend_theme', 'default');
$themeClass = 'theme-default';
if ($frontendTheme === 'theme-ocean') {
    $themeClass = 'theme-ocean';
} elseif ($frontendTheme === 'theme-sunset') {
    $themeClass = 'theme-sunset';
}

// ============= حالة تسجيل الدخول =============
$isLoggedIn = !empty($_SESSION['user']) && !empty($_SESSION['user']['role']) && $_SESSION['user']['role'] !== 'guest';
$isAdmin    = $isLoggedIn && ($_SESSION['user']['role'] === 'admin');

// ============= تحميل أقسام الهيدر من جدول categories =============
$headerCategories = [];
try {
    if ($pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY id ASC LIMIT 6");
        $headerCategories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    @error_log('[Trending] categories load error: ' . $e->getMessage());
    $headerCategories = [];
}

// ============= تحميل الأخبار الشائعة (الأكثر مشاهدة) =============
$trendingNews = [];
try {
    if ($pdo instanceof PDO) {
        $sql = "SELECT id, title, excerpt, featured_image, published_at, views
                FROM news 
                WHERE status = 'published' 
                ORDER BY views DESC, published_at DESC 
                LIMIT 20";
        $stmt = $pdo->query($sql);
        $trendingNews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    @error_log('[Trending] trendingNews load error: ' . $e->getMessage());
    $trendingNews = [];
}

// ============= روابط أساسية =============
$baseUrl = base_url();

$newsUrl = function(array $row) use ($baseUrl): string {
    $slug = isset($row['slug']) ? (string)$row['slug'] : (string)($row['id'] ?? '');
    $slug = trim($slug);
    if ($slug === '') {
        $slug = (string)($row['id'] ?? '');
    }
    return $baseUrl . '/news/id/' . (int)($row['id'] ?? 0);
};

$contactUrl  = $baseUrl . '/page/contact';
$teamUrl     = $baseUrl . '/page/team';
$sendNewsUrl = $baseUrl . '/page/send-news';
$addressUrl  = $baseUrl . '/page/address';
$privacyUrl  = $baseUrl . '/page/privacy';
$termsUrl    = $baseUrl . '/page/terms';
$archiveUrl  = $baseUrl . '/archive/' . date('Y');

// ============= استخدام نظام القوالب =============
$template = new TemplateEngine();

// تمرير جميع المتغيرات للقالب
$templateData = [
    // الإعدادات الأساسية
    'siteName' => $siteName,
    'siteTagline' => $siteTagline,
    'siteLogo' => $siteLogo,
    'primaryColor' => $primaryColor,
    'primaryDark' => $primaryDark,
    'baseUrl' => $baseUrl,
    'themeClass' => $themeClass,
    
    // نصوص الواجهة
    'searchPlaceholder' => $searchPlaceholder,
    'carbonBadgeText' => $carbonBadgeText,
    
    // بيانات المستخدم
    'isLoggedIn' => $isLoggedIn,
    'isAdmin' => $isAdmin,
    
    // التصنيفات
    'headerCategories' => $headerCategories,
    
    // الأخبار
    'trendingNews' => $trendingNews,
    
    // الإعدادات
    'showCarbonBadge' => $showCarbonBadge,
    
    // الدوال
    'newsUrl' => $newsUrl,
    
    // الروابط
    'contactUrl' => $contactUrl,
    'teamUrl' => $teamUrl,
    'sendNewsUrl' => $sendNewsUrl,
    'addressUrl' => $addressUrl,
    'privacyUrl' => $privacyUrl,
    'termsUrl' => $termsUrl,
    'archiveUrl' => $archiveUrl,
];

// تحميل القالب مع المحتوى
$template->render(__DIR__ . '/../views/trending/content.php', $templateData);