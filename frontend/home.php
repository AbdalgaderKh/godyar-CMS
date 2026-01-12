<?php
// frontend/home.php
// الصفحة الرئيسية: سلايدر أخبار مميزة + بلوكات أقسام + فيديوهات مميزة + كتّاب الرأي

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// الاتصال من bootstrap / index
$pdo = gdy_pdo_safe();

// دالة هروب
if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// إعدادات
$siteSettings = [];
if (function_exists('settings_get')) {
    $siteSettings['layout.sidebar_mode'] = settings_get('layout.sidebar_mode', 'visible');
} elseif (isset($GLOBALS['site_settings']) && is_array($GLOBALS['site_settings'])) {
    $siteSettings = $GLOBALS['site_settings'];
}
$sidebarMode   = $siteSettings['layout.sidebar_mode'] ?? 'visible';
$sidebarHidden = ($sidebarMode === 'hidden');

// baseUrl
if (isset($baseUrl) && $baseUrl !== '') {
    $baseUrl = rtrim($baseUrl, '/');
} elseif (function_exists('base_url')) {
    $baseUrl = rtrim(base_url(), '/');
} else {
    $baseUrl = '';
}

/**
 * فلتر زمني للواجهة الرئيسية (آخر الأخبار / شريط عاجل)
 * ?period=all|today|week|month
 */
$period = isset($_GET['period']) ? trim((string)$_GET['period']) : 'all';
if (!in_array($period, ['all', 'today', 'week', 'month'], true)) {
    $period = 'all';
}

/** رابط الخبر */
$buildNewsUrl = function (array $row) use ($baseUrl): string {
    $id   = isset($row['id']) ? (int)$row['id'] : 0;
    $slug = isset($row['slug']) ? trim((string)$row['slug']) : '';

    $prefix = rtrim($baseUrl, '/');
    if ($prefix !== '') {
        $prefix .= '/';
    }

    // ✅ وضع الـ ID: نُصدر دائماً /news/id/{id}
    if ($id > 0) {
        return $prefix . 'news/id/' . $id;
    }
    // fallback للروابط القديمة
    if ($slug !== '') {
        return $prefix . 'news/' . rawurlencode($slug);
    }
    return $prefix . 'news';
};

/** رابط القسم */
$buildCategoryUrl = function (array $row) use ($baseUrl): string {
    $id   = isset($row['id']) ? (int)$row['id'] : 0;
    $slug = isset($row['slug']) ? trim((string)$row['slug']) : '';

    $prefix = rtrim($baseUrl, '/');
    if ($prefix !== '') {
        $prefix .= '/';
    }

    if ($slug !== '') {
        return $prefix . 'category/' . rawurlencode($slug);
    }
    if ($id > 0) {
        return $prefix . 'category/id/' . $id;
    }
    return $prefix . 'category';
};

/** رابط صفحة كاتب الرأي */
$buildOpinionAuthorUrl = function (array $row) use ($baseUrl): string {
    $id   = isset($row['id']) ? (int)$row['id'] : 0;
    $slug = isset($row['slug']) ? trim((string)$row['slug']) : '';

    $prefix = rtrim($baseUrl, '/');
    if ($prefix !== '') {
        $prefix .= '/';
    }

    if ($slug !== '') {
        return $prefix . 'opinion_author.php?slug=' . rawurlencode($slug);
    }
    if ($id > 0) {
        return $prefix . 'opinion_author.php?id=' . $id;
    }
    return $prefix . 'opinion_author.php';
};

/** رابط الصورة */
if (!function_exists('build_image_url')) {
    function build_image_url(string $baseUrl, ?string $path): ?string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        if ($path[0] === '/') {
            return rtrim($baseUrl, '/') . $path;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}

// ==================== جلب التصنيفات ====================
$categories = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY id ASC");
        $categories = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $categories = [];
    }
}

