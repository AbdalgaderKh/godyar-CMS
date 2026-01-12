<?php
/**
 * frontend/views/category_modern.php
 * واجهة عرض الأخبار حسب التصنيف (نسخة بخلفية فاتحة + بطاقات)
 *
 * يعتمد على المتغيرات القادمة من category.php:
 * $category, $newsList, $catTrending, $sidebarHidden, $buildNewsUrl,
 * $siteName, $siteTagline, $isVideoCategory, $isOpinionCategory,
 * $opinionAuthorsMap, $baseUrl, $sort, $period
 */

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

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

$baseUrl = $baseUrl ?? '';
$slug    = $category['slug'] ?? '';
$name    = $category['name'] ?? 'تصنيف';
$desc    = $category['description'] ?? '';

$currentSort   = $sort   ?? 'latest';
$currentPeriod = $period ?? 'all';

$hasNews    = !empty($newsList);
$hasTrending = !empty($catTrending);

$makeUrl = function (string $sortVal, string $periodVal = 'all') use ($slug, $baseUrl): string {
    $query = [
        'slug'   => $slug,
        'sort'   => $sortVal,
        'period' => $periodVal,
    ];
    return rtrim($baseUrl, '/') . '/category/' . rawurlencode($slug) . '?' . http_build_query(['sort' => $sortVal, 'period' => $periodVal]);
};

