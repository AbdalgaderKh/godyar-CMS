<?php
declare(strict_types=1);

// /godyar/frontend/controllers/PageController.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/TemplateEngine.php';
require_once __DIR__ . '/../../includes/site_settings.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// الاتصال بـ PDO
$pdo = gdy_pdo_safe();
// Schema-tolerant helper: check if column exists (cached)
if (!function_exists('gdy_has_column')) {
    function gdy_has_column(PDO $pdo, string $table, string $column): bool {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return (bool)$cache[$key];
        }
        try {
            $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($db === '') {
                return $cache[$key] = false;
            }
            $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
            $stmt->execute([$db, $table, $column]);
            return $cache[$key] = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return $cache[$key] = false;
        }
    }
}
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo 'تعذّر الاتصال بقاعدة البيانات.';
    exit;
}

// ============= تحميل إعدادات وهوية الواجهة =============
$settings        = gdy_load_settings($pdo);
$frontendOptions = gdy_prepare_frontend_options($settings);

// استخراج المتغيرات كما في HomeController
extract($frontendOptions, EXTR_OVERWRITE);

// حالة تسجيل الدخول
$isLoggedIn = !empty($_SESSION['user']) && !empty($_SESSION['user']['role']) && $_SESSION['user']['role'] !== 'guest';
$isAdmin    = $isLoggedIn && ($_SESSION['user']['role'] === 'admin');

// ============= تحميل تصنيفات الهيدر =============
$headerCategories = [];
try {
    $orderBy = gdy_has_column($pdo, 'categories', 'sort_order') ? 'sort_order ASC, id ASC' : (gdy_has_column($pdo, 'categories', 'name') ? 'name ASC, id ASC' : 'id ASC');
    $stmt = $pdo->query("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY {$orderBy} LIMIT 8");
$headerCategories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[PageController] categories load error: ' . $e->getMessage());
    $headerCategories = [];
}

// ============= قراءة الـ slug المطلوب =============
$slug = trim((string)($_GET['slug'] ?? ''));

if ($slug === '') {
    // slug مفقود → نعتبرها 404 منطقية
    $pageNotFound = true;
    $page = [
        'title'      => 'الصفحة غير موجودة',
        'content'    => '<p>عذراً، الصفحة التي تحاول الوصول إليها غير متوفرة.</p>',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => null,
    ];
} else {
    $pageNotFound = false;
    $page         = null;

    try {
        $sql = "SELECT id, title, slug, content, status, created_at, updated_at
                FROM pages
                WHERE slug = :slug AND status = 'published'
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;


        // Fallback static pages (about/privacy) if not in DB
        if (!$page && in_array($slug, ['about', 'privacy'], true)) {
            $staticView = __DIR__ . '/../views/page/static/' . $slug . '.php';
            if (is_file($staticView)) {
                ob_start();
                include $staticView;
                $html = (string)ob_get_clean();

                $pageNotFound = false;
                $page = [
                    'title'      => $slug === 'about' ? 'من نحن' : 'سياسة الخصوصية',
                    'content'    => $html,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => null,
                ];
            }
        }

        if (!$page) {
            $pageNotFound = true;
            $page = [
                'title'      => 'الصفحة غير موجودة',
                'content'    => '<p>عذراً، الصفحة التي تحاول الوصول إليها غير متوفرة أو غير منشورة.</p>',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => null,
            ];
            http_response_code(404);
        }
    } catch (Throwable $e) {
        @error_log('[PageController] page load error: ' . $e->getMessage());
        $pageNotFound = true;
        $page = [
            'title'      => 'خطأ في تحميل الصفحة',
            'content'    => '<p>حدث خطأ أثناء محاولة تحميل الصفحة. الرجاء المحاولة لاحقاً.</p>',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
        ];
        http_response_code(500);
    }
}

// ============= روابط أساسية =============
$baseUrl = base_url(); // من bootstrap.php

// ============= تجهيز البيانات للقالب =============
$template = new TemplateEngine();

$templateData = [
    // إعدادات أساسية
    'siteName'          => $siteName        ?? 'Godyar News',
    'siteTagline'       => $siteTagline     ?? 'منصة إخبارية متكاملة',
    'siteLogo'          => $siteLogo        ?? '',
    'primaryColor'      => $primaryColor    ?? '#0ea5e9',
    'primaryDark'       => $primaryDark     ?? '#0369a1',
    'baseUrl'           => $baseUrl,
    'themeClass'        => $themeClass      ?? 'theme-default',

    // نصوص عامة
    'searchPlaceholder'    => $searchPlaceholder    ?? __('ابحث عن خبر أو موضوع...'),
    'homeLatestTitle'      => $homeLatestTitle      ?? '',
    'homeFeaturedTitle'    => $homeFeaturedTitle    ?? '',
    'homeTabsTitle'        => $homeTabsTitle        ?? '',
    'homeMostReadTitle'    => $homeMostReadTitle    ?? '',
    'homeMostCommentedTitle'=> $homeMostCommentedTitle ?? '',
    'homeRecommendedTitle' => $homeRecommendedTitle ?? '',
    'carbonBadgeText'      => $carbonBadgeText      ?? '',

    // حالة المستخدم
    'isLoggedIn' => $isLoggedIn,
    'isAdmin'    => $isAdmin,

    // التصنيفات للهيدر
    'headerCategories' => $headerCategories,

    // إعدادات إضافية ربما تستخدم في الفوتر/أماكن أخرى
    'enableMostRead'      => $enableMostRead      ?? false,
    'enableMostCommented' => $enableMostCommented ?? false,
    'showCarbonBadge'     => $showCarbonBadge     ?? false,

    // بيانات الصفحة نفسها
    'page'         => $page,
    'pageNotFound' => $pageNotFound,
];

// عرض القالب الخاص بالصفحات
$template->render(__DIR__ . '/../views/page/content.php', $templateData);
