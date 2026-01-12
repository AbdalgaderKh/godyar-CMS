<?php
// frontend/search.php (بحث عام: أخبار + صفحات + أقسام)
declare(strict_types=1);

// هذا الملف legacy؛ نستخدم bootstrap من جذر المشروع.
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// baseUrl (لدعم التثبيت داخل مجلد فرعي)
$baseUrl = function_exists('base_url')
    ? rtrim((string)base_url(), '/')
    : (defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '');

// helper للهروب
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// تظليل كلمة البحث
if (!function_exists('highlight_term')) {
    function highlight_term(string $text, string $term): string {
        $term = trim($term ?? '');
        if ($term === '') {
            return h($text);
        }
        // نهرب النص
        $safe    = h($text);
        $pattern = '/' . preg_quote($term, '/') . '/iu';

        return preg_replace(
            $pattern,
            '<mark class="srch-hl">$0</mark>',
            $safe
        );
    }
}

$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    echo 'مشكلة في الاتصال بقاعدة البيانات';
    exit;
}

// مدخلات البحث
$q = trim((string)($_GET['q'] ?? ''));
$q = preg_replace('/[\x00-\x1F\x7F]/u', '', $q);
if (function_exists('mb_substr')) { $q = mb_substr($q, 0, 200); } else { $q = substr($q, 0, 200); }

$typeFilter = $_GET['type'] ?? 'all'; // all | news | pages | categories
$dateFilter = $_GET['date'] ?? 'any'; // any | 1d | 7d | 30d (يطبق على الأخبار فقط غالباً)
$engine     = $_GET['engine'] ?? 'local';

$perNews = 10;
$perPages = 8;
$perCats = 8;

// لو المستخدم اختار البحث عبر قوقل نحوله مباشرة
if ($engine === 'google' && $q !== '') {
    $domain = $_SERVER['HTTP_HOST'] ?? 'godyar.org';
    $googleUrl = 'https://www.google.com/search?q=' .
        urlencode($q . ' site:' . $domain);
    header('Location: ' . $googleUrl);
    exit;
}

