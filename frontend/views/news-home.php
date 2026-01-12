<?php
/**
 * قالب الواجهة الرئيسية (news-home)
 *
 * يعتمد على:
 * - $latestNews    : آخر الأخبار (من home.php)
 * - $trendingNews  : أخبار الترند (من HomeController)
 * - $sidebarHidden : هل السايدبار مخفي أم لا (من home.php)
 * - $buildNewsUrl  : دالة بناء رابط الخبر (من home.php)
 */

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$latestNews   = is_array($latestNews ?? null) ? $latestNews : [];
$trendingNews = is_array($trendingNews ?? null) ? $trendingNews : [];

// تقسيم الأخبار إلى رئيسي + باقي
$mainNews   = $latestNews[0] ?? null;
$otherNews  = array_slice($latestNews, 1);
?>
<style>
  .gdy-home-layout {
    display: grid;
    grid-template-columns: minmax(0, <?= $sidebarHidden ? '1fr' : '2fr' ?>) minmax(0, <?= $sidebarHidden ? '0' : '1fr' ?>);
    gap: 18px;
  }
  @media (max-width: 992px) {
    .gdy-home-layout {
      grid-template-columns: minmax(0,1fr);
    }
  }
  .gdy-main-card {
    background: #020617;
    color: #f9fafb;
    border-radius: 18px;
    padding: 18px 18px 16px;
    box-shadow: 0 20px 40px rgba(15,23,42,0.8);
    position: relative;
    overflow: hidden;
  }
  .gdy-main-badge {
    position: absolute;
    top: 14px;
    right: 14px;
    background: #f97316;
    color: #111827;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 600;
  }
  .gdy-main-title {
    font-size: 1.4rem;
    margin: 0 0 8px;
  }
  .gdy-main-meta {
    font-size: .78rem;
    color: #9ca3af;
    margin-bottom: 8px;
  }
  .gdy-main-desc {
    font-size: .85rem;
    color: #e5e7eb;
    margin-bottom: 0;
  }
  .gdy-home-section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin: 18px 0 8px;
    font-size: 1rem;
    font-weight: 600;
  }
  .gdy-home-section-title span.badge-count {
    font-size: .75rem;
    padding: 3px 8px;
    border-radius: 999px;
    background: rgba(148,163,184,0.15);
    color: #e5e7eb;
  }
  .gdy-news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
  }
  .gdy-news-card {
    background: rgba(15,23,42,0.92);
    color: #e5e7eb;
    border-radius: 14px;
    padding: 10px 12px;
    border: 1px solid rgba(148,163,184,0.25);
    font-size: .85rem;
  }
  .gdy-news-card-title {
    margin: 0 0 6px;
    font-size: .9rem;
    font-weight: 600;
  }
  .gdy-news-card-meta {
    font-size: .75rem;
    color: #9ca3af;
  }
  .gdy-sidebar {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .gdy-widget {
    background: #020617;
    color: #e5e7eb;
    border-radius: 16px;
    padding: 12px 14px;
    border: 1px solid rgba(148,163,184,0.25);
  }
  .gdy-widget-title {
    margin: 0 0 10px;
    font-size: .9rem;
    font-weight: 600;
  }
  .gdy-list {
    list-style: none;
    margin: 0;
    padding: 0;
    font-size: .8rem;
  }
  .gdy-list li + li {
    margin-top: 6px;
  }
  .gdy-list a {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
  }
  .gdy-list span.date {
    color: #9ca3af;
    font-size: .7rem;
  }
</style>

<div class="gdy-home-layout">

  <div>
    <?php if ($mainNews): ?>
      <?php
        $title   = (string)($mainNews['title'] ?? '');
        $dateRaw = $mainNews['published_at'] ?? $mainNews['created_at'] ?? $mainNews['date'] ?? $mainNews['news_date'] ?? null;
        $date    = $dateRaw ? date('Y-m-d', strtotime((string)$dateRaw)) : '';
        $views   = isset($mainNews['views']) ? (int)$mainNews['views'] : null;
        $url     = $buildNewsUrl($mainNews);
      ?>
      <a href="<?= h($url) ?>" class="gdy-main-card d-block mb-3">
        <span class="gdy-main-badge">الأبرز</span>
        <h1 class="gdy-main-title"><?= h($title) ?></h1>
        <div class="gdy-main-meta">
          <?php if ($date): ?>
            <span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($date) ?></span>
          <?php endif; ?>
          <?php if ($views !== null): ?>
            <span class="ms-3"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= number_format($views) ?> مشاهدة</span>
          <?php endif; ?>
        </div>
        <p class="gdy-main-desc">
          <?= h(mb_substr($title, 0, 110, 'UTF-8')) ?>...
        </p>
      </a>
    <?php endif; ?>

    <div class="gdy-home-section-title">
      <span>آخر الأخبار</span>
      <?php if (!empty($latestNews)): ?>
        <span class="badge-count"><?= count($latestNews) ?> خبر</span>
      <?php endif; ?>
    </div>

    <?php if (empty($latestNews)): ?>
      <div class="alert alert-info rounded-3">
        لا توجد أخبار متاحة حاليًا.
        <br>
        <span class="small text-muted">
          تأكد من إضافة أخبار من لوحة التحكم وضبط حالتها على "منشور".
        </span>
      </div>
    <?php else: ?>
      <div class="gdy-news-grid">
        <?php foreach ($otherNews as $row): ?>
          <?php
            $t       = (string)($row['title'] ?? '');
            $dateRaw = $row['published_at'] ?? $row['created_at'] ?? $row['date'] ?? $row['news_date'] ?? null;
            $date    = $dateRaw ? date('Y-m-d', strtotime((string)$dateRaw)) : '';
            $url     = $buildNewsUrl($row);
          ?>
          <a href="<?= h($url) ?>" class="gdy-news-card">
            <h3 class="gdy-news-card-title"><?= h($t) ?></h3>
            <?php if ($date): ?>
              <div class="gdy-news-card-meta">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <span><?= h($date) ?></span>
              </div>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$sidebarHidden): ?>
    <aside class="gdy-sidebar">
      <section class="gdy-widget">
        <h3 class="gdy-widget-title">الأكثر قراءة</h3>
        <?php if (empty($trendingNews)): ?>
          <p class="small text-muted mb-0">لا توجد بيانات ترند متاحة حاليًا.</p>
        <?php else: ?>
          <ul class="gdy-list">
            <?php foreach ($trendingNews as $row): ?>
              <?php
                $t       = (string)($row['title'] ?? '');
                $dateRaw = $row['created_at'] ?? null;
                $date    = $dateRaw ? date('Y-m-d', strtotime((string)$dateRaw)) : '';
                $url     = $buildNewsUrl($row);
              ?>
              <li>
                <a href="<?= h($url) ?>">
                  <span><?= h($t) ?></span>
                  <?php if ($date): ?>
                    <span class="date"><?= h($date) ?></span>
                  <?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </aside>
  <?php endif; ?>

</div>
