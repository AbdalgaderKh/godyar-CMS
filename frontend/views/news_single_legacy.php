<?php
// godyar/frontend/views/news_single.php

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

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** البيانات القادمة من الكنترولر */
$post = $news ?? $article ?? [];

$title        = $post['title']           ?? '';
$slug         = $post['slug']            ?? '';
$body         = $post['content']         ?? ($post['body'] ?? '');
$excerpt      = $post['excerpt']         ?? ($post['summary'] ?? '');
$cover        = $post['featured_image']  ?? ($post['image'] ?? '');
$categoryName = $post['category_name']   ?? 'أخبار عامة';
$categorySlug = $post['category_slug']   ?? 'general-news';
$date         = $post['published_at']    ?? ($post['publish_at'] ?? ($post['created_at'] ?? ''));
$views        = (int)($post['views']     ?? 0);
$readMinutes  = (int)($post['read_time'] ?? ($readingTime ?? 0));

// بيانات الكاتب
$authorName      = $post['author_name']        ?? '';
$authorAvatar    = $post['author_avatar']      ?? '';
$authorPageTitle = $post['author_page_title']  ?? $post['opinion_page_title'] ?? '';
$authorEmail     = $post['author_email']       ?? $post['opinion_email']       ?? '';
$authorFacebook  = $post['author_facebook']    ?? $post['opinion_facebook']    ?? '';
$authorWebsite   = $post['author_website']     ?? $post['opinion_website']     ?? '';
$authorTwitter   = $post['author_twitter']     ?? $post['opinion_twitter']     ?? '';

// ✅ التحقق من وجود الخبر
$postId = (int)($post["id"] ?? ($post["news_id"] ?? 0));
$newsExists = !empty($post) && $postId > 0 && !empty($title);

// بناء رابط الصورة بشكل متوافق (يدعم روابط كاملة + مسارات نسبية)
if (!function_exists('gdy_image_url')) {
    function gdy_image_url(string $baseUrl, ?string $path): ?string
    {
        $path = trim((string)$path);
        if ($path === '') return null;

        if (preg_match('~^https?://~i', $path)) return $path;

        // Normalize duplicated segments: uploads/news/uploads/news -> uploads/news
        $path = ltrim($path, '/');
        $path = preg_replace('~^(?:uploads/news/)+uploads/news/~i', 'uploads/news/', $path);
        $path = preg_replace('~^uploads/news/~i', '', $path);
        $path = ltrim($path, '/');

        return rtrim($baseUrl, '/') . '/uploads/news/' . $path;
    }
}

$coverUrl = gdy_image_url($baseUrl, $cover) ?: null;

$categoryUrl = $baseUrl . '/category/' . rawurlencode($categorySlug);
$homeUrl     = $baseUrl . '/';
$newsUrl     = $postId > 0 ? $baseUrl . '/news/id/' . $postId : '#';

// تحسين SEO للهيدر الموحد (frontend/views/partials/header.php)
$seoDesc = $excerpt !== '' ? $excerpt : mb_substr(trim(strip_tags((string)$body)), 0, 160, 'UTF-8');
$publishedIso = '';
if ($date !== '') {
    $ts = @strtotime((string)$date);
    if ($ts) $publishedIso = date('c', $ts);
}
$pageSeo = [
    'title' => $title !== '' ? ($title . (isset($siteName) && $siteName ? ' - ' . (string)$siteName : '')) : ((string)($siteName ?? '')),
    'description' => $seoDesc,
    'image' => $coverUrl,
    'url' => $newsUrl !== '#' ? $newsUrl : '',
    'type' => 'article',
    'published_time' => $publishedIso,
];

// توحيد أفاتار الكاتب إن كان مسارًا نسبيًا
if (!empty($authorAvatar)) {
    $authorAvatar = gdy_image_url($baseUrl, $authorAvatar) ?? $authorAvatar;
}

if (is_file($header)) {
    if (!defined("GDY_TPL_WRAPPED")) {
        require $header;
    }
}
?>

<style>
:root {
    --gdy-primary: #0ea5e9;
    --gdy-primary-dark: #0369a1;
    --gdy-radius-xl: 1.5rem;
    --gdy-transition: all .3s cubic-bezier(.25,.46,.45,.94);
}

.layout-main {
    padding: 2rem 0 3rem;
}

