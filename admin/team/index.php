<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'team';
$pageTitle   = __('t_cd54bc26ba', 'فريق العمل');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Godyar Team] Auth error: ' . $e->getMessage());
    if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
        header('Location: ../login.php');
        exit;
    }
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database connection not available.');
}

// إنشاء جدول team_members تلقائياً إن لم يكن موجوداً
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `team_members` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(190) NOT NULL,
          `role` VARCHAR(190) DEFAULT NULL,
          `email` VARCHAR(190) DEFAULT NULL,
          `photo_url` VARCHAR(255) DEFAULT NULL,
          `bio` TEXT NULL,
          `status` ENUM('active','hidden') NOT NULL DEFAULT 'active',
          `sort_order` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_status_sort` (`status`,`sort_order`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Throwable $e) {
    @error_log('[Godyar Team AutoTable Index] ' . $e->getMessage());
}

// حذف عضو
if (isset($_GET['delete']) && ctype_digit((string)$_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        header('Location: index.php?deleted=1');
        exit;
    } catch (Throwable $e) {
        @error_log('[Godyar Team] Delete error: ' . $e->getMessage());
        header('Location: index.php?error=1');
        exit;
    }
}

// جلب الفريق مع دعم التصفية والبحث
$filterStatus = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, ['all','active','hidden'], true)) {
    $filterStatus = 'all';
}
$search = trim((string)($_GET['q'] ?? ''));

$rows = [];
try {
    $sql = "
        SELECT id, name, role, email, photo_url, status, sort_order
        FROM team_members
    ";
    $conds = [];
    $params = [];

    if ($filterStatus === 'active' || $filterStatus === 'hidden') {
        $conds[] = "status = :status";
        $params['status'] = $filterStatus;
    }

    if ($search !== '') {
        $conds[] = "(name LIKE :q OR role LIKE :q OR email LIKE :q)";
        $params['q'] = '%' . $search . '%';
    }

    if ($conds) {
        $sql .= " WHERE " . implode(" AND ", $conds);
    }

    $sql .= " ORDER BY sort_order ASC, id DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    @error_log('[Godyar Team] Fetch error: ' . $e->getMessage());
}

// Professional unified admin shell
$pageSubtitle = __('t_35f3082774', 'إدارة صفحة فريق العمل (إضافة/تعديل/حذف/ترتيب)');
$breadcrumbs = [
    __('t_3aa8578699', 'الرئيسية') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/index.php',
    __('t_cd54bc26ba', 'فريق العمل') => null,
];
$pageActionsHtml = __('t_0c546f14d9', '<a href="create.php" class="btn btn-gdy btn-gdy-primary"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إضافة عضو</a>');
require_once __DIR__ . '/../layout/app_start.php';
?>

<style>
:root{
    /* التصميم الموحد للعرض */
    --gdy-shell-max: min(880px, 100vw - 360px);
}

/* NOTE: global shell + background handled by admin-ui.css */

/* هيدر الصفحة بنفس روح الصفحات الأخرى */
.gdy-page-header{
    padding:.9rem 1.1rem .8rem;
    margin-bottom:.9rem;
    border-radius:1rem;
    background:radial-gradient(circle at top, #020617 0%, #020617 55%, #020617 100%);
    border:1px solid rgba(148,163,184,0.35);
    box-shadow:0 8px 20px rgba(15,23,42,0.85);
}
.gdy-page-header h1{
    color:#f9fafb;
}
.gdy-page-header p{
    font-size:.85rem;
}

/* كرت الجدول */
.glass-card{
    background:rgba(15,23,42,0.96);
    border-radius:16px;
    border:1px solid #1f2937;
}

/* فورم التصفية */
.admin-content form.row.g-2{
    background:rgba(15,23,42,0.95);
    border-radius:12px;
    padding:.6rem .75rem;
    border:1px solid rgba(31,41,55,0.9);
}

/* الجدول */
.table-team{
    background:#020617;
    color:#e5e7eb;
    font-size:.84rem;
}
.table-team thead{
    background:#020617;
}
.table-team thead th{
    border-bottom:1px solid #1f2937 !important;
    font-size:.76rem;
    white-space:nowrap;
}
.table-team th,
.table-team td{
    padding:.3rem .4rem;
}
.table-team tbody tr:hover{
    background:rgba(15,23,42,0.95);
}

/* الصورة + الاسم */
.team-name-cell{
    text-align:start;
    white-space:nowrap;
}
.team-name-cell img{
    margin-left:.35rem;
}

/* لضبط عرض البريد حتى لا يكسر الشاشة */
.team-email{
    max-width:180px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

/* أزرار صغيرة */
.btn-xs{
    padding:.15rem .35rem;
    font-size:.7rem;
}

/* على الشاشات الصغيرة نسمح بامتلاء العرض بالكامل */
@media (max-width: 991.98px){
    :root{
        --gdy-shell-max: 100vw;
    }
}
</style>

<!-- content starts -->

  <?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success py-2"><?= h(__('t_b9b21964e8', 'تم حفظ البيانات بنجاح.')) ?></div>
  <?php elseif (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success py-2"><?= h(__('t_d05889192d', 'تم حذف العضو بنجاح.')) ?></div>
  <?php elseif (!empty($_GET['error'])): ?>
    <div class="alert alert-danger py-2"><?= h(__('t_8390c993b9', 'حدث خطأ، الرجاء المحاولة لاحقاً.')) ?></div>
  <?php endif; ?>

  <form method="get" class="row g-2 align-items-center mb-3">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="col-sm-4">
      <input type="text"
             name="q"
             value="<?= h($search) ?>"
             class="form-control form-control-sm"
             placeholder="<?= h(__('t_86fa8c5827', 'بحث بالاسم أو المنصب أو البريد')) ?>">
    </div>
    <div class="col-sm-3">
      <select name="status" class="form-select form-select-sm">
        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>><?= h(__('t_c2193d082c', 'كل الحالات')) ?></option>
        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>><?= h(__('t_6dff5cfb62', 'نشط فقط')) ?></option>
        <option value="hidden" <?= $filterStatus === 'hidden' ? 'selected' : '' ?>><?= h(__('t_ce0c605438', 'مخفي فقط')) ?></option>
      </select>
    </div>
    <div class="col-sm-3">
      <button type="submit" class="btn btn-sm btn-outline-light">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> <?= h(__('t_1f53aa4f51', 'تطبيق التصفية')) ?>
      </button>
      <a href="index.php" class="btn btn-sm btn-outline-secondary">
        <?= h(__('t_6e666bfed1', 'تصفية جديدة')) ?>
      </a>
    </div>
  </form>

  <div class="card shadow-sm glass-card">
    <div class="card-body p-0">
      <?php if (empty($rows)): ?>
        <p class="mb-0 p-3 text-muted"><?= h(__('t_1c873a284c', 'لا يوجد أعضاء في فريق العمل حالياً.')) ?></p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle text-center table-team">
            <thead>
              <tr>
                <th>#</th>
                <th><?= h(__('t_2e8b171b46', 'الاسم')) ?></th>
                <th><?= h(__('t_c4c510267f', 'المنصب')) ?></th>
                <th><?= h(__('t_c707d7f2bb', 'البريد')) ?></th>
                <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                <th><?= h(__('t_ddda59289a', 'الترتيب')) ?></th>
                <th><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td class="team-name-cell">
                    <?php if (!empty($r['photo_url'])): ?>
                      <img src="<?= h($r['photo_url']) ?>" alt="" class="rounded-circle me-2" style="width:32px;height:32px;object-fit:cover;">
                    <?php endif; ?>
                    <?= h($r['name']) ?>
                  </td>
                  <td><?= h($r['role']) ?></td>
                  <td>
                    <small class="team-email"><?= h($r['email']) ?></small>
                  </td>
                  <td>
                    <?php if (($r['status'] ?? '') === 'active'): ?>
                      <span class="badge bg-success"><?= h(__('t_8caaf95380', 'نشط')) ?></span>
                    <?php else: ?>
                      <span class="badge bg-secondary"><?= h(__('t_a39aacaa71', 'مخفي')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= (int)$r['sort_order'] ?></td>
                  <td>
                    <a href="edit.php?id=<?= (int)$r['id'] ?>" class="btn btn-xs btn-outline-info">
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    </a>
                    <a href="index.php?delete=<?= (int)$r['id'] ?>" class="btn btn-xs btn-outline-danger"
                       data-confirm='حذف هذا العضو؟'>
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