// ==================== تعريف بلوكات الأقسام ====================
// الترتيب المطلوب:
// أخبار عامة → سياسة → اقتصاد → منوعات → رياضة → الرأي → فيديو → إنفوجراف
$blockConfig = [
    'general' => [
        'title'      => 'أخبار عامة',
        'match_name' => ['أخبار', 'اخبار', 'عام'],
        'match_slug' => ['news', 'general'],
    ],
    'politics' => [
        'title'      => 'سياسة',
        'match_name' => ['سياسة'],
        'match_slug' => ['politic'],
    ],
    'economy' => [
        'title'      => 'اقتصاد',
        'match_name' => ['اقتصاد', 'إقتصاد'],
        'match_slug' => ['econom', 'business'],
    ],
    'variety' => [
        'title'      => 'منوعات',
        'match_name' => ['منوعات'],
        'match_slug' => ['variety', 'life', 'life-style'],
    ],
    'sports' => [
        'title'      => 'رياضة',
        'match_name' => ['رياضة', 'رياضه'],
        'match_slug' => ['sport'],
    ],
    'opinion' => [
        'title'      => 'الرأي',
        'match_name' => ['الرأي', 'راي'],
        'match_slug' => ['opinion'],
    ],
    'video' => [
        'title'      => 'فيديو',
        'match_name' => ['فيديو'],
        'match_slug' => ['video'],
    ],
    'infograph' => [
        'title'      => 'إنفوجراف',
        'match_name' => ['إنفوجراف', 'انفوجراف'],
        'match_slug' => ['infograph', 'infographic'],
    ],
];

// مصفوفة البلوكات
$blocks = [];
foreach ($blockConfig as $key => $cfg) {
    $blocks[$key] = [
        'title'    => $cfg['title'],
        'category' => null,
        'news'     => [],
    ];
}

// مطابقة التصنيفات مع البلوكات
foreach ($categories as $catRow) {
    $name = (string)($catRow['name'] ?? '');
    $slug = strtolower((string)($catRow['slug'] ?? ''));

    foreach ($blockConfig as $key => $cfg) {
        if ($blocks[$key]['category'] !== null) continue;

        $matched = false;
        foreach ($cfg['match_name'] as $pat) {
            $pat = (string)$pat;
            if ($pat === '') continue;
            if (mb_strpos($name, $pat, 0, 'UTF-8') !== false) {
                $matched = true;
                break;
            }
        }

        if (!$matched && $slug !== '') {
            foreach ($cfg['match_slug'] as $pat) {
                $pat = strtolower((string)$pat);
                if ($pat === '') continue;
                if (strpos($slug, $pat) !== false) {
                    $matched = true;
                    break;
                }
            }
        }

        if ($matched) {
            $blocks[$key]['category'] = $catRow;
        }
    }
}

// ==================== سلايدر الأخبار المميزة ====================
$sliderNews = [];
if ($pdo instanceof PDO) {
    try {
        $sql = "
            SELECT *
            FROM news
            WHERE status = 'published'
              AND deleted_at IS NULL
              AND (publish_at IS NULL OR publish_at <= NOW())
            ORDER BY featured DESC, views DESC, COALESCE(publish_at, published_at, created_at) DESC
            LIMIT 6
        ";
        $stmt = $pdo->query($sql);
        $sliderNews = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $sliderNews = [];
    }
}

