<?php
// frontend/pages/search.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// تظليل كلمة البحث داخل النص
if (!function_exists('highlight_term')) {
    function highlight_term(string $text, string $term): string {
        $term = trim($term ?? '');
        if ($term === '') {
            return h($text);
        }
        // نهرب النص أولاً
        $safe    = h($text);
        $pattern = '/' . preg_quote($term, '/') . '/iu';

        return preg_replace(
            $pattern,
            '<mark class="search-hl">$0</mark>',
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
$q          = trim((string)($_GET['q'] ?? ''));
$page       = max(1, (int)($_GET['page'] ?? 1));
$dateFilter = $_GET['date'] ?? 'any';  // any | 1d | 7d | 30d
$engine     = $_GET['engine'] ?? 'local';

$perPage = 15;
$offset  = ($page - 1) * $perPage;

// بناء شروط البحث
$where  = [];
$params = [];

if ($q !== '') {
    $where[]        = "(p.title LIKE :q OR p.content LIKE :q OR p.slug LIKE :q)";
    $params[':q']   = '%' . $q . '%';
}

// فلتر التاريخ
if ($dateFilter === '1d') {
    $where[] = "p.updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
} elseif ($dateFilter === '7d') {
    $where[] = "p.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($dateFilter === '30d') {
    $where[] = "p.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// إجمالي النتائج
$total = 0;
try {
    $sqlCount = "
        SELECT COUNT(*)
        FROM pages p
        $whereSql
    ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();
} catch (Throwable $e) {
    $total = 0;
}

// جلب النتائج
$results = [];
try {
    $sql = "
        SELECT p.id, p.title, p.slug, p.content, p.status, p.created_at, p.updated_at
        FROM pages p
        $whereSql
        ORDER BY p.updated_at DESC, p.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $results = [];
}

$pages = max(1, (int)ceil($total / $perPage));
$currentCount = is_array($results) ? count($results) : 0;
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title>بحث الصفحات<?= $q ? ' - ' . h($q) : '' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <style>
  body{
    background:#f8fafc;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  }
  .search-page{min-height:100vh;}
  .search-hero{
    padding:24px 0 16px;
    border-bottom:1px solid #e2e8f0;
    background:#0f172a;
    color:#e5e7eb;
  }
  .search-hero h1{
    font-size:1.4rem;
    margin:0 0 .4rem;
  }
  .search-hero p{
    margin:0;
    font-size:.9rem;
    color:#cbd5f5;
  }

  .search-form-compact{
    margin-top:1rem;
    display:flex;
    flex-wrap:wrap;
    gap:.5rem;
    align-items:center;
  }
  .search-form-compact input[type="text"]{
    flex:1 1 220px;
    border-radius:999px;
    border:1px solid #e2e8f0;
    padding:.45rem .9rem;
  }
  .search-form-compact button{
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

  .search-engine-toggle{
    font-size:.8rem;
    color:#e5e7eb;
    margin-top:.35rem;
  }
  .search-engine-toggle label{
    margin-bottom:0;
  }

  .search-filters{
    margin-top:.75rem;
    display:flex;
    flex-wrap:wrap;
    gap:.5rem;
    font-size:.8rem;
    color:#cbd5f5;
  }
  .search-filters select{
    border-radius:999px;
    border:1px solid #1f2937;
    background:#020617;
    color:#e5e7eb;
    font-size:.8rem;
    padding:.25rem .7rem;
  }

  .search-results-wrap{
    padding:20px 0 32px;
  }
  .search-results-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:8px;
    margin-bottom:10px;
    font-size:.82rem;
    color:#64748b;
  }

  .page-card-list{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
    gap:16px;
  }

  .page-card{
    border-radius:16px;
    border:1px solid #e2e8f0;
    background:#ffffff;
    padding:12px 14px;
    box-shadow:0 12px 30px rgba(15,23,42,0.06);
    text-decoration:none;
    color:inherit;
    display:flex;
    flex-direction:column;
    transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;
  }
  .page-card:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 40px rgba(15,23,42,0.1);
    border-color:#bfdbfe;
  }

  .page-card-title{
    font-size:1rem;
    margin:0 0 .25rem;
    font-weight:600;
    color:#0f172a;
  }
  .page-card-excerpt{
    margin:0;
    font-size:.84rem;
    color:#64748b;
  }
  .page-card-meta{
    font-size:.78rem;
    color:#94a3b8;
    display:flex;
    justify-content:space-between;
    gap:6px;
    margin-bottom:.25rem;
  }
  .page-card-status{
    display:inline-flex;
    align-items:center;
    gap:4px;
    font-size:.72rem;
    border-radius:999px;
    padding:2px 8px;
  }
  .page-status-published{
    background:#ecfdf5;
    color:#15803d;
  }
  .page-status-draft{
    background:#f1f5f9;
    color:#475569;
  }

  .search-hl{
    background:#facc15;
    color:#111827;
    padding:0 2px;
    border-radius:2px;
  }

  .search-empty{
    text-align:center;
    padding:24px 8px;
    font-size:.9rem;
    color:#6b7280;
  }

  .search-pagination{
    margin:20px 0;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }
  .search-pagination a{
    border-radius:999px;
    padding:.2rem .7rem;
    border:1px solid #e2e8f0;
    font-size:.82rem;
    text-decoration:none;
    color:#0f172a;
    background:#fff;
  }
  .search-pagination a.is-active{
    background:#eef2ff;
    border-color:#4f46e5;
    color:#111827;
    font-weight:600;
  }

  @media (max-width: 767.98px){
    .search-form-compact{
      flex-direction:column;
      align-items:stretch;
    }
  }
  </style>
</head>
<body class="search-page">
  <main class="container">
    <section class="search-hero">
      <div class="container">
        <h1>بحث الصفحات الثابتة</h1>
        <p>
          <?= $q ? 'نتائج البحث عن: <strong>' . h($q) . '</strong>' : 'ابحث في الصفحات الثابتة مثل من نحن، اتصل بنا، السياسات وغيرها.' ?>
        </p>

        <!-- نموذج البحث + اختيار محرك البحث -->
        <form class="search-form-compact" method="get" action="" id="pagesSearchForm">
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="اكتب كلمة البحث داخل الصفحات">
          <button type="submit">
            <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> بحث
          </button>

          <div class="search-engine-toggle w-100">
            <span class="d-block mb-1">محرك البحث:</span>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="engine" id="engineLocal" value="local"
                     <?= $engine === 'google' ? '' : 'checked' ?>>
              <label class="form-check-label" for="engineLocal">بحث داخل الموقع</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="engine" id="engineGoogle" value="google"
                     <?= $engine === 'google' ? 'checked' : '' ?>>
              <label class="form-check-label" for="engineGoogle">بحث عبر قوقل</label>
            </div>
          </div>

          <!-- فلتر التاريخ -->
          <div class="search-filters w-100 mt-2">
            <span>تصفية حسب التاريخ:</span>
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

    <section class="search-results-wrap">
      <div class="search-results-header">
        <div>
          <?php if ($q): ?>
            تم العثور على <strong><?= (int)$currentCount ?></strong> نتيجة في هذه الصفحة من أصل <?= (int)$total ?>.
          <?php else: ?>
            اكتب كلمة البحث في الأعلى لعرض نتائج الصفحات.
          <?php endif; ?>
        </div>
        <?php if ($q): ?>
          <div class="text-muted">
            يمكنك اختيار "بحث عبر قوقل" لعرض نتائج <code>site:<?= h($_SERVER['HTTP_HOST'] ?? 'example.com') ?></code>.
          </div>
        <?php endif; ?>
      </div>

      <?php if ($q === ''): ?>
        <div class="search-empty">
          اكتب كلمة بحث (مثال: من نحن، سياسة الخصوصية، اتصل بنا...) ثم اضغط "بحث".
        </div>
      <?php elseif (empty($results)): ?>
        <div class="search-empty">
          لا توجد صفحات مطابقة لكلمة البحث "<strong><?= h($q) ?></strong>".
        </div>
      <?php else: ?>
        <div class="page-card-list">
          <?php foreach ($results as $p):
            $id     = (int)($p['id'] ?? 0);
            $title  = (string)($p['title'] ?? '');
            $slug   = (string)($p['slug'] ?? '');
            $content= (string)($p['content'] ?? '');
            $status = (string)($p['status'] ?? 'draft');
            $created= (string)($p['created_at'] ?? '');
            $updated= (string)($p['updated_at'] ?? '');

            // مقتطف بسيط من أول النص
            $plain  = strip_tags($content);
            if (mb_strlen($plain, 'UTF-8') > 140) {
                $excerpt = mb_substr($plain, 0, 140, 'UTF-8') . '…';
            } else {
                $excerpt = $plain;
            }

            // رابط الصفحة
            $slugOrId = $slug !== '' ? $slug : (string)$id;
            // حسب ما يبدو من لوحة الإدارة: /page/{slug}
            $url = '/page/' . rawurlencode($slugOrId);

            $statusClass = $status === 'published' ? 'page-status-published' : 'page-status-draft';
            $statusText  = $status === 'published' ? 'منشورة' : 'مسودة';
          ?>
          <a href="<?= h($url) ?>" class="page-card">
            <div class="page-card-meta">
              <span>
                <?php if ($updated): ?>
                  <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  آخر تحديث: <?= h(date('Y-m-d', strtotime($updated))) ?>
                <?php elseif ($created): ?>
                  <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <?= h(date('Y-m-d', strtotime($created))) ?>
                <?php endif; ?>
              </span>
              <span class="page-card-status <?= h($statusClass) ?>">
                <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($statusText) ?>
              </span>
            </div>
            <h2 class="page-card-title">
              <?= highlight_term($title, $q) ?>
            </h2>
            <?php if ($excerpt !== ''): ?>
              <p class="page-card-excerpt">
                <?= highlight_term($excerpt, $q) ?>
              </p>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
          <div class="search-pagination">
            <?php for($i=1;$i<=$pages;$i++): ?>
              <a href="?q=<?= urlencode($q) ?>&page=<?= $i ?>&date=<?= urlencode($dateFilter) ?>&engine=<?= urlencode($engine) ?>"
                 class="<?= $i === $page ? 'is-active' : '' ?>">
                <?= $i ?>
              </a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>

  <script>
  // إذا اختار المستخدم "بحث عبر قوقل" نفتح تبويب جديد بنتائج Google
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('pagesSearchForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      var engineRadio = form.querySelector('input[name="engine"]:checked');
      var engine = engineRadio ? engineRadio.value : 'local';

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
    });
  });
  </script>
</body>
</html>
