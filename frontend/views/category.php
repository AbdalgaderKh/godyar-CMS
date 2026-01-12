<?php
// /godyar/frontend/views/category.php

$header = __DIR__ . '/partials/header.php';
$footer = __DIR__ . '/partials/footer.php';

// baseUrl
if (function_exists('base_url')) {
    $baseUrl = rtrim(base_url(), '/');
} elseif (defined('BASE_URL')) {
    $baseUrl = rtrim(BASE_URL, '/');
} else {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $scheme . '://' . $host;
}

/**
 * ✅ دالة لبناء رابط الخبر:
 * - أولاً تستخدم رقم الـ ID مثل الرئيسية: /news/id/28
 * - لو لا يوجد ID صالح → تستخدم slug كاحتياطي
 */
$buildNewsUrl = function (array $row) use ($baseUrl): string {
    $id   = isset($row['id']) ? (int)$row['id'] : 0;
    $slug = isset($row['slug']) ? trim((string)$row['slug']) : '';

    $prefix = rtrim($baseUrl, '/');
    if ($prefix !== '') {
        $prefix .= '/';
    }

    // ✅ وضع الـ ID: روابط القسم تخرج دائماً /news/id/{id}
    if ($id > 0) {
        return $prefix . 'news/id/' . $id;
    }
    // fallback للروابط القديمة (سيُحول إلى ID إذا كانت خريطة السلاگ متوفرة)
    if ($slug !== '') {
        return $prefix . 'news/' . rawurlencode($slug);
    }
    return $prefix . 'news';
};

// ألوان افتراضية
$primaryColor = $primaryColor ?? '#0ea5e9';
$primaryDark  = $primaryDark  ?? '#0369a1';

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// بيانات القسم
$categorySlug = $category['slug'] ?? 'general-news';
$categoryName = $category['name'] ?? 'أخبار عامة';

// روابط
$homeUrl        = $baseUrl . '/';
$generalNewsUrl = $baseUrl . '/category/general-news';
$categoryUrl    = $baseUrl . '/category/' . rawurlencode($categorySlug);

// بناء روابط الترقيم مع الحفاظ على sort/period إن وُجدت
$__queryBase = [];
if (isset($sort) && $sort !== 'latest') {
    $__queryBase['sort'] = $sort;
}
if (isset($period) && $period !== 'all') {
    $__queryBase['period'] = $period;
}
$makePageUrl = function (int $p) use ($categoryUrl, $__queryBase): string {
    $q = $__queryBase;
    if ($p > 1) {
        $q['page'] = $p;
    }
    return !empty($q) ? ($categoryUrl . '?' . http_build_query($q)) : $categoryUrl;
};


// بيانات الترقيم / العناصر
$items        = $items        ?? [];
$totalItems   = $totalItems   ?? count($items);
$itemsPerPage = $itemsPerPage ?? 12;
$currentPage  = $currentPage  ?? 1;
$pages        = $pages        ?? 1;

// ربط البيانات القادمة من الكنترولر إن وُجدت
if (!empty($newsItems ?? [])) {
    $items = $newsItems;

    if (!empty($pagination ?? [])) {
        $totalItems   = $pagination['total_items']   ?? $totalItems;
        $itemsPerPage = $pagination['per_page']      ?? $itemsPerPage;
        $currentPage  = $pagination['current_page']  ?? $currentPage;
        $pages        = $pagination['total_pages']   ?? $pages;
    } else {
        $totalItems = count($items);
        $pages      = (int)ceil($totalItems / $itemsPerPage);
    }
}