?>
<section class="gdy-cat-page py-2 py-md-3">
  <div class="container">
    <div class="row g-3">

      <div class="col-lg-8">
        <div class="cat-header">

          <div class="cat-breadcrumb mb-2">
            <a href="<?= h(rtrim($baseUrl, '/') . '/') ?>">
              <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg>
              الرئيسية
            </a>
            <span class="mx-1 text-muted">/</span>
            <span><?= h($name) ?></span>
          </div>

          <div class="cat-main-title-wrap">
            <div>
              <h1 class="cat-main-title">
                <?= h($name) ?>
              </h1>
              <?php if (!empty($desc)): ?>
                <p class="cat-main-desc">
                  <?= h($desc) ?>
                </p>
              <?php endif; ?>
            </div>
          </div>

          <!-- شريط الفلترة -->
          <div class="cat-toolbar">
            <div class="cat-sort-group">
              <span class="cat-filter-label">ترتيب حسب:</span>
              <a href="<?= h($makeUrl('latest', $currentPeriod)) ?>"
                 class="cat-pill <?= $currentSort === 'latest' ? 'active' : '' ?>">
                الأحدث
              </a>
              <a href="<?= h($makeUrl('popular', $currentPeriod)) ?>"
                 class="cat-pill <?= $currentSort === 'popular' ? 'active' : '' ?>">
                الأكثر قراءة
              </a>
            </div>

            <div class="cat-filter-group">
              <span class="cat-filter-label">الفترة:</span>
              <a href="<?= h($makeUrl($currentSort, 'all')) ?>"
                 class="cat-pill <?= $currentPeriod === 'all' ? 'active' : '' ?>">
                الكل
              </a>
              <a href="<?= h($makeUrl($currentSort, 'today')) ?>"
                 class="cat-pill <?= $currentPeriod === 'today' ? 'active' : '' ?>">
                اليوم
              </a>
              <a href="<?= h($makeUrl($currentSort, 'week')) ?>"
                 class="cat-pill <?= $currentPeriod === 'week' ? 'active' : '' ?>">
                هذا الأسبوع
              </a>
              <a href="<?= h($makeUrl($currentSort, 'month')) ?>"
                 class="cat-pill <?= $currentPeriod === 'month' ? 'active' : '' ?>">
                هذا الشهر
              </a>
            </div>
          </div>

        </div>

        <!-- قائمة الأخبار على شكل بطاقات -->
        <div class="cat-content-wrap mt-2">
          <!-- قائمة الأخبار -->
          <div class="cat-list">
            <?php if (!empty($newsList)): ?>
              <div class="cat-cards-grid">
                <?php foreach ($newsList as $row): ?>
                  <?php
                  $title    = (string)($row['title'] ?? '');
                  $date     = !empty($row['created_at']) ? date('Y-m-d', strtotime((string)$row['created_at'])) : '';
                  $views    = isset($row['views']) ? (int)$row['views'] : null;
                  $newsUrl  = $buildNewsUrl($row);
                  $videoUrl = isset($row['video_url']) ? trim((string)$row['video_url']) : '';
                  $excerpt  = isset($row['excerpt']) ? trim((string)$row['excerpt']) : '';

                  $thumbRaw = $row['featured_image'] ?? $row['image'] ?? null;
                  $thumb    = build_image_url($baseUrl, $thumbRaw);

                  $isVideo  = (!empty($videoUrl));
                  ?>
                  <article class="cat-card-modern<?= $isVideo ? ' cat-card-video' : '' ?>">
                    <a href="<?= h($newsUrl) ?>" class="cat-card-thumb-wrap">
                      <?php if (!empty($thumb)): ?>
                        <div class="cat-card-thumb">
                          <img src="<?= h($thumb) ?>" alt="<?= h($title) ?>">
                          <div class="cat-card-thumb-overlay"></div>
                          <?php if ($isVideo): ?>
                            <div class="cat-card-badge">
                              <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                              <span>فيديو</span>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </a>
                    <div class="cat-card-body-modern">
                      <h3 class="cat-card-title-modern">
                        <a href="<?= h($newsUrl) ?>">
                          <?= h(mb_substr($title, 0, 90, 'UTF-8')) ?><?= mb_strlen($title,'UTF-8')>90 ? '…' : '' ?>
                        </a>
                      </h3>
                      <?php if ($excerpt !== ''): ?>
                        <p class="cat-card-excerpt">
                          <?= h(mb_substr($excerpt, 0, 110, 'UTF-8')) ?><?= mb_strlen($excerpt,'UTF-8')>110 ? '…' : '' ?>
                        </p>
                      <?php endif; ?>
                      <div class="cat-card-meta-modern">
                        <?php if ($date): ?>
                          <span>
                            <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($date) ?>
                          </span>
                        <?php endif; ?>
                        <?php if ($views !== null): ?>
                          <span>
                            <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= number_format($views) ?> مشاهدة
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <!-- لا توجد أخبار فعلاً -->
              <div class="cat-empty">
                لا توجد أخبار ضمن هذا التصنيف حالياً.
              </div>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <?php if (empty($sidebarHidden)): ?>
        <div class="col-lg-4 mt-3 mt-lg-0">
          <!-- عمود جانبي: الأكثر قراءة في هذا التصنيف -->
          <?php if (!empty($catTrending)): ?>
            <div class="cat-sidebar-card">
              <div class="cat-sidebar-title">
                <svg class="gdy-icon text-warning" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <span>الأكثر قراءة في هذا التصنيف</span>
              </div>
              <ul class="cat-sidebar-list">
                <?php foreach ($catTrending as $row): ?>
                  <?php
                    $tTitle  = (string)($row['title'] ?? '');
                    $tUrl    = $buildNewsUrl($row);
                    $tViews  = isset($row['views']) ? (int)$row['views'] : null;
                    $tDate   = !empty($row['created_at']) ? date('Y-m-d', strtotime((string)$row['created_at'])) : '';
                  ?>
                  <li class="cat-sidebar-item">
                    <a href="<?= h($tUrl) ?>">
                      <?= h(mb_substr($tTitle, 0, 70, 'UTF-8')) ?><?= mb_strlen($tTitle, 'UTF-8')>70 ? '…':'' ?>
                    </a>
                    <div class="cat-sidebar-meta">
                      <?php if ($tDate): ?>
                        <span><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($tDate) ?></span>
                      <?php endif; ?>
                      <?php if ($tViews !== null): ?>
                        <span class="ms-2"><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= number_format($tViews) ?></span>
                      <?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php
          // لو عندك سايدبار جاهز
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

<style>
.gdy-cat-page {
  background: #f3f4f6;
}
.gdy-cat-page .container {
  max-width: 1140px;
}

/* العناوين والأوصاف */
.cat-header {
  background: #ffffff;
  border-radius: 1rem;
  padding: 1rem 1.1rem;
  border: 1px solid #e5e7eb;
}
.cat-breadcrumb {
  font-size: .8rem;
  color: #6b7280;
}
.cat-breadcrumb a {
  color: #4b5563;
  text-decoration: none;
}
.cat-breadcrumb a:hover {
  text-decoration: underline;
}
.cat-main-title-wrap {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: .5rem;
}
.cat-main-title {
  font-size: 1.3rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: .2rem;
}
.cat-main-desc {
  font-size: .85rem;
  color: #4b5563;
  margin: 0;
}

