<?php
declare(strict_types=1);

// /godyar/frontend/controllers/NewsController.php
// عرض خبر مفرد بنفس هوية الصفحة الرئيسية (HomeController)

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/TemplateEngine.php';
require_once __DIR__ . '/../../includes/site_settings.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// ============= تحميل الإعدادات من جدول settings =============
$settings        = gdy_load_settings($pdo);
$frontendOptions = gdy_prepare_frontend_options($settings);

// إتاحة نفس المتغيرات المستخدمة في HomeController
extract($frontendOptions, EXTR_OVERWRITE);

// ============= دالة لعرض الإعلانات =============
function display_ad($location = 'content_top') {
    global $pdo, $baseUrl;
    
    if (!$pdo) {
        return '<!-- No DB connection -->';
    }
    
    try {
        $sql = "SELECT * FROM ads WHERE location = :location AND is_active = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':location' => $location]);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ad) {
            return "<!-- No active ad found for location: $location -->";
        }
        
        // بناء HTML الإعلان
        $html = '<div class="ad-container text-center my-4 p-3 border rounded">';
        
        if ($ad['ad_type'] === 'text' && !empty($ad['content'])) {
            // إعلان نصي منسق
            $html .= '<div class="ad-content">' . $ad['content'] . '</div>';
        } else {
            // إعلان مصور تقليدي
            if (!empty($ad['image_url'])) {
                $html .= '<img src="' . h($ad['image_url']) . '" class="img-fluid rounded mb-2" alt="' . h($ad['title']) . '" style="max-height: 200px;">';
            }
            
            if (!empty($ad['title'])) {
                $html .= '<h5 class="ad-title">' . h($ad['title']) . '</h5>';
            }
            
            if (!empty($ad['description'])) {
                $html .= '<p class="ad-description text-muted">' . h($ad['description']) . '</p>';
            }
        }
        
        if (!empty($ad['target_url'])) {
            $html .= '<a href="' . $baseUrl . '/track_click.php?ad_id=' . $ad['id'] . '&redirect=' . urlencode($ad['target_url']) . '" 
                         target="_blank" class="btn btn-primary btn-sm">
                         انقر هنا للمزيد
                      </a>';
        }
        
        $html .= '</div>';
        return $html;
        
    } catch (Exception $e) {
        return "<!-- Ad Error: " . $e->getMessage() . " -->";
    }
}

// دوال مساعدة متوافقة (إن لم تكن معرّفة)
if (!function_exists('setting')) {
    function setting(array $settings, string $key, $default = ''): string {
        return gdy_setting($settings, $key, $default);
    }
}
if (!function_exists('setting_int')) {
    function setting_int(array $settings, string $key, int $default, int $min, int $max): int {
        return gdy_setting_int($settings, $key, $default, $min, $max);
    }
}

// ============= حالة تسجيل الدخول من السيشن =============
$currentUser = $_SESSION['user'] ?? null;

$isLoggedIn = is_array($currentUser)
    && !empty($currentUser['role'])
    && $currentUser['role'] !== 'guest'
    && (($currentUser['status'] ?? 'active') === 'active');

$isAdmin = $isLoggedIn && (
    ($currentUser['role'] ?? '') === 'admin'
    || (int)($currentUser['is_admin'] ?? 0) === 1
);

// ============= وضع المعاينة (Preview) =============
$isPreviewRequested = isset($_GET['preview']) && (string)$_GET['preview'] === '1';
if ($isPreviewRequested && !$isAdmin) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}


// ============= تحميل أقسام الهيدر من جدول categories ==========
$headerCategories = [];

