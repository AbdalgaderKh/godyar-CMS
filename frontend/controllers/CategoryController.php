<?php
declare(strict_types=1);

// /frontend/controllers/CategoryController.php

// ===================================================
// تحميل bootstrap (الاتصال بقاعدة البيانات + الإعدادات)
// ===================================================
$target = dirname(__DIR__, 2) . '/includes/bootstrap.php';
if (!is_file($target)) {
    http_response_code(500);
    exit('ملف الإعدادات الرئيسي مفقود. يرجى التحقق من وجود: ' . $target);
}
require_once $target;

// ===================================================
// دوال مساعدة عامة
// ===================================================

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * جلب baseUrl للنظام
 */
function gdy_get_base_url(): string
{
    if (function_exists('base_url')) {
        return rtrim(base_url(), '/');
    } elseif (defined('BASE_URL')) {
        return rtrim((string)BASE_URL, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = $scheme . '://' . $host;

    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptDir !== '/' && $scriptDir !== '\\' && $scriptDir !== '.') {
        $base .= $scriptDir;
    }

    return rtrim($base, '/');
}

function gdy_news_url_by_slug(string $slug): string
{
    return gdy_get_base_url() . '/news/id/' . (int)$id;
}

function gdy_category_url(string $slug): string
{
    return gdy_get_base_url() . '/category/' . rawurlencode($slug);
}

function gdy_home_url(): string
{
    // نفضّل الجذر الكامل
    $base = gdy_get_base_url();
    // كثير من الاستضافات تجعل المشروع في الجذر مباشرة، فلا نضيف "/" إضافية
    return $base . '/';
}

// ===================================================
// دوال عرض رسائل بسيطة (احتياطية لو لم توجد View)
// ===================================================

if (!function_exists('gdy_render_message_page')) {
    function gdy_render_message_page(string $title, string $message, int $code = 200): void
    {
        http_response_code($code);
        ?>
        <!doctype html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="utf-8">
            <title><?= h($title) ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css"
                  rel="stylesheet">
        </head>
        <body class="bg-dark text-light">
        <main class="container py-5">
            <div class="alert alert-info rounded-3 shadow-sm bg-opacity-75">
                <h1 class="h4 mb-2"><?= h($title) ?></h1>
                <p class="mb-0"><?= nl2br(h($message)) ?></p>
            </div>
        </main>
        </body>
        </html>
        <?php
        exit;
    }
}

if (!function_exists('gdy_render_error_page')) {
    function gdy_render_error_page(string $title, string $message): void
    {
        gdy_render_message_page($title, $message, 500);
    }
}

if (!function_exists('gdy_render_not_found_page')) {
    function gdy_render_not_found_page(string $title, string $message): void
    {
        gdy_render_message_page($title, $message, 404);
    }
}

if (!function_exists('gdy_render_not_active_page')) {
    function gdy_render_not_active_page(string $title, string $message): void
    {
        gdy_render_message_page($title, $message, 410);
    }
}

/**
 * View احتياطية في حال لم يوجد ملف /frontend/views/category.php
 */
if (!function_exists('gdy_render_default_category_view')) {
    function gdy_render_default_category_view(array $data): void
    {
        $category = $data['category'] ?? null;
        $items    = $data['items'] ?? [];
        $title    = $category['name'] ?? ($data['pageTitle'] ?? 'التصنيف');

        $pageTitle = 'تصنيف: ' . $title;

        ?>
        <!doctype html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="utf-8">
            <title><?= h($pageTitle) ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css"
                  rel="stylesheet">
        </head>
        <body class="bg-dark text-light">
        <main class="container my-4">
            <h1 class="h4 mb-3"><?= h($pageTitle) ?></h1>
            <?php if (empty($items)): ?>
                <div class="alert alert-info rounded-3">
                    لا توجد أخبار ضمن هذا التصنيف حالياً.
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($items as $row): ?>
                        <a class="list-group-item list-group-item-action mb-2 rounded-3"
                           href="<?= h($row['url'] ?? '#') ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h2 class="h6 mb-1"><?= h($row['title'] ?? '') ?></h2>
                                <?php if (!empty($row['published_at']) || !empty($row['created_at'])): ?>
                                    <small class="text-muted">
                                        <?= h(substr((string)($row['published_at'] ?? $row['created_at']), 0, 10)) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
        </body>
        </html>
        <?php
        exit;
    }
}

// ===================================================
// التأكد من اتصال قاعدة البيانات
// ===================================================

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

if (!$pdo || !($pdo instanceof PDO)) {
    gdy_render_error_page(
        'خطأ في الإعدادات',
        'اتصال قاعدة البيانات غير متوفر. يرجى التحقق من إعدادات قاعدة البيانات في ملف bootstrap.'
    );
}

// تجربة اتصال مبسّطة
try {
    $pdo->query('SELECT 1');
} catch (PDOException $e) {
    gdy_render_error_page(
        'خطأ في الاتصال',
        'لا يمكن الاتصال بقاعدة البيانات: ' . $e->getMessage()
    );
}

// ===================================================
// قراءة slug من الرابط
// ===================================================
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
if ($slug === '') {
    gdy_render_not_found_page('القسم غير موجود', 'لم يتم تحديد اسم القسم في الرابط.');
}

// تنظيف slug من أي محارف غريبة
$slug = preg_replace('~[^a-zA-Z0-9\-]~', '', $slug) ?? '';

// ===================================================
// جلب بيانات القسم
// ===================================================
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'categories'")->fetch();
    if (!$tableExists) {
        throw new RuntimeException('جدول categories غير موجود في قاعدة البيانات.');
    }

    $stmt = $pdo->prepare("
        SELECT id, name, slug, description, meta_title, meta_description, parent_id, is_active
        FROM categories
        WHERE slug = :slug
        LIMIT 1
    ");
    $stmt->execute([':slug' => $slug]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // مثال alias قديم (يمكنك تعديلها كما تريد)
    if (!$category && $slug === 'policy') {
        $stmt->execute([':slug' => 'politics']);
        $category = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) {
    error_log('Category Controller Error: ' . $e->getMessage());
    gdy_render_error_page('خطأ في تحميل البيانات', 'حدث خطأ أثناء تحميل بيانات القسم.');
}

if (!$category) {
    gdy_render_not_found_page('القسم غير موجود', 'لم نتمكن من العثور على القسم المطلوب.');
}

if (isset($category['is_active']) && (int)$category['is_active'] === 0) {
    gdy_render_not_active_page('القسم غير مفعل', 'هذا القسم غير مفعل حالياً.');
}

$categoryId          = (int)$category['id'];
$categoryName        = (string)($category['name'] ?? $slug);
$categoryDescription = (string)($category['description'] ?? '');

// ===================================================
// جلب الأخبار الخاصة بالقسم + ميزات إضافية
// ===================================================
$items      = [];
$total      = 0;
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;  // يمكنك ربطه بإعداد من جدول settings إن أحببت
$totalPages = 1;

// ===================================================
// قراءة sort/period من الـ query string (اختياري)
// ===================================================
$sort   = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'latest';
$period = isset($_GET['period']) ? trim((string)$_GET['period']) : 'all';

if (!in_array($sort, ['latest', 'popular'], true)) {
    $sort = 'latest';
}
if (!in_array($period, ['all', 'today', 'week', 'month'], true)) {
    $period = 'all';
}

// فلتر الفترة الزمنية يعتمد على COALESCE(published_at, created_at)
$periodSql = '';
if ($period === 'today') {
    $periodSql = " AND COALESCE(n.published_at, n.created_at) >= CURDATE() ";
} elseif ($period === 'week') {
    $periodSql = " AND COALESCE(n.published_at, n.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
} elseif ($period === 'month') {
    $periodSql = " AND COALESCE(n.published_at, n.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";
}

// ترتيب النتائج
$orderSql = " ORDER BY n.published_at DESC, n.id DESC ";
if ($sort === 'popular') {
    $orderSql = " ORDER BY COALESCE(n.views, 0) DESC, n.published_at DESC, n.id DESC ";
}


try {
    $newsTableExists = $pdo->query("SHOW TABLES LIKE 'news'")->fetch();
    if ($newsTableExists) {
        // إجمالي المقالات
        $cnt = $pdo->prepare("
            SELECT COUNT(*)
            FROM news n
            WHERE n.category_id = :cid
              AND n.status = 'published'
              AND (n.published_at IS NULL OR n.published_at <= NOW())
              {$periodSql}
        ");
        $cnt->execute([':cid' => $categoryId]);
        $total = (int)$cnt->fetchColumn();

        $offset     = ($page - 1) * $perPage;
        $totalPages = max(1, (int)ceil($total / $perPage));

        // جلب المقالات
        $sql = "
            SELECT
                n.id,
                n.title,
                n.slug,
                n.excerpt,
                n.image,
                n.published_at,
                n.created_at,
                n.views
            FROM news n
            WHERE n.status = 'published'
              AND n.category_id = :cid
              AND (n.published_at IS NULL OR n.published_at <= NOW())
              {$periodSql}
            {$orderSql}
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':cid',    $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $perPage,    PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            // صورة افتراضية
            $image = (string)($row['image'] ?? '');
            if ($image === '') {
                $image = '';
            }

            $slugNews = (string)($row['slug'] ?? '');
            // ✅ وضع الـ ID: كل روابط الأخبار تخرج /news/id/{id}
            $url = gdy_get_base_url() . '/news/id/' . (int)$row['id'];

            $items[] = [
                'id'             => (int)$row['id'],
                'title'          => (string)$row['title'],
                'slug'           => $slugNews,
                'excerpt'        => (string)($row['excerpt'] ?? ''),
                'featured_image' => $image,
                'published_at'   => $row['published_at'],
                'created_at'     => $row['created_at'],
                'views'          => (int)($row['views'] ?? 0),
                'url'            => $url,
            ];
        }
    }
} catch (Throwable $e) {
    error_log('News Loading Error: ' . $e->getMessage());
    // نُكمل الصفحة بدون تكسير
}

// ===================================================
// أقسام فرعية & أقسام شقيقة (ميزات إضافية للقالب)
// ===================================================
$subcategories     = [];
$siblingCategories = [];

try {
    // الأقسام الفرعية
    $stmt = $pdo->prepare("
        SELECT id, name, slug, description, sort_order
        FROM categories
        WHERE parent_id = :parent_id
          AND (is_active = 1 OR is_active IS NULL)
        ORDER BY sort_order ASC, name ASC
        LIMIT 10
    ");
    $stmt->execute([':parent_id' => $categoryId]);
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // الأقسام الشقيقة
    $parentId = $category['parent_id'] ?? null;
    if ($parentId) {
        $stmt = $pdo->prepare("
            SELECT id, name, slug, description, sort_order
            FROM categories
            WHERE parent_id = :parent_id
              AND id != :current_id
              AND (is_active = 1 OR is_active IS NULL)
            ORDER BY sort_order ASC, name ASC
            LIMIT 8
        ");
        $stmt->execute([
            ':parent_id'  => $parentId,
            ':current_id' => $categoryId
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, name, slug, description, sort_order
            FROM categories
            WHERE parent_id IS NULL
              AND id != :current_id
              AND (is_active = 1 OR is_active IS NULL)
            ORDER BY sort_order ASC, name ASC
            LIMIT 8
        ");
        $stmt->execute([':current_id' => $categoryId]);
    }
    $siblingCategories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('Subcategories Loading Error: ' . $e->getMessage());
}

// ===================================================
// إعداد بيانات العرض للـ View
// ===================================================

$baseUrl            = gdy_get_base_url();
$homeUrl            = gdy_home_url();
$currentCategoryUrl = gdy_category_url($slug);

// يمكنك استخدام هذه البيانات في View لعمل breadcrumbs
$breadcrumbs = [
    ['label' => 'الرئيسية', 'url' => $homeUrl],
    ['label' => $categoryName, 'url' => $currentCategoryUrl],
];

$viewData = [
    'category'          => $category,
    'items'             => $items,
    'subcategories'     => $subcategories,
    'siblingCategories' => $siblingCategories,
    'totalItems'        => $total,
    'itemsPerPage'      => $perPage,
    'currentPage'       => $page,
    'pages'             => $totalPages,
    'baseUrl'           => $baseUrl,
    'homeUrl'           => $homeUrl,
    'currentCategoryUrl'=> $currentCategoryUrl,
    'breadcrumbs'       => $breadcrumbs,

    // ميتا اختيارية إن أردت استخدامها في الهيدر
    'pageTitle'        => $category['meta_title'] ?? ($categoryName . ' - أخبار'),
    'metaDescription'  => $category['meta_description']
                          ?? ($categoryDescription !== '' ? $categoryDescription : 'أحدث الأخبار في قسم ' . $categoryName),
    'canonicalUrl'     => $currentCategoryUrl . (function() use ($page, $sort, $period) {
        $q = [];
        if ($page > 1) $q['page'] = $page;
        if ($sort !== 'latest') $q['sort'] = $sort;
        if ($period !== 'all') $q['period'] = $period;
        return $q ? ('?' . http_build_query($q)) : '';
    })(),
];

// ===================================================
// استدعاء الـ View
// ===================================================

$viewPath = __DIR__ . '/../views/category.php';
if (is_file($viewPath)) {
    // نجعل متغيرات الـ view متاحة بشكل مباشر
    extract($viewData, EXTR_SKIP);
    require $viewPath;
} else {
    gdy_render_default_category_view($viewData);
}