/* شريط الأدوات */
.cat-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-top: .6rem;
  gap: .5rem;
  flex-wrap: wrap;
}
.cat-filter-label {
  font-size: .78rem;
  font-weight: 600;
  color: #6b7280;
  margin-inline-end: .25rem;
}
.cat-sort-group,
.cat-filter-group {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: .25rem;
}
.cat-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 2px 9px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  font-size: .78rem;
  color: #374151;
  text-decoration: none;
  background: #f9fafb;
}
.cat-pill.active {
  border-color: var(--primary);
  background: #e0f2fe;
  color: var(--primary-dark);
}
.cat-pill:hover {
  border-color: var(--primary);
}

/* قائمة الأخبار / الحاوية */
.cat-list {
  margin-top: .75rem;
}

/* لا توجد أخبار */
.cat-empty {
  margin-top: 1rem;
  padding: 1rem 1.1rem;
  border-radius: .9rem;
  background: #f9fafb;
  border: 1px dashed #e5e7eb;
  font-size: .9rem;
  color: #6b7280;
}

/* سايدبار */
.cat-sidebar-card {
  background: #ffffff;
  border-radius: 1rem;
  border: 1px solid #e5e7eb;
  padding: .9rem .9rem;
}
.cat-sidebar-title {
  display: flex;
  align-items: center;
  font-size: .9rem;
  font-weight: 700;
  color: #111827;
  margin-bottom: .5rem;
}
.cat-sidebar-title span {
  margin-inline-start: .35rem;
}
.cat-sidebar-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.cat-sidebar-item + .cat-sidebar-item {
  margin-top: .4rem;
}
.cat-sidebar-item a {
  font-size: .85rem;
  color: #111827;
  text-decoration: none;
}
.cat-sidebar-item a:hover {
  text-decoration: underline;
}
.cat-sidebar-meta {
  font-size: .72rem;
  color: #6b7280;
}

/* شبكة البطاقات */
.cat-cards-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .75rem;
  margin-top: .75rem;
}

.cat-card-modern {
  position: relative;
  background: #ffffff;
  border-radius: 1rem;
  border: 1px solid #e5e7eb;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  min-height: 100%;
  box-shadow: 0 4px 10px rgba(15,23,42,0.04);
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.cat-card-modern:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(15,23,42,0.12);
  border-color: var(--primary);
}

.cat-card-thumb-wrap {
  display: block;
  text-decoration: none;
}
.cat-card-thumb {
  position: relative;
  overflow: hidden;
  aspect-ratio: 16 / 10;
  background: #e5e7eb;
}
.cat-card-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transform: scale(1.03);
  transition: transform .3s ease;
}
.cat-card-modern:hover .cat-card-thumb img {
  transform: scale(1.06);
}
.cat-card-thumb-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(15,23,42,0.65), transparent 40%);
}
.cat-card-badge {
  position: absolute;
  inset-inline-start: .4rem;
  inset-block-start: .4rem;
  display: inline-flex;
  align-items: center;
  gap: .25rem;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(var(--primary-rgb),0.92);
  color: #fef2f2;
  font-size: .72rem;
}
.cat-card-badge i {
  font-size: .7rem;
}

.cat-card-body-modern {
  padding: .55rem .65rem .7rem;
  display: flex;
  flex-direction: column;
  gap: .25rem;
}
.cat-card-title-modern {
  font-size: .86rem;
  font-weight: 700;
  margin: 0;
  color: #0f172a;
}
.cat-card-title-modern a {
  color: inherit;
  text-decoration: none;
}
.cat-card-title-modern a:hover {
  text-decoration: underline;
}
.cat-card-excerpt {
  font-size: .75rem;
  color: #6b7280;
  margin: 0;
}
.cat-card-meta-modern {
  margin-top: .15rem;
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
  font-size: .72rem;
  color: #9ca3af;
}
.cat-card-meta-modern i {
  color: var(--primary);
}

/* حالة الفيديو */
.cat-card-video {
  border-color: rgba(var(--primary-rgb),0.45);
}
.cat-card-video:hover {
  border-color: rgba(var(--primary-rgb),0.9);
}

/* responsive columns: نحرص أن تكون 5 بطاقات في الصف على الشاشات الكبيرة */
@media (min-width: 576px) {
  .cat-cards-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (min-width: 768px) {
  .cat-cards-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (min-width: 992px) {
  .cat-cards-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
@media (min-width: 1200px) {
  .cat-cards-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}

/* responsive tweaks */
@media (max-width: 767.98px) {
  .cat-header {
    padding: .8rem .85rem;
  }
  .cat-main-title {
    font-size: 1.1rem;
  }
  .cat-toolbar {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