// SEO للصفحة (Category)
$canonicalUrl = $categoryUrl;
if (($currentPage ?? 1) > 1) {
    $canonicalUrl .= '?page=' . (int)$currentPage;
}
$pageSeo = [
    'title' => 'تصنيف: ' . $categoryName,
    'description' => 'آخر أخبار ' . $categoryName . ' - تحديثات ومقالات وتقارير مرتبطة بالقسم.',
    'url' => $canonicalUrl,
    'type' => 'website',
    'image' => $baseUrl . '/og_image.php?type=category&title=' . rawurlencode($categoryName),
    'jsonld' => json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type'=>'ListItem','position'=>1,'name'=>'الرئيسية','item'=>$homeUrl],
            ['@type'=>'ListItem','position'=>2,'name'=>$categoryName,'item'=>$canonicalUrl],
        ],
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
];
$pageSeo['rss'] = rtrim((string)base_url(), '/') . '/rss/category/' . rawurlencode((string)($category['slug'] ?? '')) . '.xml';

if (is_file($header)) {
    require $header;
}
?>

<style>
body{
      /* Use global theme tokens from theme (body.theme-*) */
      --gdy-primary: var(--primary);
      --gdy-primary-dark: var(--primary-dark);
      --gdy-primary-rgb: var(--primary-rgb);
      --gdy-soft-bg: rgba(var(--primary-rgb), .08);
      --gdy-card-radius: 18px;
      --gdy-transition: 180ms ease;
    }
/* ===== أساس الصفحة بدون أي container ===== */
#gdy-category-page {
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 0 0 2.5rem 0;
    background: var(--gdy-soft-bg);
    box-sizing: border-box;
}

#gdy-category-page * {
    box-sizing: border-box;
}

/* غلاف داخلي بسيط بدون هوامش جانبية */
.gdy-shell {
    width: 100%;
    padding: 0;
    margin: 0;
}

/* ===== الشريط العلوي (عنوان القسم + المسار) ===== */
.gdy-category-header {
    padding: 1.6rem 1.5rem 0.5rem 1.5rem;
}

.gdy-breadcrumb {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-size: .85rem;
    color: #6b7280;
    margin-bottom: .4rem;
}

.gdy-breadcrumb a {
    color: #4b5563;
    text-decoration: none;
}

.gdy-breadcrumb a:hover {
    color: var(--gdy-primary);
}

.gdy-breadcrumb i {
    color: var(--gdy-primary) !important;
}

.gdy-category-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
}

.gdy-category-title h1 {
    font-size: 1.8rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.gdy-category-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .35rem .9rem;
    border-radius: 999px;
    background: rgba(var(--primary-rgb), .12);
    border: 1px solid rgba(var(--primary-rgb), .18);
    color: var(--primary-dark);
    font-size: .8rem;
}

/* وصف القسم */
.gdy-category-desc {
    padding: 0 1.5rem 1rem 1.5rem;
    font-size: .92rem;
    color: #6b7280;
}

