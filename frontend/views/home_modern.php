<?php
/**
 * frontend/views/home_modern.php
 * واجهة الصفحة الرئيسية الحديثة (خلفية فاتحة + بطاقات + شريط عاجل + فلاتر زمنية)
 * يعتمد على المتغيرات القادمة من frontend/home.php:
 * $sliderNews, $blocks, $featuredVideos, $latestNews, $homeAds,
 * $trendingNews, $sidebarHidden,
 * $buildNewsUrl, $buildCategoryUrl, $buildOpinionAuthorUrl,
 * $baseUrl, $renderOpinionAuthorsBlock, $breakingNews, $period
 */
if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// نمنع استخدام صور (أفاتار) الكتّاب كصور افتراضية للأخبار في الصفحة الرئيسية.
// المطلوب: صورة الخبر فقط (news.image) أو صورة افتراضية للموقع.
if (!function_exists('hm_is_avatar_url')) {
    function hm_is_avatar_url(string $url): bool
    {
        $u = strtolower(trim($url));
        if ($u === '') return false;

        // الأكثر شيوعاً في السكربت: uploads/avatars
        if (strpos($u, 'uploads/avatars/') !== false) return true;
        if (strpos($u, '/uploads/avatars/') !== false) return true;
        if (strpos($u, '/avatars/') !== false) return true;

        // مسارات شائعة أخرى لصور الكتّاب/الملفات الشخصية
        if (strpos($u, 'uploads/authors/') !== false) return true;
        if (strpos($u, '/uploads/authors/') !== false) return true;
        if (strpos($u, 'uploads/writers/') !== false) return true;
        if (strpos($u, '/uploads/writers/') !== false) return true;
        if (strpos($u, 'uploads/opinion_authors/') !== false) return true;
        if (strpos($u, '/uploads/opinion_authors/') !== false) return true;
        if (strpos($u, 'uploads/users/') !== false) return true;
        if (strpos($u, '/uploads/users/') !== false) return true;

        // دعم احتمالات أخرى (بدون تشدد زائد)
        if (strpos($u, 'avatar') !== false && strpos($u, 'uploads/') !== false) return true;
        if (strpos($u, 'author') !== false && strpos($u, 'uploads/') !== false) return true;
        if (strpos($u, 'profile') !== false && strpos($u, 'uploads/') !== false) return true;
        return false;
    }
}

if (!function_exists('hm_news_image')) {
    function hm_news_image(string $baseUrl, $rawPath, string $fallback): string
    {
        // build_image_url معرفة في frontend/home.php، لكن نضيف بديل احتياطي هنا
        if (!function_exists('build_image_url')) {
            $raw = trim((string)$rawPath);
            if ($raw === '') return $fallback;
            if (preg_match('~^https?://~i', $raw)) return $raw;
            if ($raw[0] === '/') return rtrim($baseUrl, '/') . $raw;
            return rtrim($baseUrl, '/') . '/' . ltrim($raw, '/');
        }

        $img = build_image_url($baseUrl, (string)($rawPath ?? '')) ?? $fallback;
        if (hm_is_avatar_url($img)) {
            return $fallback;
        }
        return $img;
    }
}

$todayDate = date('Y-m-d');
$nowTime   = date('H:i');
?>
<style>
  /* ===== الصفحة عامة بخلفية فاتحة ===== */
  .hm-page {
    padding: 1.5rem 0 2.5rem;
    background: var(--bg-page, #f3f4f6);
  }

  .hm-main-col {
    display: flex;
    flex-direction: column;
    gap: 1.75rem;
  }

  .hm-section {
    margin-bottom: .25rem;
  }

  .hm-section-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: .75rem;
    gap: .5rem;
  }

  .hm-section-title {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: center;
    gap: .4rem;
  }

  .hm-section-title i {
    font-size: .95rem;
    color: var(--primary);
  }

  .hm-section-subtitle {
    font-size: .8rem;
    color: #6b7280;
  }

  /* ===== الشريط العلوي: التاريخ + الوقت + عاجل ===== */
  
.hm-topbar {
  margin-bottom: 1rem;

  /* ✅ شريط علوي خفيف يتبع لون الثيم */
  background: linear-gradient(
    180deg,
    rgba(var(--primary-rgb), .16),
    rgba(255,255,255,.92)
  );
  color: #111827;
  border: 1px solid rgba(var(--primary-rgb), .18);

  border-radius: 999px;
  padding: .35rem .7rem;
  display: flex;
  align-items: center;
  gap: .75rem;
  flex-wrap: wrap;
  box-shadow: 0 12px 28px rgba(15,23,42,0.06);
}

  .hm-topbar-left {
    display: flex;
    align-items: center;
    gap: .45rem;
    font-size: .8rem;
  }

  
.hm-topbar-badge {
  padding: .15rem .45rem;
  border-radius: 999px;
  background: rgba(255,255,255,.92);
  border: 1px solid rgba(var(--primary-rgb), .20);
  font-size: .75rem;
  display: inline-flex;
  align-items: center;
  gap: .25rem;
  color: #111827;
}

  .hm-topbar-badge i {
    font-size: .8rem;
    color: rgba(var(--primary-rgb), .9);
  }

  .hm-topbar-text {
    font-size: .82rem;
  }

  .hm-topbar-ticker {
    flex: 1;
    display: flex;
    align-items: center;
    gap: .4rem;
    min-width: 0;
  }

  .hm-topbar-ticker-label {
    font-size: .75rem;
    padding: .15rem .5rem;
    border-radius: 999px;
    background: var(--primary);
    color: #f9fafb;
    font-weight: 700;
  }

  .hm-topbar-ticker-track {
    position: relative;
    overflow: hidden;
    flex: 1;
  }

  .hm-topbar-ticker-inner {
    display: inline-flex;
    align-items: center;
    gap: 1.5rem;
    white-space: nowrap;
    animation: hmTickerScroll 28s linear infinite;
  }

  
.hm-topbar-ticker-item {
  font-size: .8rem;
  color: #111827;
  text-decoration: none;
}

  
.hm-topbar-ticker-item:hover {
  color: var(--primary);
  text-decoration: underline;
}

  
.hm-topbar-ticker-sep {
  font-size: .8rem;
  color: #6b7280;
}

  @keyframes hmTickerScroll {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-50%); }
  }

  /* ===== شريط الطقس (أسفل العاجل) ===== */
  .hm-weather {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 1.25rem;
    box-shadow: 0 10px 28px rgba(15,23,42,0.06);
    padding: .85rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .85rem;
    flex-wrap: wrap;
  }
  .hm-weather-head {
    display: flex;
    align-items: center;
    gap: .65rem;
    flex-wrap: wrap;
  }
  .hm-weather-title {
    font-weight: 800;
    color: #111827;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-size: .9rem;
  }
  .hm-weather-title i {
    color: var(--primary);
  }
  .hm-weather-controls {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-wrap: wrap;
  }
  .hm-weather-select,
  .hm-weather-input {
    height: 36px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    padding: 0 .75rem;
    font-size: .85rem;
    outline: none;
  }
  .hm-weather-btn {
    height: 36px;
    border-radius: 999px;
    border: 1px solid var(--primary);
    background: var(--primary);
    color: #fff;
    font-weight: 700;
    font-size: .85rem;
    padding: 0 .85rem;
  }
  .hm-weather-btn:disabled {
    opacity: .7;
    cursor: not-allowed;
  }
  .hm-weather-circles {
    display: flex;
    align-items: center;
    gap: .55rem;
    flex-wrap: wrap;
  }
  
