<?php
// frontend/views/partials/sidebar.php

$pdo = $pdo ?? gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * 🔒 قراءة إعداد إظهار/إخفاء السايدبار من الإعدادات العامة
 * المفتاح في قاعدة البيانات: layout.sidebar_mode (visible / hidden)
 */
$sidebarMode  = 'visible';

// ✅ Force sidebar override (used by specific pages مثل صفحة التصنيف)
$forceSidebar = (bool)($GLOBALS['GDY_FORCE_SIDEBAR'] ?? false);
$siteSettings = $GLOBALS['site_settings'] ?? null;

// أولاً: من $GLOBALS['site_settings'] لو تم حقنها من الـ front controller
if (is_array($siteSettings) && isset($siteSettings['layout_sidebar_mode'])) {
    $sidebarMode = ($siteSettings['layout_sidebar_mode'] === 'hidden') ? 'hidden' : 'visible';
} else {
    // ثانياً: استعلام مباشر من جدول settings في حال لم تُحقن $site_settings
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` = :k LIMIT 1");
            $stmt->execute([':k' => 'layout.sidebar_mode']);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val === 'hidden') {
                $sidebarMode = 'hidden';
            }
        } catch (Throwable $e) {
            // تجاهل الخطأ، واعتبار الوضع visible
        }
    }
}

// إذا كان الإعداد "hidden" → لا نعرض السايدبار أبداً
if ($sidebarMode === 'hidden' && !$forceSidebar) {
    return;
}
// لو تم تفعيل الإجبار، نتجاوز وضع hidden
if ($forceSidebar) {
    $sidebarMode = 'visible';
}


$sidebarAds     = [];
$mostReadNews   = [];
$sidebarAuthors = [];

if ($pdo instanceof PDO) {
    // إعلانات الشريط الجانبي
    try {
        // توافق مع اختلاف أعمدة جدول ads
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
            $sqlAds = "SELECT id, title, image, url FROM ads WHERE (location IS NULL OR location = '' OR location IN ('sidebar', 'sidebar_ads', 'sidebar_top', 'sidebar_bottom')) AND location <> 'home_under_featured_video' ORDER BY id DESC LIMIT 5";
        } else {
            $sqlAds = "SELECT id, title, image, url FROM ads ORDER BY id DESC LIMIT 5";
        }

        if ($stmt = $pdo->query($sqlAds)) {
            $sidebarAds = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $sidebarAds = [];
    }

    // شائع الآن (الأكثر قراءة)
    try {
        $sqlMostRead = "
            SELECT id, title, slug, published_at, views
            FROM news
            WHERE status = 'published'
            ORDER BY views DESC, id DESC
            LIMIT 5
        ";
        if ($stmt = $pdo->query($sqlMostRead)) {
            $mostReadNews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $mostReadNews = [];
    }

    

// الأحدث (آخر الأخبار)
try {
    $sqlLatest = "
        SELECT id, title, slug, published_at, views
        FROM news
        WHERE status = 'published'
        ORDER BY published_at DESC, id DESC
        LIMIT 5
    ";
    if ($stmt = $pdo->query($sqlLatest)) {
        $latestNews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    $latestNews = [];
}
// كتّاب الموقع
    try {
        $sqlAuthors = "
            SELECT id, name, specialization, avatar, social_website, social_twitter
            FROM opinion_authors
            WHERE is_active = 1
            ORDER BY display_order DESC, articles_count DESC, name ASC
            LIMIT 6
        ";
        if ($stmt = $pdo->query($sqlAuthors)) {
            $sidebarAuthors = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $sidebarAuthors = [];
    }
}

// baseUrl من السياق إن وُجد
$baseUrl = rtrim($baseUrl ?? ($GLOBALS['baseUrl'] ?? ''), '/');

/**
 * ✅ توحيد منطق رابط الخبر:
 * - أولاً: /news/id/{id}
 * - احتياطيًا: /news/{slug}
 */
$buildNewsUrl = function (array $row) use ($baseUrl): string {
    $id = isset($row['id']) ? (int)$row['id'] : 0;

    // ✅ وضع الـ ID: نُصدر دائماً روابط /news/id/{id}
    if ($id > 0) {
        return rtrim($baseUrl, '/') . '/news/id/' . $id;
    }

    // fallback: لو ما عندنا ID لأي سبب، نرجع للـ slug (وسيتم تحويله إلى ID عبر app.php إذا كانت خريطة السلاگ متوفرة)
    $slug = isset($row['slug']) ? trim((string)$row['slug']) : '';
    if ($slug !== '') {
        return rtrim($baseUrl, '/') . '/news/' . rawurlencode($slug);
    }

    return rtrim($baseUrl, '/') . '/news';
};
?>

<style>
/* ضبط عرض السايدبار حتى لا تتمدد البطاقات على كامل العمود */
.gdy-sidebar {
    max-width: 340px;   /* يمكنك تغيير العرض هنا (مثلاً 320 أو 360) */
    margin-left: auto;  /* في RTL يدفع السايدبار إلى يمين العمود */
}
@media (max-width: 991.98px) {
    .gdy-sidebar {
        max-width: 100%;
    }
}

/* بطاقة سايدبار رئيسية */
.gdy-sidecard {
    background: linear-gradient(135deg, rgba(var(--primary-rgb), .07), rgba(255,255,255,.96));
    border-radius: 1.3rem;
    border: 1px solid rgba(var(--primary-rgb), .20);
    box-shadow: var(--soft-shadow, 0 16px 34px rgba(15,23,42,.10));
    margin-bottom: 1.4rem;
    overflow: hidden;
    position: relative;
}
.gdy-sidecard::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at top right, rgba(var(--primary-rgb), .18), transparent 58%),
        radial-gradient(circle at bottom left, rgba(var(--primary-rgb), .10), transparent 60%);
    opacity: 1;
    pointer-events: none;
}
.gdy-sidecard-inner {
    position: relative;
    z-index: 1;
}

/* رأس البطاقة */
.gdy-sidecard-header {
    padding: .75rem 1rem .6rem;
    border-bottom: 1px solid rgba(var(--primary-rgb), .16);
    background: rgba(var(--primary-rgb), .06);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.gdy-sidecard-title {
    font-size: .9rem;
    font-weight: 900;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.gdy-sidecard-title i {
    color: var(--primary);
}
.gdy-sidecard-badge {
    font-size: .7rem;
    padding: .15rem .65rem;
    border-radius: 999px;
    background: rgba(var(--primary-rgb), .10);
    color: var(--primary-dark);
    border: 1px solid rgba(var(--primary-rgb), .22);
}

/* جسم البطاقة */
.gdy-sidecard-body {
    padding: .9rem .95rem 1rem;
}

/* الكروت الداخلية (مشتركة للأخبار والإعلانات) */
.gdy-mini-card {
    border-radius: .9rem;
    border: 1px solid rgba(var(--primary-rgb), .18);
    background: rgba(255,255,255,.92);
    padding: .55rem .7rem;
    margin-bottom: .55rem;
    text-decoration: none;
    display: flex;
    align-items: flex-start;
    gap: .6rem;
    transition: all .25s ease;
}
.gdy-mini-card:hover {
    border-color: rgba(var(--primary-rgb), .55);
    box-shadow: 0 14px 28px rgba(15,23,42,.10);
    background: rgba(255,255,255,.98);
    text-decoration: none;
}
.gdy-mini-rank {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: radial-gradient(circle at top, var(--primary), var(--primary-dark));
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    font-weight: 900;
    flex-shrink: 0;
}
.gdy-mini-icon {
    font-size: .75rem;
}
.gdy-mini-content {
    flex: 1;
}
.gdy-mini-title {
    font-size: .86rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: .15rem;
}
.gdy-mini-meta {
    font-size: .73rem;
    color: #64748b;
}

/* كتّاب الموقع */
.gdy-author-card {
    border-radius: .9rem;
    border: 1px solid rgba(148,163,184,0.4);
    background: rgba(15,23,42,0.9);
    padding: .55rem .7rem;
    margin-bottom: .55rem;
    display: flex;
    align-items: center;
    gap: .6rem;
    transition: all .25s.ease;
}
.gdy-author-card:hover {
    border-color: #38bdf8;
    box-shadow: 0 10px 24px rgba(15,23,42,0.9);
    background: rgba(15,23,42,0.98);
}
.gdy-author-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    overflow: hidden;
    flex-shrink: 0;
    background: #1f2933;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    color: #e5e7eb;
}
.gdy-author-name {
    font-size: .85rem;
    font-weight: 600;
    color: #f9fafb;
}
.gdy-author-specialty {
    font-size: .72rem;
    color: #9ca3af;
}

/* صورة صغيرة في كرت الإعلان (اختيارية) */
.gdy-mini-image {
    margin-top: .3rem;
    border-radius: .6rem;
    overflow: hidden;
}
.gdy-mini-image img {
    width: 100%;
    height: auto;
    display: block;
}
</style>

<aside class="gdy-sidebar">

  <!-- بطاقة الإعلانات -->
  <div class="gdy-sidecard">
    <div class="gdy-sidecard-inner">
      <div class="gdy-sidecard-header">
        <span class="gdy-sidecard-title">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إعلانات
        </span>
        <span class="gdy-sidecard-badge">إعلان</span>
      </div>
      <div class="gdy-sidecard-body">
        <?php if (empty($sidebarAds)): ?>
          <div class="gdy-mini-card">
            <div class="gdy-mini-rank">
              <svg class="gdy-icon gdy-mini-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </div>
            <div class="gdy-mini-content">
              <div class="gdy-mini-title">مساحة إعلانية</div>
              <div class="gdy-mini-meta">
                يمكنك إضافة إعلانات من لوحة التحكم (قسم الإعلانات) لتظهر هنا بشكل تلقائي.
              </div>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($sidebarAds as $ad): ?>
            <div class="gdy-mini-card">
              <div class="gdy-mini-rank">
                <svg class="gdy-icon gdy-mini-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              </div>
              <div class="gdy-mini-content">
                <?php if (!empty($ad['url'])): ?>
                  <a href="<?= h($ad['url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                <?php endif; ?>

                <?php if (!empty($ad['title'])): ?>
                  <div class="gdy-mini-title"><?= h($ad['title']) ?></div>
                <?php endif; ?>

                <?php if (!empty($ad['image'])): ?>
                  <div class="gdy-mini-image">
                    <img src="<?= h($ad['image']) ?>" alt="<?= h($ad['title'] ?? '') ?>">
                  </div>
                <?php endif; ?>

                <?php if (!empty($ad['url'])): ?>
                  </a>
                <?php endif; ?>

                <div class="gdy-mini-meta mt-1">
                  إعلان من رعاة الموقع.
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
<!-- بطاقة الأخبار (شائع/الأحدث) -->
<div class="gdy-sidecard gdy-sidecard--tabs" data-gdy-tabs>
  <div class="gdy-sidecard-inner">
    <div class="gdy-sidecard-header">
      <span class="gdy-sidecard-title">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg> <?= h(__('الأخبار')) ?>
      </span>

      <div class="gdy-side-tabs" role="tablist" aria-label="Sidebar News Tabs">
        <button type="button" class="gdy-tab-btn is-active" role="tab" aria-selected="true" data-tab="mostread">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('شائع')) ?>
        </button>
        <button type="button" class="gdy-tab-btn" role="tab" aria-selected="false" data-tab="latest">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('الأحدث')) ?>
        </button>
      </div>
    </div>

    <div class="gdy-sidecard-body">
      <!-- شائع -->
      <div class="gdy-tab-panel is-active" role="tabpanel" data-panel="mostread">
        <?php if (empty($mostReadNews)): ?>
          <div class="gdy-mini-card">
            <div class="gdy-mini-rank">1</div>
            <div class="gdy-mini-content">
              <div class="gdy-mini-title">لا توجد بيانات كافية بعد</div>
              <div class="gdy-mini-meta">
                ستظهر هنا أكثر الأخبار قراءة بعد تفاعل القرّاء مع المحتوى.
              </div>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($mostReadNews as $i => $item): ?>
            <?php $link = $buildNewsUrl($item); ?>
            <a href="<?= h($link) ?>" class="gdy-mini-card">
              <div class="gdy-mini-rank"><?= $i + 1 ?></div>
              <div class="gdy-mini-content">
                <div class="gdy-mini-title"><?= h($item['title'] ?? '') ?></div>
                <div class="gdy-mini-meta">
                  <?php if (!empty($item['published_at'])): ?>
                    <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <?= h(date('Y-m-d', strtotime($item['published_at']))) ?>
                  <?php endif; ?>
                  <?php if (!empty($item['views'])): ?>
                    — <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= (int)$item['views'] ?> <?= h(__('مشاهدة')) ?>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- الأحدث -->
      <div class="gdy-tab-panel" role="tabpanel" data-panel="latest">
        <?php if (empty($latestNews)): ?>
          <div class="gdy-mini-card">
            <div class="gdy-mini-rank">1</div>
            <div class="gdy-mini-content">
              <div class="gdy-mini-title"><?= h(__('لا توجد أخبار بعد')) ?></div>
              <div class="gdy-mini-meta"><?= h(__('ستظهر هنا أحدث الأخبار عند نشرها.')) ?></div>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($latestNews as $i => $item): ?>
            <?php $link = $buildNewsUrl($item); ?>
            <a href="<?= h($link) ?>" class="gdy-mini-card">
              <div class="gdy-mini-rank"><?= $i + 1 ?></div>
              <div class="gdy-mini-content">
                <div class="gdy-mini-title"><?= h($item['title'] ?? '') ?></div>
                <div class="gdy-mini-meta">
                  <?php if (!empty($item['published_at'])): ?>
                    <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <?= h(date('Y-m-d', strtotime($item['published_at']))) ?>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- بطاقة كتّاب الموقع -->

  <div class="gdy-sidecard">
    <div class="gdy-sidecard-inner">
      <div class="gdy-sidecard-header">
        <span class="gdy-sidecard-title">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> كتّاب الموقع
        </span>
        <span class="gdy-sidecard-badge">رأي</span>
      </div>
      <div class="gdy-sidecard-body">
        <?php if (empty($sidebarAuthors)): ?>
          <div class="gdy-author-card">
            <div class="gdy-author-avatar">أ</div>
            <div>
              <div class="gdy-author-name">أحمد علي</div>
              <div class="gdy-author-specialty">زاوية أسبوعية حول القضايا العامة.</div>
            </div>
          </div>
          <div class="gdy-author-card">
            <div class="gdy-author-avatar">س</div>
            <div>
              <div class="gdy-author-name">سارة محمد</div>
              <div class="gdy-author-specialty">مقالات تحليلية في الاقتصاد والمجتمع.</div>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($sidebarAuthors as $a): ?>
            <?php $authorUrl = $a['social_website'] ?? $a['social_twitter'] ?? ''; ?>
            <div class="gdy-author-card">
              <div class="gdy-author-avatar">
                <?php if (!empty($a['avatar'])): ?>
                  <img src="<?= h($a['avatar']) ?>" alt="<?= h($a['name'] ?? '') ?>"
                       style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                  <?= h(mb_substr($a['name'] ?? '?', 0, 1, 'UTF-8')) ?>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1">
                <?php if ($authorUrl): ?>
                  <a href="<?= h($authorUrl) ?>" target="_blank" rel="noopener"
                     class="text-decoration-none">
                    <div class="gdy-author-name"><?= h($a['name'] ?? '') ?></div>
                  </a>
                <?php else: ?>
                  <div class="gdy-author-name"><?= h($a['name'] ?? '') ?></div>
                <?php endif; ?>

                <?php if (!empty($a['specialization'])): ?>
                  <div class="gdy-author-specialty"><?= h($a['specialization']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

</aside>