/* ===== شريط الأدوات (الفرز / طريقة العرض / إحصائيات) ===== */
.gdy-toolbar {
    padding: .75rem 1.5rem 1.1rem 1.5rem;
    background: #ffffff;
    border-top: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 4px 14px rgba(15,23,42,.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.gdy-toolbar-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.gdy-stats {
    font-size: .85rem;
    color: #6b7280;
}

.gdy-stats strong {
    color: #111827;
}

/* أزرار الفرز */
.gdy-sort-buttons .btn {
    font-size: .82rem;
    padding: .35rem .9rem;
    border-radius: 999px;
}

.gdy-sort-buttons .btn.active {
    background: var(--gdy-primary);
    border-color: var(--gdy-primary);
    color: #fff;
}

/* أزرار طريقة العرض */
.gdy-view-toggle .btn {
    font-size: .8rem;
    padding: .35rem .7rem;
    border-radius: 999px;
}

.gdy-view-toggle .btn.active {
    background: var(--gdy-primary-dark);
    color: #fff;
}

/* مؤشر "جديد" يتبع لون الثيم */
.gdy-new-dot{ color: var(--gdy-primary) !important; }

/* أيقونات الإحصاءات/العناوين تتبع لون الثيم دائماً */
.gdy-stats i,
.gdy-category-chip i{
    color: var(--gdy-primary) !important;
}

/* ===== تخطيط الجسم: يمين مقالات – يسار سايدبار ===== */
.gdy-category-layout {
    display: grid;
    grid-template-columns: minmax(0, 2.2fr) minmax(260px, 1fr);
    column-gap: 2rem;   /* الفراغ بين الأعمدة فقط */
    align-items: flex-start;
    width: 100%;
    padding: 1.5rem;
}

/* عمود المقالات (يمين في RTL) */
.gdy-main-column {
    order: 2; /* في حالة dir=ltr */
}

/* عمود السايدبار (يسار في RTL) */
.gdy-sidebar-column {
    order: 1;
    position: sticky;
    top: 110px;
    display: block !important;
    visibility: visible !important;
}

/* إذا الـ HTML dir="rtl" نعكس الترتيب تلقائياً */
html[dir="rtl"] .gdy-main-column {
    order: 1;
}
html[dir="rtl"] .gdy-sidebar-column {
    order: 2;
}

/* صندوق السايدبار */
.gdy-sidebar-box {
    background:#ffffff;
    border-radius: 1.2rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 12px 30px rgba(15,23,42,.08);
    padding:1.35rem 1.2rem;
}

/* ===== شبكة المقالات ===== */
.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(260px,1fr));
    gap: 1.4rem;
    width: 100%;
}

.news-grid.list-view {
    grid-template-columns: 1fr;
}

/* كرت الخبر */
.news-card {
    position: relative;
    border-radius: var(--gdy-card-radius);
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 8px 22px rgba(15,23,42,.06);
    transition: var(--gdy-transition);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.news-card::before {
    content: '';
    position: absolute;
    inset-inline-end: 0;
    top: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg,var(--gdy-primary),var(--gdy-primary-dark));
    transform: scaleX(0);
    transform-origin: inline-end;
    transition: transform .35s ease;
}

.news-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 40px rgba(15,23,42,.18);
    border-color: var(--gdy-primary);
}

.news-card:hover::before {
    transform: scaleX(1);
}

/* صورة الخبر */
.news-thumb {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: #e5e7eb;
}

.news-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scale(1.02);
    opacity: 0;
    transition: transform .55s cubic-bezier(0.22, 0.61, 0.36, 1),
                opacity .4s ease;
}

.news-card:hover .news-thumb img {
    transform: scale(1.07);
}

/* شارة مميز */
.news-badge {
    position: absolute;
    top: .75rem;
    inset-inline-start: .8rem;
    padding: .3rem .7rem;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--gdy-primary), var(--gdy-primary-dark));
    color: #ffffff;
    font-size: .7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    box-shadow: 0 4px 12px rgba(var(--gdy-primary-rgb), 0.28);
}


/* شارة للأعضاء فقط */
.news-lock{
    position:absolute;
    top:.75rem;
    inset-inline-end:.8rem;
    padding:.3rem .7rem;
    border-radius:999px;
    background:rgba(15,23,42,.88);
    color:#fff;
    font-size:.78rem;
    display:flex;
    align-items:center;
    gap:.35rem;
    box-shadow:0 8px 18px rgba(0,0,0,.18);
    border:1px solid rgba(148,163,184,.35);
}
.news-lock i{ font-size:.85rem; }