.hm-weather-circle {
  width: 72px;
  height: 72px;
  border-radius: 999px;
  border: 1px solid rgba(var(--primary-rgb), .18);
  background: rgba(255,255,255,.94);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: .35rem;
}
  .hm-weather-circle i {
    color: var(--primary);
    font-size: 1rem;
    margin-bottom: .15rem;
  }
  .hm-weather-circle .v {
    font-weight: 800;
    font-size: .85rem;
    color: #111827;
    line-height: 1.1;
  }
  .hm-weather-circle .l {
    font-size: .7rem;
    color: #6b7280;
    margin-top: .05rem;
  }
  .hm-weather-hint {
    font-size: .75rem;
    color: #6b7280;
  }
  @media (max-width: 576px) {
    .hm-weather {
      padding: .8rem .85rem;
    }
    .hm-weather-circle {
      width: 66px;
      height: 66px;
    }
  }

  /* ===== سلايدر الأخبار المميزة (كارت أبيض كبير) ===== */
  .hm-hero {
    background: #ffffff;
    border-radius: 1.5rem;
    border: 1px solid #e5e7eb;
    padding: 1rem;
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1.7fr);
    gap: 1rem;
    box-shadow: 0 10px 28px rgba(15,23,42,0.08);
  }
  @media (max-width: 992px) {
    .hm-hero {
      grid-template-columns: minmax(0,1fr);
    }
  }
  .hm-hero-main {
    position: relative;
    overflow: hidden;
    border-radius: 1.25rem;
    background: #020617;
  }
  .hm-hero-main img {
    width: 100%;
    height: 260px;
    object-fit: cover;
    display: block;
  }
  @media (max-width: 576px) {
    .hm-hero-main img {
      height: 200px;
    }
  }
  .hm-hero-main-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(15,23,42,.9), rgba(15,23,42,.1));
    display: flex;
    align-items: flex-end;
    padding: 1rem 1.2rem;
  }
  .hm-hero-main-text h2 {
    font-size: 1.1rem;
    font-weight: 800;
    color: #f9fafb;
    margin-bottom: .25rem;
  }
  .hm-hero-main-text h2 a {
    color: inherit;
    text-decoration: none;
  }
  .hm-hero-main-text h2 a:hover {
    text-decoration: underline;
  }
  .hm-hero-main-text p {
    font-size: .8rem;
    color: #e5e7eb;
    margin-bottom: .25rem;
  }
  .hm-hero-main-meta {
    display: flex;
    gap: .6rem;
    font-size: .75rem;
    color: #9ca3af;
  }
  .hm-hero-main-meta span {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
  }

  .hm-hero-list {
    display: flex;
    flex-direction: column;
    gap: .5rem;
  }
  .hm-hero-item {
    display: flex;
    gap: .55rem;
    padding: .5rem .6rem;
    border-radius: .9rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    transition: all .18s ease;
  }
  .hm-hero-item:hover {
    border-color: var(--primary);
    box-shadow: 0 7px 18px rgba(15,23,42,0.08);
    transform: translateY(-2px);
  }
  .hm-hero-item-thumb {
    width: 84px;
    flex: 0 0 84px;
    border-radius: .7rem;
    overflow: hidden;
    background: #020617;
  }
  .hm-hero-item-thumb img {
    width: 100%;
    height: 60px;
    object-fit: cover;
    display: block;
  }
  .hm-hero-item-body {
    flex: 1;
  }
  .hm-hero-item-title {
    font-size: .82rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: .1rem;
  }
  .hm-hero-item-title a {
    color: inherit;
    text-decoration: none;
  }
  .hm-hero-item-title a:hover {
    text-decoration: underline;
  }
  .hm-hero-item-meta {
    font-size: .72rem;
    color: #6b7280;
  }

  /* ===== بطاقات عامة (تستخدم في "آخر الأخبار" + البلوكات) ===== */
  .hm-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px,1fr));
    gap: .9rem;
  }

  .hm-card {
    position: relative;
    background: #f9fafb;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 100%;
    transition: all .18s ease;
    box-shadow: 0 5px 12px rgba(15,23,42,0.04);
  }
  .hm-card:hover {
    border-color: var(--primary);
    box-shadow: 0 12px 26px rgba(15,23,42,0.08);
    transform: translateY(-2px);
  }

  .hm-card-thumb {
    position: relative;
    width: 100%;
    overflow: hidden;
    background: #e5e7eb;
  }
  .hm-card-thumb img {
    width: 100%;
    height: 140px;
    object-fit: cover;
    display: block;
    transition: transform .22s ease;
  }
  .hm-card:hover .hm-card-thumb img {
    transform: scale(1.04);
  }

  .hm-card-body {
    padding: .65rem .75rem .8rem;
    display: flex;
    flex-direction: column;
    gap: .25rem;
  }

  .hm-card-title {
    font-size: .88rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: .1rem;
  }
  .hm-card-title a {
    color: inherit;
    text-decoration: none;
  }
  .hm-card-title a:hover {
    color: var(--primary);
    text-decoration: underline;
  }

  .hm-card-meta {
    font-size: .72rem;
    color: #6b7280;
    display: flex;
    justify-content: space-between;
    gap: .4rem;
    margin-top: .15rem;
  }

  .hm-card-excerpt {
    font-size: .78rem;
    color: #4b5563;
  }

  .hm-card-badge {
    position: absolute;
    inset-inline-start: .55rem;
    top: .55rem;
    z-index: 2;
    padding: .15rem .55rem;
    border-radius: 999px;
    font-size: .7rem;
    background: rgba(15,23,42,0.85);
    color: #f9fafb;
  }

  .hm-card-empty {
    padding: .9rem 1rem;
    border-radius: .9rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    color: #6b7280;
    font-size: .85rem;
  }

  /* ===== صندوق القسم (بلوك) ===== */
  .hm-block {
    background: #ffffff;
    border-radius: 1.5rem;
    border: 1px solid #e5e7eb;
    padding: .9rem .95rem 1.1rem;
    box-shadow: 0 8px 20px rgba(15,23,42,0.05);
  }

  .hm-block-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: .6rem;
  }

  .hm-block-title {
    font-size: .95rem;
    font-weight: 700;
    color: #111827;
  }
  .hm-block-title a {
    color: inherit;
    text-decoration: none;
  }
  .hm-block-title a:hover {
    text-decoration: underline;
  }

  .hm-block-more {
    font-size: .78rem;
  }
  .hm-block-more a {
    color: var(--primary);
    text-decoration: none;
  }
  .hm-block-more a:hover {
    text-decoration: underline;
  }

  /* ===== بلوك الرأي (تصميم خاص) ===== */
  .hm-block-opinion-light {
    background: #f9fafb;
    border-radius: 1.5rem;
    border: 1px solid #e5e7eb;
  }

  .hm-opinion-articles {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
  }

  .hm-opinion-article-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e5e7eb;
    padding: 1rem 1.2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    direction: rtl;
    box-shadow: 0 6px 18px rgba(15,23,42,0.05);
    transition: box-shadow .18s ease, transform .18s ease, border-color .18s ease;
  }
  .hm-opinion-article-card:hover {
    border-color: #22c55e;
    box-shadow: 0 14px 30px rgba(15,23,42,0.09);
    transform: translateY(-2px);
  }

  .hm-opinion-article-text {
    flex: 1;
    padding-left: 1rem;
  }

  .hm-opinion-article-header {
    display: flex;
    align-items: center;
    gap: .4rem;
    margin-bottom: .25rem;
  }

  .hm-opinion-article-author-name {
    font-size: .9rem;
    font-weight: 700;
    color: #166534;
  }
  .hm-opinion-article-author-name a {
    color: inherit;
    text-decoration: none;
  }
  .hm-opinion-article-author-name a:hover {
    text-decoration: underline;
  }

  .hm-opinion-article-quote {
    font-size: 1.1rem;
    color: var(--breaking-bg, var(--primary-dark));
  }

  .hm-opinion-article-page-title {
    font-size: .78rem;
    color: #6b7280;
    margin-bottom: .2rem;
  }

  .hm-opinion-article-title {
    font-size: .9rem;
    line-height: 1.6;
    color: #111827;
    margin: 0;
    font-weight: 600;
  }
  .hm-opinion-article-title a {
    color: inherit;
    text-decoration: none;
  }
  .hm-opinion-article-title a:hover {
    text-decoration: underline;
  }

  .hm-opinion-article-snippet {
    font-size: .78rem;
    color: #4b5563;
    margin-top: .2rem;
  }

  .hm-opinion-article-meta {
    margin-top: .3rem;
    font-size: .75rem;
    color: #6b7280;
  }

  .hm-opinion-article-badge {
    font-size: .7rem;
    color: #f97316;
    margin-inline-end: .4rem;
  }

  .hm-opinion-article-avatar {
    width: 72px;
    height: 72px;
    flex: 0 0 72px;
    border-radius: 999px;
    overflow: hidden;
    border: 2px solid #e5e7eb;
  }

  .hm-opinion-article-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  @media (max-width: 576px) {
    .hm-opinion-article-card {
      padding: .85rem 1rem;
    }
    .hm-opinion-article-avatar {
      width: 60px;
      height: 60px;
    }
  }

  /* ===== فلاتر الفترة لـ "آخر الأخبار" ===== */
  .hm-latest-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
    gap: 1rem;
    align-items: start;
  }
  @media (max-width: 992px) {
    .hm-latest-layout {
      grid-template-columns: minmax(0, 1fr);
    }
  }

  .hm-featured-video {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow: 0 10px 24px rgba(15,23,42,0.06);
  }
  .hm-featured-video-head {
    padding: .8rem .9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    border-bottom: 1px solid #eef2f7;
  }
  .hm-featured-video-title {
    margin: 0;
    font-size: .95rem;
    font-weight: 800;
    color: #111827;
    display: flex;
    align-items: center;
    gap: .45rem;
  }
  .hm-featured-video-badge {
    font-size: .72rem;
    color: var(--primary);
    background: rgba(var(--primary-rgb),.12);
    border: 1px solid rgba(var(--primary-rgb),.25);
    padding: .12rem .55rem;
    border-radius: 999px;
    font-weight: 700;
  }
  .hm-featured-video-body {
    padding: .75rem .9rem .9rem;
  }
  .hm-featured-video-frame {
    width: 100%;
    aspect-ratio: 16 / 9;
    border-radius: 1rem;
    overflow: hidden;
    background: #0b1220;
  }
  .hm-featured-video-frame iframe,
  .hm-featured-video-frame video {
    width: 100%;
    height: 100%;
    display: block;
    border: 0;
  }
  .hm-featured-video-desc {
    margin-top: .6rem;
    font-size: .8rem;
    color: #6b7280;
    line-height: 1.6;
  }
  .hm-latest-filters {
    margin-bottom: .6rem;
    display: flex;
    flex-wrap: wrap;
    gap: .35rem .5rem;
    align-items: center;
  }

  .hm-latest-label {
    font-size: .78rem;
    color: #6b7280;
  }

  .hm-pill {
    font-size: .76rem;
    padding: .2rem .65rem;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    color: #374151;
    text-decoration: none;
    background: #ffffff;
    display: inline-flex;
    align-items: center;
    gap: .25rem;
  }
  .hm-pill.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #f9fafb;
    font-weight: 600;
  }
  .hm-pill:hover {
    border-color: var(--primary);
  }

  /* ===== النشرة البريدية + الروابط السريعة ===== */
  .hm-newsletter {
    background: linear-gradient(135deg, #0f172a, #020617);
    border-radius: 1.5rem;
    border: 1px solid rgba(56,189,248,.6);
    padding: 1rem 1.1rem;
    display: grid;
    grid-template-columns: minmax(0,1.4fr) minmax(0,1.1fr);
    gap: 1rem;
    color: #f9fafb;
  }
  @media (max-width: 768px) {
    .hm-newsletter {
      grid-template-columns: minmax(0,1fr);
    }
  }
  .hm-newsletter h2 {
    font-size: 1rem;
    margin-bottom: .35rem;
  }
  .hm-newsletter p {
    font-size: .8rem;
    color: #e5e7eb;
    margin-bottom: .4rem;
  }
  .hm-newsletter-form {
    display: flex;
    gap: .4rem;
  }
  .hm-newsletter-form input {
    flex: 1;
  }

  .hm-quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    align-content: flex-start;
  }
  .hm-quick-link {
    padding: .35rem .6rem;
    border-radius: 999px;
    border: 1px solid rgba(148,163,184,.5);
    font-size: .76rem;
    color: #e5e7eb;
    text-decoration: none;
  }
  .hm-quick-link:hover {
    background: rgba(15,23,42,.9);
    border-color: rgba(var(--primary-rgb), .9);
  }


  /* ===== Slot: home_under_featured_video (تحت الفيديو المميز) ===== */
  .hm-under-featured-ad{
    margin-top: 12px;
    width: 100%;
    max-width: 300px; /* العرض المطلوب */
    margin-inline: auto;
  }
  .hm-under-featured-ad .gdy-ad-slot{
    width: 100%;
    aspect-ratio: 300 / 480; /* الطول المطلوب */
    border-radius: 18px;
    overflow: hidden;
    background: #0b1220;
    border: 1px solid rgba(15,23,42,0.10);
    box-shadow: 0 14px 30px rgba(15,23,42,0.10);
  }
  .hm-under-featured-ad .gdy-ad-slot .gdy-ad-img{
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .hm-under-featured-ad .gdy-ad-slot .gdy-ad-html,
  .hm-under-featured-ad .gdy-ad-slot iframe,
  .hm-under-featured-ad .gdy-ad-slot embed,
  .hm-under-featured-ad .gdy-ad-slot object{
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
  }
  @media (max-width: 991.98px){
    .hm-under-featured-ad{ max-width: 100%; }
  }
  }

  /* ===== إعلانات الواجهة الرئيسية ===== */
  .hm-ads-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
    gap: .75rem;
  }
  .hm-ad-card {
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    transition: all .22s ease;
    box-shadow: 0 6px 16px rgba(15,23,42,0.05);
  }
  .hm-ad-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 12px 26px rgba(15,23,42,0.09);
  }
  .hm-ad-image img {
    width: 100%;
    display: block;
    object-fit: cover;
  }
  .hm-ad-body {
    padding: .6rem .75rem .7rem;
  }
  .hm-ad-title {
    font-size: .9rem;
    color: #111827;
    margin: 0;
  }

  /* ===== بلوك الفيديو المميز ===== */
  .hm-video-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: .9rem;
  }
  .hm-video-card {
    display: flex;
    gap: .75rem;
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    padding: .6rem .75rem;
    align-items: center;
    box-shadow: 0 4px 12px rgba(15,23,42,0.05);
  }
  .hm-video-thumb {
    width: 74px;
    flex: 0 0 74px;
  }
  .hm-video-thumb-inner {
    position: relative;
    width: 100%;
    padding-top: 56%;
    border-radius: .8rem;
    background: radial-gradient(circle at 30% 30%, rgba(var(--primary-rgb), .9), #0f172a);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .hm-video-play-icon {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: 2px solid #f9fafb;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f9fafb;
    font-size: .7rem;
  }
  .hm-video-body {
    flex: 1;
  }
  .hm-video-title {
    font-size: .9rem;
    margin: 0 0 .15rem;
    font-weight: 600;
    color: #111827;
  }
  .hm-video-title a {
    color: inherit;
    text-decoration: none;
  }
  .hm-video-title a:hover {
    text-decoration: underline;
  }
  .hm-video-desc {
    font-size: .78rem;
    color: #4b5563;
    margin: 0;
  }

  /* ===== كتّاب الرأي أسفل الصفحة (من الدالة) ===== */
  .hm-opinion-authors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: .8rem;
  }
  .hm-opinion-author-card {
    display: flex;
    gap: .7rem;
    align-items: center;
    background: #ffffff;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    padding: .55rem .75rem;
    box-shadow: 0 4px 12px rgba(15,23,42,0.05);
  }
  .hm-opinion-author-avatar {
    width: 52px;
    height: 52px;
    flex: 0 0 52px;
    border-radius: 999px;
    overflow: hidden;
    border: 2px solid rgba(56,189,248,.8);
  }
  .hm-opinion-author-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .hm-opinion-author-body {
    flex: 1;
  }
  .hm-opinion-author-name {
    font-size: .86rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: .05rem;
  }
  .hm-opinion-author-spec {
    font-size: .75rem;
    color: #6b7280;
    margin-bottom: .15rem;
  }
  .hm-opinion-author-last {
    font-size: .76rem;
    color: var(--primary);
    text-decoration: none;
  }
  .hm-opinion-author-last.muted {
    color: #9ca3af;
  }


  /* ===== Featured Videos Slider (222) ===== */
  .hm-featured-video--slider .hm-featured-video-body { padding: 0; }
  .hm-video-slider{ position:relative; }
  .hm-video-track{
    display:flex;
    gap:.75rem;
    overflow:auto;
    scroll-snap-type:x mandatory;
    padding:.75rem;
    scroll-behavior:smooth;
  }
  .hm-video-track::-webkit-scrollbar{ height:8px; }
  .hm-video-card{
    flex:0 0 80%;
    max-width:80%;
    background:#fff;
    border:1px solid #eef2f7;
    border-radius:14px;
    overflow:hidden;
    scroll-snap-align:start;
    box-shadow:0 6px 18px rgba(0,0,0,.05);
    cursor:pointer;
  }
  @media (min-width: 992px){
    .hm-video-card{ flex-basis: 46%; max-width:46%; }
  }
  .hm-video-thumb{
    position:relative;
    aspect-ratio: 16/9;
    background: linear-gradient(135deg, rgba(124,58,237,.18), rgba(var(--primary-rgb),.18));
    background-size: cover;
    background-position:center;
  }
  .hm-video-overlay{
    position:absolute; inset:0;
    display:flex; align-items:center; justify-content:center;
    background: linear-gradient(180deg, rgba(0,0,0,.10), rgba(0,0,0,.35));
  }
  .hm-video-play{
    width:64px; height:64px;
    border-radius:999px;
    display:flex; align-items:center; justify-content:center;
    background: rgba(255,255,255,.92);
    box-shadow:0 10px 30px rgba(0,0,0,.18);
    color:#111827;
    transform: translateZ(0);
  }
  .hm-video-card:hover .hm-video-play{ transform: scale(1.03); }
  .hm-video-meta{ padding:.7rem .85rem .85rem; }
  .hm-video-title{ margin:0; font-size:.95rem; font-weight:800; color:#111827; }
  .hm-video-desc{ margin:.35rem 0 0; font-size:.82rem; color:#6b7280; line-height:1.6; }
  .hm-video-nav{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    width:36px; height:36px;
    border-radius:999px;
    border:1px solid rgba(17,24,39,.10);
    background:rgba(255,255,255,.95);
    box-shadow:0 8px 20px rgba(0,0,0,.10);
    display:flex; align-items:center; justify-content:center;
    cursor:pointer;
    z-index:2;
  }
  .hm-video-nav--prev{ left:.4rem; }
  .hm-video-nav--next{ right:.4rem; }

  /* Modal */
  .hm-video-modal{ position:fixed; inset:0; display:none; z-index:9999; }
  .hm-video-modal.is-open{ display:block; }
  .hm-video-modal__backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.60); }
  .hm-video-modal__dialog{
    position:relative;
    width:min(980px, 92vw);
    margin: 6vh auto 0;
    background:#fff;
    border-radius:16px;
    overflow:hidden;
    box-shadow:0 20px 70px rgba(0,0,0,.35);
  }
  .hm-video-modal__close{
    position:absolute; top:.5rem; left:.5rem;
    width:38px; height:38px;
    border-radius:999px;
    border:1px solid rgba(17,24,39,.10);
    background:rgba(255,255,255,.95);
    font-size:24px;
    line-height:1;
    cursor:pointer;
    z-index:2;
  }
  .hm-video-modal__frame{
    aspect-ratio: 16/9;
    background:#000;
  }
  .hm-video-modal__frame iframe,
  .hm-video-modal__frame video{
    width:100%; height:100%;
    display:block;
    border:0;
  }


  /* ===== (222) Featured video like screenshot ===== */
  .hm-section-header--split{ display:none; }

  .hm-latest-left-head{
    font-size:.95rem;
    font-weight:700;
    color:#6b7280;
    margin:.2rem 0 .75rem;
  }

  .hm-featured-box{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:1.25rem;
    overflow:hidden;
    box-shadow:0 10px 24px rgba(15,23,42,0.06);
  }

  .hm-featured-stage{ padding:.85rem; }
  .hm-featured-media{
    position:relative;
    width:100%;
    height:420px;
    border-radius:1rem;
    overflow:hidden;
    background: radial-gradient(circle at 30% 30%, rgba(var(--primary-rgb), .9), #0f172a);
    background-size:cover;
    background-position:center;
  }
  @media (max-width: 992px){
    .hm-featured-media{ height:320px; }
  }

  .hm-featured-play{
    position:absolute;
    left:50%;
    top:50%;
    transform:translate(-50%,-50%);
    width:88px;
    height:88px;
    border-radius:999px;
    border:0;
    cursor:pointer;
    background:rgba(255,255,255,.18);
    backdrop-filter: blur(8px);
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .hm-featured-play i{ color:#fff; font-size:26px; }
  .hm-featured-play__ring{
    position:absolute;
    inset:-10px;
    border-radius:999px;
    border:2px solid rgba(255,255,255,.55);
  }

  .hm-featured-meta{ margin-top:.75rem; }
  .hm-featured-title{ font-weight:800; font-size:1rem; color:#111827; }
  .hm-featured-desc{ margin-top:.35rem; font-size:.85rem; color:#6b7280; line-height:1.65; }

  .hm-featured-thumbs-wrap{
    position:relative;
  }
  .hm-featured-thumbs{
    display:flex;
    gap:.55rem;
    padding:.7rem 3.1rem .85rem;
    border-top:1px solid #eef2f7;
    overflow-x:auto;
    overflow-y:hidden;
    scroll-snap-type:x mandatory;
    scrollbar-width:none;
    -ms-overflow-style:none;
  }
  .hm-featured-thumbs::-webkit-scrollbar{ height:0; }
  .hm-featured-thumbs-nav{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    width:38px;
    height:38px;
    border-radius:999px;
    border:1px solid #e5e7eb;
    background:rgba(255,255,255,.92);
    display:grid;
    place-items:center;
    box-shadow:0 10px 20px rgba(15,23,42,.15);
    z-index:4;
    cursor:pointer;
  }
  .hm-featured-thumbs-nav--prev{ right:.55rem; }
  .hm-featured-thumbs-nav--next{ left:.55rem; }
  .hm-featured-thumbs-nav:disabled{ opacity:.35; cursor:default; pointer-events:none; }
  .hm-featured-thumbs-nav i{ font-size:14px; }
  .hm-featured-thumb{
    flex:0 0 180px;
    max-width:180px;
    scroll-snap-align:start;
    border:1px solid #eef2f7;
    background:#fff;
    border-radius:14px;
    padding:.5rem;
    cursor:pointer;
    text-align:right;
    display:flex;
    flex-direction:column;
    gap:.45rem;
  }
  .hm-featured-thumb.active{ border-color: rgba(var(--primary-rgb),.55); box-shadow:0 10px 18px rgba(var(--primary-rgb),.12); }
  .hm-featured-thumb__img{
    position:relative;
    width:100%;
    height:92px;
    border-radius:12px;
    background: radial-gradient(circle at 30% 30%, rgba(var(--primary-rgb), .9), #0f172a);
    background-size:cover;
    background-position:center;
    overflow:hidden;
  }
  .hm-featured-thumb__play{
    position:absolute;
    left:50%;
    top:50%;
    transform:translate(-50%,-50%);
    width:34px;height:34px;
    border-radius:999px;
    background:rgba(255,255,255,.22);
    display:flex;align-items:center;justify-content:center;
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
  }
  .hm-featured-thumb__play i{ color:#fff; font-size:14px; }
  .hm-featured-thumb__text{
    font-size:.78rem;
    font-weight:700;
    color:#111827;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .hm-latest-right-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:.35rem;
  }
  .hm-latest-right-title{ margin:0; font-size:1.05rem; font-weight:900; color:#0f172a; display:flex; gap:.45rem; align-items:center;}


  /* ================================
     PRO newsroom spacing & rhythm
     (final polish)
     ================================ */
  .hm-page{
    padding: 1.75rem 0 3rem;
  }

  .hm-main-col{
    gap: 1.5rem;
  }

  @media (min-width: 992px){
    .hm-main-col{ gap: 1.85rem; }
  }

  .hm-section{
    margin-bottom: 1.15rem;
  }
  @media (min-width: 992px){
    .hm-section{ margin-bottom: 1.35rem; }
  }

  .hm-section-header{
    margin-bottom: .9rem;
    align-items: center;
  }

  .hm-section-title{
    font-size: 1.06rem;
    line-height: 1.25;
    gap: .45rem;
  }

  .hm-section-subtitle{
    margin-top: .15rem;
  }

  .hm-block{
    padding: 1.05rem 1.25rem;
  }

  .hm-block-header{
    margin-bottom: .8rem;
  }

  .hm-block-title{
    font-size: 1.02rem;
    line-height: 1.25;
  }

  .hm-card{
    background: #fff;
    border-color: rgba(15, 23, 42, .10);
    box-shadow: 0 6px 16px rgba(15, 23, 42, .045);
  }

  .hm-card-body{
    padding: .9rem .95rem 1rem;
    gap: .35rem;
  }

  .hm-card-title{
    font-size: .96rem;
    line-height: 1.35;
    margin-bottom: .12rem;
  }

  .hm-card-excerpt{
    font-size: .82rem;
    line-height: 1.55;
    margin-top: .1rem;
  }

  .hm-card-meta{
    font-size: .74rem;
    margin-top: .25rem;
  }


</style>

<section class="hm-page">
  <h1 class="visually-hidden"><?= h($siteName ?? 'الرئيسية') ?></h1>
  <div class="container-xxl">
    <div class="row">
      <div class="<?= !empty($sidebarHidden) ? 'col-12' : 'col-lg-8' ?>">
        <div class="hm-main-col">

          <!-- الشريط العلوي: التاريخ + الوقت + شريط عاجل -->
          <div class="hm-topbar">
            <div class="hm-topbar-left">
              <span class="hm-topbar-badge">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <?= h(__('اليوم')) ?>
              </span>
              <span class="hm-topbar-text">
                <?= h($todayDate) ?> - <?= h($nowTime) ?>
              </span>
            </div>

            <?php if (!empty($breakingNews)): ?>
              <div class="hm-topbar-ticker" aria-label="<?= h(__('شريط الأخبار العاجلة')) ?>">
                <div class="hm-topbar-ticker-label"><?= h(__('عاجل')) ?></div>
                <div class="hm-topbar-ticker-track">
                  <div class="hm-topbar-ticker-inner">
                    <?php foreach ($breakingNews as $bn): ?>
                      <?php
                      $bnTitle = (string)($bn['title'] ?? '');
                      $bnUrl   = $buildNewsUrl($bn);
                      ?>
                      <a href="<?= h($bnUrl) ?>" class="hm-topbar-ticker-item">
                        <?= h(mb_substr($bnTitle,0,80,'UTF-8')) ?><?= mb_strlen($bnTitle,'UTF-8')>80?'…':'' ?>
                      </a>
                      <span class="hm-topbar-ticker-sep">•</span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <?php
          $weatherActive = isset($weatherSettings) && is_array($weatherSettings)
              && ((int)($weatherSettings['is_active'] ?? 0) === 1)
              && trim((string)($weatherSettings['api_key'] ?? '')) !== '';
          ?>
          <?php if ($weatherActive): ?>
            <div class="hm-weather" id="hmWeather"
                 data-default-city="<?= h((string)($weatherSettings['city'] ?? '')) ?>"
                 data-default-cc="<?= h((string)($weatherSettings['country_code'] ?? '')) ?>"
                 data-api-url="<?= h(rtrim((string)$baseUrl,'/') . '/api/v1/weather.php') ?>">
              <div class="hm-weather-head">
                <div class="hm-weather-title"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('الطقس الآن')) ?></div>
                <div class="hm-weather-controls">
                  <select class="hm-weather-select" id="hmWeatherCitySelect" aria-label="<?= h(__('اختيار المدينة')) ?>">
                    <?php
                    $defaultCity = trim((string)($weatherSettings['city'] ?? ''));
                    $defaultCC   = strtoupper(trim((string)($weatherSettings['country_code'] ?? '')));
                    $hasAny = false;
                    if (isset($weatherLocations) && is_array($weatherLocations)) {
                      foreach ($weatherLocations as $loc) {
                        $hasAny = true;
                        break;
                      }
                    }
                    ?>
                    <?php if ($hasAny): ?>
                      <?php foreach ($weatherLocations as $loc): ?>
                        <?php
                        $c = (string)($loc['city_name'] ?? '');
                        $cc = strtoupper((string)($loc['country_code'] ?? ''));
                        $sel = ($c !== '' && mb_strtolower($c,'UTF-8') === mb_strtolower($defaultCity,'UTF-8') && $cc === $defaultCC) ? 'selected' : '';
                        ?>
                        <option value="<?= h($c) ?>" data-cc="<?= h($cc) ?>" <?= $sel ?>><?= h($c) ?><?= $cc ? ' (' . h($cc) . ')' : '' ?></option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="<?= h($defaultCity) ?>" data-cc="<?= h($defaultCC) ?>" selected><?= h($defaultCity ?: __('المدينة')) ?></option>
                    <?php endif; ?>
                  </select>
                  <input class="hm-weather-input" id="hmWeatherCityInput" type="text" placeholder="<?= h(__('أو اكتب مدينة')) ?>" value="" aria-label="إدخال مدينة" />
                  <button class="hm-weather-btn" id="hmWeatherApply" type="button"><?= h(__('تحديث')) ?></button>
                  <span class="hm-weather-hint" id="hmWeatherHint"></span>
                </div>
              </div>

              <div class="hm-weather-circles" aria-label="مؤشرات الطقس">
                <div class="hm-weather-circle">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <div class="v" id="hmWCity">—</div>
                  <div class="l"><?= h(__('المدينة')) ?></div>
                </div>
                <div class="hm-weather-circle">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <div class="v" id="hmWTemp">—</div>
                  <div class="l"><?= h(__('الحرارة')) ?></div>
                </div>
                <div class="hm-weather-circle">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <div class="v" id="hmWWind">—</div>
                  <div class="l"><?= h(__('سرعة الرياح')) ?></div>
                </div>
                <div class="hm-weather-circle">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <div class="v" id="hmWDir">—</div>
                  <div class="l"><?= h(__('اتجاه الرياح')) ?></div>
                </div>
                <div class="hm-weather-circle">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <div class="v" id="hmWTime">—</div>
                  <div class="l"><?= h(__('الساعة')) ?></div>
                </div>
              </div>
            </div>

            <script>
              (function(){
                const box = document.getElementById('hmWeather');
                if (!box) return;

                const sel = document.getElementById('hmWeatherCitySelect');
                const inp = document.getElementById('hmWeatherCityInput');
                const btn = document.getElementById('hmWeatherApply');
                const hint = document.getElementById('hmWeatherHint');

                const elCity = document.getElementById('hmWCity');
                const elTemp = document.getElementById('hmWTemp');
                const elWind = document.getElementById('hmWWind');
                const elDir  = document.getElementById('hmWDir');
                const elTime = document.getElementById('hmWTime');

                const LS_CITY = 'godyar_weather_city';
                const LS_CC   = 'godyar_weather_cc';

                function setHint(t){
                  if (!hint) return;
                  hint.textContent = t || '';
                }

                function getSelected(){
                  const custom = (inp && inp.value) ? inp.value.trim() : '';
                  if (custom) {
                    return { city: custom, cc: '' };
                  }
                  const opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;
                  const city = opt ? (opt.value || '') : '';
                  const cc = opt ? (opt.getAttribute('data-cc') || '') : '';
                  return { city: city, cc: cc };
                }

                async function fetchWeather(city, cc){
                  if (!city) return;
                  btn && (btn.disabled = true);
                  setHint('جارٍ التحديث...');

                  try {
                    const qs = new URLSearchParams();
                    qs.set('city', city);
                    if (cc) qs.set('cc', cc);
                    const apiUrl = (box.getAttribute('data-api-url') || '/api/v1/weather.php');
                    const r = await fetch(apiUrl + '?' + qs.toString(), {
                      method: 'GET',
                      headers: { 'Accept': 'application/json' }
                    });
                    const j = await r.json();

                    if (!j || !j.ok) {
                      setHint('تعذّر جلب بيانات الطقس.');
                      return;
                    }
                    if (!j.active) {
                      setHint('ميزة الطقس غير مفعلة حالياً.');
                      return;
                    }

                    const units = j.units || 'metric';
                    const tempUnit = units === 'imperial' ? '°F' : '°C';
                    const windUnit = units === 'imperial' ? 'mph' : 'm/s';

                    elCity && (elCity.textContent = j.city || city);
                    elTemp && (elTemp.textContent = (j.temp === null || j.temp === undefined) ? '—' : (Math.round(j.temp) + tempUnit));
                    elWind && (elWind.textContent = (j.wind_speed === null || j.wind_speed === undefined) ? '—' : (Math.round(j.wind_speed) + windUnit));
                    elDir  && (elDir.textContent  = j.wind_dir || '—');
                    elTime && (elTime.textContent = j.time || '—');

                    setHint('');

                    try {
                      localStorage.setItem(LS_CITY, city);
                      localStorage.setItem(LS_CC, cc || '');
                    } catch (e) {}
                  } catch (e) {
                    setHint('تعذّر جلب بيانات الطقس.');
                  } finally {
                    btn && (btn.disabled = false);
                  }
                }

                // تحميل آخر مدينة اختارها المستخدم إن وُجدت
                let city = '';
                let cc = '';
                try {
                  city = localStorage.getItem(LS_CITY) || '';
                  cc   = localStorage.getItem(LS_CC) || '';
                } catch (e) {}

                if (city) {
                  // حاول ضبطها في القائمة إن كانت موجودة
                  if (sel) {
                    const opts = Array.from(sel.options || []);
                    const idx = opts.findIndex(o => (o.value || '').trim().toLowerCase() === city.trim().toLowerCase());
                    if (idx >= 0) {
                      sel.selectedIndex = idx;
                      cc = opts[idx].getAttribute('data-cc') || cc;
                    } else {
                      // مدينة خارج القائمة -> ضعها في input
                      if (inp) inp.value = city;
                    }
                  }
                } else {
                  city = (box.getAttribute('data-default-city') || '').trim();
                  cc   = (box.getAttribute('data-default-cc') || '').trim();
                }

                // جلب أول مرة
                fetchWeather(city, cc);

                // أحداث تغيير المدينة
                if (btn) {
                  btn.addEventListener('click', function(){
                    const selVal = getSelected();
                    fetchWeather(selVal.city, selVal.cc);
                  });
                }
                if (sel) {
                  sel.addEventListener('change', function(){
                    if (inp) inp.value = '';
                    const selVal = getSelected();
                    fetchWeather(selVal.city, selVal.cc);
                  });
                }
                if (inp) {
                  inp.addEventListener('keydown', function(e){
                    if (e.key === 'Enter') {
                      e.preventDefault();
                      const selVal = getSelected();
                      fetchWeather(selVal.city, selVal.cc);
                    }
                  });
                }
              })();
            
  // ===== Featured video thumbs switching (222) =====
  (function(){
    const root = document.querySelector('[data-hm-featured]');
    if(!root) return;
    const stage = root.querySelector('[data-hm-stage]');
    const titleEl = root.querySelector('[data-hm-stage-title]');
    const descEl  = root.querySelector('[data-hm-stage-desc]');
    const playBtn = root.querySelector('.hm-featured-play');
    const thumbs  = root.querySelectorAll('[data-hm-thumb]');
    const setActive = (btn) => {
      thumbs.forEach(b=>b.classList.toggle('active', b===btn));
      const t = btn.getAttribute('data-title') || '';
      const d = btn.getAttribute('data-desc') || '';
      const type = btn.getAttribute('data-type') || 'iframe';
      const src  = btn.getAttribute('data-src') || '';
      const thumb= btn.getAttribute('data-thumb') || '';
      if(titleEl) titleEl.textContent = t;
      if(descEl){
        if(d){ descEl.textContent=d; descEl.style.display='block'; }
        else { descEl.textContent=''; descEl.style.display='none'; }
      }
      if(stage){
        stage.setAttribute('data-type', type);
        stage.setAttribute('data-src', src);
        stage.style.backgroundImage = thumb ? `url('${thumb}')` : '';
      }
      if(playBtn){
        playBtn.setAttribute('data-hm-video-type', type);
        playBtn.setAttribute('data-hm-video-src', src);
      }
    };
    thumbs.forEach(btn=>{
      btn.addEventListener('click', ()=> setActive(btn));
    });
  })();

</script>
          <?php endif; ?>

          <!-- 1) سلايدر الأخبار المميزة -->
          <?php if (!empty($sliderNews)): ?>
            <?php
            $hero = $sliderNews[0];
            $heroUrl = $buildNewsUrl($hero);
            $heroImg = hm_news_image($baseUrl, $hero['image'] ?? '', (rtrim($baseUrl,'/').'/assets/images/placeholder-hero.jpg'));
            $heroTitle = (string)($hero['title'] ?? '');
            $heroExcerpt = (string)($hero['excerpt'] ?? '');
            $heroDate = !empty($hero['created_at']) ? date('Y-m-d', strtotime((string)$hero['created_at'])) : '';
            ?>
            <section class="hm-section" aria-label="أخبار مميزة">
              <div class="hm-hero">
                <div class="hm-hero-main">
                  <a href="<?= h($heroUrl) ?>">
                    <img src="<?= h($heroImg) ?>" alt="<?= h($heroTitle) ?>" loading="eager" fetchpriority="high" decoding="async">
                  </a>
                  <div class="hm-hero-main-overlay">
                    <div class="hm-hero-main-text">
                      <h2>
                        <a href="<?= h($heroUrl) ?>">
                          <?= h($heroTitle) ?>
                        </a>
                      </h2>
                      <?php if ($heroExcerpt !== ''): ?>
                        <p><?= h(mb_substr($heroExcerpt,0,120,'UTF-8')) ?><?= mb_strlen($heroExcerpt,'UTF-8')>120?'…':'' ?></p>
                      <?php endif; ?>
                      <div class="hm-hero-main-meta">
                        <?php if ($heroDate): ?>
                          <span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($heroDate) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="hm-hero-list">
                  <?php foreach (array_slice($sliderNews,1) as $row): ?>
                    <?php
                    $url   = $buildNewsUrl($row);
                    $title = (string)($row['title'] ?? '');
                    $date  = !empty($row['created_at']) ? date('Y-m-d', strtotime((string)$row['created_at'])) : '';
                    $img   = hm_news_image($baseUrl, $row['image'] ?? '', (rtrim($baseUrl,'/').'/assets/images/placeholder-thumb.jpg'));
                    ?>
                    <article class="hm-hero-item">
                      <div class="hm-hero-item-thumb">
                        <a href="<?= h($url) ?>">
                          <img src="<?= h($img) ?>" alt="<?= h($title) ?>">
                        </a>
                      </div>
                      <div class="hm-hero-item-body">
                        <div class="hm-hero-item-title">
                          <a href="<?= h($url) ?>">
                            <?= h(mb_substr($title,0,80,'UTF-8')) ?><?= mb_strlen($title,'UTF-8')>80?'…':'' ?>
                          </a>
                        </div>
                        <div class="hm-hero-item-meta">
                          <?php if ($date): ?>
                            <span><?= h($date) ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </div>
            </section>
          <?php endif; ?>

          <!-- 2) آخر الأخبار كبطاقات + فلاتر الفترة -->
          <?php if (!empty($latestNews)): ?>
            <?php
            $homeBaseUrl     = rtrim($baseUrl, '/') . '/index.php';
            $currentPeriod   = $period ?? 'all';
            $makeHomePeriodUrl = function (string $p) use ($homeBaseUrl) {
                return $homeBaseUrl . '?period=' . urlencode($p);
            };
	            // فيديوهات مميزة (يُدار من لوحة التحكم manage_videos.php عبر جدول featured_videos)
	            $featuredVideo = null;
	            if (!empty($featuredVideos) && is_array($featuredVideos)) {
	                $featuredVideo = $featuredVideos[0] ?? null;
	            }
	            $buildVideoEmbed = function (string $url): array {
	                $u = trim($url);
	                if ($u === '') return ['type' => 'none', 'src' => ''];
	
	                // YouTube
	                if (strpos($u, 'youtube.com') !== false || strpos($u, 'youtu.be') !== false) {
	                    $id = '';
	                    if (preg_match('~(?:v=|/)([0-9A-Za-z_-]{11})(?:[?&/]|$)~', $u, $m)) {
	                        $id = $m[1];
	                    }
	                    if ($id !== '') {
	                        return ['type' => 'iframe', 'src' => 'https://www.youtube.com/embed/' . $id];
	                    }
	                }
	
	                // Vimeo
	                if (strpos($u, 'vimeo.com') !== false) {
	                    if (preg_match('~vimeo\.com/(\d+)~', $u, $m)) {
	                        return ['type' => 'iframe', 'src' => 'https://player.vimeo.com/video/' . $m[1]];
	                    }
	                }
	
	                // Direct video
	                $ext = strtolower(pathinfo(parse_url($u, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
	                if (in_array($ext, ['mp4','webm','ogg'], true)) {
	                    return ['type' => 'video', 'src' => $u];
	                }
	
	                // Fallback: try iframe
	                return ['type' => 'iframe', 'src' => $u];
	            };
            ?>
            <section class="hm-section" aria-label="<?= h(__('آخر الأخبار')) ?>">
              <div class="hm-section-header hm-section-header--split" aria-hidden="true"></div>
<div class="hm-latest-layout">
	                <!-- المكان (222): فيديوهات مميزة (سلايدر + زر تشغيل) -->
                <div class="hm-latest-left">
                  <div class="hm-latest-left-head"><?= h(__('أحدث الفيديوهات')) ?></div>

                  <?php if (!empty($featuredVideos) && is_array($featuredVideos)): ?>
                    <?php
                      // تجهيز بيانات الفيديوهات للعرض
                      $preparedVideos = [];
                      foreach ($featuredVideos as $v) {
                        $vTitle = trim((string)($v['title'] ?? ''));
                        if ($vTitle === '') { $vTitle = 'أحدث الفيديوهات'; }
                        $vDesc  = trim((string)($v['description'] ?? ''));
                        $embed  = $buildVideoEmbed((string)($v['video_url'] ?? ''));
                        if (($embed['type'] ?? 'none') === 'none') continue;

                        // Thumbnail (YouTube) – fallback to gradient
                        $thumb = '';
                        if (preg_match('~(?:youtube\.com|youtu\.be)~', (string)($v['video_url'] ?? ''))
                            && preg_match('~(?:v=|/)([0-9A-Za-z_-]{11})(?:[?&/]|$)~', (string)$v['video_url'], $mm)) {
                          $thumb = 'https://img.youtube.com/vi/' . $mm[1] . '/hqdefault.jpg';
                        }
                        $preparedVideos[] = [
                          'title' => $vTitle,
                          'desc'  => $vDesc,
                          'type'  => $embed['type'],
                          'src'   => $embed['src'],
                          'thumb' => $thumb,
                        ];
                      }
                      $firstVideo = $preparedVideos[0] ?? null;
                    ?>

                    <?php if (!empty($firstVideo)): ?>
                      <div class="hm-featured-box" data-hm-featured>
                        <!-- شاشة العرض الكبيرة -->
                        <div class="hm-featured-stage">
                          <div class="hm-featured-media"
                               data-hm-stage
                               data-type="<?= h($firstVideo['type']) ?>"
                               data-src="<?= h($firstVideo['src']) ?>"
                               style="<?= $firstVideo['thumb'] ? "background-image:url('".h($firstVideo['thumb'])."');" : '' ?>">
                            <button class="hm-featured-play" type="button" data-hm-video-open
                                    data-hm-video-type="<?= h($firstVideo['type']) ?>"
                                    data-hm-video-src="<?= h($firstVideo['src']) ?>"
                                    aria-label="تشغيل الفيديو">
                              <span class="hm-featured-play__ring"></span>
                              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            </button>
                          </div>

                          <div class="hm-featured-meta">
                            <div class="hm-featured-title" data-hm-stage-title><?= h($firstVideo['title']) ?></div>
                            <?php if (!empty($firstVideo['desc'])): ?>
                              <div class="hm-featured-desc" data-hm-stage-desc><?= h($firstVideo['desc']) ?></div>
                            <?php endif; ?>
                          </div>
                        </div>

                        <!-- شريط المصغرات (السلايدر) -->
                        <div class="hm-featured-thumbs-wrap" data-hm-thumbs-wrap>
                          <button class="hm-featured-thumbs-nav hm-featured-thumbs-nav--prev" type="button" data-hm-thumbs-prev aria-label="السابق">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                          </button>

                          <div class="hm-featured-thumbs" data-hm-thumbs>
<?php foreach ($preparedVideos as $i => $pv): ?>
                            <button class="hm-featured-thumb <?= $i === 0 ? 'active' : '' ?>"
                                    type="button"
                                    data-hm-thumb
                                    data-title="<?= h($pv['title']) ?>"
                                    data-desc="<?= h($pv['desc']) ?>"
                                    data-type="<?= h($pv['type']) ?>"
                                    data-src="<?= h($pv['src']) ?>"
                                    data-thumb="<?= h($pv['thumb']) ?>">
                              <span class="hm-featured-thumb__img"
                                    style="<?= $pv['thumb'] ? "background-image:url('".h($pv['thumb'])."');" : '' ?>">
                                <span class="hm-featured-thumb__play"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></span>
                              </span>
                              <span class="hm-featured-thumb__text"><?= h($pv['title']) ?></span>
                            </button>
                          <?php endforeach; ?>
                          </div>

                          <button class="hm-featured-thumbs-nav hm-featured-thumbs-nav--next" type="button" data-hm-thumbs-next aria-label="التالي">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                          </button>
                        </div>
                      </div>

                      <!-- Modal للتشغيل -->
                      <div class="hm-video-modal" id="hmVideoModal" aria-hidden="true">
                        <div class="hm-video-modal__backdrop" data-hm-video-close></div>
                        <div class="hm-video-modal__dialog" role="dialog" aria-modal="true" aria-label="تشغيل الفيديو">
                          <button class="hm-video-modal__close" type="button" data-hm-video-close aria-label="إغلاق">×</button>
                          <div class="hm-video-modal__frame" id="hmVideoModalFrame"></div>
                        </div>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
<?php
  // ✅ Slot 5: إعلان تحت الفيديو المميز مباشرة (location: home_under_featured_video)
  // يُدار من لوحة التحكم admin/ads
  $__homeUnderFeaturedVideoAdHtml = '';

  // 1) المسار المفضل: AdService->render()
  try {
    if (isset($pdo) && $pdo instanceof \PDO && class_exists('\Godyar\Services\AdService')) {
      $adSvc = new \Godyar\Services\AdService($pdo);
      if (method_exists($adSvc, 'render')) {
        $__homeUnderFeaturedVideoAdHtml = (string)$adSvc->render('home_under_featured_video', $baseUrl ?? '');
      }
    }
  } catch (\Throwable $e) {
    $__homeUnderFeaturedVideoAdHtml = '';
  }

  // 2) fallback آمن: جلب الإعلان يدوياً لو لم تتوفر render()
  if (trim($__homeUnderFeaturedVideoAdHtml) === '' && isset($pdo) && $pdo instanceof \PDO) {
    try {
      $cols = [];
      try {
        $cstmt = $pdo->query("SHOW COLUMNS FROM ads");
        $cols = $cstmt ? $cstmt->fetchAll(\PDO::FETCH_COLUMN, 0) : [];
      } catch (\Throwable $e) { $cols = []; }

      $colImage = in_array('image', $cols, true) ? 'image' : (in_array('image_url', $cols, true) ? 'image_url' : null);
      $colUrl   = in_array('url', $cols, true) ? 'url' : (in_array('target_url', $cols, true) ? 'target_url' : null);
      $colType  = in_array('type', $cols, true) ? 'type' : null;
      $colHtml  = in_array('html', $cols, true) ? 'html' : (in_array('html_code', $cols, true) ? 'html_code' : null);

      $colStart = in_array('starts_at', $cols, true) ? 'starts_at' : (in_array('start_date', $cols, true) ? 'start_date' : null);
      $colEnd   = in_array('ends_at', $cols, true) ? 'ends_at' : (in_array('end_date', $cols, true) ? 'end_date' : null);

      $where = "location = :loc AND is_active = 1";
      if ($colStart) $where .= " AND ($colStart IS NULL OR $colStart <= NOW())";
      if ($colEnd)   $where .= " AND ($colEnd IS NULL OR $colEnd >= NOW())";

      $order = in_array('is_featured', $cols, true) ? "is_featured DESC, id DESC" : "id DESC";
      $select = "id, title, location";
      if ($colImage) $select .= ", $colImage AS image";
      if ($colUrl)   $select .= ", $colUrl AS url";
      if ($colType)  $select .= ", $colType AS type";
      if ($colHtml)  $select .= ", $colHtml AS html";

      $stmt = $pdo->prepare("SELECT $select FROM ads WHERE $where ORDER BY $order LIMIT 1");
      $stmt->execute([':loc' => 'home_under_featured_video']);
      $ad = $stmt->fetch(\PDO::FETCH_ASSOC);

      if ($ad) {
        $adTitle = trim((string)($ad['title'] ?? ''));
        $adType  = strtolower(trim((string)($ad['type'] ?? 'image')));
        $adHtml  = (string)($ad['html'] ?? '');
        $adImg   = trim((string)($ad['image'] ?? ''));
        $adUrl   = trim((string)($ad['url'] ?? ''));

        if ($adImg !== '' && !preg_match('~^https?://~i', $adImg)) {
          $adImg = rtrim((string)$baseUrl, '/') . '/' . ltrim($adImg, '/');
        }

        if ($adType === 'html' && trim($adHtml) !== '') {
          $__homeUnderFeaturedVideoAdHtml = '<div class="gdy-ad-slot gdy-ad-slot--html"><div class="gdy-ad-html">'.$adHtml.'</div></div>';
        } elseif ($adImg !== '') {
          $imgTag = '<img class="gdy-ad-img" src="'.h($adImg).'" alt="'.h($adTitle ?: 'Ad').'" loading="lazy">';
          if ($adUrl !== '') {
            $__homeUnderFeaturedVideoAdHtml = '<div class="gdy-ad-slot gdy-ad-slot--image"><a class="gdy-ad-link" href="'.h($adUrl).'" target="_blank" rel="noopener sponsored">'.$imgTag.'</a></div>';
          } else {
            $__homeUnderFeaturedVideoAdHtml = '<div class="gdy-ad-slot gdy-ad-slot--image">'.$imgTag.'</div>';
          }
        }
      }
    } catch (\Throwable $e) {
      $__homeUnderFeaturedVideoAdHtml = '';
    }
  }
?>
<?php if (trim($__homeUnderFeaturedVideoAdHtml) !== ''): ?>
  <div class="hm-under-featured-ad" aria-label="إعلان تحت الفيديو المميز">
    <?= $__homeUnderFeaturedVideoAdHtml ?>
  </div>
<?php endif; ?>


                </div>



                <!-- آخر الأخبار -->
	                <div class="hm-latest-right">
	                  <div class="hm-latest-right-head">
	                    <h2 class="hm-latest-right-title"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg> <?= h(__('آخر الأخبار')) ?></h2>
	                  </div>
	                  <div class="hm-latest-filters">
	                    <span class="hm-latest-label"><?= h(__('الفترة:')) ?></span>
	                    <a href="<?= h($makeHomePeriodUrl('all')) ?>"
	                       class="hm-pill <?= $currentPeriod === 'all' ? 'active' : '' ?>">
	                      <?= h(__('الكل')) ?>
	                    </a>
	                    <a href="<?= h($makeHomePeriodUrl('today')) ?>"
	                       class="hm-pill <?= $currentPeriod === 'today' ? 'active' : '' ?>">
	                      <?= h(__('اليوم')) ?>
	                    </a>
	                    <a href="<?= h($makeHomePeriodUrl('week')) ?>"
	                       class="hm-pill <?= $currentPeriod === 'week' ? 'active' : '' ?>">
	                      <?= h(__('هذا الأسبوع')) ?>
	                    </a>
	                    <a href="<?= h($makeHomePeriodUrl('month')) ?>"
	                       class="hm-pill <?= $currentPeriod === 'month' ? 'active' : '' ?>">
	                      <?= h(__('هذا الشهر')) ?>
	                    </a>
	                  </div>

	                  <div class="hm-card-grid">
	                    <?php foreach ($latestNews as $row): ?>
                  <?php
                  $title   = (string)($row['title'] ?? '');
                  $url     = $buildNewsUrl($row);
                  $date    = !empty($row['created_at']) ? date('Y-m-d', strtotime((string)$row['created_at'])) : '';
                  $excerpt = isset($row['excerpt']) ? trim((string)$row['excerpt']) : '';
                  $img     = hm_news_image($baseUrl, $row['image'] ?? '', (rtrim($baseUrl,'/').'/assets/images/placeholder-thumb.jpg'));
                  ?>
                  <article class="hm-card">
                    <a href="<?= h($url) ?>" class="hm-card-thumb">
                      <span class="hm-card-badge"><?= h(__('خبر')) ?></span>
                      <img src="<?= h($img) ?>" alt="<?= h($title) ?>">
                    </a>
                    <div class="hm-card-body">
                      <h3 class="hm-card-title">
                        <a href="<?= h($url) ?>">
                          <?= h(mb_substr($title,0,90,'UTF-8')) ?><?= mb_strlen($title,'UTF-8')>90?'…':'' ?>
                        </a>
                      </h3>
                      <?php if ($excerpt !== ''): ?>
                        <div class="hm-card-excerpt">
                          <?= h(mb_substr($excerpt,0,110,'UTF-8')) ?><?= mb_strlen($excerpt,'UTF-8')>110?'…':'' ?>
                        </div>
                      <?php endif; ?>
                      <div class="hm-card-meta">
                        <?php if ($date): ?>
                          <span><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($date) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </article>
	                    <?php endforeach; ?>
	                  </div>
	                </div>
	              </div>
            </section>
          <?php endif; ?>

          <!-- 3) بلوكات الأقسام بالترتيب المحدد -->
          <?php foreach ($blocks as $key => $block): ?>
            <?php
            // إخفاء بلوك "الرأي" مع الإبقاء على بلوك "كتّاب الرأي" في الأسفل
            if ($key === 'opinion') continue;

            if (empty($block['category'])) continue;
            $catRow         = $block['category'];
            $catUrl         = $buildCategoryUrl($catRow);
            $hasNews        = !empty($block['news']);
            $isOpinionBlock = ($key === 'opinion');

            // عنوان البلوك (خام) ثم ترجمة إن وُجدت في ملفات اللغات
            $blockTitleRaw = isset($block['title']) && $block['title'] !== ''
                ? (string)$block['title']
                : (string)($catRow['name'] ?? '');
            $blockTitle = __($blockTitleRaw);
?>
            <section class="hm-section" aria-label="<?= h($blockTitle) ?>">
              <div class="hm-block <?= $isOpinionBlock ? 'hm-block-opinion-light' : '' ?>">
                <div class="hm-block-header">
                  <div class="hm-block-title">
                    <a href="<?= h($catUrl) ?>"><?= h($blockTitle) ?></a>
                  </div>
                  <div class="hm-block-more">
                    <a href="<?= h($catUrl) ?>"><?= h(__('عرض المزيد')) ?></a>
                  </div>
                </div>

                <?php if ($isOpinionBlock): ?>
                  <!-- قسم الرأي: اسم الكاتب + صورته + اسم صفحته + عنوان المقال -->
                  <div class="hm-opinion-articles">
                    <?php if ($hasNews): ?>
                      <?php
                      static $hmOpinionAuthorsCache = [];
                      ?>
                      <?php foreach ($block['news'] as $row): ?>
                        <?php
                        $title   = (string)($row['title'] ?? '');
                        $url     = $buildNewsUrl($row);
                        $date    = !empty($row['created_at']) ? date('Y-m-d', strtotime((string)$row['created_at'])) : '';
                        $excerpt = '';
                        if (!empty($row['excerpt'])) {
                            $excerpt = (string)$row['excerpt'];
                        } elseif (!empty($row['content'])) {
                            $excerpt = strip_tags((string)$row['content']);
                        }

                        // صورة المقال للرأي
                        $opinionArticleImage = hm_news_image(
                            $baseUrl,
                            $row['image'] ?? '',
                            (rtrim($baseUrl,'/').'/assets/images/placeholder-thumb.jpg')
                        );

                        $opinionAuthorName      = '';
                        $opinionAuthorPageTitle = '';
                        $opinionAuthorAvatar    = ''; // سنملؤها من جدول opinion_authors إن وجدت
                        $opinionAuthorUrl       = '';

                        $aid = isset($row['opinion_author_id']) ? (int)$row['opinion_author_id'] : 0;
                        $pdoLocal = gdy_pdo_safe();
                        if ($aid > 0 && $pdoLocal instanceof PDO) {
                            if (!array_key_exists($aid, $hmOpinionAuthorsCache)) {
                                try {
                                    $stmtA = $pdoLocal->prepare("
                                        SELECT id, name, slug, avatar, page_title
                                        FROM opinion_authors
                                        WHERE id = :id AND is_active = 1
                                        LIMIT 1
                                    ");
                                    $stmtA->execute([':id' => $aid]);
                                    $hmOpinionAuthorsCache[$aid] = $stmtA->fetch(PDO::FETCH_ASSOC) ?: null;
                                } catch (Throwable $e) {
                                    $hmOpinionAuthorsCache[$aid] = null;
                                }
                            }

                            $aRow = $hmOpinionAuthorsCache[$aid] ?? null;
                            if ($aRow) {
                                $opinionAuthorName      = (string)($aRow['name'] ?? '');
                                $opinionAuthorPageTitle = (string)($aRow['page_title'] ?? '');

                                // صورة الكاتب لو موجودة
                                $avatarRaw = trim((string)($aRow['avatar'] ?? ''));
                                if ($avatarRaw !== '') {
                                    if (preg_match('~^https?://~i', $avatarRaw)) {
                                        $opinionAuthorAvatar = $avatarRaw;
                                    } else {
                                        $opinionAuthorAvatar = rtrim($baseUrl, '/') . '/' . ltrim($avatarRaw, '/');
                                    }
                                }

                                $slugA = trim((string)($aRow['slug'] ?? ''));
                                if ($slugA !== '') {
                                    $opinionAuthorUrl = rtrim($baseUrl, '/') . '/opinion_author.php?slug=' . rawurlencode($slugA);
                                } else {
                                    $opinionAuthorUrl = rtrim($baseUrl, '/') . '/opinion_author.php?id=' . $aid;
                                }
                            }
                        }

                        // المطلوب: عدم إظهار صورة الكاتب في الصفحة الرئيسية.
                        // نستخدم صورة المقال فقط (أو الافتراضية) داخل الدائرة.
                        $avatarFinal = $opinionArticleImage;
                        ?>
                        <article class="hm-opinion-article-card">
                          <div class="hm-opinion-article-text">
                            <div class="hm-opinion-article-header">
                              <?php if ($opinionAuthorName !== ''): ?>
                                <div class="hm-opinion-article-author-name">
                                  <?php if ($opinionAuthorUrl !== ''): ?>
                                    <a href="<?= h($opinionAuthorUrl) ?>">
                                      <?= h($opinionAuthorName) ?>
                                    </a>
                                  <?php else: ?>
                                    <?= h($opinionAuthorName) ?>
                                  <?php endif; ?>
                                </div>
                              <?php endif; ?>
                              <div class="hm-opinion-article-quote">«»</div>
                              <span class="hm-opinion-article-badge"><?= h(__('مقال رأي')) ?></span>
                            </div>

                            <?php if ($opinionAuthorPageTitle !== ''): ?>
                              <div class="hm-opinion-article-page-title">
                                <?= h($opinionAuthorPageTitle) ?>
                              </div>
                            <?php endif; ?>

                            <!-- عنوان المقال -->
                            <p class="hm-opinion-article-title">
                              <a href="<?= h($url) ?>">
                                <?= h(mb_substr($title,0,110,'UTF-8')) ?><?= mb_strlen($title,'UTF-8')>110?'…':'' ?>
                              </a>
                            </p>

                            <!-- مقتطف اختياري تحت العنوان -->
                            <?php if ($excerpt !== ''): ?>
                              <div class="hm-opinion-article-snippet">
                                <?= h(mb_substr($excerpt,0,130,'UTF-8')) ?><?= mb_strlen($excerpt,'UTF-8')>130?'…':'' ?>
                              </div>
                            <?php endif; ?>

                            <?php if ($date): ?>
                              <div class="hm-opinion-article-meta">
                                <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($date) ?>
                              </div>
                            <?php endif; ?>
                          </div>

                          <div class="hm-opinion-article-avatar">
                            <?php if ($opinionAuthorUrl !== ''): ?>
                              <a href="<?= h($opinionAuthorUrl) ?>">
                                <img src="<?= h($avatarFinal) ?>"
                                     alt="<?= h($opinionAuthorName ?: $title) ?>"
                                     onerror="this.onerror=null;this.src='<?= h($opinionArticleImage) ?>';">
                              </a>
                            <?php else: ?>
                              <img src="<?= h($avatarFinal) ?>"
                                   alt="<?= h($opinionAuthorName ?: $title) ?>"
                                   onerror="this.onerror=null;this.src='<?= h($opinionArticleImage) ?>';">
                            <?php endif; ?>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <div class="hm-card-empty">
                        <?= h(__('لا توجد مقالات رأي حالياً.')) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <!-- باقي الأقسام: أخبار عامة، سياسة، اقتصاد، منوعات، رياضة، فيديو، إنفوجراف -->
                  <div class="hm-card-grid">
                    <?php if ($hasNews): ?>
                      <?php foreach ($block['news'] as $row): ?>
                        <?php
                        $title   = (string)($row['title'] ?? '');
                        $url     = $buildNewsUrl($row);
                        $date    = !empty($row['created_at']) ? date('Y-m-d', strtotime((string)$row['created_at'])) : '';
                        $excerpt = isset($row['excerpt']) ? trim((string)$row['excerpt']) : '';
                        $img     = hm_news_image($baseUrl, $row['image'] ?? '', (rtrim($baseUrl,'/').'/assets/images/placeholder-thumb.jpg'));
                        ?>
                        <article class="hm-card">
                          <a href="<?= h($url) ?>" class="hm-card-thumb">
                            <span class="hm-card-badge"><?= h($blockTitle) ?></span>
                            <img src="<?= h($img) ?>" alt="<?= h($title) ?>">
                          </a>
                          <div class="hm-card-body">
                            <h3 class="hm-card-title">
                              <a href="<?= h($url) ?>">
                                <?= h(mb_substr($title,0,90,'UTF-8')) ?><?= mb_strlen($title,'UTF-8')>90?'…':'' ?>
                              </a>
                            </h3>
                            <?php if ($excerpt !== ''): ?>
                              <div class="hm-card-excerpt">
                                <?= h(mb_substr($excerpt,0,110,'UTF-8')) ?><?= mb_strlen($excerpt,'UTF-8')>110?'…':'' ?>
                              </div>
                            <?php endif; ?>
                            <div class="hm-card-meta">
                              <?php if ($date): ?>
                                <span><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($date) ?></span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <div class="hm-card-empty">
                        <?= h(__('لا توجد أخبار في هذا القسم حالياً.')) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

              </div>
            </section>
          <?php endforeach; ?>

          <!-- 7) كتّاب الرأي (من الدالة في home.php إن وُجدت) -->
          <?php if (isset($renderOpinionAuthorsBlock) && is_callable($renderOpinionAuthorsBlock)): ?>
            <?php $renderOpinionAuthorsBlock(); ?>
          <?php endif; ?>
<!-- 4) النشرة البريدية + الروابط السريعة -->
          <section class="hm-section" aria-label="<?= h(__('النشرة البريدية والروابط السريعة')) ?>">
            <div class="hm-newsletter">
              <div>
                <h2><?= h(__('اشترك في النشرة البريدية')) ?></h2>
                <p><?= h(__('استقبل أحدث الأخبار والتقارير مباشرة إلى بريدك الإلكتروني.')) ?></p>
                <form method="post" action="<?= h($baseUrl) ?>/api/newsletter/subscribe" class="hm-newsletter-form" data-newsletter-form>
                  <input type="email" name="newsletter_email" class="form-control form-control-sm" required autocomplete="email" placeholder="<?= h(__('أدخل بريدك الإلكتروني')) ?>">
                  <button type="submit" class="btn btn-sm btn-primary">
                    <?= h(__('اشتراك')) ?>
                    </button>
                </form>
                <div class="hm-newsletter-msg" data-newsletter-msg></div>
              </div>
              <div class="hm-quick-links">
                <a href="<?= h(rtrim($baseUrl,'/')) ?>/" class="hm-quick-link"><?= h(__('الرئيسية')) ?></a>
                <a href="<?= h($buildCategoryUrl(['slug'=>'general-news'])) ?>" class="hm-quick-link"><?= h(__('أخبار عامة')) ?></a>
                <a href="<?= h($buildCategoryUrl(['slug'=>'politics'])) ?>" class="hm-quick-link"><?= h(__('سياسة')) ?></a>
                <a href="<?= h($buildCategoryUrl(['slug'=>'economy'])) ?>" class="hm-quick-link"><?= h(__('اقتصاد')) ?></a>
                <a href="<?= h($buildCategoryUrl(['slug'=>'sports'])) ?>" class="hm-quick-link"><?= h(__('رياضة')) ?></a>
                <a href="<?= h($buildCategoryUrl(['slug'=>'opinion'])) ?>" class="hm-quick-link"><?= h(__('مقالات الرأي')) ?></a>
              </div>
            </div>
          </section>


          

        </div><!-- /.hm-main-col -->
      </div><!-- /.main col -->

      <?php if (empty($sidebarHidden)): ?>
        <div class="col-lg-4 mt-3 mt-lg-0">
          <?php
          $sidebarPath = __DIR__ . '/partials/sidebar.php';
          if (is_file($sidebarPath)) {
              require $sidebarPath;
          }
          ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>


<script>
(function(){
  function qs(sel, ctx){ return (ctx||document).querySelector(sel); }
  function qsa(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }

  function buildPlayer(type, src){
    if(!src) return null;
    type = (type||'').toLowerCase();
    if(type === 'iframe'){
      var url = src;
      // add autoplay
      if(url.indexOf('?') === -1) url += '?autoplay=1&rel=0';
      else url += '&autoplay=1&rel=0';
      var iframe = document.createElement('iframe');
      iframe.src = url;
      iframe.setAttribute('frameborder','0');
      iframe.setAttribute('allow','autoplay; encrypted-media; picture-in-picture');
      iframe.setAttribute('allowfullscreen','');
      iframe.style.width='100%';
      iframe.style.height='100%';
      return iframe;
    } else if(type === 'video'){
      var v = document.createElement('video');
      v.controls = true;
      v.autoplay = true;
      v.playsInline = true;
      v.style.width='100%';
      v.style.height='100%';
      var s = document.createElement('source');
      s.src = src;
      s.type = 'video/mp4';
      v.appendChild(s);
      return v;
    }
    return null;
  }

  function initFeaturedVideo(){
    var box = qs('[data-hm-featured]');
    if(!box) return;

    var stage = qs('[data-hm-stage]', box);
    var titleEl = qs('[data-hm-stage-title]', box);
    var descEl  = qs('[data-hm-stage-desc]', box);
    var playBtn = qs('[data-hm-video-open]', box);
    var thumbsWrap = qs('[data-hm-thumbs]', box);

    // arrows for thumbs (بديل شريط التمرير)
    var thumbsPrev = qs('[data-hm-thumbs-prev]', box);
    var thumbsNext = qs('[data-hm-thumbs-next]', box);

    function hm_isRTL(el){
      try { return (getComputedStyle(el).direction || 'ltr') === 'rtl'; } catch(e){ return true; }
    }

    function hm_getScrollPos(el){
      var max = Math.max(0, el.scrollWidth - el.clientWidth);
      var sl = el.scrollLeft || 0;
      if(hm_isRTL(el)){
        // Firefox: negative, Chrome/Safari: max - visual
        if(sl < 0) return Math.min(max, -sl);
        return Math.min(max, max - sl);
      }
      return Math.min(max, sl);
    }

    function hm_setScrollPos(el, pos){
      var max = Math.max(0, el.scrollWidth - el.clientWidth);
      var p = Math.max(0, Math.min(max, pos));
      if(hm_isRTL(el)){
        var sl = el.scrollLeft || 0;
        if(sl < 0){
          el.scrollLeft = -p;
        }else{
          el.scrollLeft = max - p;
        }
      }else{
        el.scrollLeft = p;
      }
    }

    function hm_updateThumbNav(){
      if(!thumbsWrap || !thumbsPrev || !thumbsNext) return;
      var max = Math.max(0, thumbsWrap.scrollWidth - thumbsWrap.clientWidth);
      if(max <= 2){
        thumbsPrev.style.display='none';
        thumbsNext.style.display='none';
        return;
      }
      thumbsPrev.style.display='';
      thumbsNext.style.display='';
      var pos = hm_getScrollPos(thumbsWrap);
      thumbsPrev.disabled = (pos <= 0);
      thumbsNext.disabled = (pos >= (max - 1));
    }

    function hm_scrollThumbs(dir){
      if(!thumbsWrap) return;
      var max = Math.max(0, thumbsWrap.scrollWidth - thumbsWrap.clientWidth);
      if(max <= 0) return;
      var step = Math.max(220, Math.floor(thumbsWrap.clientWidth * 0.8));
      var pos = hm_getScrollPos(thumbsWrap);
      hm_setScrollPos(thumbsWrap, pos + (dir * step));
      hm_updateThumbNav();
    }

    if(thumbsPrev) thumbsPrev.addEventListener('click', function(){ hm_scrollThumbs(-1); });
    if(thumbsNext) thumbsNext.addEventListener('click', function(){ hm_scrollThumbs(1); });
    if(thumbsWrap){
      var _hmThumbRAF = 0;
      thumbsWrap.addEventListener('scroll', function(){
        if(_hmThumbRAF) cancelAnimationFrame(_hmThumbRAF);
        _hmThumbRAF = requestAnimationFrame(hm_updateThumbNav);
      }, {passive:true});
    }
    window.addEventListener('resize', hm_updateThumbNav);
    setTimeout(hm_updateThumbNav, 0);

    var thumbs = qsa('[data-hm-thumb]', box);

    var modal = qs('#hmVideoModal');
    var frame = qs('#hmVideoModalFrame');
    var closeEls = modal ? qsa('[data-hm-video-close]', modal) : [];

    function setActiveThumb(btn){
      thumbs.forEach(function(b){ b.classList.remove('active'); });
      if(btn) btn.classList.add('active');
    }

    function updateStage(data, setBg){
      if(!data) return;
      if(stage){
        stage.setAttribute('data-type', data.type || '');
        stage.setAttribute('data-src', data.src || '');
        if(setBg){
          var t = (data.thumb || '').trim();
          stage.style.backgroundImage = t ? "url('"+t.replace(/'/g,"%27")+"')" : '';
        }
      }
      if(playBtn){
        playBtn.setAttribute('data-hm-video-type', data.type || '');
        playBtn.setAttribute('data-hm-video-src', data.src || '');
      }
      if(titleEl) titleEl.textContent = data.title || '';
      if(descEl){
        if((data.desc||'').trim() === ''){
          descEl.style.display='none';
          descEl.textContent='';
        }else{
          descEl.style.display='';
          descEl.textContent = data.desc;
        }
      }
    }

    function openModal(type, src){
      if(!modal || !frame) return;
      // clear previous
      frame.innerHTML = '';
      var player = buildPlayer(type, src);
      if(!player) return;
      frame.appendChild(player);
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
    }

    function closeModal(){
      if(!modal || !frame) return;
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden','true');
      frame.innerHTML = '';
      document.body.style.overflow='';
    }

    // thumbs click
    thumbs.forEach(function(btn){
      btn.addEventListener('click', function(){
        var data = {
          title: btn.getAttribute('data-title') || '',
          desc:  btn.getAttribute('data-desc') || '',
          type:  btn.getAttribute('data-type') || '',
          src:   btn.getAttribute('data-src') || '',
          thumb: btn.getAttribute('data-thumb') || ''
        };
        setActiveThumb(btn);
        updateStage(data, true);
      });
    });

    // play click (button + stage click)
    function handlePlay(){
      var type = playBtn ? playBtn.getAttribute('data-hm-video-type') : (stage ? stage.getAttribute('data-type') : '');
      var src  = playBtn ? playBtn.getAttribute('data-hm-video-src')  : (stage ? stage.getAttribute('data-src') : '');
      openModal(type, src);
    }

    if(playBtn) playBtn.addEventListener('click', function(e){ e.preventDefault(); handlePlay(); });
    if(stage) stage.addEventListener('click', function(e){
      // avoid double when clicking play button itself
      if(e.target && playBtn && playBtn.contains(e.target)) return;
      handlePlay();
    });

    // close handlers
    if(modal){
      closeEls.forEach(function(el){ el.addEventListener('click', function(e){ e.preventDefault(); closeModal(); }); });
      modal.addEventListener('click', function(e){
        if(e.target === modal) closeModal();
      });
      document.addEventListener('keydown', function(e){
        if(e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', initFeaturedVideo);
})();
</script>