try {
    if ($pdo instanceof PDO) {

        // تحقق من وجود عمود status في جدول news (لتجنب كسر قواعد بيانات قديمة)
        $newsHasStatusColumn = false;
        try {
            $newsCols = $pdo->query('SHOW COLUMNS FROM news')->fetchAll(PDO::FETCH_COLUMN, 0);
            $newsHasStatusColumn = is_array($newsCols) && in_array('status', $newsCols, true);
        } catch (Throwable $e) {
            $newsHasStatusColumn = false;
        }

        $catNameColumn = 'name';
        $cols          = [];

        try {
            $colStmt = $pdo->query('SHOW COLUMNS FROM categories');
            $cols    = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (Throwable $e) {
            @error_log('[News] categories columns error: ' . $e->getMessage());
        }

        if (!in_array('name', $cols, true)) {
            if (in_array('category_name', $cols, true)) {
                $catNameColumn = 'category_name';
            } elseif (in_array('cat_name', $cols, true)) {
                $catNameColumn = 'cat_name';
            } elseif (in_array('title', $cols, true)) {
                $catNameColumn = 'title';
            } else {
                $catNameColumn = null;
            }
        }

        if ($catNameColumn !== null) {
            $sql = "SELECT id, {$catNameColumn} AS name, slug FROM categories ORDER BY id ASC LIMIT 6";
        } else {
            $sql = "SELECT id, slug FROM categories ORDER BY id ASC LIMIT 6";
        }

        $stmt             = $pdo->query($sql);
        $headerCategories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    @error_log('[News] categories load error: ' . $e->getMessage());
    $headerCategories = [];
}

// ============= قراءة الباراميتر من الرابط (slug أو id) ==========
$param = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';

if ($param === '') {
    header('HTTP/1.1 404 Not Found');
    echo 'الخبر غير موجود';
    exit;
}

$isNumeric = ctype_digit($param);
$id        = $isNumeric ? (int)$param : 0;
$slug      = $isNumeric ? '' : $param;

$news          = null;
$related       = [];
$tags          = [];
$latestNews    = [];
$mostReadNews  = [];
$readingTime   = null;

// دالة مساعدة للتأكد من وجود جدول (مع حماية من التكرار)
if (!function_exists('gdy_table_exists')) {
    function gdy_table_exists(PDO $pdo, string $table): bool {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE :t');
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

// دالة لحساب وقت القراءة
function calculate_reading_time($content) {
    $plain = trim(strip_tags((string)$content));
    if ($plain === '') {
        return 1;
    }
    $chars = mb_strlen($plain, 'UTF-8');
    return max(1, (int)ceil($chars / 800)); // تقريبًا 800 حرف في الدقيقة
}

try {
    if ($pdo instanceof PDO) {

        $cacheKey = 'news_show_' . ($isNumeric ? 'id_' . $id : 'slug_' . $slug);
        $result   = null;

        $useCache = class_exists('Cache') && !$isPreviewRequested;

        if ($useCache) {
            $result = Cache::remember($cacheKey, 300, function () use ($pdo, $slug, $id, $isNumeric, $isPreviewRequested, $newsHasStatusColumn) {

                if ($isNumeric && $id > 0) {
                    $where = '(n.id = :id OR n.slug = :slug)';


                    if (!$isPreviewRequested && $newsHasStatusColumn) {
                        $where .= " AND n.status = 'published'";
                    }
                } else {
                    $where = 'n.slug = :slug';


                if (!$isPreviewRequested && $newsHasStatusColumn) {
                    $where .= " AND n.status = 'published'";
                }
                }

                $sql = "
                    SELECT n.*,
                           c.name AS category_name,
                           c.slug AS category_slug
                    FROM news n
                    LEFT JOIN categories c ON c.id = n.category_id
                    WHERE {$where}
                    LIMIT 1
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':slug' => $slug,
                    ':id'   => $id,
                ]);

                $newsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if (!$newsRow) {
                    return ['news' => null, 'related' => [], 'tags' => [], 'latestNews' => [], 'mostReadNews' => []];
                }

                // أخبار ذات صلة (أولوية: الوسوم -> القسم -> كلمات العنوان)
                $related = [];
                try {
                    $limit = 6;
                    $nid   = (int)$newsRow['id'];
                    $pickedIds = [];

                    // 1) حسب الوسوم (إذا كانت متوفرة)
                    $tagIds = [];
                    if (
                        !empty($newsRow['id'])
                        && gdy_table_exists($pdo, 'news_tags')
                        && gdy_table_exists($pdo, 'tags')
                    ) {
                        $stTagIds = $pdo->prepare("SELECT tag_id FROM news_tags WHERE news_id = :nid");
                        $stTagIds->execute([':nid' => $nid]);
                        $tagIds = array_values(array_unique(array_map('intval', $stTagIds->fetchAll(PDO::FETCH_COLUMN) ?: [])));
                    }

                    if (!empty($tagIds)) {
                        // IN placeholders
                        $in = implode(',', array_fill(0, count($tagIds), '?'));
                        $sql = "
                            SELECT n.id, n.title, n.slug, n.published_at, n.featured_image, n.excerpt,
                                   COUNT(*) AS match_score
                            FROM news n
                            INNER JOIN news_tags nt ON nt.news_id = n.id
                            WHERE n.status = 'published'
                              AND n.id != ?
                              AND nt.tag_id IN ($in)
                            GROUP BY n.id
                            ORDER BY match_score DESC, n.published_at DESC
                            LIMIT $limit
                        ";
                        $st = $pdo->prepare($sql);
                        $params = array_merge([$nid], $tagIds);
                        $st->execute($params);
                        $related = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    }

                    foreach ($related as $r) { $pickedIds[(int)($r['id'] ?? 0)] = true; }

                    // 2) تكملة حسب القسم
                    if (count($related) < $limit && !empty($newsRow['category_id'])) {
                        $need = $limit - count($related);
                        $sql = "
                            SELECT id, title, slug, published_at, featured_image, excerpt
                            FROM news
                            WHERE status = 'published'
                              AND category_id = :cid
                              AND id != :id
                            ORDER BY published_at DESC
                            LIMIT $need
                        ";
                        $stmtRel = $pdo->prepare($sql);
                        $stmtRel->execute([
                            ':cid' => (int)$newsRow['category_id'],
                            ':id'  => $nid,
                        ]);
                        $more = $stmtRel->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        foreach ($more as $r) {
                            $rid = (int)($r['id'] ?? 0);
                            if ($rid > 0 && empty($pickedIds[$rid])) {
                                $pickedIds[$rid] = true;
                                $related[] = $r;
                                if (count($related) >= $limit) break;
                            }
                        }
                    }

                    // 3) تكملة حسب كلمات العنوان
                    if (count($related) < $limit && !empty($newsRow['title'])) {
                        $need = $limit - count($related);
                        $title = (string)$newsRow['title'];

                        $words = preg_split('~\s+~u', trim($title)) ?: [];
                        $kw = [];
                        foreach ($words as $w) {
                            $w = trim($w);
                            if (mb_strlen($w, 'UTF-8') < 4) continue;
                            // استبعد كلمات شائعة جدًا
                            if (in_array($w, ['هذا','هذه','ذلك','تلك','على','من','إلى','في','عن','مع','بين','بعد','قبل','أمام','خلال','حول','حتى','وقد','كما'], true)) continue;
                            $kw[] = $w;
                            if (count($kw) >= 6) break;
                        }
                        $kw = array_values(array_unique($kw));

                        if (!empty($kw)) {
                            $where = [];
                            $params = [':id' => $nid];
                            foreach ($kw as $i => $w) {
                                $k = ':k' . $i;
                                $where[] = "title LIKE $k";
                                $params[$k] = '%' . $w . '%';
                            }
                            $sql = "
                                SELECT id, title, slug, published_at, featured_image, excerpt
                                FROM news
                                WHERE status = 'published'
                                  AND id != :id
                                  AND (" . implode(' OR ', $where) . ")
                                ORDER BY published_at DESC
                                LIMIT $need
                            ";
                            $st = $pdo->prepare($sql);
                            $st->execute($params);
                            $more = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            foreach ($more as $r) {
                                $rid = (int)($r['id'] ?? 0);
                                if ($rid > 0 && empty($pickedIds[$rid])) {
                                    $pickedIds[$rid] = true;
                                    $related[] = $r;
                                    if (count($related) >= $limit) break;
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // لا تكسر الصفحة
                    $related = $related ?? [];
                }

                // أحدث الأخبار
                $latestNews = [];
                $stmtLatest = $pdo->query("
                    SELECT id, title, slug, published_at, featured_image
                    FROM news
                    WHERE status = 'published'
                    ORDER BY published_at DESC
                    LIMIT 6
                ");
                $latestNews = $stmtLatest->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // أكثر الأخبار قراءة
                $mostReadNews = [];
                $stmtMostRead = $pdo->query("
                    SELECT id, title, slug, published_at, views, featured_image
                    FROM news
                    WHERE status = 'published'
                    ORDER BY views DESC, published_at DESC
                    LIMIT 6
                ");
                $mostReadNews = $stmtMostRead->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // وسوم الخبر لو الجداول موجودة
                $tags = [];
                if (!empty($newsRow['id'])
                    && gdy_table_exists($pdo, 'tags')
                    && gdy_table_exists($pdo, 'news_tags')
                ) {
                    $stmtTags = $pdo->prepare("
                        SELECT t.id, t.name, t.slug
                        FROM tags t
                        INNER JOIN news_tags nt ON nt.tag_id = t.id
                        WHERE nt.news_id = :nid
                        ORDER BY t.name ASC
                    ");
                    $stmtTags->execute([':nid' => (int)$newsRow['id']]);
                    $tags = $stmtTags->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                if (!$isPreviewRequested) {
// زيادة المشاهدات
                try {
                    $pdo->prepare('UPDATE news SET views = views + 1 WHERE id = :id')
                        ->execute([':id' => (int)$newsRow['id']]);
                } catch (Throwable $e) {
                    @error_log('[NewsController] views update error: ' . $e->getMessage());
                }
}


                return [
                    'news'         => $newsRow,
                    'related'      => $related,
                    'tags'         => $tags,
                    'latestNews'   => $latestNews,
                    'mostReadNews' => $mostReadNews,
                ];
            });
        }

        if ($result === null) {
            if ($isNumeric && $id > 0) {
                $where = '(n.id = :id OR n.slug = :slug)';
            } else {
                $where = 'n.slug = :slug';
            }

            $sql = "
                SELECT n.*,
                       c.name AS category_name,
                       c.slug AS category_slug
                FROM news n
                LEFT JOIN categories c ON c.id = n.category_id
                WHERE {$where}
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':slug' => $slug,
                ':id'   => $id,
            ]);

            $news = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($news) {
                // أخبار ذات صلة
                if (!empty($news['category_id'])) {
                    $stmtRel = $pdo->prepare("
                        SELECT id, title, slug, published_at, featured_image, excerpt
                        FROM news
                        WHERE category_id = :cid
                          AND id != :id
                        ORDER BY published_at DESC
                        LIMIT 6
                    ");
                    $stmtRel->execute([
                        ':cid' => (int)$news['category_id'],
                        ':id'  => (int)$news['id'],
                    ]);
                    $related = $stmtRel->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                // أحدث الأخبار
                $stmtLatest = $pdo->query("
                    SELECT id, title, slug, published_at, featured_image
                    FROM news
                    WHERE status = 'published'
                    ORDER BY published_at DESC
                    LIMIT 6
                ");
                $latestNews = $stmtLatest->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // أكثر الأخبار قراءة
                $stmtMostRead = $pdo->query("
                    SELECT id, title, slug, published_at, views, featured_image
                    FROM news
                    WHERE status = 'published'
                    ORDER BY views DESC, published_at DESC
                    LIMIT 6
                ");
                $mostReadNews = $stmtMostRead->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // وسوم الخبر لو الجداول موجودة
                if (gdy_table_exists($pdo, 'tags') && gdy_table_exists($pdo, 'news_tags')) {
                    $stmtTags = $pdo->prepare("
                        SELECT t.id, t.name, t.slug
                        FROM tags t
                        INNER JOIN news_tags nt ON nt.tag_id = t.id
                        WHERE nt.news_id = :nid
                        ORDER BY t.name ASC
                    ");
                    $stmtTags->execute([':nid' => (int)$news['id']]);
                    $tags = $stmtTags->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                if (!$isPreviewRequested) {
// زيادة المشاهدات
                try {
                    $pdo->prepare('UPDATE news SET views = views + 1 WHERE id = :id')
                        ->execute([':id' => (int)$news['id']]);
                } catch (Throwable $e) {
                    @error_log('[NewsController] views update error (no cache): ' . $e->getMessage());
                }
            }
        } else {
            $news         = $result['news']         ?? null;
            $related      = $result['related']      ?? [];
            $tags         = $result['tags']         ?? [];
            $latestNews   = $result['latestNews']   ?? [];
            $mostReadNews = $result['mostReadNews'] ?? [];
        }
}


        // حساب وقت القراءة
        if ($news && !empty($news['content'])) {
            $readingTime = calculate_reading_time($news['content']);
        }
    }
} catch (Throwable $e) {
    @error_log('[NewsController] ' . $e->getMessage());
    $news = null;
}

if (!$news) {
    header('HTTP/1.1 404 Not Found');
    echo 'الخبر غير موجود أو غير منشور';
    exit;
}

// ============= إعداد بيانات العرض =============

// مسار أساسي للموقع (نفس HomeController)
$baseUrl = base_url();

// رابط كامل للخبر (يُستخدم في المشاركة داخل الـ view)
$articleUrlFull = rtrim((string)$baseUrl, '/') . '/news/id/' . (int)($news['id'] ?? 0);

// فئة التصنيف (ليستخدمها الـ view لبناء رابط القسم)
$category = null;
if (!empty($news['category_name']) || !empty($news['category_slug'])) {
    $category = [
        'name' => $news['category_name'] ?? '',
        'slug' => $news['category_slug'] ?? '',
    ];
}

// ============= استخدام محرك القوالب =============
$template = new TemplateEngine();

$templateData = [
    // الهوية العامة (نفس HomeController)
    'siteName'          => $siteName,
    'siteTagline'       => $siteTagline,
    'siteLogo'          => $siteLogo,
    'primaryColor'      => $primaryColor,
    'primaryDark'       => $primaryDark,
    'baseUrl'           => $baseUrl,
    'themeClass'        => $themeClass,
    'searchPlaceholder' => $searchPlaceholder,
    'headerCategories'  => $headerCategories,
    'isLoggedIn'        => $isLoggedIn,
    'isAdmin'           => $isAdmin,
    'showCarbonBadge'   => $showCarbonBadge,
    'carbonBadgeText'   => $carbonBadgeText,

    // بيانات الخبر
    'news'           => $news,
    'category'       => $category,
    'tags'           => $tags,
    'articleUrlFull' => $articleUrlFull,
    'readingTime'    => $readingTime,

    // أخبار إضافية للشريط الجانبي
    'related'      => $related,
    'latestNews'   => $latestNews,
    'mostReadNews' => $mostReadNews,

    // الإعلانات
    'contentTopAd'    => display_ad('content_top'),
    'contentBottomAd' => display_ad('content_bottom'),
    'sidebarTopAd'    => display_ad('sidebar_top'),
    'sidebarBottomAd' => display_ad('sidebar_bottom'),

    // دوال مساعدة
    'display_ad' => 'display_ad',
];

// عرض صفحة الخبر المفرد باستخدام نفس الهيدر/الفوتر
$template->render(__DIR__ . '/../views/news_detail.php', $templateData);