/* محتوى الكرت */
.news-body {
    padding: 1.2rem 1.3rem 1.1rem;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.news-meta {
    font-size:.82rem;
    color:#6b7280;
    margin-bottom:.55rem;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.6rem;
}

.news-meta i {
    margin-left:.25rem;
    color:var(--gdy-primary);
    font-size: .8rem;
}

.news-title {
    color:#111827;
    line-height:1.5;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
    font-weight:700;
    margin-bottom:.5rem;
    transition: color .25s ease;
    font-size: 1.05rem;
}

.news-card:hover .news-title {
    color: var(--gdy-primary);
}

.news-excerpt {
    font-size:.86rem;
    color:#6b7280;
    margin-bottom:.9rem;
    display:-webkit-box;
    -webkit-line-clamp:3;
    -webkit-box-orient:vertical;
    overflow:hidden;
    line-height: 1.6;
}

/* الكاتب + زر القراءة */
.news-footer {
    margin-top:auto;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.75rem;
    font-size:.8rem;
    padding-top:.75rem;
    border-top:1px solid #f1f5f9;
}

.news-author {
    display:inline-flex;
    align-items:center;
    gap:.4rem;
    color:#6b7280;
}

.news-author i {
    color:var(--gdy-primary);
}

.more-link {
    font-size:.8rem;
    padding:.35rem .8rem;
    border-radius:999px;
    border:1px solid #e5e7eb;
    color:#374151;
    text-decoration:none;
    background:#f9fafb;
    transition:var(--gdy-transition);
    display: inline-flex;
    align-items: center;
    gap: .3rem;
}

.more-link i {
    font-size:.75rem;
}

.more-link:hover {
    background:var(--gdy-primary);
    color:#fff;
    border-color:var(--gdy-primary);
    transform: translateX(-2px);
}

/* حالة عدم وجود مقالات */
.empty-state {
    text-align:center;
    padding:4rem 2rem;
    color:#6b7280;
    background:#ffffff;
    border-radius:1.2rem;
    border:1px solid #e5e7eb;
}

.empty-state-icon {
    font-size:3.5rem;
    margin-bottom:1rem;
    opacity:.5;
    color:var(--gdy-primary);
}

/* ترقيم الصفحات */
.pagination {
    margin: 2.5rem 0 0 0;
}

.page-link {
    border: 1px solid #e5e7eb;
    color: #6b7280;
    padding: .5rem .75rem;
    margin: 0 .2rem;
    border-radius: .75rem;
    transition: var(--gdy-transition);
}

.page-link:hover {
    background: var(--gdy-primary);
    color: #fff;
    border-color: var(--gdy-primary);
}

.page-item.active .page-link {
    background: var(--gdy-primary);
    border-color: var(--gdy-primary);
    color: #fff;
}

/* ===== استجابة ===== */
@media (max-width: 991px) {
    .gdy-category-layout {
        grid-template-columns: 1fr;
        column-gap: 0;
        row-gap: 2rem;
        padding: 1rem;
    }

    .gdy-sidebar-column {
        position: static;
    }

    .gdy-sidebar-box {
        border-radius: 1rem;
    }
}

@media (max-width: 768px) {
    .gdy-category-header,
    .gdy-category-desc,
    .gdy-toolbar {
        padding-inline: 1rem;
    }

    .gdy-category-layout {
        padding-inline: .75rem;
    }

    .news-thumb {
        height: 200px;
    }

    .gdy-toolbar {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 576px) {
    .gdy-category-layout {
        padding-inline: .5rem;
    }
}
</style>

<div id="gdy-category-page">
    <div class="gdy-shell">

        <!-- رأس القسم -->
        <header class="gdy-category-header">
            <div class="gdy-breadcrumb">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <a href="<?= h($homeUrl) ?>">الرئيسية</a>
                <span>›</span>
                <a href="<?= h($generalNewsUrl) ?>">أخبار عامة</a>
                <span>›</span>
                <span><?= h($categoryName) ?></span>
            </div>

            <div class="gdy-category-title">
                <h1><?= h($categoryName) ?></h1>
                <span class="gdy-category-chip">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    قسم: <?= h($categoryName) ?>
                </span>
            </div>
        </header>

        <?php if (!empty($category['description'])): ?>
            <div class="gdy-category-desc">
                <?= h($category['description']) ?>
            </div>
        <?php endif; ?>

        <!-- شريط الأدوات -->
        <section class="gdy-toolbar">
            <div class="gdy-toolbar-left">
                <div class="gdy-stats">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
                    إجمالي الأخبار: <strong><?= (int)$totalItems ?></strong>
                </div>

                <div class="btn-group gdy-sort-buttons" role="group">
                    <button type="button" class="btn btn-outline-secondary active" data-sort="latest">
                        الأحدث
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-sort="popular">
                        الأكثر قراءة
                    </button>
                </div>
            </div>

            <div class="btn-group gdy-view-toggle" role="group" aria-label="طريقة العرض">
                <button type="button"
                        class="btn btn-outline-dark active"
                        data-view="grid"
                        title="عرض شبكة">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </button>
                <button type="button"
                        class="btn btn-outline-dark"
                        data-view="list"
                        title="عرض قائمة">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </button>
            </div>
        </section>

        <!-- التخطيط: مقالات يمين – سايدبار يسار -->
        <div class="gdy-category-layout">

            <!-- العمود الرئيسي (مقالات) -->
            <main class="gdy-main-column">
                <?php if (!empty($items)): ?>
                    <div class="news-grid" id="newsGrid">
                        <?php foreach ($items as $index => $n): ?>
                            <?php
                                $slug    = $n['slug'] ?? '';
                                $id      = $n['id']   ?? null;
                                $title   = $n['title'] ?? '';

                                /**
                                 * ✅ نتأكد أن:
                                 *   - يوجد عنوان
                                 *   - ويوجد على الأقل ID أو slug لبناء الرابط
                                 */
                                if (!$title || (!$slug && !$id)) {
                                    continue;
                                }

                                $excerpt = $n['excerpt'] ?? '';
                                // الصورة قد تأتي بأكثر من اسم حقل حسب الاستعلام/النسخة
                                $img     = $n['featured_image']
    ?? ($n['image'] ?? null)
    ?? ($n['image_url'] ?? null)
    ?? ($n['image_path'] ?? null)
    ?? ($n['cover_image'] ?? null)
    ?? ($n['featured_image_url'] ?? null)
    ?? ($n['thumbnail'] ?? null)
    ?? ($n['thumb'] ?? null)
    ?? ($n['photo'] ?? null)
    ?? ($n['img'] ?? null)
    ?? ($n['cover'] ?? null)
    ?? ($n['banner'] ?? null)
    ?? '';

                                $date    = $n['publish_at'] ?? ($n['published_at'] ?? '');
                                $views   = (int)($n['views'] ?? 0);
                                $author  = $n['author_name'] ?? '';

                                // ✅ استخدام نفس منطق الرئيسية في بناء الرابط (ID أولاً ثم slug)
                                $newsUrl = $buildNewsUrl($n);

                                // ✅ بناء رابط الصورة بشكل متسامح (لأن بعض الاستعلامات ترجع مسارات مختلفة)
                                // حالات شائعة:
                                // 1) URL كامل: https://...
                                // 2) مسار مطلق: /uploads/news/xxx.jpg
                                // 3) مسار نسبي: uploads/news/xxx.jpg أو uploads/xxx.jpg أو news/xxx.jpg
                                // 4) اسم ملف فقط: xxx.jpg  (نعتبره داخل uploads/news)
                                $imgUrl = '';
                                $imgRaw = trim((string)$img);
                                if ($imgRaw !== '') {
                                    if (preg_match('~^https?://~i', $imgRaw)) {
                                        $imgUrl = $imgRaw;
                                    } elseif ($imgRaw[0] === '/') {
                                        $imgUrl = rtrim($baseUrl, '/') . $imgRaw;
                                    } else {
                                        $p = ltrim($imgRaw, '/');
                                        // تطبيع تكرار المسار
                                        $p = preg_replace('~^(?:uploads/news/)+uploads/news/~i', 'uploads/news/', $p);

                                        // لو مجرد اسم ملف بدون مجلدات → نضعه داخل uploads/news
                                        if (strpos($p, '/') === false) {
                                            $p = 'uploads/news/' . $p;
                                        }

                                        // لو بدأ بـ uploads/news أو uploads أو news نتركه كما هو
                                        if (!preg_match('~^(uploads/|news/|images/)~i', $p)) {
                                            // آخر fallback: نعتبره تحت uploads/news
                                            $p = 'uploads/news/' . $p;
                                        }

                                        $imgUrl = rtrim($baseUrl, '/') . '/' . $p;
                                    }
                                }
// ✅ لو لا توجد صورة مميزة نحاول استخراج أول صورة من محتوى الخبر
if ($imgUrl === '') {
    $contentHtml = (string)($n['content'] ?? ($n['body'] ?? ($n['details'] ?? '')));
    if ($contentHtml !== '') {
        if (preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $contentHtml, $mm)) {
            $candidate = trim($mm[1]);
            if ($candidate !== '') {
                // نعيد استخدام نفس المنطق لتطبيع الروابط
                if (preg_match('~^https?://~i', $candidate)) {
                    $imgUrl = $candidate;
                } elseif ($candidate[0] === '/') {
                    $imgUrl = rtrim($baseUrl, '/') . $candidate;
                } else {
                    $p2 = ltrim($candidate, '/');
                    if (strpos($p2, '/') === false) $p2 = 'uploads/news/' . $p2;
                    $imgUrl = rtrim($baseUrl, '/') . '/' . $p2;
                }
            }
        }
    }
}

                                $dateAttr   = $date ? date('Y-m-d H:i:s', strtotime($date)) : '';
                                $isFeatured = ($index < 2);
                            ?>
                            <article class="news-card"
                                     data-date="<?= h($dateAttr) ?>"
                                     data-views="<?= (int)$views ?>">
                                <a href="<?= h($newsUrl) ?>" class="text-decoration-none text-dark d-block h-100">
                                    <div class="news-thumb">
                                        <?php if ($imgUrl !== ''): ?>
                                            <img src="<?= h($imgUrl) ?>" loading="lazy" decoding="async"
                                                 alt="<?= h($title) ?>"
                                                 onerror="this.style.display='none'; this.parentElement.classList.add('news-thumb-empty');">
                                        <?php else: ?>
                                            <div class="news-thumb-placeholder gdy-skeleton" aria-hidden="true"></div>
                                        <?php endif; ?>
                                        <?php if ($isFeatured): ?>
                                            <div class="gdy-badge gdy-badge--solid news-badge">
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> مميز
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($n['is_locked'])): ?>
                                            <div class="news-lock" title="هذا المحتوى للأعضاء فقط">
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> للأعضاء
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="news-body">
                                        <div class="news-meta">
                                            <span>
                                                <?php if ($date): ?>
                                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                                    <?= date('Y-m-d', strtotime($date)) ?>
                                                <?php endif; ?>
                                            </span>
                                            <span>
                                                <?php if ($views > 0): ?>
                                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                                    <?= number_format($views) ?>
                                                <?php else: ?>
                                                    <svg class="gdy-icon gdy-new-dot" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                                    جديد
                                                <?php endif; ?>
                                            </span>
                                        </div>

                                        <h2 class="news-title h5">
                                            <?= h($title) ?>
                                        </h2>

                                        <?php if ($excerpt): ?>
                                            <p class="news-excerpt">
                                                <?= h($excerpt) ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="news-footer">
                                            <?php if ($author): ?>
                                                <div class="news-author">
                                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                                                    <span><?= h($author) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="news-author">
                                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
                                                    <span>فريق التحرير</span>
                                                </div>
                                            <?php endif; ?>

                                            <span class="more-link">
                                                قراءة المزيد
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($pages) && $pages > 1): ?>
                        <nav class="mt-4" aria-label="التصفح بين الصفحات">
                            <ul class="pagination justify-content-center">
                                <?php if ($currentPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                           href="<?= h($makePageUrl($currentPage - 1)) ?>">
                                            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> السابقة
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $currentPage - 2);
                                $endPage   = min($pages, $startPage + 4);
                                $startPage = max(1, $endPage - 4);
                                ?>

                                <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                    <li class="page-item <?= $p == $currentPage ? 'active' : '' ?>">
                                        <a class="page-link"
                                           href="<?= h($makePageUrl((int)$p)) ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($currentPage < $pages): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                           href="<?= h($makePageUrl($currentPage + 1)) ?>">
                                            التالية <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        </div>
                        <h3 class="h4 mb-2">لا توجد مقالات</h3>
                        <p class="mb-4">لم يتم إضافة أي مقالات في هذا القسم حتى الآن.</p>
                        <a href="<?= h($homeUrl) ?>" class="btn btn-primary">
                            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg> العودة للرئيسية
                        </a>
                    </div>
                <?php endif; ?>
            </main>

            <!-- السايدبار (يسار) -->
            <aside class="gdy-sidebar-column" style="display:block!important;visibility:visible!important;">
                <div class="gdy-sidebar-box">
                    <?php
                    $sidebarFile = __DIR__ . '/partials/sidebar.php';
                    if (is_file($sidebarFile)) {
                        // ✅ إظهار السايدبار في صفحة التصنيفات حتى لو كان مخفيًا عالميًا
                        $gdySiteSettings = $GLOBALS['site_settings'] ?? [];
                        if (!is_array($gdySiteSettings)) {
                            $gdySiteSettings = [];
                        }
                        $gdySiteSettings['layout_sidebar_mode'] = 'visible';
                        // ✅ إجبار السايدبار حتى لو كانت الدالة return داخل partial
                        $GLOBALS['GDY_FORCE_SIDEBAR'] = true;
                        $GLOBALS['site_settings'] = $gdySiteSettings;

                        require $sidebarFile;
                    }
                    ?>
                </div>
            </aside>

        </div><!-- /gdy-category-layout -->
    </div><!-- /gdy-shell -->