// ==================== آخر الأخبار (مع فلتر الفترة) ====================
$latestNews = [];
if ($pdo instanceof PDO) {
    try {
        // استبعاد مقالات كتّاب الرأي من "آخر الأخبار" (إن وُجد العمود opinion_author_id)
        $excludeOpinionFromLatest = false;
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM news LIKE 'opinion_author_id'");
            if ($chk && $chk->fetchColumn() !== false) {
                $excludeOpinionFromLatest = true;
            }
        } catch (Throwable $e) {
            $excludeOpinionFromLatest = false;
        }

        $dateWhereLatest = '';
        if ($period === 'today') {
            $dateWhereLatest = " AND DATE(created_at) = CURDATE()";
        } elseif ($period === 'week') {
            $dateWhereLatest = " AND created_at >= (CURDATE() - INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $dateWhereLatest = " AND created_at >= (CURDATE() - INTERVAL 30 DAY)";
        }

        $opinionWhereLatest = $excludeOpinionFromLatest
            ? " AND (opinion_author_id IS NULL OR opinion_author_id = 0)"
            : '';

        $sql = "
            SELECT *
            FROM news
            WHERE status = 'published'
              AND deleted_at IS NULL
              AND (publish_at IS NULL OR publish_at <= NOW())
              {$dateWhereLatest}
              {$opinionWhereLatest}
            ORDER BY COALESCE(publish_at, published_at, created_at) DESC
            LIMIT 12
        ";
        $stmt = $pdo->query($sql);
        $latestNews = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $latestNews = [];
    }
}

// ==================== شريط الأخبار العاجلة (Breaking News) ====================
$breakingNews = [];
if ($pdo instanceof PDO) {
    try {
        // استخدام حقل is_breaking إن وجد
        $sql = "
            SELECT *
            FROM news
            WHERE status = 'published'
              AND deleted_at IS NULL
              AND (publish_at IS NULL OR publish_at <= NOW())
              AND is_breaking = 1
            ORDER BY COALESCE(publish_at, published_at, created_at) DESC
            LIMIT 10
        ";
        $stmt = $pdo->query($sql);
        $breakingNews = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // إن لم يوجد أي عاجل، نستخدم آخر الأخبار كبديل
        if (!$breakingNews && $latestNews) {
            $breakingNews = $latestNews;
        }
    } catch (Throwable $e) {
        // لو فشل الاستعلام (مثلاً حقل is_breaking غير موجود)، نستخدم آخر الأخبار كبديل
        $breakingNews = $latestNews;
    }
}

// ==================== إعدادات الطقس + قائمة المدن (لشريط الطقس في الرئيسية) ====================
$weatherSettings = [
    'api_key'         => '',
    'city'            => '',
    'country_code'    => '',
    'units'           => 'metric',
    'is_active'       => 0,
    'refresh_minutes' => 30,
];
$weatherLocations = [];

if ($pdo instanceof PDO) {
    try {
        // إعدادات الطقس (سجل واحد)
        $hasWeatherSettings = false;
        $chk = $pdo->query("SHOW TABLES LIKE 'weather_settings'");
        if ($chk && $chk->fetchColumn()) {
            $hasWeatherSettings = true;
        }

        if ($hasWeatherSettings) {
            $stmt = $pdo->query("SELECT api_key, city, country_code, units, is_active, refresh_minutes FROM weather_settings ORDER BY id ASC LIMIT 1");
            $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (is_array($row) && $row) {
                $weatherSettings = array_merge($weatherSettings, $row);
            }
        }

        // قائمة المدن (اختياري)
        $hasWeatherLocations = false;
        $chk2 = $pdo->query("SHOW TABLES LIKE 'weather_locations'");
        if ($chk2 && $chk2->fetchColumn()) {
            $hasWeatherLocations = true;
        }
        if ($hasWeatherLocations) {
            $stmt = $pdo->query("SELECT id, country_code, city_name FROM weather_locations WHERE is_active = 1 ORDER BY country_name ASC, city_name ASC");
            $weatherLocations = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }
    } catch (Throwable $e) {
        @error_log('[home] weather settings load error: ' . $e->getMessage());
        $weatherLocations = [];
    }
}

// ==================== سلايدر الفيديوهات المميزة (من جدول featured_videos) ====================
$featuredVideos = [];
if ($pdo instanceof PDO) {
    try {
        $sql = "
            SELECT id, title, video_url, description, is_active, created_at
            FROM featured_videos
            WHERE is_active = 1
            ORDER BY created_at DESC
            LIMIT 6
        ";
        $stmt = $pdo->query($sql);
        $featuredVideos = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $featuredVideos = [];
    }
}

