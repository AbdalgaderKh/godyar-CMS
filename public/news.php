<?php
declare(strict_types=1);

// /godyar/public/news.php — عرض قائمة الأخبار مع بحث وترقيم صفحات

require_once __DIR__ . '/../includes/bootstrap.php';

// ملف الإعلانات (اختياري)
$adsHelper = __DIR__ . '/../includes/ads.php';
if (is_file($adsHelper)) {
    require_once $adsHelper;
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = gdy_pdo_safe();

// إعدادات بسيطة
$perPage = 10;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $perPage;

$q        = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['cat'] ?? ''));

// نقرأ أعمدة جدول news عشان نتأكد قبل ما نستخدم أعمدة اختيارية
$newsColumns = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM news");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $newsColumns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        @error_log('[Front News] SHOW COLUMNS news: ' . $e->getMessage());
    }
}

// نبني الاستعلام ديناميكياً
$where  = [];
$params = [];

if (isset($newsColumns['status'])) {
    $where[] = "status = 'published'";
}
if (isset($newsColumns['published_at'])) {
    $where[] = "published_at <= NOW()";
}

// البحث
if ($q !== '') {
    // لو في أعمدة title / content
    $likeParts = [];
    if (isset($newsColumns['title'])) {
        $likeParts[] = "title LIKE :q";
    }
    if (isset($newsColumns['content'])) {
        $likeParts[] = "content LIKE :q";
    }
    if ($likeParts) {
        $where[] = '(' . implode(' OR ', $likeParts) . ')';
        $params[':q'] = '%' . $q . '%';
    }
}