</div><!-- /gdy-category-page -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    // إظهار الصور بسلاسة
    const images = document.querySelectorAll('#gdy-category-page .news-thumb img');
    images.forEach(function (img) {
        if (img.complete) {
            img.style.opacity = '1';
        } else {
            img.addEventListener('load', function () {
                img.style.opacity = '1';
            });
        }
    });

    const newsGrid    = document.getElementById('newsGrid');
    const sortButtons = document.querySelectorAll('.gdy-sort-buttons .btn');
    const viewButtons = document.querySelectorAll('.gdy-view-toggle .btn');

    // فرز الأخبار
    if (sortButtons.length && newsGrid) {
        sortButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                sortButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const mode  = this.getAttribute('data-sort');
                const cards = Array.from(newsGrid.querySelectorAll('.news-card'));

                cards.sort((a, b) => {
                    const dateA  = a.dataset.date || '';
                    const dateB  = b.dataset.date || '';
                    const viewsA = parseInt(a.dataset.views || '0', 10);
                    const viewsB = parseInt(b.dataset.views || '0', 10);

                    if (mode === 'popular') {
                        return viewsB - viewsA;
                    } else {
                        return (new Date(dateB)) - (new Date(dateA));
                    }
                });

                cards.forEach(card => newsGrid.appendChild(card));
            });
        });
    }

    // تغيير طريقة العرض (شبكة / قائمة)
    if (viewButtons.length && newsGrid) {
        viewButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                viewButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const view = this.getAttribute('data-view');
                if (view === 'list') {
                    newsGrid.classList.add('list-view');
                } else {
                    newsGrid.classList.remove('list-view');
                }
            });
        });
    }
});
</script>

<?php
if (is_file($footer)) {
    require $footer;
}
?>