/* ✅ تصميم حالة الخطأ */
.news-error-state {
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 3rem 1rem;
}

.error-icon {
    font-size: 4rem;
    color: #ef4444;
    margin-bottom: 1.5rem;
    opacity: 0.8;
}

.error-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.error-message {
    font-size: 1.1rem;
    color: #6b7280;
    margin-bottom: 2rem;
    max-width: 500px;
    line-height: 1.6;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.error-action-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.error-action-btn.primary {
    background: var(--gdy-primary);
    color: white;
    border: 1px solid var(--gdy-primary);
}

.error-action-btn.secondary {
    background: #f9fafb;
    color: #374151;
    border: 1px solid #e5e7eb;
}

.error-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

/* هيدر المقال */
.article-hero {
    background: radial-gradient(circle at top left, rgba(56,189,248,.25), transparent 60%),
                radial-gradient(circle at bottom right, rgba(59,130,246,.3), transparent 60%),
                linear-gradient(120deg, #020617 0%, #020617 55%, #0b1120 100%);
    color: #e5e7eb;
    padding: 1.8rem 0 1.6rem;
    margin-bottom: 2.2rem;
    border-radius: 0 0 2rem 2rem;
    position: relative;
    overflow: hidden;
}

.article-hero .article-path {
    display: inline-flex;
    gap: .35rem;
    align-items: center;
    font-size: .8rem;
    margin-bottom: .7rem;
    color: #9ca3af;
}
.article-path a {
    color: inherit;
    text-decoration: none;
}
.article-path a:hover { color: #e5e7eb; }

/* ✅ صندوق الكاتب داخل الهيدر */
.article-author-box {
    max-width: 780px;
    margin: 0.3rem auto 1.2rem auto;
    padding: 0.9rem 1.1rem;
    border-radius: 1rem;
    background: rgba(15,23,42,.85);
    border: 1px solid rgba(148,163,184,.6);
    box-shadow: 0 12px 32px rgba(15,23,42,.7);
    display: flex;
    align-items: center;
    gap: .9rem;
    direction: rtl;
}

.article-author-avatar {
    width: 58px;
    height: 58px;
    border-radius: 999px;
    overflow: hidden;
    background: #020617;
    flex-shrink: 0;
    border: 2px solid rgba(56,189,248,.9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.15rem;
    color: #e5e7eb;
}

.article-author-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.article-author-meta {
    flex: 1;
    text-align: right;
}

.article-author-page-title {
    font-size: .8rem;
    font-weight: 700;
    color: #38bdf8;
    margin-bottom: .1rem;
}

.article-author-name {
    font-size: .95rem;
    font-weight: 800;
    color: #f9fafb;
}

.article-author-social {
    margin-top: .25rem;
    display: flex;
    align-items: center;
    gap: .4rem;
}

.article-author-social a {
    width: 26px;
    height: 26px;
    border-radius: 999px;
    border: 1px solid rgba(148,163,184,.7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    color: #e5e7eb;
    text-decoration: none;
    background: #020617;
    transition: var(--gdy-transition);
}
.article-author-social a:hover {
    border-color: #38bdf8;
    background: #0f172a;
    transform: translateY(-1px);
}

.article-title {
    font-size: 1.9rem;
    font-weight: 800;
    margin-bottom: .7rem;
    color: #f9fafb;
}

.article-sub {
    max-width: 720px;
    margin: 0 auto .9rem;
    font-size: .9rem;
    color: #cbd5f5;
}

/* ميتا */
.article-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    font-size: .8rem;
}

.article-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .25rem .7rem;
    border-radius: 999px;
    background: rgba(15,23,42,.8);
    border: 1px solid rgba(148,163,184,.5);
    color: #e5e7eb;
}
.article-meta-chip i { color: #38bdf8; }

/* كارت الصورة */
.article-cover-card {
    background: #020617;
    border-radius: var(--gdy-radius-xl);
    padding: .6rem;
    box-shadow: 0 18px 45px rgba(15,23,42,.75);
    margin-top: 1.4rem;
    max-width: 820px;
    margin-inline: auto;
    position: relative;
}
.article-cover-inner {
    border-radius: calc(var(--gdy-radius-xl) - .4rem);
    border: 2px solid transparent;
    background:
      linear-gradient(#020617,#020617) padding-box,
      linear-gradient(120deg,#38bdf8,#4f46e5,#ec4899) border-box;
    overflow: hidden;
}
.article-cover-inner img {
    width: 100%;
    height: auto;
    display: block;
}

/* جسم المقال */
.article-layout {
    margin-top: .5rem;
}

.article-card {
    background: #ffffff;
    border-radius: 1.4rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 14px 30px rgba(15,23,42,.08);
    padding: 1.6rem 1.7rem;
}

/* تنسيق النص */
.article-body {
    font-size: 1rem;
    line-height: 1.9;
    color: #111827;
}
.article-body p {
    margin-bottom: 1rem;
    font-weight: 500; /* خط أوضح وأسمك قليلاً */
}
.article-body h2,
.article-body h3 {
    margin-top: 1.7rem;
    margin-bottom: .8rem;
    font-weight: 700;
    color: #0f172a;
}
.article-body h2 { font-size: 1.25rem; }
.article-body h3 { font-size: 1.05rem; }

.article-body ul,
.article-body ol {
    padding-right: 1.3rem;
    margin-bottom: 1rem;
}
.article-body li { margin-bottom: .3rem; }

/* اقتباس */
.article-body blockquote {
    margin: 1.5rem 0;
    padding: 1.1rem 1.2rem;
    border-radius: 1rem;
    background: #f9fafb;
    border-inline-start: 4px solid var(--gdy-primary);
    font-size: .95rem;
    color: #374151;
}

/* أسفل المقال */
.article-footer-bar {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: .8rem;
    margin-top: 1.6rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}
.article-tags {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}
.article-tag {
    font-size: .78rem;
    padding: .2rem .6rem;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
}
.article-share {
    display: flex;
    align-items: center;
    gap: .4rem;
}
.article-share a {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e5e7eb;
    color: #4b5563;
}
.article-share a:hover {
    background: #0ea5e9;
    border-color: #0ea5e9;
    color: #fff;
}

/* استجابة */
@media (max-width: 992px) {
    .article-hero { border-radius: 0; }
    .article-card { padding: 1.3rem 1.2rem; }
}
@media (max-width: 768px) {
    .article-title { font-size: 1.5rem; }
    .error-title { font-size: 1.5rem; }
    .error-icon { font-size: 3rem; }
    .error-actions { flex-direction: column; }
    .article-author-box {
        flex-direction: row;
        align-items: center;
    }
}
</style>

<div class="layout-main">

    <?php if (!$newsExists): ?>
        <!-- ✅ عرض رسالة الخطأ عندما لا يكون الخبر موجوداً -->
        <div class="container">
            <div class="news-error-state">
                <div class="error-content">
                    <div class="error-icon">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    </div>
                    <h1 class="error-title">الخبر غير موجود</h1>
                    <p class="error-message">
                        عذراً، لم نتمكن من العثور على الخبر الذي تبحث عنه.<br>
                        قد يكون الخبر قد تم حذفه أو أنه غير منشور حالياً.
                    </p>
                    <div class="error-actions">
                        <a href="<?= h($homeUrl) ?>" class="error-action-btn primary">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg>
                            العودة للرئيسية
                        </a>
                        <a href="<?= h($categoryUrl) ?>" class="error-action-btn secondary">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
                            أخبار <?= h($categoryName) ?>
                        </a>
                        <a href="javascript:history.back()" class="error-action-btn secondary">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            العودة للخلف
                        </a>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ✅ عرض الخبر عندما يكون موجوداً -->

        <!-- هيدر المقال -->
        <header class="article-hero">
            <div class="container text-center">
                <div class="article-path">
                    <a href="<?= h($homeUrl) ?>">الرئيسية</a>
                    <span>›</span>
                    <a href="<?= h($categoryUrl) ?>"><?= h($categoryName) ?></a>
                </div>

                <?php if ($authorName): ?>
                    <!-- صندوق كاتب المقال -->
                    <div class="article-author-box">
                        <div class="article-author-avatar">
                            <?php if ($authorAvatar): ?>
                                <img src="<?= h($authorAvatar) ?>"
                                     alt="<?= h($authorName) ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <?= h(mb_substr($authorName, 0, 1, 'UTF-8')) ?>
                            <?php endif; ?>
                        </div>
                        <div class="article-author-meta">
                            <?php if ($authorPageTitle): ?>
                                <div class="article-author-page-title">
                                    <?= h($authorPageTitle) ?>
                                </div>
                            <?php endif; ?>
                            <div class="article-author-name">
                                <?= h($authorName) ?>
                            </div>
                            <div class="article-author-social">
                                <?php if ($authorFacebook): ?>
                                    <a href="<?= h($authorFacebook) ?>" target="_blank" rel="noopener" title="فيسبوك">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg>
                                    </a>
                                <?php endif; ?>
                                <?php if ($authorEmail): ?>
                                    <a href="mailto:<?= h($authorEmail) ?>" title="البريد الإلكتروني">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    </a>
                                <?php endif; ?>
                                <?php if ($authorWebsite): ?>
                                    <a href="<?= h($authorWebsite) ?>" target="_blank" rel="noopener" title="الموقع الشخصي">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#globe"></use></svg>
                                    </a>
                                <?php endif; ?>
                                <?php if ($authorTwitter): ?>
                                    <a href="<?= h($authorTwitter) ?>" target="_blank" rel="noopener" title="تويتر">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#x"></use></svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <h1 class="article-title">
                    <?= h($title) ?>
                </h1>

                <?php if ($excerpt): ?>
                    <p class="article-sub">
                        <?= h($excerpt) ?>
                    </p>
                <?php endif; ?>

                <div class="article-meta">
                    <?php if ($date): ?>
                        <div class="article-meta-chip">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            <span><?= date('Y-m-d', strtotime($date)) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($views > 0): ?>
                        <div class="article-meta-chip">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            <span><?= $views ?> مشاهدة</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($readMinutes > 0): ?>
                        <div class="article-meta-chip">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            <span><?= $readMinutes ?> دقيقة قراءة تقريباً</span>
                        </div>
                    <?php endif; ?>

                    <div class="article-meta-chip">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        <span><?= h($categoryName) ?></span>
                    </div>
                </div>

                <!-- كارت الصورة الرئيسية -->
                <div class="article-cover-card">
                    <div class="article-cover-inner">
                        <img src="<?= h($coverUrl) ?>"
                             alt="<?= h($title) ?>"
                             onerror="this.style.display='none';">
                    </div>
                </div>
            </div>
        </header>

        <!-- المحتوى + السايدبار -->
        <div class="container article-layout">
            <div class="row g-4">
                <!-- المقال -->
                <div class="col-lg-8 order-lg-1 order-1">
                    <article class="article-card">

                        <!-- نص المقال -->
                        <div class="article-body">
                            <?= $body /* يحتوي HTML من لوحة التحكم */ ?>
                        </div>

                        <!-- أسفل المقال -->
                        <div class="article-footer-bar">
                            <div class="article-tags">
                                <?php if (!empty($post['tags']) && is_array($post['tags'])): ?>
                                    <?php foreach ($post['tags'] as $tag): ?>
                                        <span class="article-tag">#<?= h($tag) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="article-share">
                                <span class="text-muted small">شارك:</span>
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($newsUrl) ?>" target="_blank" rel="noopener">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg>
                                </a>
                                <a href="https://x.com/intent/tweet?url=<?= urlencode($newsUrl) ?>&text=<?= urlencode($title) ?>" target="_blank" rel="noopener">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#x"></use></svg>
                                </a>
                                <a href="https://t.me/share/url?url=<?= urlencode($newsUrl) ?>&text=<?= urlencode($title) ?>" target="_blank" rel="noopener">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#telegram"></use></svg>
                                </a>
                            </div>
                        </div>

                    </article>
                </div>

                <!-- السايدبار -->
                <aside class="col-lg-4 order-lg-2 order-2">
                    <?php
                    $sidebarFile = __DIR__ . '/partials/sidebar.php';
                    if (is_file($sidebarFile)) {
                        require $sidebarFile;
                    }
                    ?>
                </aside>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const imgs = document.querySelectorAll('.article-cover-inner img');
    imgs.forEach(function (img) {
        if (img.complete) {
            img.style.opacity = '1';
        } else {
            img.addEventListener('load', function () {
                img.style.opacity = '1';
            });
        }
    });
});
</script>

<?php
if (is_file($footer)) {
    if (!defined("GDY_TPL_WRAPPED")) {
        require $footer;
    }
}
?>
