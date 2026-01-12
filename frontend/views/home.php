<?php
// تضمين الهيدر والفوتر الموحدين
$header = __DIR__ . '/templates/header.php';
$footer = __DIR__ . '/templates/footer.php';

if (is_file($header)) {
    require $header;
}

// نتأكد أن المتغيرات موجودة حتى لو الكنترولر ما أرسلها كلها
$featuredNews      = $featuredNews      ?? null;  // خبر مميز في الهيرو
$latestNews        = $latestNews        ?? [];    // أحدث الأخبار
$importantNews     = $importantNews     ?? [];    // أهم الأخبار
$categoryTabs      = $categoryTabs      ?? [];    // تبويب الأقسام (إن وجد)
$mostRead          = $mostRead          ?? [];    // الأكثر قراءة
$mostCommented     = $mostCommented     ?? [];    // الأكثر تعليقاً
$recommendedNews   = $recommendedNews   ?? [];    // مقترحة لك (بديل أو إضافة)
$mainCategories    = $mainCategories    ?? [];    // لو حاب تعرض أسماء الأقسام
$homePrimaryColor  = $homePrimaryColor  ?? '#40e0d0';

// دالة مساعدة للحصول على تاريخ مناسب من العنصر
if (!function_exists('godyar_news_date')) {
    function godyar_news_date(array $n): string {
        $d = $n['publish_at'] ?? $n['published_at'] ?? $n['created_at'] ?? null;
        if (!$d) return '';
        $ts = strtotime($d);
        if (!$ts) return '';
        return date('Y-m-d', $ts);
    }
}

// دالة كرت خبر في الشبكة
if (!function_exists('godyar_news_card')) {
    function godyar_news_card(array $n, string $baseUrl = ''): void {
        $slug   = $n['slug']   ?? '';
        $title  = $n['title']  ?? '';
        $ex     = $n['excerpt']?? '';
        $img    = $n['featured_image'] ?? ($n['image_url'] ?? '');
        $date   = godyar_news_date($n);

        if (!$slug || !$title) {
            return; // نحتاج على الأقل slug + title
        }

        $url  = $baseUrl ? rtrim($baseUrl, '/') . '/news/id/' . (int)$id : '/news/id/' . (int)$id;
        $date = $date ?: '';
        ?>
        <article class="card h-100 border-0 shadow-sm" style="border-radius:18px;overflow:hidden;background:#ffffff;">
            <?php if (!empty($img)): ?>
                <div style="position:relative;height:180px;overflow:hidden;">
                    <img src="/img.php?src=<?= rawurlencode($img) ?>&w=600"
                         alt="<?= h($title) ?>"
                         style="width:100%;height:100%;object-fit:cover;transform:scale(1.03);transition:transform .35s ease;">
                </div>
            <?php endif; ?>
            <div class="card-body d-flex flex-column" style="padding:12px 14px 10px;">
                <h3 class="h6 mb-1" style="font-weight:700;">
                    <a href="<?= h($url) ?>" class="stretched-link text-decoration-none text-dark">
                        <?= h($title) ?>
                    </a>
                </h3>
                <?php if ($ex): ?>
                    <p class="text-muted small mb-2" style="font-size:.8rem;">
                        <?= h(mb_substr($ex, 0, 120)) ?><?= mb_strlen($ex) > 120 ? '…' : '' ?>
                    </p>
                <?php endif; ?>
                <?php if ($date): ?>
                    <div class="mt-auto d-flex align-items-center justify-content-between text-muted" style="font-size:.75rem;">
                        <span><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($date) ?></span>
                        <span><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg><?= h(__('خبر')) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }
}

// دالة عنصر صغير في "الأكثر قراءة / تعليقاً"
if (!function_exists('godyar_news_small_item')) {
    function godyar_news_small_item(array $n, string $baseUrl = ''): void {
        $slug  = $n['slug']   ?? '';
        $title = $n['title']  ?? '';
        $date  = godyar_news_date($n);

        if (!$slug || !$title) {
            return;
        }

        $url = $baseUrl ? rtrim($baseUrl, '/') . '/news/id/' . (int)$id : '/news/id/' . (int)$id;
        ?>
        <div class="d-flex flex-column mb-2">
            <a href="<?= h($url) ?>" class="text-decoration-none" style="font-size:.85rem;font-weight:600;">
                <?= h($title) ?>
            </a>
            <?php if ($date): ?>
                <span class="text-muted" style="font-size:.75rem;">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($date) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }
}

