<?php
declare(strict_types=1);

// GDY_BUILD: v9


require_once __DIR__ . '/../_admin_guard.php';
// godyar/admin/news/index.php — إدارة الأخبار (قائمة)

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}


// تحديد نوع المستخدم
$isWriter = Auth::isWriter();
$userId   = (int)($_SESSION['user']['id'] ?? 0);

$currentPage = 'posts';
$pageTitle   = __('t_e06a9f8f17', 'إدارة الأخبار');

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database not available');
}

// ترقيم صفحات + عدد العناصر في الصفحة
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 20;
}
$offset  = ($page - 1) * $perPage;

$search = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

// السماح بحالات معينة فقط
$allowedStatuses = ['', 'published', 'draft', 'pending', 'approved', 'archived'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

// فلاتر إضافية
$categoryId = (int)($_GET['category_id'] ?? 0);
$dateFrom   = trim((string)($_GET['from'] ?? '')); // YYYY-MM-DD
$dateTo     = trim((string)($_GET['to'] ?? ''));   // YYYY-MM-DD
$sort       = trim((string)($_GET['sort'] ?? 'published_desc'));

// بحث متقدم وفلاتر جودة
$inContent  = ((int)($_GET['in_content'] ?? 0) === 1);
$noImage    = ((int)($_GET['no_image'] ?? 0) === 1);
$noDesc     = ((int)($_GET['no_desc'] ?? 0) === 1);
$noKeywords = ((int)($_GET['no_keywords'] ?? 0) === 1);


// تحقق بسيط من تنسيق التاريخ
$dateRe = '/^\d{4}-\d{2}-\d{2}$/';
if ($dateFrom !== '' && !preg_match($dateRe, $dateFrom)) $dateFrom = '';
if ($dateTo   !== '' && !preg_match($dateRe, $dateTo)) $dateTo = '';

$allowedSort = [
    'published_desc', 'published_asc',
    'created_desc', 'created_asc',
    'title_asc', 'title_desc',
    'id_desc', 'id_asc',
];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'published_desc';
}

// إحصائيات سريعة
$stats = [
    'total'     => 0,
    'published' => 0,
    'draft'     => 0,
    'pending'   => 0,
    'approved'  => 0,
    'archived'  => 0,
    'today'     => 0,
];