// لو فاضي وما في فلتر نوع: ما نبحث
if ($q === '' && $typeFilter === 'all') {
    $newsResults  = [];
    $pageResults  = [];
    $catResults   = [];
    $counts = ['news' => 0, 'pages' => 0, 'cats' => 0];
} else {
    $newsResults  = [];
    $pageResults  = [];
    $catResults   = [];
    $counts       = ['news' => 0, 'pages' => 0, 'cats' => 0];

    // ===== بحث الأخبار =====
    if ($typeFilter === 'all' || $typeFilter === 'news') {
        try {
            // شروط البحث
            $where  = [];
            $params = [];

            // كلمة البحث
            $where[] = "(n.title LIKE :q OR n.excerpt LIKE :q OR n.content LIKE :q OR n.slug LIKE :q OR n.tags LIKE :q OR n.keywords LIKE :q)";
            $params[':q'] = '%' . $q . '%';

            // فقط المنشور + احترام الجدولة
            $now = date('Y-m-d H:i:s');
            $params[':now'] = $now;

            $where[] = "n.status = 'published'";
            $where[] = "(n.deleted_at IS NULL OR n.deleted_at = '0000-00-00 00:00:00')";
            $where[] = "(n.publish_at IS NULL OR n.publish_at = '0000-00-00 00:00:00' OR n.publish_at <= :now)";
            $where[] = "(n.unpublish_at IS NULL OR n.unpublish_at = '0000-00-00 00:00:00' OR n.unpublish_at > :now)";

            // فلتر القسم (اختياري)
            if ($cat !== '') {
                $where[] = "c.slug = :cslug";
                $params[':cslug'] = $cat;
            }

            // فلتر المدة (اختياري)
            if ($dateFilter === 'day') {
                $where[] = "n.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            } elseif ($dateFilter === 'week') {
                $where[] = "n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            } elseif ($dateFilter === 'month') {
                $where[] = "n.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            }

            $whereSql = "WHERE " . implode(" AND ", $where);

            // العدد الكلي للأخبار
            $sqlCount = "SELECT COUNT(*)
                FROM news n
                LEFT JOIN categories c ON c.id = n.category_id
                {$whereSql}";
            $stmtCount = $pdo->prepare($sqlCount);
            foreach ($params as $k => $v) {
                $stmtCount->bindValue($k, $v, PDO::PARAM_STR);
            }
            $stmtCount->execute();
            $countNews = (int)$stmtCount->fetchColumn();

            // النتائج
            $sql = "SELECT
                    n.id,
                    n.title,
                    n.slug,
                    n.excerpt,
                    n.image AS featured_image,
                    n.created_at,
                    n.views,
                    n.is_breaking,
                    n.is_featured,
                    0 AS is_exclusive,
                    '' AS type,
                    c.slug AS category_slug
                FROM news n
                LEFT JOIN categories c ON c.id = n.category_id
                {$whereSql}
                ORDER BY n.created_at DESC
                LIMIT :limit";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $newsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($newsRows as $n) {
                $n['type'] = 'news'; // لتنسيق النتائج
                $results[] = $n;
            }
        } catch (Throwable $e) {
            // لا تكسر الصفحة، لكن سجّل الخطأ
            error_log('[Search] News query failed: ' . $e->getMessage());
        }
    }

// ===== بحث الصفحات =====
    if ($typeFilter === 'all' || $typeFilter === 'pages') {
        try {
            $where  = [];
            $params = [];

            if ($q !== '') {
                $where[] = "(p.title LIKE :q OR p.content LIKE :q OR p.slug LIKE :q)";
                $params[':q'] = '%' . $q . '%';
            }

            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            $sqlCount = "
                SELECT COUNT(*)
                FROM pages p
                $whereSql
            ";
            $stC = $pdo->prepare($sqlCount);
            $stC->execute($params);
            $counts['pages'] = (int)$stC->fetchColumn();

            $sql = "
                SELECT p.id, p.slug, p.title, p.content, p.status, p.updated_at
                FROM pages p
                $whereSql
                ORDER BY p.updated_at DESC, p.id DESC
                LIMIT :limit
            ";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->bindValue(':limit', $perPages, PDO::PARAM_INT);
            $st->execute();
            $pageResults = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $pageResults = [];
        }
    }

    // ===== بحث الأقسام =====
    if ($typeFilter === 'all' || $typeFilter === 'categories') {
        try {
            // نتحقق من وجود description
            $hasDescription = false;
            try {
                $colsStmt = $pdo->query("SHOW COLUMNS FROM categories");
                $cols     = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
                $hasDescription = in_array('description', $cols, true);
            } catch (Throwable $e) {
                $hasDescription = false;
            }

            $where  = [];
            $params = [];

            if ($q !== '') {
                if ($hasDescription) {
                    $where[] = "(c.name LIKE :q OR c.slug LIKE :q OR c.description LIKE :q)";
                } else {
                    $where[] = "(c.name LIKE :q OR c.slug LIKE :q)";
                }
                $params[':q'] = '%' . $q . '%';
            }

            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            // نستخدم subquery للعد
            $sqlCount = "
                SELECT COUNT(*) FROM (
                  SELECT c.id
                  FROM categories c
                  LEFT JOIN news n ON n.category_id = c.id
                  $whereSql
                  GROUP BY c.id
                ) AS t
            ";
            $stC = $pdo->prepare($sqlCount);
            $stC->execute($params);
            $counts['cats'] = (int)$stC->fetchColumn();

            $selectDesc = $hasDescription ? 'c.description' : "'' AS description";

            $sql = "
                SELECT c.id, c.name, c.slug,
                       $selectDesc,
                       COUNT(n.id) AS news_count
                FROM categories c
                LEFT JOIN news n ON n.category_id = c.id
                $whereSql
                GROUP BY c.id, c.name, c.slug, c.description
                ORDER BY news_count DESC, c.name ASC
                LIMIT :limit
            ";
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->bindValue(':limit', $perCats, PDO::PARAM_INT);
            $st->execute();
            $catResults = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $catResults = [];
        }
    }
}