// ==================== إعلانات الواجهة الرئيسية (بلوك داخل العمود الرئيسي) ====================
$homeAds = [];
if ($pdo instanceof PDO) {
    try {
        // توافق مع اختلاف أعمدة جدول ads (قد لا يوجد location في بعض النسخ)
        $cols = [];
        try {
            $cst = $pdo->query("SHOW COLUMNS FROM ads");
            $cols = $cst ? $cst->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        } catch (Throwable $e) {
            $cols = [];
        }

        $hasLocation = in_array('location', $cols, true);

        if ($hasLocation) {
            // لا تُظهر إعلان slot الخاص بالفيديو المميز داخل بلوك الإعلانات العام
            $sql = "SELECT id, title, image, url FROM ads WHERE (location IS NULL OR location = '' OR location IN ('home', 'home_main', 'home_ads')) AND location <> 'home_under_featured_video' ORDER BY id DESC LIMIT 4";
        } else {
            $sql = "SELECT id, title, image, url FROM ads ORDER BY id DESC LIMIT 4";
        }

        $stmt = $pdo->query($sql);
        $homeAds = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $homeAds = [];
    }
}

// ==================== كتّاب الرأي + آخر مقال لكل كاتب ====================
$opinionAuthors       = [];
$opinionAuthorsMap    = []; // للخريطة (id => author row)
$opinionAuthorsLast   = [];
$authorImageDefault   = rtrim($baseUrl, '/') . '/assets/images/author-placeholder.png';

if ($pdo instanceof PDO) {
    try {
	        // ملاحظة: إخفاء الكاتب "هيئة التحرير" من بلوك كتّاب الرأي في الصفحة الرئيسية
	        $stmt = $pdo->query("
	            SELECT id, name, slug, avatar, specialization, page_title
	            FROM opinion_authors
	            WHERE is_active = 1
	              AND TRIM(name) <> 'هيئة التحرير'
	            ORDER BY display_order ASC, id DESC
	            LIMIT 50
	        ");
        $opinionAuthors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // خريطة سريعة للوصول لكاتب حسب id
        foreach ($opinionAuthors as $aRow) {
            $aid = isset($aRow['id']) ? (int)$aRow['id'] : 0;
            if ($aid > 0) {
                $opinionAuthorsMap[$aid] = $aRow;
            }
        }

        if ($opinionAuthors) {
            $ids = array_column($opinionAuthors, 'id');
            $ids = array_map('intval', $ids);
            $ids = array_values(array_unique($ids));

            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $sql = "
                    SELECT n.*
                    FROM news n
                    JOIN (
                        SELECT opinion_author_id, MAX(created_at) AS max_created
                        FROM news
                        WHERE opinion_author_id IN ($in)
                          AND status = 'published'
                          AND deleted_at IS NULL
                          AND (publish_at IS NULL OR publish_at <= NOW())
                        GROUP BY opinion_author_id
                    ) t ON n.opinion_author_id = t.opinion_author_id
                       AND n.created_at = t.max_created
                ";
                $stmt2 = $pdo->prepare($sql);
                $stmt2->execute($ids);
                foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $aid = (int)$row['opinion_author_id'];
                    $opinionAuthorsLast[$aid] = $row;
                }
            }
        }
    } catch (Throwable $e) {
        @error_log('[Home Opinion Authors] ' . $e->getMessage());
        $opinionAuthors = [];
        $opinionAuthorsMap = [];
    }
}

