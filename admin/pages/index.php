<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'pages';
$pageTitle   = __('t_0046fa59f3', 'الصفحات الثابتة');

// تحقّق من تسجيل الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    } else {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user']['id']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Admin Pages] auth check error: ' . $e->getMessage());
    header('Location: ../login.php');
    exit;
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database connection not available.');
}

// ===== مدخلات الفلترة / البحث =====
$statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
$search       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$allowedStatuses = ['all', 'published', 'draft'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

// ===== إحصائيات سريعة للصفحات =====
$statsPages = [
    'total'     => 0,
    'published' => 0,
    'draft'     => 0,
];

try {
    $statsPages['total'] = (int)$pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn();
    $statsPages['published'] = (int)$pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published'")->fetchColumn();
    $statsPages['draft'] = (int)$pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'draft'")->fetchColumn();
} catch (Throwable $e) {
    @error_log('[Admin Pages] stats error: ' . $e->getMessage());
}

// ===== ترقيم الصفحات =====
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// ===== بناء شرط البحث =====
$where  = '1=1';
$params = [];

if ($statusFilter === 'published') {
    $where .= " AND status = 'published'";
} elseif ($statusFilter === 'draft') {
    $where .= " AND status = 'draft'";
}

if ($search !== '') {
    $where .= " AND (title LIKE :q OR slug LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

// عدد السجلات
$totalRows = 0;
try {
    $sqlCount = "SELECT COUNT(*) FROM pages WHERE $where";
    $stmtCount = $pdo->prepare($sqlCount);
    foreach ($params as $k => $v) {
        $stmtCount->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmtCount->execute();
    $totalRows = (int)$stmtCount->fetchColumn();
} catch (Throwable $e) {
    @error_log('[Admin Pages] count error: ' . $e->getMessage());
}

// جلب البيانات
$pages = [];
try {
    $sql = "SELECT id, title, slug, status, created_at, updated_at
            FROM pages
            WHERE $where
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[Admin Pages] list error: ' . $e->getMessage());
}

$totalPages = $perPage > 0 ? (int)ceil($totalRows / $perPage) : 1;

// ===== إعداد رابط المعاينة الأمامي (اختياري حسب بنية الموقع) =====
function front_page_url(array $row): string {
    $slug = (string)($row['slug'] ?? '');
    if ($slug === '') {
        $slug = (string)($row['id'] ?? '');
    }
    // عدّل المسار حسب بنية موقعك
    return '/page/' . rawurlencode($slug);
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<style>
:root {
  --gdy-primary: #0ea5e9;
  --gdy-primary-dark: #0369a1;
  --gdy-accent: #22c55e;
  --gdy-warning: #eab308;
  --gdy-danger: #ef4444;
  --gdy-purple: #8b5cf6;
}
.admin-content {
  background: radial-gradient(circle at top left, #020617 0%, #020617 45%, #020617 100%);
  min-height: 100vh;
  color: #e5e7eb;
}

/* هيدر الصفحة */
.gdy-page-header {
  padding: 1rem 1.25rem;
  margin-bottom: 1.25rem;
  border-radius: 1rem;
  background: linear-gradient(135deg, #0f172a 0%, #020617 60%, #0f172a 100%);
  border: 1px solid rgba(148,163,184,0.35);
  box-shadow: 0 14px 30px rgba(15,23,42,0.9);
  color: #e5e7eb;
}
.gdy-page-header h1 {
  font-weight: 700;
}
.gdy-page-header p {
  font-size: .88rem;
}

/* شريط إحصائيات الصفحات */
.gdy-pages-stats-strip {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: .75rem;
  margin-bottom: 1rem;
}
.gdy-pages-stat {
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
}
.gdy-pages-stat::before {
  content: '';
  position: absolute;
  inset: -40%;
  background: radial-gradient(circle at top right, rgba(14,165,233,0.18), transparent 60%);
  opacity: .8;
}
.gdy-pages-stat-inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: .6rem;
}
.gdy-pages-stat-icon {
  width: 32px;
  height: 32px;
  border-radius: .8rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15,23,42,0.9);
}
.gdy-pages-stat-icon i {
  font-size: .9rem;
}
.gdy-pages-stat-text {
  line-height: 1.2;
}
.gdy-pages-stat-label {
  font-size: .78rem;
  color: #9ca3af;
}
.gdy-pages-stat-value {
  font-size: 1.1rem;
  font-weight: 700;
}
.gdy-pages-stat-tag {
  position: relative;
  z-index: 1;
  font-size: .75rem;
  padding: .2rem .5rem;
  border-radius: 999px;
  background: rgba(15,23,42,0.9);
}

/* شريط الفلترة والبحث */
.gdy-filter-bar {
  margin-bottom: 1rem;
  padding: .75rem 1rem;
  border-radius: 1rem;
  background: rgba(15,23,42,0.9);
  border: 1px solid rgba(31,41,55,0.9);
}
.nav-status .nav-link {
  border-radius: 999px;
  padding: .25rem .8rem;
  font-size: .78rem;
}
.nav-status .nav-link.active {
  background: linear-gradient(135deg, #0ea5e9, #22c55e);
  border-color: transparent;
  color: #0f172a !important;
  font-weight: 600;
}

/* كارت الجدول */
.gdy-card {
  border-radius: 1rem;
  border: 1px solid rgba(148,163,184,0.35);
  background: radial-gradient(circle at top left, rgba(15,23,42,0.97), rgba(15,23,42,0.99));
  box-shadow: 0 16px 40px rgba(15,23,42,0.95);
  overflow: hidden;
}
.gdy-card-header {
  padding: .7rem 1rem;
  border-bottom: 1px solid rgba(55,65,81,0.9);
  background: linear-gradient(135deg, #020617, #0b1120);
  color: #e5e7eb;
}

/* جدول الصفحات */
.table-pages {
  color: #e5e7eb;
  font-size: .85rem;
}
.table-pages thead {
  background: rgba(15,23,42,1);
}
.table-pages thead th {
  border-bottom: 1px solid rgba(55,65,81,0.9) !important;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .05em;
}
.table-pages tbody tr {
  transition: background-color .2s ease, transform .1s.ease;
}
.table-pages tbody tr:hover {
  background: rgba(15,23,42,0.95);
  transform: translateY(-1px);
}
.table-pages tbody td {
  vertical-align: middle;
}

/* شارة الحالة */
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

/* أزرار صغيرة دائرية */
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
}

/* منع التمرير الأفقي + حاوية موحدة للمحتوى */
html, body {
  overflow-x: hidden;
}

.admin-content.container-fluid {
  padding-top: 1.2rem;
  padding-bottom: 1.4rem;
}

.gdy-layout-wrap {
  width: 100%;
  max-width: 1180px;
  margin-right: .75rem;
  margin-left: auto;
}

@media (max-width: 991.98px) {
  .gdy-layout-wrap {
    margin-right: .5rem;
    margin-left: .5rem;
    max-width: 100%;
  }
}
</style>

<div class="admin-content container-fluid py-4">
  <div class="gdy-layout-wrap">
  <div class="gdy-page-header d-flex justify-content-between align-items-center.mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 text-white mb-1"><?= h(__('t_0046fa59f3', 'الصفحات الثابتة')) ?></h1>
      <p class="text-muted mb-0 small">
        <?= h(__('t_89b58b47e8', 'إدارة صفحات مثل من نحن، اتصل بنا، الشروط، الخصوصية...')) ?>
      </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <div class="badge bg-primary-subtle text-info-emphasis border.border-primary-subtle small px-3 py-2">
        <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        <span>إجمالي: <?= number_format($statsPages['total']) ?> صفحة</span>
      </div>
      <a href="create.php" class="btn btn-sm btn-primary">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_2a1b455803', 'صفحة جديدة')) ?>
      </a>
    </div>
  </div>

  <!-- إحصائيات سريعة للصفحات -->
  <div class="gdy-pages-stats-strip">
    <div class="gdy-pages-stat">
      <div class="gdy-pages-stat-inner">
        <div class="gdy-pages-stat-icon text-info">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        </div>
        <div class="gdy-pages-stat-text">
          <div class="gdy-pages-stat-label"><?= h(__('t_eb1400e221', 'إجمالي الصفحات')) ?></div>
          <div class="gdy-pages-stat-value"><?= number_format($statsPages['total']) ?></div>
        </div>
      </div>
      <div class="gdy-pages-stat-tag"><?= h(__('t_de3c9734aa', 'كل الصفحات')) ?></div>
    </div>

    <div class="gdy-pages-stat">
      <div class="gdy-pages-stat-inner">
        <div class="gdy-pages-stat-icon text-success">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        </div>
        <div class="gdy-pages-stat-text">
          <div class="gdy-pages-stat-label"><?= h(__('t_da9181305e', 'صفحات منشورة')) ?></div>
          <div class="gdy-pages-stat-value"><?= number_format($statsPages['published']) ?></div>
        </div>
      </div>
      <div class="gdy-pages-stat-tag"><?= h(__('t_d4737d83ce', 'ظاهرة في الموقع')) ?></div>
    </div>

    <div class="gdy-pages-stat">
      <div class="gdy-pages-stat-inner">
        <div class="gdy-pages-stat-icon text-warning">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        </div>
        <div class="gdy-pages-stat-text">
          <div class="gdy-pages-stat-label"><?= h(__('t_7b17ab78a5', 'مسودات')) ?></div>
          <div class="gdy-pages-stat-value"><?= number_format($statsPages['draft']) ?></div>
        </div>
      </div>
      <div class="gdy-pages-stat-tag"><?= h(__('t_5cde709cef', 'تحت التجهيز')) ?></div>
    </div>
  </div>

  <?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success py-2"><?= h(__('t_ec9c600345', 'تم حفظ الصفحة بنجاح.')) ?></div>
  <?php elseif (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success py-2"><?= h(__('t_8e3da1b7c5', 'تم حذف الصفحة بنجاح.')) ?></div>
  <?php elseif (!empty($_GET['error'])): ?>
    <div class="alert alert-danger py-2"><?= h(__('t_8390c993b9', 'حدث خطأ، الرجاء المحاولة لاحقاً.')) ?></div>
  <?php endif; ?>

  <!-- شريط فلترة الحالة + البحث -->
  <div class="gdy-filter-bar mb-3">
    <form class="row g-2 align-items-center" method="get" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="col-md-4 col-sm-12">
        <input
          type="text"
          name="q"
          value="<?= h($search) ?>"
          class="form-control.form-control-sm bg-dark border-secondary text-light"
          placeholder="<?= h(__('t_6263c20317', 'بحث في عنوان الصفحة أو الـ slug...')) ?>"
        >
      </div>
      <div class="col-md-5 col-sm-12">
        <ul class="nav nav-pills nav-status">
          <?php
            $baseLink = '?q=' . urlencode($search);
          ?>
          <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'all' ? 'active' : '' ?>"
               href="<?= $baseLink ?>&status=all">
              <?= h(__('t_6d08f19681', 'الكل')) ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'published' ? 'active' : '' ?>"
               href="<?= $baseLink ?>&status=published">
              <?= h(__('t_c67d973434', 'منشورة')) ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'draft' ? 'active' : '' ?>"
               href="<?= $baseLink ?>&status=draft">
              <?= h(__('t_7b17ab78a5', 'مسودات')) ?>
            </a>
          </li>
        </ul>
      </div>
      <div class="col-md-3 col-sm-12 text-md-end text-start small.text-muted">
        <span><?= h(__('t_c6a5fe1902', 'عدد النتائج:')) ?> <strong><?= number_format($totalRows) ?></strong></span>
      </div>
    </form>
  </div>

  <!-- كارت الجدول -->
  <div class="card gdy-card">
    <div class="gdy-card-header d-flex justify-content-between align-items-center">
      <span><?= h(__('t_d60941d5b3', 'قائمة الصفحات')) ?></span>
      <small class="text-muted"><?= h(__('t_2f56b62be0', 'إدارة الصفحات الثابتة للموقع')) ?></small>
    </div>
    <div class="card-body p-0">
      <?php if (empty($pages)): ?>
        <p class="p-3 mb-0 text-muted"><?= h(__('t_5c2345720e', 'لا توجد صفحات بعد. ابدأ بإنشاء صفحة جديدة.')) ?></p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover.mb-0 align-middle text-center table-pages">
            <thead>
              <tr>
                <th style="width: 60px;">#</th>
                <th class="text-start"><?= h(__('t_6dc6588082', 'العنوان')) ?></th>
                <th>Slug</th>
                <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                <th><?= h(__('t_d4ef3a02e7', 'تاريخ الإنشاء')) ?></th>
                <th><?= h(__('t_4041e7805b', 'آخر تحديث')) ?></th>
                <th style="width: 210px;"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pages as $row): ?>
                <?php
                  $id    = (int)$row['id'];
                  $title = (string)($row['title'] ?? '');
                  $slug  = (string)($row['slug'] ?? '');
                  $status = (string)($row['status'] ?? 'draft');
                  $createdAt = (string)($row['created_at'] ?? '');
                  $updatedAt = (string)($row['updated_at'] ?? '');
                  $dateShown = $updatedAt ?: $createdAt;

                  $label = $status === 'published' ? __('t_c67d973434', 'منشورة') : __('t_9071af8f2d', 'مسودة');
                  $statusClass = $status === 'published' ? 'published' : 'draft';

                  $frontUrl = front_page_url($row);
                ?>
                <tr>
                  <td><?= $id ?></td>
                  <td class="text-start">
                    <div class="fw-semibold"><?= h($title) ?></div>
                    <div class="small text-muted">
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      <span><?= h($dateShown) ?></span>
                    </div>
                  </td>
                  <td>
                    <code class="small"><?= h($slug) ?></code>
                  </td>
                  <td>
                    <span class="badge-status <?= $statusClass ?>">
                      <?= h($label) ?>
                    </span>
                  </td>
                  <td><small><?= h($createdAt) ?></small></td>
                  <td><small><?= h($updatedAt) ?></small></td>
                  <td>
                    <div class="d-flex justify-content-center gap-1 flex-wrap">
                      <a href="<?= h($frontUrl) ?>" target="_blank"
                         class="btn btn-sm btn-outline-info btn-icon"
                         title="<?= h(__('t_ac5402edac', 'عرض في الموقع')) ?>">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      </a>
                      <a href="edit.php?id=<?= $id ?>"
                         class="btn btn-sm btn-outline-primary btn-icon"
                         title="<?= h(__('t_759fdc242e', 'تعديل')) ?>">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      </a>
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary btn-icon btn-copy-slug"
                        data-slug="<?= h($slug) ?>"
                        title="<?= h(__('t_2c7a36f6dc', 'نسخ الـ Slug')) ?>">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      </button>
                      <a href="delete.php?id=<?= $id ?>"
                         class="btn btn-sm btn-outline-danger btn-icon"
                         data-confirm='هل أنت متأكد من حذف هذه الصفحة؟'
                         title="<?= h(__('t_3b9854e1bb', 'حذف')) ?>">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                      </a>
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
        <?php if ($totalRows > 0): ?>
          <span>عرض <?= count($pages) ?> من أصل <?= number_format($totalRows) ?> صفحة.</span>
        <?php endif; ?>
      </div>
      <?php if ($totalPages > 1): ?>
        <nav>
          <ul class="pagination.pagination-sm justify-content-end mb-0">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link"
                   href="?page=<?= $i ?>&amp;q=<?= urlencode($search) ?>&amp;status=<?= urlencode($statusFilter) ?>">
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
  // زر نسخ الـ slug
  document.querySelectorAll('.btn-copy-slug').forEach(function(btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var slug = this.getAttribute('data-slug') || '';
      if (!slug) return;

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(slug).then(function () {
          showCopyToast('تم نسخ الـ Slug بنجاح');
        }).catch(function () {
          alert('تمت محاولة النسخ، لكن قد لا يكون مدعوماً في هذا المتصفح.');
        });
      } else {
        var tmp = document.createElement('input');
        tmp.value = slug;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        alert('تم نسخ الـ Slug إلى الحافظة');
      }
    });
  });

  function showCopyToast(msg) {
    var toast = document.createElement('div');
    toast.textContent = msg;
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.left = '50%';
    toast.style.transform = 'translateX(-50%)';
    toast.style.background = 'rgba(15,23,42,0.96)';
    toast.style.color = '#e5e7eb';
    toast.style.padding = '8px 16px';
    toast.style.borderRadius = '999px';
    toast.style.fontSize = '13px';
    toast.style.zIndex = '9999';
    toast.style.border = '1px solid rgba(148,163,184,0.5)';
    document.body.appendChild(toast);
    setTimeout(function () {
      toast.remove();
    }, 2000);
  }
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