$totalAll = $counts['news'] + $counts['pages'] + $counts['cats'];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title>بحث الموقع<?= $q ? ' - ' . h($q) : '' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
	<style>
  body{
    background:#f8fafc;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  }
  .global-search-page{min-height:100vh;}
  .global-hero{
    padding:24px 0 16px;
    border-bottom:1px solid #e2e8f0;
    background:#0f172a;
    color:#e5e7eb;
  }
  .global-hero h1{
    font-size:1.4rem;
    margin:0 0 .4rem;
  }
  .global-hero p{
    margin:0;
    font-size:.9rem;
    color:#cbd5f5;
  }

  .global-search-form{
    margin-top:1rem;
    display:flex;
    flex-wrap:wrap;
    gap:.5rem;
    align-items:center;
  }
  .global-search-form input[type="text"]{
    flex:1 1 240px;
    border-radius:999px;
    border:1px solid #e2e8f0;
    padding:.45rem .9rem;
  }
  .global-search-form button{
    border-radius:999px;
    padding:.45rem 1.1rem;
    border:none;
    background:#2563eb;
    color:#f9fafb;
    font-size:.9rem;
    display:inline-flex;
    align-items:center;
    gap:.35rem;
  }

  .global-filters{
    margin-top:.75rem;
    display:flex;
    flex-wrap:wrap;
    gap:.5rem;
    font-size:.8rem;
    color:#cbd5f5;
  }
  .global-filters select{
    border-radius:999px;
    border:1px solid #1f2937;
    background:#020617;
    color:#e5e7eb;
    font-size:.8rem;
    padding:.25rem .7rem;
  }

  .engine-toggle{
    font-size:.8rem;
    color:#e5e7eb;
    margin-top:.35rem;
  }

  .results-wrap{
    padding:20px 0 32px;
  }
  .results-summary{
    font-size:.82rem;
    color:#64748b;
    margin-bottom:12px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    justify-content:space-between;
    align-items:center;
  }
  .results-summary strong{color:#111827;}

  .section-title{
    font-size:.95rem;
    font-weight:700;
    margin:18px 0 8px;
    display:flex;
    align-items:center;
    gap:6px;
    color:#0f172a;
  }
  .section-title span.count{
    font-size:.78rem;
    color:#6b7280;
  }

  .grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
    gap:12px;
  }

  .card-news,
  .card-page,
  .card-cat{
    border-radius:16px;
    border:1px solid #e2e8f0;
    background:#ffffff;
    padding:10px 12px;
    box-shadow:0 10px 24px rgba(15,23,42,0.06);
    text-decoration:none;
    color:inherit;
    display:flex;
    flex-direction:column;
    transition:transform .18s ease,box-shadow .18s.ease,border-color .18s.ease;
  }
  .card-news:hover,
  .card-page:hover,
  .card-cat:hover{
    transform:translateY(-2px);
    box-shadow:0 14px 36px rgba(15,23,42,0.12);
    border-color:#bfdbfe;
  }

  .card-title{
    font-size:.98rem;
    margin:0 0 .25rem;
    font-weight:600;
    color:#0f172a;
  }
  .card-excerpt{
    margin:0;
    font-size:.84rem;
    color:#64748b;
  }
  .card-meta{
    font-size:.78rem;
    color:#94a3b8;
    display:flex;
    justify-content:space-between;
    gap:6px;
    margin-bottom:.25rem;
  }

  .badge-type{
    border-radius:999px;
    padding:2px 8px;
    font-size:.72rem;
    background:#eef2ff;
    color:#4338ca;
  }
  .badge-type-page{
    background:#ecfdf5;
    color:#15803d;
  }
  .badge-type-cat{
    background:#fef2f2;
    color:#b91c1c;
  }

  .srch-hl{
    background:#facc15;
    color:#111827;
    padding:0 2px;
    border-radius:2px;
  }

  .empty{
    text-align:center;
    padding:24px 8px;
    font-size:.9rem;
    color:#6b7280;
  }

  @media (max-width: 767.98px){
    .global-search-form{
      flex-direction:column;
      align-items:stretch;
    }
  }
  </style>
</head>
<body class="global-search-page">
  <main class="container">
    <!-- الهيدر -->
    <section class="global-hero">
      <div class="container">
        <h1>بحث في محتوى الموقع</h1>
        <p>
          <?= $q ? 'نتائج البحث عن: <strong>' . h($q) . '</strong>' : 'ابحث في الأخبار والصفحات والأقسام من مكان واحد.' ?>
        </p>

        <form class="global-search-form" method="get" action="" id="globalSearchForm">
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="اكتب كلمة البحث (مثال: اقتصاد، سياسة، من نحن...)">
          <button type="submit">
            <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> بحث
          </button>

          <div class="engine-toggle w-100">
            <span class="d-block mb-1">محرك البحث:</span>
            <div class="form-check.form-check-inline">
              <input class="form-check-input" type="radio" name="engine" id="engineLocal" value="local"
                     <?= $engine === 'google' ? '' : 'checked' ?>>
              <label class="form-check-label" for="engineLocal">بحث داخل الموقع</label>
            </div>
            <div class="form-check.form-check-inline">
              <input class="form-check-input" type="radio" name="engine" id="engineGoogle" value="google"
                     <?= $engine === 'google' ? 'checked' : '' ?>>
              <label class="form-check-label" for="engineGoogle">بحث عبر قوقل</label>
            </div>
          </div>

          <div class="global-filters w-100 mt-2">
            <span>نوع المحتوى:</span>
            <select name="type">
              <option value="all"        <?= $typeFilter==='all'?'selected':'' ?>>كل الأنواع</option>
              <option value="news"       <?= $typeFilter==='news'?'selected':'' ?>>أخبار فقط</option>
              <option value="pages"      <?= $typeFilter==='pages'?'selected':'' ?>>الصفحات الثابتة فقط</option>
              <option value="categories" <?= $typeFilter==='categories'?'selected':'' ?>>الأقسام فقط</option>
            </select>

            <span>المدة:</span>
            <select name="date">
              <option value="any" <?= $dateFilter==='any'?'selected':'' ?>>أي وقت</option>
              <option value="1d"  <?= $dateFilter==='1d'?'selected':''  ?>>آخر 24 ساعة</option>
              <option value="7d"  <?= $dateFilter==='7d'?'selected':''  ?>>آخر 7 أيام</option>
              <option value="30d" <?= $dateFilter==='30d'?'selected':'' ?>>آخر 30 يومًا</option>
            </select>
          </div>
        </form>
      </div>
    </section>

    <!-- النتائج -->
    <section class="results-wrap">
      <div class="results-summary">
        <div>
          <?php if ($q !== '' || $typeFilter !== 'all' || $dateFilter !== 'any'): ?>
            تم العثور على تقريبًا
            <strong><?= (int)$totalAll ?></strong>
            نتيجة في الموقع (معروضة منها مجموعة مختصرة لكل نوع).
          <?php else: ?>
            اكتب كلمة البحث واختر نوع المحتوى والمدة الزمنية ثم اضغط "بحث".
          <?php endif; ?>
        </div>
        <?php if ($q): ?>
          <div class="text-muted">
            يمكن استخدام "بحث عبر قوقل" لعرض نتائج <code>site:<?= h($_SERVER['HTTP_HOST'] ?? 'godyar.org') ?></code>.
          </div>
        <?php endif; ?>
      </div>

      <?php if ($q === '' && $typeFilter === 'all' && $dateFilter === 'any'): ?>
        <div class="empty">
          ابدأ بالبحث عن أي كلمة مفتاحية، مثل: <strong>اقتصاد</strong>، <strong>الطقس</strong>، <strong>من نحن</strong>...
        </div>
      <?php else: ?>

        <?php if (($typeFilter === 'all' || $typeFilter === 'news') && !empty($newsResults)): ?>
          <h2 class="section-title">
            <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg> أخبار
            <span class="count">(<?= (int)$counts['news'] ?> نتيجة تقريبًا)</span>
          </h2>
          <div class="grid">
            <?php foreach ($newsResults as $n):
              $id    = (int)($n['id'] ?? 0);
              $slug  = (string)($n['slug'] ?? '');
              $title = (string)($n['title'] ?? '');
              $type  = (string)($n['type'] ?? '');
              $date  = !empty($n['created_at'] ?? '') ? date('Y-m-d', strtotime((string)$n['created_at'])) : '';
              $ex    = (string)($n['excerpt'] ?? '');

	              // المسار الحديث: /news/{slug} (واحتياطيًا /news/id/{id})
	              if ($slug !== '') {
	                $url = $baseUrl . '/news/id/' . (int)$id;
	              } elseif ($id > 0) {
	                $url = $baseUrl . '/news/id/' . $id;
	              } else {
	                $url = '#';
	              }
            ?>
            <a href="<?= h($url) ?>" class="card-news">
              <div class="card-meta">
                <span><?= $date ? '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>'.h($date) : '' ?></span>
                <span class="badge-type">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>خبر
                </span>
              </div>
              <h3 class="card-title"><?= highlight_term($title, $q) ?></h3>
              <?php if ($ex): ?>
                <p class="card-excerpt"><?= highlight_term($ex, $q) ?></p>
              <?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
        <?php elseif ($typeFilter === 'news' && empty($newsResults)): ?>
          <div class="empty">لا توجد أخبار مطابقة لبحثك.</div>
        <?php endif; ?>

        <?php if (($typeFilter === 'all' || $typeFilter === 'pages') && !empty($pageResults)): ?>
          <h2 class="section-title">
            <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> الصفحات الثابتة
            <span class="count">(<?= (int)$counts['pages'] ?> نتيجة تقريبًا)</span>
          </h2>
          <div class="grid">
            <?php foreach ($pageResults as $p):
              $id    = (int)($p['id'] ?? 0);
              $slug  = (string)($p['slug'] ?? '');
              $title = (string)($p['title'] ?? '');
              $body  = (string)($p['content'] ?? '');
              $upd   = (string)($p['updated_at'] ?? '');

              $plain = strip_tags($body);
              $excerpt = mb_strlen($plain, 'UTF-8') > 140
                  ? mb_substr($plain, 0, 140, 'UTF-8').'…'
                  : $plain;

	              $slugOrId = $slug !== '' ? $slug : (string)$id;
	              $url = $baseUrl . '/page/' . rawurlencode($slugOrId);
            ?>
            <a href="<?= h($url) ?>" class="card-page">
              <div class="card-meta">
                <span>
                  <?php if ($upd): ?>
                    <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(date('Y-m-d', strtotime($upd))) ?>
                  <?php endif; ?>
                </span>
                <span class="badge-type badge-type-page">
                  <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>صفحة
                </span>
              </div>
              <h3 class="card-title"><?= highlight_term($title, $q) ?></h3>
              <?php if ($excerpt): ?>
                <p class="card-excerpt"><?= highlight_term($excerpt, $q) ?></p>
              <?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
        <?php elseif ($typeFilter === 'pages' && empty($pageResults)): ?>
          <div class="empty">لا توجد صفحات مطابقة لبحثك.</div>
        <?php endif; ?>

        <?php if (($typeFilter === 'all' || $typeFilter === 'categories') && !empty($catResults)): ?>
          <h2 class="section-title">
            <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> الأقسام
            <span class="count">(<?= (int)$counts['cats'] ?> نتيجة تقريبًا)</span>
          </h2>
          <div class="grid">
            <?php foreach ($catResults as $c):
              $id        = (int)($c['id'] ?? 0);
              $name      = (string)($c['name'] ?? '');
              $slug      = (string)($c['slug'] ?? '');
              $desc      = (string)($c['description'] ?? '');
              $newsCount = (int)($c['news_count'] ?? 0);

              $plainDesc = strip_tags($desc);
              $excerpt = mb_strlen($plainDesc, 'UTF-8') > 120
                  ? mb_substr($plainDesc, 0, 120, 'UTF-8').'…'
                  : $plainDesc;

	              $slugOrId = $slug !== '' ? $slug : (string)$id;
	              $url = $baseUrl . '/category/' . rawurlencode($slugOrId);

              $badgeClass = $newsCount > 0 ? '' : 'badge-type-cat';
              $badgeText  = $newsCount > 0
                  ? ('يحتوي ' . $newsCount . ' خبر/مقال')
                  : 'بدون محتوى بعد';
            ?>
            <a href="<?= h($url) ?>" class="card-cat">
              <div class="card-meta">
                <span class="badge-type badge-type-cat <?= h($badgeClass) ?>">
                  <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($badgeText) ?>
                </span>
                <span class="text-muted small">
                  <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($slugOrId) ?>
                </span>
              </div>
              <h3 class="card-title"><?= highlight_term($name, $q) ?></h3>
              <?php if ($excerpt): ?>
                <p class="card-excerpt"><?= highlight_term($excerpt, $q) ?></p>
              <?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
        <?php elseif ($typeFilter === 'categories' && empty($catResults)): ?>
          <div class="empty">لا توجد أقسام مطابقة لبحثك.</div>
        <?php endif; ?>

        <?php if ($totalAll === 0 && $q !== ''): ?>
          <div class="empty">
            لم يتم العثور على نتائج مطابقة لعبارة "<strong><?= h($q) ?></strong>".
            جرّب كلمات أقل أو نوع محتوى مختلف، أو فعّل "بحث عبر قوقل" في الأعلى.
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </section>
  </main>

  <script>
  // في حال أردت الاعتماد فقط على JS لفتح قوقل بدون تحويل PHP، يمكن إلغاء كتلة التحويل في الأعلى
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('globalSearchForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      var engineRadio = form.querySelector('input[name="engine"]:checked');
      var engine = engineRadio ? engineRadio.value : 'local';

      // حالياً التحويل لقوقل يتم من PHP (لمن وقف JS)،
      // لو حاب يكون من JS فقط، أزل شرط التحويل من PHP واستعمل الكود هنا:
      /*
      if (engine === 'google') {
        e.preventDefault();
        var qInput = form.querySelector('input[name="q"]');
        var q = qInput ? qInput.value.trim() : '';
        if (!q) return;

        var domain = window.location.hostname;
        var url = 'https://www.google.com/search?q=' +
                  encodeURIComponent(q + ' site:' + domain);

        window.open(url, '_blank');
      }
      */
    });
  });
  </script>
</body>
</html>