// ==================== أخبار كل بلوك (حتى 18 خبر لكل قسم) ====================
if ($pdo instanceof PDO) {
    foreach ($blocks as $key => &$block) {
        if (empty($block['category'])) continue;

        $catId = (int)$block['category']['id'];
        if ($catId <= 0) continue;

        $limit = 18;

        try {
            $sql = "
                SELECT *
                FROM news
                WHERE status = 'published'
                  AND deleted_at IS NULL
                  AND (publish_at IS NULL OR publish_at <= NOW())
                  AND category_id = :cid
                ORDER BY COALESCE(publish_at, published_at, created_at) DESC
                LIMIT {$limit}
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['cid' => $catId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $block['news'] = $rows;
        } catch (Throwable $e) {
            $block['news'] = [];
        }
    }
    unset($block);
}

// ==================== الترند للسايدبار (إن وجد) ====================
$trendingNews = [];
if (class_exists('HomeController')) {
    try {
        $trendingNews = HomeController::getTrendingNews(10);
        if (!is_array($trendingNews)) {
            $trendingNews = [];
        }
    } catch (Throwable $e) {
        $trendingNews = [];
    }
}

// دالة طباعة بلوك كتّاب الرأي (للاستخدام في الواجهة)
$renderOpinionAuthorsBlock = function () use (
    $opinionAuthors,
    $opinionAuthorsLast,
    $authorImageDefault,
    $buildNewsUrl,
    $buildOpinionAuthorUrl,
    $baseUrl
) {
    if (empty($opinionAuthors)) {
        return;
    }
    ?>
    <section id="opinion-authors" class="hm-section hm-opinion-authors" aria-label="<?= h(__('كتّاب الرأي')) ?>">
        <div class="hm-section-header">
            <h2 class="hm-section-title">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <?= h(__('كتّاب الرأي')) ?>
            </h2>
        </div>

        <div class="hm-opinion-authors-grid">
            <?php foreach ($opinionAuthors as $author): ?>
                <?php
                $aid    = (int)$author['id'];
                $name   = (string)($author['name'] ?? '');
	                // طبقة أمان إضافية: لا تعرض "هيئة التحرير" حتى لو وصلت بالخطأ
	                if (trim($name) === 'هيئة التحرير') {
	                    continue;
	                }
                $avatar = trim((string)($author['avatar'] ?? ''));
                if ($avatar === '') {
                    $avatar = $authorImageDefault;
                } else {
                    $avatar = build_image_url($baseUrl, $avatar) ?? $authorImageDefault;
                }
                $spec       = (string)($author['specialization'] ?? '');
                $pageTitle  = (string)($author['page_title'] ?? '');
                $authorUrl  = $buildOpinionAuthorUrl($author);

                $lastNews  = $opinionAuthorsLast[$aid] ?? null;
                $lastUrl   = null;
                $lastTitle = null;
                if ($lastNews) {
                    $lastTitle = (string)($lastNews['title'] ?? '');
                    $lastUrl   = $buildNewsUrl($lastNews);
                }
                ?>
                <article class="hm-opinion-author-card">
                    <a href="<?= h($authorUrl) ?>" class="hm-opinion-author-avatar">
                        <img src="<?= h($avatar) ?>"
                             alt="<?= h($name) ?>"
                             onerror="this.src='<?= h($authorImageDefault) ?>';this.onerror=null;">
                    </a>
                    <div class="hm-opinion-author-body">
                        <div class="hm-opinion-author-name">
                            <a href="<?= h($authorUrl) ?>" style="color:inherit;text-decoration:none;">
                                <?= h($name) ?>
                            </a>
                        </div>

                        <?php if ($pageTitle !== ''): ?>
                            <div class="hm-opinion-author-spec">
                                <?= h($pageTitle) ?>
                            </div>
                        <?php elseif ($spec !== ''): ?>
                            <div class="hm-opinion-author-spec">
                                <?= h($spec) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($lastUrl && $lastTitle): ?>
                            <a href="<?= h($lastUrl) ?>" class="hm-opinion-author-last">
                                آخر مقال:
                                <?= h(mb_substr($lastTitle, 0, 80, 'UTF-8')) ?>
                                <?= mb_strlen($lastTitle, 'UTF-8') > 80 ? '…' : '' ?>
                            </a>
                        <?php else: ?>
                            <div class="hm-opinion-author-last muted">
                                لا توجد مقالات مسجلة بعد
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
};

// استخدام الواجهة الحديثة للصفحة الرئيسية
require __DIR__ . '/views/home_modern.php';