$baseUrl = function_exists('base_url') ? base_url() : '';
// اختيار خبر للهيرو: أولوية لـ $featuredNews ثم أول عنصر من latestNews
$heroItem = $featuredNews;
if (!$heroItem && !empty($latestNews) && is_array($latestNews)) {
    $heroItem = $latestNews[0];
}

?>
<style>
    /* خلفية خفيفة للصفحة الرئيسية (تركواز بنسبة بسيطة جداً) */
    .godyar-home-wrap {
        background:
            radial-gradient(circle at top, rgba(15,23,42,0.95), #020617 55%, #000000 100%),
            radial-gradient(circle at bottom right, rgba(64,224,208,0.06), transparent 65%);
        min-height: calc(100vh - 120px);
        padding: 18px 0 28px;
    }
    .godyar-home-hero-card {
        position: relative;
        border-radius: 20px;
        overflow: hidden;
        background: radial-gradient(circle at top left, rgba(56,189,248,0.18), transparent 60%),
                    radial-gradient(circle at bottom right, rgba(15,118,110,0.22), transparent 65%),
                    linear-gradient(135deg, #020617, #020617);
        color: #e5e7eb;
        box-shadow: 0 22px 60px rgba(15,23,42,0.9);
        border: 1px solid rgba(148,163,184,0.28);
    }
    .godyar-home-hero-card img {
        transition: transform .5s ease;
    }
    .godyar-home-hero-card:hover img {
        transform: scale(1.05);
    }
    .godyar-home-tabs .nav-link {
        font-size: .8rem;
        border-radius: 999px;
        padding: 6px 14px;
        margin-inline: 3px;
        border: 1px solid rgba(148,163,184,0.35);
        color: #e5e7eb;
        background: rgba(15,23,42,0.9);
    }
    .godyar-home-tabs .nav-link.active {
        background: #38bdf8;
        border-color: #38bdf8;
        color: #0f172a;
        box-shadow: 0 10px 28px rgba(56,189,248,0.5);
    }
    .godyar-home-section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #e5e7eb;
    }
    .godyar-home-section-sub {
        font-size: .8rem;
        color: #9ca3af;
    }
    .godyar-home-side-box {
        border-radius: 16px;
        background: radial-gradient(circle at top left, rgba(15,23,42,0.98), rgba(15,23,42,0.98));
        border: 1px solid rgba(31,41,55,0.95);
        padding: 12px 14px;
        box-shadow: 0 16px 42px rgba(15,23,42,0.9);
        margin-bottom: 14px;
    }
    .godyar-home-side-title {
        font-size: .82rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #e5e7eb;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .godyar-home-side-title i {
        font-size: .85rem;
        color: #fbbf24;
    }
</style>

<div class="godyar-home-wrap">
  <div class="container">

    <div class="row g-3">
      <!-- العمود الرئيسي -->
      <div class="col-12 col-lg-8">

        <!-- هيرو / خبر مميز -->
        <?php if ($heroItem && !empty($heroItem['id']) && !empty($heroItem['title'])): ?>
          <?php
            $heroId = (int)($heroItem['id'] ?? 0);
            $heroSlug = (string)($heroItem['slug'] ?? '');
            $heroTitle = $heroItem['title'];
            $heroImg = $heroItem['featured_image'] ?? ($heroItem['image_url'] ?? '');
            $heroExcerpt = $heroItem['excerpt'] ?? '';
            $heroDate = godyar_news_date($heroItem);
            $heroUrl = ($baseUrl ? rtrim($baseUrl, '/') : '') . '/news/id/' . $heroId;
          ?>
          <section class="godyar-home-hero-card mb-3">
            <div class="row g-0">
              <div class="col-md-6">
                <?php if ($heroImg): ?>
                  <div style="height:100%;min-height:220px;overflow:hidden;">
                    <img src="/img.php?src=<?= rawurlencode($heroImg) ?>&w=900"
                         alt="<?= h($heroTitle) ?>"
                         style="width:100%;height:100%;object-fit:cover;">
                  </div>
                <?php else: ?>
                  <div style="height:100%;min-height:220px;background:radial-gradient(circle at top,#1e293b,#020617);"></div>
                <?php endif; ?>
              </div>
              <div class="col-md-6 d-flex">
                <div class="p-3 p-md-4 d-flex flex-column w-100 position-relative">
                  <div class="mb-2 d-flex align-items-center gap-2 small text-teal-300" style="font-size:.8rem;">
                    <span class="badge rounded-pill bg-dark border border-teal-400" style="border-color:rgba(45,212,191,0.8)!important;">
                      <svg class="gdy-icon me-1 text-warning" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> خبر مميز
                    </span>
                    <?php if ($heroDate): ?>
                      <span class="text-muted">
                        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($heroDate) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <h1 class="h4 mb-2" style="color:#f9fafb;">
                    <a href="<?= h($heroUrl) ?>" class="text-decoration-none text-reset">
                      <?= h($heroTitle) ?>
                    </a>
                  </h1>
                  <?php if ($heroExcerpt): ?>
                    <p class="mb-3" style="font-size:.86rem;color:#e5e7eb;opacity:.9;">
                      <?= h(mb_substr($heroExcerpt, 0, 200)) ?><?= mb_strlen($heroExcerpt) > 200 ? '…' : '' ?>
                    </p>
                  <?php endif; ?>
                  <div class="mt-auto d-flex align-items-center justify-content-between">
                    <a href="<?= h($heroUrl) ?>" class="btn btn-sm btn-light" style="border-radius:999px;font-size:.8rem;">
                      اقرأ المزيد <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    </a>
                    <span class="text-muted" style="font-size:.75rem;">
                      <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>Godyar News
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </section>
        <?php endif; ?>

        <!-- أحدث الأخبار -->
        <section class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-2">
            <div>
              <div class="godyar-home-section-title">
                <svg class="gdy-icon me-1 text-warning" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> أحدث الأخبار
              </div>
              <div class="godyar-home-section-sub">
                آخر ما تم نشره في الموقع.
              </div>
            </div>
            <a href="<?= h($baseUrl) ?>/archive" class="text-decoration-none" style="font-size:.8rem;color:#a5b4fc;">
              أرشيف الأخبار <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </a>
          </div>

          <?php if (!empty($latestNews) && is_array($latestNews)): ?>
            <div class="row g-3">
              <?php foreach ($latestNews as $n): ?>
                <div class="col-12 col-sm-6 col-lg-4">
                  <?php godyar_news_card($n, $baseUrl); ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted small mb-0">لا توجد أخبار حالياً في هذا القسم.</p>
          <?php endif; ?>
        </section>

        <!-- تبويب الأقسام (عام، سياسة، اقتصاد، رياضة ...) -->
        <section class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-2">
            <div>
              <div class="godyar-home-section-title">
                <svg class="gdy-icon me-1 text-info" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تغطية حسب الأقسام
              </div>
              <div class="godyar-home-section-sub">
                تصفح الأخبار حسب التصنيف.
              </div>
            </div>
          </div>

          <?php
          // مثال بنية متوقعة لـ $categoryTabs:
          // [
          //   ['slug' => 'general-news', 'name' => 'أخبار عامة', 'items' => [ ...أخبار... ]],
          //   ['slug' => 'politics',    'name' => 'سياسة',     'items' => [...]],
          //   ...
          // ]
          ?>
          <?php if (!empty($categoryTabs) && is_array($categoryTabs)): ?>
            <ul class="nav godyar-home-tabs mb-2" role="tablist">
              <?php $first = true; ?>
              <?php foreach ($categoryTabs as $tab): ?>
                <?php
                  $slug = $tab['slug'] ?? '';
                  $name = $tab['name'] ?? '';
                  if (!$slug || !$name) continue;
                  $id = 'tab-' . preg_replace('~[^a-z0-9\-]+~i', '-', $slug);
                ?>
                <li class="nav-item" role="presentation">
                  <button class="nav-link <?= $first ? 'active' : '' ?>"
                          id="<?= h($id) ?>-tab"
                          data-bs-toggle="tab"
                          data-bs-target="#<?= h($id) ?>"
                          type="button" role="tab"
                          aria-controls="<?= h($id) ?>"
                          aria-selected="<?= $first ? 'true' : 'false' ?>">
                    <?= h($name) ?>
                  </button>
                </li>
                <?php $first = false; ?>
              <?php endforeach; ?>
            </ul>

            <div class="tab-content">
              <?php $first = true; ?>
              <?php foreach ($categoryTabs as $tab): ?>
                <?php
                  $slug = $tab['slug'] ?? '';
                  $name = $tab['name'] ?? '';
                  if (!$slug || !$name) continue;
                  $id    = 'tab-' . preg_replace('~[^a-z0-9\-]+~i', '-', $slug);
                  $items = $tab['items'] ?? [];
                ?>
                <div class="tab-pane fade <?= $first ? 'show active' : '' ?>"
                     id="<?= h($id) ?>" role="tabpanel"
                     aria-labelledby="<?= h($id) ?>-tab">
                  <?php if (!empty($items) && is_array($items)): ?>
                    <div class="row g-3 mt-1">
                      <?php foreach ($items as $n): ?>
                        <div class="col-12 col-md-6">
                          <?php godyar_news_card($n, $baseUrl); ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <p class="text-muted small mt-2 mb-2">
                      لا توجد أخبار في هذا القسم حالياً.
                    </p>
                  <?php endif; ?>
                </div>
                <?php $first = false; ?>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted small mb-0">
              لم يتم تهيئة تبويبات الأقسام بعد من الكنترولر.
            </p>
          <?php endif; ?>
        </section>

        <!-- أهم الأخبار (قائمة جانبية داخل العمود) -->
        <section class="mb-2">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <div class="godyar-home-section-title" style="font-size:.95rem;">
              <svg class="gdy-icon me-1 text-danger" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> أهم الأخبار
            </div>
          </div>
          <?php if (!empty($importantNews) && is_array($importantNews)): ?>
            <div class="row g-2">
              <?php foreach ($importantNews as $n): ?>
                <div class="col-12 col-md-6">
                  <?php godyar_news_small_item($n, $baseUrl); ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted small mb-0">سيتم عرض الأخبار الأهم هنا عند توفرها.</p>
          <?php endif; ?>
        </section>

      </div>

      <!-- عمود جانبي: الأكثر قراءة + الأكثر تعليقاً / مقترحة لك -->
      <div class="col-12 col-lg-4">

        <!-- الأكثر قراءة -->
        <aside class="godyar-home-side-box">
          <div class="godyar-home-side-title">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <span>الأكثر قراءة</span>
          </div>
          <?php if (!empty($mostRead) && is_array($mostRead)): ?>
            <div>
              <?php foreach ($mostRead as $n): ?>
                <?php godyar_news_small_item($n, $baseUrl); ?>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted small mb-0">لا توجد بيانات متاحة حالياً.</p>
          <?php endif; ?>
        </aside>

        <!-- الأكثر تعليقاً أو مقترحة لك -->
        <aside class="godyar-home-side-box">
          <div class="godyar-home-side-title">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <span>الأكثر تعليقاً / مقترحة لك</span>
          </div>
          <?php
          $blockItems = !empty($mostCommented) ? $mostCommented : $recommendedNews;
          ?>
          <?php if (!empty($blockItems) && is_array($blockItems)): ?>
            <div>
              <?php foreach ($blockItems as $n): ?>
                <?php godyar_news_small_item($n, $baseUrl); ?>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted small mb-0">
              سيتم عرض الأخبار الأكثر تفاعلاً أو المقترحة لك هنا.
            </p>
          <?php endif; ?>
        </aside>

      </div>

    </div>

  </div>
</div>

<?php
if (is_file($footer)) {
    require $footer;
}
?>