try {
    if ($isWriter) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = :uid");
        $stmt->execute([':uid' => $userId]);
        $stats['total'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = :uid AND status = 'published'");
        $stmt->execute([':uid' => $userId]);
        $stats['published'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = :uid AND status = 'draft'");
        $stmt->execute([':uid' => $userId]);
        $stats['draft'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = :uid AND status = 'pending'");
        $stmt->execute([':uid' => $userId]);
        $stats['pending'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = :uid AND status = 'approved'");
        $stmt->execute([':uid' => $userId]);
        $stats['approved'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = :uid AND status = 'archived'");
        $stmt->execute([':uid' => $userId]);
        $stats['archived'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE author_id = :uid AND DATE(created_at) = CURDATE()");
        $stmt->execute([':uid' => $userId]);
        $stats['today'] = (int)$stmt->fetchColumn();
    } else {
        $stats['total']     = (int)$pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
        $stats['published'] = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE status = 'published'")->fetchColumn();
        $stats['draft']     = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE status = 'draft'")->fetchColumn();
        $stats['pending']   = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE status = 'pending'")->fetchColumn();
        $stats['approved']  = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE status = 'approved'")->fetchColumn();
        $stats['archived']  = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE status = 'archived'")->fetchColumn();
        $stats['today']     = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    }
} catch (Throwable $e) {
    @error_log('[Admin News] stats: ' . $e->getMessage());
}


// قائمة التصنيفات (للفلترة)
$categories = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM categories")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $hasSort = false;
    foreach ($cols as $c) {
        if (isset($c['Field']) && $c['Field'] === 'sort_order') { $hasSort = true; break; }
    }
    $order = $hasSort ? "ORDER BY sort_order ASC, name ASC" : "ORDER BY name ASC";
    $categories = $pdo->query("SELECT id, name FROM categories $order")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    // ignore
}

// بارامترات مشتركة للروابط
$baseParams = [
    'q'           => $search,
    'per_page'    => $perPage,
    'category_id' => $categoryId ?: '',
    'from'        => $dateFrom,
    'to'          => $dateTo,
    'sort'        => $sort,
    'in_content'  => $inContent ? 1 : '',
    'no_image'    => $noImage ? 1 : '',
    'no_desc'     => $noDesc ? 1 : '',
    'no_keywords' => $noKeywords ? 1 : '',
];
$buildQuery = function(array $overrides = []) use ($baseParams): string {
    $p = array_merge($baseParams, $overrides);
    // remove empties
    foreach ($p as $k => $v) {
        if ($v === '' || $v === null) unset($p[$k]);
    }
    return http_build_query($p);
};

// بناء شرط WHERE
$where  = '1=1';
$params = [];

// الكاتب يرى مقالاته فقط
if ($isWriter) {
    $where .= " AND n.author_id = :uid";
    $params[':uid'] = (string)$userId;
}

if ($search !== '') {
    $q = '%' . $search . '%';
    if ($inContent) {
        $where .= " AND (n.title LIKE :q_title OR n.slug LIKE :q_slug OR n.excerpt LIKE :q_excerpt OR n.content LIKE :q_content)";
        $params[':q_title']   = $q;
        $params[':q_slug']    = $q;
        $params[':q_excerpt'] = $q;
        $params[':q_content'] = $q;
    } else {
        $where .= " AND (n.title LIKE :q_title OR n.slug LIKE :q_slug)";
        $params[':q_title'] = $q;
        $params[':q_slug']  = $q;
    }
}

if ($status !== '') {
    $where .= " AND n.status = :status";
    $params[':status'] = $status;
}

if ($categoryId > 0) {
    $where .= " AND n.category_id = :cid";
    $params[':cid'] = (string)$categoryId;
}


if ($noImage) {
    $where .= " AND (n.image IS NULL OR n.image = '')";
}
if ($noDesc) {
    $where .= " AND (n.seo_description IS NULL OR n.seo_description = '')";
}
if ($noKeywords) {
    $where .= " AND (n.seo_keywords IS NULL OR n.seo_keywords = '')";
}


if ($dateFrom !== '') {
    $where .= " AND DATE(n.created_at) >= :df";
    $params[':df'] = $dateFrom;
}

if ($dateTo !== '') {
    $where .= " AND DATE(n.created_at) <= :dt";
    $params[':dt'] = $dateTo;
}

// ترتيب
switch ($sort) {
    case 'published_asc': $orderBy = 'n.published_at ASC, n.id ASC'; break;
    case 'created_desc':  $orderBy = 'n.created_at DESC, n.id DESC'; break;
    case 'created_asc':   $orderBy = 'n.created_at ASC, n.id ASC'; break;
    case 'title_asc':     $orderBy = 'n.title ASC, n.id DESC'; break;
    case 'title_desc':    $orderBy = 'n.title DESC, n.id DESC'; break;
    case 'id_asc':        $orderBy = 'n.id ASC'; break;
    case 'id_desc':       $orderBy = 'n.id DESC'; break;
    case 'published_desc':
    default:              $orderBy = 'n.published_at DESC, n.id DESC'; break;
}

// تصدير CSV (حسب الفلاتر الحالية)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($isWriter) {
        http_response_code(403);
        die('forbidden');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=news_export_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fwrite($out, "ï»¿");
    fputcsv($out, ['id','title','slug','status','published_at','created_at','category']);
    try {
        $sqlE = "SELECT n.id, n.title, n.slug, n.status, n.published_at, n.created_at, c.name AS category_name
                 FROM news n
                 LEFT JOIN categories c ON c.id = n.category_id
                 WHERE $where
                 ORDER BY $orderBy
                 LIMIT 5000";
        $stE = $pdo->prepare($sqlE);
        foreach ($params as $k => $v) {
            $stE->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stE->execute();
        while ($r = $stE->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $r['id'] ?? '',
                $r['title'] ?? '',
                $r['slug'] ?? '',
                $r['status'] ?? '',
                $r['published_at'] ?? '',
                $r['created_at'] ?? '',
                $r['category_name'] ?? '',
            ]);
        }
    } catch (Throwable $e) {
        // ignore
    }
    fclose($out);
    exit;
}

// عدد السجلات (حسب الفلتر)
$total = 0;
try {
    $sql = "SELECT COUNT(*) FROM news n WHERE $where";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    @error_log('[Admin News] count: ' . $e->getMessage());
}

// جلب البيانات
$items = [];
try {
    $sql = "SELECT n.id, n.title, n.slug, n.status, n.published_at, n.created_at, n.category_id, c.name AS category_name,
                   n.image, n.seo_title, n.seo_description, n.seo_keywords,
                   n.excerpt, SUBSTRING(n.content,1,4000) AS content_snip
            FROM news n
            LEFT JOIN categories c ON c.id = n.category_id
            WHERE $where
            ORDER BY $orderBy
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @error_log('[Admin News] list: ' . $e->getMessage());
}

$totalPages =$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<style>
/* إزالة التمرير الأفقي عن الصفحة كلها */
html, body {
  overflow-x: hidden;
}

/* ضمان عدم دخول المحتوى خلف السايدبار على الشاشات الكبيرة */
@media (min-width: 992px) {
  .admin-content {
    margin-right: 260px !important; /* نفس عرض القائمة الجانبية */
  }
}

/* صفحة الأخبار داخل لوحة التحكم */
.admin-content.gdy-admin-page {
  background: radial-gradient(circle at top left, #020617 0%, #020617 45%, #020617 100%);
  min-height: 100vh;
  color: #e5e7eb;
}

/* حاوية داخلية لتقليص عرض الصفحة وتوسيط المحتوى */
.gdy-admin-page-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1.5rem 1rem 2rem;
}

/* هيدر الصفحة */
.gdy-page-header {
  padding: 1rem 1.25rem;
  margin-bottom: 1.25rem;
  border-radius: 1rem;
  background: linear-gradient(135deg, #0f172a 0%, #020617 60%, #0f172a 100%);
  border: 1px solid rgba(148,163,184,0.35);
  box-shadow: 0 14px 30px rgba(15,23,42,0.9);
}
.gdy-page-header h1 {
  font-weight: 700;
}
.gdy-page-header p {
  font-size: .88rem;
  color: #9ca3af;
}

/* شريط الإحصائيات السريعة */
.gdy-stats-strip {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: .75rem;
  margin-bottom: 1rem;
}
.gdy-stat-pill {
  position: relative;
  overflow: hidden;
  border-radius: .9rem;
  padding: .7rem .9rem;
  background: radial-gradient(circle at top left, rgba(15,23,42,0.95), rgba(15,23,42,0.98));
  border: 1px solid rgba(148,163,184,0.4);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .6rem;
  color: #e5e7eb;
}
.gdy-stat-pill::before {
  content: '';
  position: absolute;
  inset: -40%;
  background: radial-gradient(circle at top right, rgba(14,165,233,0.18), transparent 60%);
  opacity: .8;
}
.gdy-stat-pill-inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: .6rem;
}
.gdy-stat-pill-icon {
  width: 32px;
  height: 32px;
  border-radius: .8rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15,23,42,0.9);
}
.gdy-stat-pill-icon i {
  font-size: .9rem;
}
.gdy-stat-pill-label {
  font-size: .78rem;
  color: #9ca3af;
}
.gdy-stat-pill-value {
  font-size: 1.1rem;
  font-weight: 700;
}
.gdy-stat-pill-tag {
  font-size: .75rem;
  padding: .2rem .5rem;
  border-radius: 999px;
  background: rgba(15,23,42,0.85);
}

/* فورم البحث والفلاتر */
.gdy-filter-bar {
  margin-bottom: 1rem;
  padding: .75rem 1rem;
  border-radius: 1rem;
  background: rgba(15,23,42,0.95);
  border: 1px solid rgba(31,41,55,0.9);
}
.gdy-status-filters .nav-link {
  border-radius: 999px;
  padding: .25rem .8rem;
  font-size: .78rem;
}
.gdy-status-filters .nav-link.active {
  background: linear-gradient(135deg, #0ea5e9, #22c55e);
  border-color: transparent;
  color: #0f172a !important;
  font-weight: 600;
}

/* الكارد الزجاجي للجدول */
.glass-card {
  border-radius: 1rem;
  border: 1px solid rgba(148,163,184,0.35);
  background: radial-gradient(circle at top left, rgba(15,23,42,0.97), rgba(15,23,42,0.99));
  box-shadow: 0 16px 40px rgba(15,23,42,0.95);
  overflow: hidden;
}
.glass-card .card-body {
  background: transparent;
}


/* تحديد جماعي */
.table-news tbody tr.is-selected { background: rgba(14,165,233,0.10) !important; }
.gdy-bulkbar { padding: .5rem .75rem; border-bottom: 1px solid rgba(31,41,55,0.9); display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
.gdy-bulkbar .form-select, .gdy-bulkbar .btn { font-size: .8rem; }
.gdy-bulkbar .count { font-size: .8rem; color:#9ca3af; }
/\* جدول الأخبار \*/
.table-news {
  color: #e5e7eb;
  font-size: .85rem;
}
.table-news thead {
  background: rgba(15,23,42,1);
}
.table-news thead th {
  border-bottom: 1px solid rgba(55,65,81,0.9) !important;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #9ca3af;
}
.table-news tbody tr {
  transition: background-color .2s ease, transform .1s ease;
}
.table-news tbody tr:hover {
  background: rgba(15,23,42,0.9);
  transform: translateY(-1px);
}
.table-news tbody td {
  vertical-align: middle;
}

/* عمود slug يكون ضيق مع ellipsis */
.small-slug {
  max-width: 140px;
}
.small-slug code {
  display: inline-block;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* شارة <?= h(__('t_1253eb5642', 'الحالة')) ?> */
.badge-status {
  font-size: .7rem;
  padding: .25rem .6rem;
  border-radius: 999px;
}
.badge-status.published {
  background: rgba(22,163,74,0.15);
  color: #4ade80;
  border: 1px solid rgba(34,197,94,0.5);
}
.badge-status.draft {
  background: rgba(148,163,184,0.1);
  color: #e5e7eb;
  border: 1px solid rgba(148,163,184,0.5);
}
.badge-status.pending {
  background: rgba(245,158,11,0.12);
  color: #fbbf24;
  border: 1px solid rgba(245,158,11,0.55);
}
.badge-status.approved {
  background: rgba(59,130,246,0.12);
  color: #93c5fd;
  border: 1px solid rgba(59,130,246,0.55);
}
.badge-status.archived {
  background: rgba(107,114,128,0.14);
  color: #cbd5e1;
  border: 1px solid rgba(107,114,128,0.55);
}

/* أزرار ال<?= h(__('t_901efe9b1c', 'إجراءات')) ?> */
.btn-icon {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .75rem;
}

/* شريط أسفل الجدول */
.gdy-table-footer {
  padding: .5rem .9rem;
  border-top: 1px solid rgba(31,41,55,0.9);
  font-size: .78rem;
  color: #9ca3af;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: .5rem;
}

/* الترقيم */
.pagination.pagination-sm .page-link {
  font-size: .78rem;
}

/* جعل سكرول الجدول نفسه فقط عند صغر الشاشة */
.table-responsive {
  overflow-x: auto;
}

/* استجابة */
@media (max-width: 767.98px) {
  .gdy-page-header {
    padding: .75rem .9rem;
  }
  .gdy-filter-bar {
    padding: .75rem .8rem;
  }
}
</style>

<div class="admin-content gdy-admin-page">
  <div class="gdy-admin-page-inner">

    <div class="gdy-page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h1 class="h4 mb-1 text-white"><?= h(__('t_e06a9f8f17', 'إدارة الأخبار')) ?></h1>
	        <p class="mb-0 small"><?= h(__('t_8403e3b222', 'إضافة وتعديل وحذف الأخبار المنشورة في الموقع.')) ?></p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="create.php" class="btn btn-primary btn-sm">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_0d1f6ecf66', 'إضافة خبر جديد')) ?>
        </a>
        <a href="translations.php" class="btn btn-outline-light btn-sm">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#globe"></use></svg> <?= h('ترجمات') ?>
        </a>
        <a href="polls.php" class="btn btn-outline-light btn-sm">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h('استطلاعات') ?>
        </a>
        <a href="questions.php" class="btn btn-outline-light btn-sm">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h('أسئلة القرّاء') ?>
        </a>
        <?php if (!$isWriter): ?>
        <a href="trash.php" class="btn btn-outline-light btn-sm">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_e92d1bf1e5', 'سلة المحذوفات')) ?>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- شريط الإحصائيات السريعة -->
    <div class="gdy-stats-strip mb-3">
      <div class="gdy-stat-pill">
        <div class="gdy-stat-pill-inner">
          <div class="gdy-stat-pill-icon text-info">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
          </div>
          <div>
            <div class="gdy-stat-pill-label"><?= h(__('t_93e37eb4e5', 'إجمالي الأخبار')) ?></div>
            <div class="gdy-stat-pill-value"><?= number_format($stats['total']) ?></div>
          </div>
        </div>
        <div class="gdy-stat-pill-tag">
          <?= h(__('t_6d08f19681', 'الكل')) ?>
        </div>
      </div>

      <div class="gdy-stat-pill">
        <div class="gdy-stat-pill-inner">
          <div class="gdy-stat-pill-icon text-success">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </div>
          <div>
	            <div class="gdy-stat-pill-label"><?= h(__('t_f23ef521b6', 'الأخبار المنشورة')) ?></div>
            <div class="gdy-stat-pill-value"><?= number_format($stats['published']) ?></div>
          </div>
        </div>
	        <div class="gdy-stat-pill-tag">
	          <?= h(__('t_ddcbb708cd', 'جاهزة على الموقع')) ?>
	        </div>
      </div>

      <div class="gdy-stat-pill">
        <div class="gdy-stat-pill-inner">
          <div class="gdy-stat-pill-icon text-warning">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </div>
          <div>
            <div class="gdy-stat-pill-label"><?= h(__('t_e66db1aa36', 'المسودات')) ?></div>
            <div class="gdy-stat-pill-value"><?= number_format($stats['draft']) ?></div>
          </div>
        </div>
        <div class="gdy-stat-pill-tag">
          <?= h(__('t_94fa971b80', 'قيد الإعداد')) ?>
        </div>
      </div>

      <div class="gdy-stat-pill">
        <div class="gdy-stat-pill-inner">
          <div class="gdy-stat-pill-icon text-danger">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          </div>
          <div>
            <div class="gdy-stat-pill-label"><?= h(__('t_0f6db0d92a', 'أخبار اليوم')) ?></div>
            <div class="gdy-stat-pill-value"><?= number_format($stats['today']) ?></div>
          </div>
        </div>
        <div class="gdy-stat-pill-tag">
          <?= h(__('t_39901a1f84', 'آخر 24 ساعة')) ?>
        </div>
      </div>
    </div>

    <!-- شريط البحث والفلاتر -->
    <div class="gdy-filter-bar mb-3">
      <?php
        // Saved Filters UI
        require_once __DIR__ . '/../includes/saved_filters_ui.php';
          echo gdy_saved_filters_ui('news');
?>
      <form class="row g-2 align-items-center" method="get" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="col-sm-5 col-md-4 col-lg-3">
          <input
            type="text"
            name="q"
            value="<?= h($search) ?>"
            class="form-control form-control-sm bg-dark border-secondary text-light"
	            placeholder="<?= h(__('t_f3ac22dd80', 'بحث (العنوان/الرابط) ...')) ?>"
          >
        </div>
        <div class="col-12">
          <div class="d-flex flex-wrap gap-3 align-items-center small text-light">
            <label class="form-check form-check-inline mb-0">
              <input class="form-check-input" type="checkbox" name="in_content" value="1" <?= $inContent ? 'checked' : '' ?>>
              <span class="form-check-label">بحث داخل المحتوى</span>
            </label>
            <label class="form-check form-check-inline mb-0">
              <input class="form-check-input" type="checkbox" name="no_image" value="1" <?= $noImage ? 'checked' : '' ?>>
              <span class="form-check-label">بدون صورة</span>
            </label>
            <label class="form-check form-check-inline mb-0">
              <input class="form-check-input" type="checkbox" name="no_desc" value="1" <?= $noDesc ? 'checked' : '' ?>>
              <span class="form-check-label">بدون وصف SEO</span>
            </label>
            <label class="form-check form-check-inline mb-0">
              <input class="form-check-input" type="checkbox" name="no_keywords" value="1" <?= $noKeywords ? 'checked' : '' ?>>
              <span class="form-check-label">بدون كلمات مفتاحية</span>
            </label>
          </div>
        </div>

        <div class="col-sm-4 col-md-3 col-lg-3">
          <ul class="nav nav-pills nav-fill gdy-status-filters">
            <li class="nav-item">
              <a class="nav-link <?= $status === '' ? 'active' : '' ?>"
                 href="?<?= h($buildQuery(['status' => '', 'page' => 1])) ?>">
                <?= h(__('t_6d08f19681', 'الكل')) ?>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $status === 'published' ? 'active' : '' ?>"
                 href="?<?= h($buildQuery(['status' => 'published', 'page' => 1])) ?>">
                <?= h(__('t_ecfb62b400', 'منشور')) ?>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $status === 'draft' ? 'active' : '' ?>"
                 href="?<?= h($buildQuery(['status' => 'draft', 'page' => 1])) ?>">
                <?= h(__('t_9071af8f2d', 'مسودة')) ?>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>"
                 href="?<?= h($buildQuery(['status' => 'pending', 'page' => 1])) ?>">
                <?= h(__('t_64d3e10dc3', 'للمراجعة')) ?>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $status === 'approved' ? 'active' : '' ?>"
                 href="?<?= h($buildQuery(['status' => 'approved', 'page' => 1])) ?>">
                <?= h(__('t_d7fe42c8a3', 'جاهز')) ?>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $status === 'archived' ? 'active' : '' ?>"
                 href="?<?= h($buildQuery(['status' => 'archived', 'page' => 1])) ?>">
                <?= h(__('t_c220a1c484', 'أرشيف')) ?>
              </a>
            </li>
          </ul>
        </div>

        

        <div class="col-sm-4 col-md-3 col-lg-3">
          <select name="category_id" class="form-select form-select-sm bg-dark border-secondary text-light">
            <option value="0"><?= h(__('t_2f9a3a66fd', 'كل التصنيفات')) ?></option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === (int)$categoryId) ? 'selected' : '' ?>>
                <?= h($c['name'] ?? '') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-sm-6 col-md-3 col-lg-2">
          <input type="date" name="from" value="<?= h($dateFrom) ?>" class="form-control form-control-sm bg-dark border-secondary text-light" title="<?= h(__('t_3c7ab1e348', 'من تاريخ')) ?>">
        </div>
        <div class="col-sm-6 col-md-3 col-lg-2">
          <input type="date" name="to" value="<?= h($dateTo) ?>" class="form-control form-control-sm bg-dark border-secondary text-light" title="<?= h(__('t_4e7112c8a1', 'إلى تاريخ')) ?>">
        </div>

        <div class="col-sm-6 col-md-3 col-lg-2">
          <select name="sort" class="form-select form-select-sm bg-dark border-secondary text-light">
            <option value="published_desc" <?= $sort==='published_desc'?'selected':'' ?>><?= h(__('t_4d9df0f319', 'الأحدث نشرًا')) ?></option>
            <option value="published_asc" <?= $sort==='published_asc'?'selected':'' ?>><?= h(__('t_a2fd2d3dbe', 'الأقدم نشرًا')) ?></option>
            <option value="created_desc" <?= $sort==='created_desc'?'selected':'' ?>><?= h(__('t_0f58c4c34e', 'الأحدث إنشاءً')) ?></option>
            <option value="created_asc" <?= $sort==='created_asc'?'selected':'' ?>><?= h(__('t_0a4a9edc8c', 'الأقدم إنشاءً')) ?></option>
            <option value="title_asc" <?= $sort==='title_asc'?'selected':'' ?>><?= h(__('t_9d5b2aa3b3', 'العنوان A→Z')) ?></option>
            <option value="title_desc" <?= $sort==='title_desc'?'selected':'' ?>><?= h(__('t_3eabd2e8d2', 'العنوان Z→A')) ?></option>
          </select>
        </div>
<!-- اختيار عدد العناصر <?= h(__('t_1787671ea7', 'في الصفحة')) ?> -->
        <div class="col-sm-3 col-md-2 col-lg-2">
          <select name="per_page" class="form-select form-select-sm bg-dark border-secondary text-light">
            <?php foreach ($allowedPerPage as $pp): ?>
              <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>>
                <?= $pp ?> <?= h(__('t_1787671ea7', 'في الصفحة')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-auto">
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> <?= h(__('t_ab79fc1485', 'بحث')) ?>
          </button>
        <a href="index.php" class="btn btn-outline-light btn-sm" title="<?= h(__('t_2ab2c7a79f', 'إعادة ضبط الفلاتر')) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        </a>
        <?php if (!$isWriter): ?>
        <a href="?<?= h($buildQuery(['export'=>'csv','page'=>1])) ?>" class="btn btn-outline-success btn-sm" title="<?= h(__('t_5bb5d93d1a', 'تصدير CSV')) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> CSV
        </a>
        <?php endif; ?>

        </div>
        <div class="col-12 col-md-auto ms-md-auto text-md-end small text-muted mt-2 mt-md-0">
          <span><?= h(__('t_c6a5fe1902', 'عدد النتائج:')) ?> <strong><?= number_format($total) ?></strong></span>
        </div>
      </form>
    </div>

    <div class="card shadow-sm glass-card">
      <div class="card-body p-0">
        <?php if (empty($items)): ?>
          <p class="p-3 text-muted mb-0"><?= h(__('t_8912ba2932', 'لا توجد أخبار حالياً.')) ?></p>
        <?php else: ?>
          
          <?php if (!$isWriter): ?>
          <div class="gdy-bulkbar">
            <select id="bulkAction" class="form-select form-select-sm bg-dark border-secondary text-light" style="max-width:220px">
              <option value=""><?= h(__('t_7f4c0ad5f1', 'إجراءات جماعية...')) ?></option>
              <option value="status:published"><?= h(__('t_ecfb62b400', 'نشر')) ?></option>
              <option value="status:draft"><?= h(__('t_9071af8f2d', 'تحويل لمسودة')) ?></option>
              <option value="status:pending"><?= h(__('t_64d3e10dc3', 'إرسال للمراجعة')) ?></option>
              <option value="status:approved"><?= h(__('t_d7fe42c8a3', 'اعتماد')) ?></option>
              <option value="status:archived"><?= h(__('t_c220a1c484', 'أرشفة')) ?></option>
              <option value="duplicate">نسخ خبر (Duplicate)</option>
              <option value="move_category">نقل لتصنيف...</option>
              <option value="delete"><?= h(__('t_3b9854e1bb', 'نقل إلى سلة المحذوفات')) ?></option>
            </select>
            <select id="bulkCategory" class="form-select form-select-sm bg-dark border-secondary text-light d-none" style="max-width:220px">
              <option value="0">اختر التصنيف...</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= h($c['name'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" id="bulkApply" class="btn btn-primary btn-sm" disabled>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_7a5d1d39c4', 'تطبيق')) ?>
            </button>
            <button type="button" id="bulkClear" class="btn btn-outline-light btn-sm" disabled>
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_9b2b5d1c34', 'إلغاء التحديد')) ?>
            </button>
            <span class="count ms-auto"><span id="selectedCount">0</span> <?= h(__('t_45d980a7f0', 'محدد')) ?></span>
          </div>
            <div id="selectAllResultsBar" class="gdy-selectall-results d-none px-3 py-2 small bg-dark text-light border-top border-secondary">
              <span id="selectAllMsg"></span>
              <button type="button" id="selectAllResultsBtn" class="btn btn-sm btn-outline-warning ms-2">تحديد كل النتائج (<span id="selectAllTotal">0</span>)</button>
              <button type="button" id="clearAllResultsBtn" class="btn btn-sm btn-outline-light ms-2 d-none">إلغاء تحديد كل النتائج</button>
              <span class="ms-auto" id="bulkProgress"></span>
            </div>
          <?php endif; ?>
<div class="table-responsive">
            <table class="table table-sm table-hover table-striped mb-0 align-middle text-center table-news">
              <thead>
                <tr>
                  <th style="width:38px"><input type="checkbox" id="checkAll" title="تحديد الكل"></th>
                  <th style="width:60px">#</th>
                  <th class="text-start"><?= h(__('t_6dc6588082', 'العنوان')) ?></th>
                  <th><?= h(__('t_0781965540', 'الرابط (Slug)')) ?></th>
                  <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                  <th><?= h(__('t_4da1d32d5f', 'تاريخ النشر')) ?></th>
                  <th style="width:260px">SEO</th>
                  <th style="width:240px"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $row): ?>
                  <?php
                    $id    = (int)$row['id'];
                    $title = (string)($row['title'] ?? '');
                    $slug  = (string)($row['slug'] ?? '');
                    $st    = (string)($row['status'] ?? '');
                    $label = $st === 'published' ? __('t_ecfb62b400', 'منشور')
                        : ($st === 'draft' ? __('t_9071af8f2d', 'مسودة')
                        : ($st === 'pending' ? __('t_e9210fb9c2', 'بانتظار المراجعة')
                        : ($st === 'approved' ? __('t_aeb4d514db', 'جاهز للنشر')
                        : ($st === 'archived' ? __('t_2e67aea8ca', 'مؤرشف') : $st))));

                    $statusClass = $st;

                    $dateVal   = $row['published_at'] ?: ($row['created_at'] ?? '');
                    $slugOrId  = $slug !== '' ? $slug : (string)$id;
                    $__base = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
                    $frontUrl  = ($__base !== '' ? $__base : '') . '/news/id/' . $id;

                    // تقصير عرض الـ slug في الجدول مع الاحتفاظ بالقيمة الكاملة في الـ title
                    $slugFull   = $slug;
                    $maxSlugLen = 25;
                    if ($slugFull !== '' && function_exists('mb_strlen')) {
                        $slugShort = mb_strlen($slugFull, 'UTF-8') > $maxSlugLen
                            ? mb_substr($slugFull, 0, $maxSlugLen - 1, 'UTF-8') . '…'
                            : $slugFull;
                    } else {
                        $slugShort = strlen($slugFull) > $maxSlugLen
                            ? substr($slugFull, 0, $maxSlugLen - 1) . '…'
                            : $slugFull;
                    }

// --- SEO indicators ---
$imgMain = (string)($row['image'] ?? '');
$seoTitle = (string)($row['seo_title'] ?? '');
$seoDesc  = (string)($row['seo_description'] ?? '');
$seoKw    = (string)($row['seo_keywords'] ?? '');
$contentSnip = (string)($row['content_snip'] ?? '');

$len = function (string $s): int {
    if ($s === '') return 0;
    if (function_exists('mb_strlen')) return (int)mb_strlen($s, 'UTF-8');
    return (int)strlen($s);
};

$titleLen = $len($title);
$seoTitleLen = $len($seoTitle !== '' ? $seoTitle : $title);
$seoDescLen  = $len($seoDesc);

$kwCount = 0;
if ($seoKw !== '') {
    $parts = preg_split('/[\n,]+/u', $seoKw) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts)));
    $kwCount = count($parts);
}

$slugLen = $len($slug);
// Use a delimiter that doesn't conflict with '/' inside the character class.
$slugOk = ($slug !== '' && $slugLen <= 80 && preg_match('~^[a-z0-9\-_/]+$~i', $slug));

// Alt indicator: check for any <img> without alt
$altOk = true;
if ($contentSnip !== '' && preg_match_all('/<img\b[^>]*>/i', $contentSnip, $mImgs)) {
    foreach (($mImgs[0] ?? []) as $imgTag) {
        if (!preg_match('/\balt\s*=\s*([\"\']).*?\1/i', $imgTag)) { $altOk = false; break; }
    }
}

// Quality thresholds
$titleLenOk = ($titleLen >= 15 && $titleLen <= 70);
$seoTitleLenOk = ($seoTitleLen >= 15 && $seoTitleLen <= 70);
$descLenOk  = ($seoDescLen >= 50 && $seoDescLen <= 170);
$kwOk       = ($kwCount >= 3);
$imgOk      = ($imgMain !== '');

                  ?>
                  <tr>
                    <td><input type="checkbox" class="form-check-input row-check" value="<?= $id ?>" aria-label="select"></td>
                    <td><?= $id ?></td>
                    <td class="text-start">
                      <div class="fw-semibold"><?= h($title) ?></div>
                      <div class="small text-muted">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        <span><?= h($dateVal) ?></span>
                      </div>
                    </td>
                    <td class="small-slug text-start">
                      <code class="small" title="<?= h($slugFull) ?>">
                        <?= h($slugShort) ?>
                      </code>
                    </td>
                    <td>
                      <span class="badge-status <?= $statusClass ?>">
                        <?= h($label) ?>
                      </span>
                    </td>
                    <td><small><?= h($dateVal) ?></small></td>
                    <td class="text-start">
                      <div class="d-flex flex-wrap gap-1">
                        <span class="badge <?= $titleLenOk ? 'bg-success' : 'bg-warning text-dark' ?>" title="طول العنوان"><?= (int)$titleLen ?></span>
                        <span class="badge <?= $descLenOk ? 'bg-success' : 'bg-warning text-dark' ?>" title="وصف SEO"><?= (int)$seoDescLen ?></span>
                        <span class="badge <?= $kwOk ? 'bg-success' : 'bg-warning text-dark' ?>" title="عدد الكلمات المفتاحية"><?= (int)$kwCount ?></span>
                        <span class="badge <?= $slugOk ? 'bg-success' : 'bg-danger' ?>" title="Slug"><?= $slugOk ? 'OK' : '!' ?></span>
                        <span class="badge <?= $altOk ? 'bg-success' : 'bg-danger' ?>" title="Alt للصور"><?= $altOk ? 'ALT' : 'NO ALT' ?></span>
                        <span class="badge <?= $imgOk ? 'bg-success' : 'bg-danger' ?>" title="صورة"><?= $imgOk ? 'IMG' : 'NO IMG' ?></span>
                      </div>
                      <div class="small text-muted mt-1">
                        <?= $seoTitle !== '' ? 'SEO title ✓' : 'SEO title ✗' ?>
                      </div>
                    </td>
                    <td>
                      <div class="d-flex justify-content-center gap-1 flex-wrap">
                        <a href="<?= h($frontUrl) ?>" target="_blank"
                           class="btn btn-sm btn-outline-info btn-icon" title="<?= h(__('t_ac5402edac', 'عرض في الموقع')) ?>">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        </a>
                        <a href="edit.php?id=<?= $id ?>"
                           class="btn btn-sm btn-outline-primary btn-icon" title="<?= h(__('t_759fdc242e', 'تعديل')) ?>">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        </a>
                        <?php if (!$isWriter): ?>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-icon btn-duplicate-one" data-id="<?= $id ?>" title="نسخ (Duplicate)">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        </button>
                        <?php endif; ?>
                        <?php if (!$isWriter): ?>
                        <button type="button" class="btn btn-sm btn-outline-warning btn-icon btn-toggle-publish" data-id="<?= $id ?>" title="<?= h(__('t_7df6f1d7d1', 'تبديل نشر/مسودة')) ?>">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        </button>
                        <?php endif; ?>
                        <?php if (!$isWriter): ?>
                        <a href="delete.php?id=<?= $id ?>"
                           class="btn btn-sm btn-outline-danger btn-icon"
                           data-confirm=<?= json_encode(__('t_ff433bbc04', 'هل أنت متأكد من حذف هذا الخبر؟'), JSON_UNESCAPED_UNICODE) ?> title="<?= h(__('t_3b9854e1bb', 'حذف')) ?>">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        </a>
                        <?php endif; ?>
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-secondary btn-icon btn-copy-link"
                          data-url="<?= h($frontUrl) ?>"
                          title="<?= h(__('t_086a81f788', 'نسخ رابط الخبر')) ?>">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="gdy-table-footer">
        <div>
          <?php if ($total > 0): ?>
            <span><?= h(__('t_6e63a5f0af', 'عرض')) ?><?= count($items) ?><?= h(__('t_b535458712', 'من أصل')) ?><?= number_format($total) ?><?= h(__('t_8717261ce0', 'خبر.')) ?></span>
            <span class="ms-2">عدد العناصر <?= h(__('t_1787671ea7', 'في الصفحة')) ?>: <strong><?= $perPage ?></strong></span>
          <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
          <nav>
            <ul class="pagination pagination-sm justify-content-end mb-0">
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link"
                     href="?<?= h($buildQuery(['page' => $i])) ?>">
                    <?= $i ?>
                  </a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var CSRF = <?= json_encode(generate_csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  var TOTAL = <?= (int)$total ?>;
  var FILTERS = <?= json_encode([
      'q' => $search,
      'status' => $status,
      'category_id' => $categoryId,
      'from' => $dateFrom,
      'to' => $dateTo,
      'sort' => $sort,
      'in_content' => $inContent ? 1 : 0,
      'no_image' => $noImage ? 1 : 0,
      'no_desc' => $noDesc ? 1 : 0,
      'no_keywords' => $noKeywords ? 1 : 0,
      'trash' => 0,
  ], JSON_UNESCAPED_UNICODE) ?>;

  // Elements
  var checkAll = document.getElementById('checkAll');
  var rowChecks = Array.from(document.querySelectorAll('.row-check'));
  var bulkApply = document.getElementById('bulkApply');
  var bulkClear = document.getElementById('bulkClear');
  var bulkAction = document.getElementById('bulkAction');
  var bulkCategory = document.getElementById('bulkCategory');
  var selectedCount = document.getElementById('selectedCount');

  var selectAllBar = document.getElementById('selectAllResultsBar');
  var selectAllMsg = document.getElementById('selectAllMsg');
  var selectAllBtn = document.getElementById('selectAllResultsBtn');
  var clearAllBtn  = document.getElementById('clearAllResultsBtn');
  var selectAllTotal = document.getElementById('selectAllTotal');
  var bulkProgress = document.getElementById('bulkProgress');

  var modeAll = false;
  var excluded = new Set(); // only used when modeAll=true

  function getSelectedIds() {
    return rowChecks.filter(c => c.checked).map(c => parseInt(c.value || '0', 10)).filter(Boolean);
  }

  function countSelected() {
    if (modeAll) return Math.max(0, TOTAL - excluded.size);
    return getSelectedIds().length;
  }

  function showSelectAllPrompt() {
    if (!selectAllBar || !selectAllBtn || !selectAllTotal) return;
    if (TOTAL > rowChecks.length && getSelectedIds().length === rowChecks.length && rowChecks.length > 0 && !modeAll) {
      selectAllTotal.textContent = String(TOTAL);
      selectAllMsg.textContent = 'تم تحديد ' + rowChecks.length + ' عنصر في هذه الصفحة.';
      selectAllBar.classList.remove('d-none');
      selectAllBtn.classList.remove('d-none');
      clearAllBtn.classList.add('d-none');
    } else if (!modeAll) {
      selectAllBar.classList.add('d-none');
    }
  }

  function syncUI() {
    // checkAll state
    if (checkAll) {
      if (!rowChecks.length) {
        checkAll.checked = false;
        checkAll.indeterminate = false;
      } else {
        var all = rowChecks.every(c => c.checked);
        var none = rowChecks.every(c => !c.checked);
        checkAll.checked = all;
        checkAll.indeterminate = !all && !none;
      }
    }

    var c = countSelected();
    if (selectedCount) selectedCount.textContent = String(c);

    if (bulkApply) bulkApply.disabled = (c === 0 || !bulkAction || !bulkAction.value);
    if (bulkClear) bulkClear.disabled = (c === 0 && !modeAll);

    // category picker
    if (bulkCategory && bulkAction) {
      if (bulkAction.value === 'move_category') {
        bulkCategory.classList.remove('d-none');
      } else {
        bulkCategory.classList.add('d-none');
        bulkCategory.value = '0';
      }
    }

    if (modeAll && selectAllBar) {
      selectAllTotal.textContent = String(TOTAL);
      selectAllMsg.textContent = 'تم تحديد كل النتائج (' + TOTAL + '). العناصر المستثناة: ' + excluded.size;
      selectAllBar.classList.remove('d-none');
      selectAllBtn.classList.add('d-none');
      clearAllBtn.classList.remove('d-none');
    } else {
      showSelectAllPrompt();
    }
  }

  // Toggle all on page
  if (checkAll) {
    checkAll.addEventListener('change', function(){
      modeAll = false;
      excluded.clear();
      rowChecks.forEach(c => c.checked = checkAll.checked);
      syncUI();
    });
  }

  // Row check changes
  rowChecks.forEach(function(c){
    c.addEventListener('change', function(){
      if (modeAll) {
        var id = parseInt(c.value || '0', 10);
        if (!id) return;
        if (c.checked) excluded.delete(id);
        else excluded.add(id);
      }
      syncUI();
    });
  });

  if (bulkAction) bulkAction.addEventListener('change', syncUI);

  if (bulkClear) {
    bulkClear.addEventListener('click', function(){
      modeAll = false;
      excluded.clear();
      rowChecks.forEach(c => c.checked = false);
      if (bulkAction) bulkAction.value = '';
      if (bulkCategory) bulkCategory.value = '0';
      syncUI();
    });
  }

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function(){
      modeAll = true;
      excluded.clear();
      // Keep current page checked; allow user to uncheck to exclude
      rowChecks.forEach(c => c.checked = true);
      syncUI();
    });
  }
  if (clearAllBtn) {
    clearAllBtn.addEventListener('click', function(){
      modeAll = false;
      excluded.clear();
      rowChecks.forEach(c => c.checked = false);
      syncUI();
    });
  }

  async function postBulk(payload) {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.keys(payload).forEach(function(k){
      var v = payload[k];
      if (Array.isArray(v)) {
        v.forEach(function(x){ fd.append(k + '[]', String(x)); });
      } else if (typeof v === 'object' && v !== null) {
        fd.append(k, JSON.stringify(v));
      } else if (v !== undefined && v !== null) {
        fd.append(k, String(v));
      }
    });
    var res = await fetch('bulk.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    var data = {};
    try { data = await res.json(); } catch (e) {}
    if (!res.ok || !data.ok) throw new Error(data.msg || 'Bulk failed');
    return data;
  }

  async function runBulk(action, extra) {
    if (!bulkProgress) return;
    bulkProgress.textContent = '...';
    var processed = 0;
    var cursor = 0;

    while (true) {
      var payload = Object.assign({}, extra || {});
      payload.action = action;

      if (modeAll) {
        payload.scope = 'all';
        payload.filters = FILTERS;
        payload.excluded_ids = Array.from(excluded);
        payload.cursor = cursor;
      } else {
        payload.scope = 'ids';
        payload.ids = getSelectedIds();
      }

      var data = await postBulk(payload);
      processed += (data.processed || 0);
      bulkProgress.textContent = 'تمت معالجة: ' + processed;

      if (data.continue && data.next_cursor) {
        cursor = parseInt(data.next_cursor, 10) || 0;
        continue;
      }
      break;
    }
    bulkProgress.textContent = '';
  }

  if (bulkApply) {
    bulkApply.addEventListener('click', async function(){
      if (!bulkAction || !bulkAction.value) return;

      var v = bulkAction.value;
      var extra = {};

      // move category requires target
      if (v === 'move_category') {
        var cid = parseInt((bulkCategory && bulkCategory.value) ? bulkCategory.value : '0', 10);
        if (!cid) { alert('اختر التصنيف أولاً'); return; }
        extra.category_id = cid;
      }

      if (!confirm('تأكيد تنفيذ الإجراء على العناصر المحددة؟')) return;

      try {
        await runBulk(v, extra);
        location.reload();
      } catch (e) {
        alert(e.message || 'Error');
      }
    });
  }

  // Single duplicate button
  document.querySelectorAll('.btn-duplicate-one').forEach(function(btn){
    btn.addEventListener('click', async function(){
      var id = parseInt(btn.getAttribute('data-id') || '0', 10);
      if (!id) return;
      if (!confirm('نسخ هذا الخبر؟')) return;
      try {
        await postBulk({ action: 'duplicate', scope: 'ids', ids: [id] });
        location.reload();
      } catch (e) {
        alert(e.message || 'Error');
      }
    });
  });

  // Quick toggle published/draft
  document.querySelectorAll('.btn-toggle-status, .btn-toggle-publish').forEach(function(btn){
    btn.addEventListener('click', async function(){
      var id = parseInt(this.getAttribute('data-id') || '0', 10);
      if (!id) return;
      var fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('id', String(id));
      try {
        var res = await fetch('toggle.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        var data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.msg || 'failed');
        location.reload();
      } catch (e) {
        alert(e.message || 'Error');
      }
    });
  });

  syncUI();
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