// الفلترة بحسب التصنيف (لو فيه عمود category_id)
if ($category !== '' && isset($newsColumns['category_id'])) {
    $where[] = "category_id = :cat";
    $params[':cat'] = $category;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
$items = [];

if ($pdo instanceof PDO) {
    // عدد السجلات
    try {
        $sqlCount = "SELECT COUNT(*) FROM news {$whereSql}";
        $stmt = $pdo->prepare($sqlCount);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        @error_log('[Front News] count: ' . $e->getMessage());
    }

    // جلب السجلات
    try {
        // ترتيب افتراضي: published_at ثم id
        $orderBy = [];
        if (isset($newsColumns['published_at'])) {
            $orderBy[] = "published_at DESC";
        }
        $orderBy[] = "id DESC";
        $orderSql = 'ORDER BY ' . implode(',', $orderBy);

        $sql = "SELECT * FROM news {$whereSql} {$orderSql} LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);

        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        @error_log('[Front News] list: ' . $e->getMessage());
    }
}

// عدد الصفحات
$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
if ($totalPages < 1) {
    $totalPages = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
}

$siteName = (string)env('SITE_NAME', 'Godyar News');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title>الأخبار - <?= h($siteName) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 -->
  <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    .news-hero {
      background: linear-gradient(135deg, #4361ee, #4895ef);
      color: #fff;
      padding: 32px 0;
      margin-bottom: 24px;
    }
    .news-hero h1 {
      font-size: 1.8rem;
      margin-bottom: 8px;
    }
    .news-card {
      border-radius: 16px;
      border: none;
      box-shadow: 0 8px 20px rgba(15,23,42,.08);
      overflow: hidden;
      transition: transform .15s ease, box-shadow .15s ease;
      background-color: #ffffff;
    }
    .news-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 14px 30px rgba(15,23,42,.14);
    }
    .news-thumb {
      width: 100%;
      height: 180px;
      object-fit: cover;
      background-color: #e9ecef;
    }
    .news-meta {
      font-size: 0.8rem;
      color: #6c757d;
    }
    .pagination .page-link {
      border-radius: 999px !important;
    }
  </style>
</head>
<body>

<header class="news-hero">
  <div class="container">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div>
        <h1 class="mb-1">الأخبار</h1>
        <p class="mb-0">استعرض آخر الأخبار والمقالات المنشورة في الموقع.</p>
      </div>
      <form class="d-flex gap-2" method="get" action="news.php">
        <input
          type="text"
          name="q"
          class="form-control form-control-sm"
          placeholder="ابحث في العناوين..."
          value="<?= h($q) ?>"
        >
        <button class="btn btn-light btn-sm" type="submit">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg>
          بحث
        </button>
      </form>
    </div>
  </div>
</header>

<main class="py-3">
  <div class="container">
    <div class="row g-3">
      <div class="col-lg-8">

        <?php if (!$items): ?>
          <div class="alert alert-info">
            لا توجد أخبار مطابقة لبحثك حالياً.
          </div>
        <?php else: ?>
          <?php foreach ($items as $row): ?>
            <?php
              $title   = $row['title']   ?? 'بدون عنوان';
              $slug    = $row['slug']    ?? null;
              $created = $row['published_at'] ?? ($row['created_at'] ?? null);
              $excerpt = $row['excerpt'] ?? '';
              $image   = $row['image']   ?? $row['featured_image'] ?? '';

              // رابط المقال (Canonical)
              $rowId = (int)($row['id'] ?? 0);
              $base  = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
              // نستخدم /news/id/{id} لتجنب كسر الروابط في حال تغيّر الـ slug
              $url   = ($base !== '' ? $base : '') . '/news/id/' . $rowId;

              // لو ما فيه excerpt نقص جزء من المحتوى
              if ($excerpt === '' && !empty($row['content'])) {
                  $txt = strip_tags($row['content']);
                  if (function_exists('mb_strimwidth')) {
                      $excerpt = mb_strimwidth($txt, 0, 200, '...', 'UTF-8');
                  } else {
                      $excerpt = substr($txt, 0, 200) . '...';
                  }
              }
            ?>
            <article class="news-card mb-3">
              <div class="row g-0">
                <?php if ($image): ?>
                  <div class="col-md-4">
                    <a href="<?= h($url) ?>">
                      <img src="<?= h($image) ?>" alt="<?= h($title) ?>" class="news-thumb">
                    </a>
                  </div>
                  <div class="col-md-8">
                <?php else: ?>
                  <div class="col-12">
                <?php endif; ?>
                    <div class="p-3">
                      <h2 class="h5">
                        <a href="<?= h($url) ?>" class="text-decoration-none text-dark">
                          <?= h($title) ?>
                        </a>
                      </h2>
                      <?php if ($created): ?>
                        <div class="news-meta mb-2">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                          <span><?= h($created) ?></span>
                        </div>
                      <?php endif; ?>
                      <?php if ($excerpt): ?>
                        <p class="mb-2"><?= nl2br(h($excerpt)) ?></p>
                      <?php endif; ?>
                      <a href="<?= h($url) ?>" class="btn btn-sm btn-outline-primary">
                        قراءة المزيد
                      </a>
                    </div>
                  </div>
              </div>
            </article>
          <?php endforeach; ?>

          <!-- الترقيم -->
          <?php if ($totalPages > 1): ?>
            <nav aria-label="ترقيم الصفحات" class="mt-3">
              <ul class="pagination justify-content-center">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                  <?php
                    $query = $_GET;
                    $query['page'] = $p;
                    $link = 'news.php?' . http_build_query($query);
                  ?>
                  <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h($link) ?>"><?= $p ?></a>
                  </li>
                <?php endfor; ?>
              </ul>
            </nav>
          <?php endif; ?>

        <?php endif; ?>
      </div>

      <!-- سايدبار -->
      <div class="col-lg-4">
        <?php if (function_exists('godyar_render_ad')): ?>
          <div class="mb-3">
            <?php godyar_render_ad('sidebar_top', 1); ?>
          </div>
        <?php endif; ?>

        <!-- يمكنك إضافة أقسام: أكثر الأخبار قراءة، تصنيفات، ... -->

        <?php if (function_exists('godyar_render_ad')): ?>
          <div class="mt-3">
            <?php godyar_render_ad('sidebar_bottom', 1); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<footer class="py-4 text-center text-muted small">
  &copy; <?= date('Y') ?> <?= h($siteName) ?> . جميع الحقوق محفوظة.
</footer>

</body>
</html>
