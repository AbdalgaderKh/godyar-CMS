<?php
declare(strict_types=1);

// /godyar/frontend/views/archive.php
// محتوى صفحة الأرشيف (الهيدر/الفوتر يتم تضمينهما عبر FrontendRenderer)

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('archive_card')) {
    /**
     * كرت خبر داخل الأرشيف
     * @param array<string,mixed> $n
     */
    function archive_card(array $n): void {
        $slug  = (string)($n['slug'] ?? '');
        $img   = (string)($n['featured_image'] ?? '');
        $title = (string)($n['title'] ?? '');
        $ex    = (string)($n['excerpt'] ?? '');
        $id    = (int)($n['id'] ?? ($n['news_id'] ?? 0));
        $url   = $id ? '/news/id/' . $id : ($slug ? '/news/' . $slug : '#');
        ?>
        <a class="card h-100 text-decoration-none" href="<?= h($url) ?>">
          <?php if ($img): ?>
            <div class="thumb" style="height:160px;overflow:hidden;border-radius:14px;">
              <img
                src="/img.php?src=<?= rawurlencode($img) ?>&w=400"
                alt="<?= h($title) ?>"
                style="width:100%;height:100%;object-fit:cover;"
              >
            </div>
          <?php endif; ?>
          <div class="meta mt-2">
            <h3 class="h6 mb-1"><?= h($title) ?></h3>
            <?php if ($ex): ?>
              <p class="text-muted small mb-0"><?= h($ex) ?></p>
            <?php endif; ?>
          </div>
        </a>
        <?php
    }
}

$page_title = $page_title ?? 'الأرشيف';
$items = $items ?? [];
$page  = (int)($page ?? 1);
$pages = (int)($pages ?? 1);
$year  = isset($year) ? (int)$year : null;
$month = isset($month) ? (int)$month : null;

// تجهيز baseUrl (لو احتجناه مستقبلاً)
if (!isset($baseUrl)) {
    $baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
}

$archiveBasePath = $baseUrl . '/archive';
if ($year) {
    $archiveBasePath .= '/' . $year;
    if ($month) {
        $archiveBasePath .= '/' . $month;
    }
}
?>

<div class="container py-4">
  <header class="mb-3">
    <h1 class="h4 mb-1"><?= h($page_title) ?></h1>
    <p class="text-muted mb-0">أرشيف الأخبار حسب الفترات الزمنية.</p>
  </header>

  <?php if (empty($items)): ?>
    <p class="text-muted">لا توجد أخبار في هذه الفترة الزمنية حالياً.</p>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($items as $n): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100" style="border-radius:18px;overflow:hidden;">
            <div class="card-body p-3">
              <?php archive_card($n); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <div class="mt-4 d-flex justify-content-center gap-2 flex-wrap">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <?php
            $isActive = ($i === $page);
            // نفس النمط المستخدم في الراوتر: /archive[/year[/month]]/page/N
            $url = $archiveBasePath . '/page/' . $i;
          ?>
          <a href="<?= h($url) ?>" class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>