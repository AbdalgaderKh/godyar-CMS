<?php declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/elections/index.php — إدارة الانتخابات (قائمة + إظهار/إخفاء/أرشفة)

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_elections_lib.php';

// محاولة تحميل نظام الصلاحيات إن وجد
$authFile = __DIR__ . '/../../includes/auth.php';
if (file_exists($authFile)) {
    require_once $authFile;
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * حماية لوحة التحكم:
 * - لو يوجد كلاس Godyar\Auth نستعمله
 * - لو لا، نرجع إلى الجلسات (admin_id أو user_role)
 */
$authorized = false;

if (class_exists('\Godyar\Auth')) {
    if (method_exists('\Godyar\Auth', 'check') && \Godyar\Auth::check()) {
        if (method_exists('\Godyar\Auth', 'userIsAdmin')) {
            $authorized = \Godyar\Auth::userIsAdmin();
        } elseif (method_exists('\Godyar\Auth', 'user')) {
            $user = \Godyar\Auth::user();
            if (is_array($user) && isset($user['role']) &&
                in_array($user['role'], ['admin', 'superadmin'], true)
            ) {
                $authorized = true;
            } else {
                $authorized = true;
            }
        } else {
            $authorized = true;
        }
    }
} else {
    if (!empty($_SESSION['admin_id'])) {
        $authorized = true;
    } elseif (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $authorized = true;
    }
}

if (!$authorized) {
    $loginUrl = function_exists('base_url')
        ? base_url('/admin/login')
        : '/admin/login';

    header('Location: ' . $loginUrl);
    exit;
}

// =======================
// 1) الإعدادات العامة للعرض + الفلترة
// =======================
/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    exit(__('t_ed202270ee', 'قاعدة البيانات غير متاحة حالياً.'));
}

// إنشاء/ترقية جداول الانتخابات (مرة واحدة عند الحاجة)
gdy_elections_ensure_schema($pdo);

// =======================
// 2) إحصائيات سريعة (للواجهة)
// =======================
$electionStats = [
    'total'    => 0,
    'visible'  => 0,
    'hidden'   => 0,
    'archived' => 0,
];
try {
    $stmt = $pdo->query("SELECT COALESCE(status,'') AS status, COUNT(*) AS c FROM elections GROUP BY COALESCE(status,'')");
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($rows as $r) {
        $st = (string)($r['status'] ?? '');
        $c  = (int)($r['c'] ?? 0);
        $electionStats['total'] += $c;
        if (isset($electionStats[$st])) {
            $electionStats[$st] += $c;
        }
    }
} catch (Throwable $e) {
    // تجاهل إن فشل الاستعلام (لا يؤثر على الصفحة)
}


// فلاتر
$statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : '';
$search       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// صفحة حالية + عدد في الصفحة
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

// رسائل فورية
$flashSuccess = '';
$flashError   = '';

// =======================
// 2) معالجة POST (تغيير حالة / حذف / bulk)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
        $flashError = __('t_0f296c4fe0', 'فشل التحقق الأمني، يرجى إعادة المحاولة.');
    } else {
        if ($action === 'set_status') {
            $id     = (int)($_POST['id'] ?? 0);
            $newSt  = (string)($_POST['new_status'] ?? '');

            if ($id > 0 && in_array($newSt, ['visible','hidden','archived'], true)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE elections
                           SET status = :st,
                               updated_at = NOW()
                         WHERE id = :id
                    ");
                    $stmt->execute([':st' => $newSt, ':id' => $id]);
                    $flashSuccess = __('t_4770efc5dd', 'تم تحديث حالة التغطية بنجاح.');
                } catch (Throwable $e) {
                    @error_log('[elections index] set_status error: ' . $e->getMessage());
                    $flashError = __('t_5b5f2fdf6e', 'حدث خطأ أثناء تحديث الحالة.');
                }
            } else {
                $flashError = __('t_bd129a6a7d', 'طلب غير صالح.');
            }

        } elseif ($action === 'delete') {

            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM elections WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $flashSuccess = __('t_13538cf967', 'تم حذف التغطية بنجاح.');
                } catch (Throwable $e) {
                    @error_log('[elections index] delete error: ' . $e->getMessage());
                    $flashError = __('t_470e695a0c', 'تعذر حذف التغطية، ربما مرتبطة ببيانات أخرى.');
                }
            }

        } elseif ($action === 'bulk_status') {

            $newSt  = (string)($_POST['new_status'] ?? '');
            $ids    = $_POST['ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            $idList = array_map('intval', $ids);
            $idList = array_filter($idList, static fn($v) => $v > 0);

            if ($idList && in_array($newSt, ['visible','hidden','archived'], true)) {
                try {
                    $in  = implode(',', array_fill(0, count($idList), '?'));
                    $sql = "UPDATE elections SET status = ?, updated_at = NOW() WHERE id IN ($in)";
                    $stmt = $pdo->prepare($sql);
                    $params = array_merge([$newSt], $idList);
                    $stmt->execute($params);
                    $flashSuccess = __('t_0eeaa2fe67', 'تم تحديث حالة التغطيات المحددة بنجاح.');
                } catch (Throwable $e) {
                    @error_log('[elections index] bulk_status error: ' . $e->getMessage());
                    $flashError = __('t_22ed99fb09', 'تعذر تنفيذ عملية التحديث الجماعي.');
                }
            }
        }
    }
}

// =======================
// 3) بناء شرط WHERE حسب الفلاتر
// =======================
$whereParts = [];
$params     = [];

if (in_array($statusFilter, ['visible','hidden','archived'], true)) {
    $whereParts[]      = 'status = :status';
    $params[':status'] = $statusFilter;
}

if ($search !== '') {
    $whereParts[] = '(title LIKE :q OR slug LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

// =======================
// 4) إجمالي السجلات + جلب الصفحة
// =======================
$totalRows = 0;
try {
    $sqlCnt = "SELECT COUNT(*) FROM elections {$whereSql}";
    $stmt   = $pdo->prepare($sqlCnt);
    $stmt->execute($params);
    $totalRows = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    @error_log('[elections index] count error: ' . $e->getMessage());
    $totalRows = 0;
}

$items = [];
try {
    $sql = "
        SELECT
            id,
            title,
            slug,
            status,
            total_seats,
            majority_seats,
            created_at,
            updated_at,
            description
        FROM elections
        {$whereSql}
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @error_log('[elections index] fetch list error: ' . $e->getMessage());
    $items = [];
    $totalRows = 0;
}

$totalPages  = max(1, (int)ceil($totalRows / $perPage));
$currentPage = $page;

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$currentPageSlug = 'elections';


// Professional unified admin shell
$currentPage = 'elections';
$pageTitle   = __('t_afe1c16914', 'إدارة الانتخابات');
$pageSubtitle = __('t_cba38f3708', 'إدارة التغطيات الانتخابية، المناطق، الأحزاب، والنتائج');
$adminBase = (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin';
$breadcrumbs = [
  __('t_3aa8578699', 'الرئيسية') => $adminBase . '/index.php',
  __('t_b9af904113', 'الانتخابات') => null,
];
$pageActionsHtml = __('t_c0688b58ad', '<a href="create.php" class="btn btn-gdy btn-gdy-primary"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إضافة تغطية</a>');
require_once __DIR__ . '/../layout/app_start.php';

$csrf = generate_csrf_token();
?>

<div class="gdy-card mb-3">
  <div class="gdy-card-header d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
    <div class="d-flex flex-wrap gap-2">
      <?php
        $totalC = (int)($electionStats['total'] ?? 0);
        $visC   = (int)($electionStats['visible'] ?? 0);
        $hidC   = (int)($electionStats['hidden'] ?? 0);
        $arcC   = (int)($electionStats['archived'] ?? 0);
      ?>
      <span class="badge rounded-pill bg-secondary">الكل: <?= (int)$totalC ?></span>
      <span class="badge rounded-pill bg-success">ظاهر: <?= (int)$visC ?></span>
      <span class="badge rounded-pill bg-warning text-dark">مخفي: <?= (int)$hidC ?></span>
      <span class="badge rounded-pill bg-danger">مؤرشف: <?= (int)$arcC ?></span>
    </div>

    <form class="row g-2 align-items-end w-100 w-lg-auto" method="get" action="">
      <div class="col-12 col-md-5">
        <label class="form-label text-muted mb-1"><?= h(__('t_ab79fc1485', 'بحث')) ?></label>
        <input type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="<?= h(__('t_ab232dbd00', 'عنوان / slug')) ?>">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label text-muted mb-1"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
        <select class="form-select" name="status">
          <option value="" <?= $statusFilter===''?'selected':'' ?>><?= h(__('t_6d08f19681', 'الكل')) ?></option>
          <option value="visible" <?= $statusFilter==='visible'?'selected':'' ?>><?= h(__('t_4f619e05c6', 'ظاهر')) ?></option>
          <option value="hidden" <?= $statusFilter==='hidden'?'selected':'' ?>><?= h(__('t_a39aacaa71', 'مخفي')) ?></option>
          <option value="archived" <?= $statusFilter==='archived'?'selected':'' ?>><?= h(__('t_2e67aea8ca', 'مؤرشف')) ?></option>
        </select>
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button class="btn btn-gdy btn-gdy-primary w-100" type="submit"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> <?= h(__('t_abe3151c63', 'تطبيق')) ?></button>
        <a class="btn btn-gdy btn-gdy-ghost w-100" href="index.php"><?= h(__('t_ec2ce8be93', 'مسح')) ?></a>
      </div>
    </form>
  </div>

  <div class="gdy-card-body">
    <?php if ($flashSuccess): ?>
      <div class="alert alert-success"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger"><?= h($flashError) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th style="width:72px">#</th>
            <th><?= h(__('t_6dc6588082', 'العنوان')) ?></th>
            <th style="width:140px"><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
            <th style="width:160px"><?= h(__('t_91bfba79c3', 'المقاعد')) ?></th>
            <th style="width:190px"><?= h(__('t_4041e7805b', 'آخر تحديث')) ?></th>
            <th style="width:280px" class="text-end"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4"><?= h(__('t_760b0f31b8', 'لا توجد بيانات مطابقة للبحث.')) ?></td></tr>
          <?php else: ?>
            <?php foreach ($items as $it): 
              $st = (string)($it['status'] ?? '');
              $badge = 'bg-secondary';
              $label = __('t_cd09c30d57', 'غير محدد');
              if ($st === 'visible') { $badge = 'bg-success'; $label=__('t_4f619e05c6', 'ظاهر'); }
              elseif ($st === 'hidden') { $badge = 'bg-warning text-dark'; $label=__('t_a39aacaa71', 'مخفي'); }
              elseif ($st === 'archived') { $badge = 'bg-danger'; $label=__('t_2e67aea8ca', 'مؤرشف'); }

              $seats = (int)($it['total_seats'] ?? 0);
              $maj   = (int)($it['majority_seats'] ?? 0);
              $updated = $it['updated_at'] ?: $it['created_at'];
            ?>
            <tr>
              <td><?= (int)$it['id'] ?></td>
              <td>
                <div class="fw-bold"><?= h($it['title'] ?? '') ?></div>
                <div class="text-muted small"><?= h($it['slug'] ?? '') ?></div>
                <?php if (!empty($it['description'])): ?>
                  <div class="text-muted small mt-1" style="max-width: 60ch;"><?= h(mb_strimwidth((string)$it['description'], 0, 160, '…', 'UTF-8')) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $badge ?> rounded-pill px-3 py-2"><?= h($label) ?></span></td>
              <td class="text-muted">
                <div><?= h(__('t_35c497cbae', 'إجمالي:')) ?> <span class="fw-semibold text-light"><?= $seats ?></span></div>
                <div><?= h(__('t_c43fb95c54', 'أغلبية:')) ?> <span class="fw-semibold text-light"><?= $maj ?></span></div>
              </td>
              <td class="text-muted small"><?= h((string)$updated) ?></td>
              <td class="text-end">
                <div class="d-flex flex-wrap justify-content-end gap-2">
                  <a class="btn btn-sm btn-gdy btn-gdy-ghost" href="edit.php?id=<?= (int)$it['id'] ?>"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_759fdc242e', 'تعديل')) ?></a>
                  <a class="btn btn-sm btn-gdy btn-gdy-ghost" href="regions.php?election_id=<?= (int)$it['id'] ?>"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_745a7a18bc', 'مناطق')) ?></a>
                  <a class="btn btn-sm btn-gdy btn-gdy-ghost" href="parties.php?election_id=<?= (int)$it['id'] ?>"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_604826c00c', 'أحزاب')) ?></a>
                  <a class="btn btn-sm btn-gdy btn-gdy-primary" href="results.php?election_id=<?= (int)$it['id'] ?>"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_981d67a249', 'نتائج')) ?></a>

                  <div class="dropdown">
                    <button class="btn btn-sm btn-gdy btn-gdy-ghost dropdown-toggle" type="button" data-bs-toggle="dropdown">
                      <?= h(__('t_1253eb5642', 'الحالة')) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark">
                      <li>
                        <form method="post" action="" class="px-3 py-1">
                          <input type="hidden" name="_csrf_token" value="<?= h($csrf) ?>">
                          <input type="hidden" name="action" value="set_status">
                          <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                          <input type="hidden" name="new_status" value="visible">
                          <button class="btn btn-sm btn-link text-success p-0" type="submit"><?= h(__('t_d7c6255e5b', 'جعلها ظاهرة')) ?></button>
                        </form>
                      </li>
                      <li>
                        <form method="post" action="" class="px-3 py-1">
                          <input type="hidden" name="_csrf_token" value="<?= h($csrf) ?>">
                          <input type="hidden" name="action" value="set_status">
                          <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                          <input type="hidden" name="new_status" value="hidden">
                          <button class="btn btn-sm btn-link text-warning p-0" type="submit"><?= h(__('t_cdd3df9b53', 'إخفاء')) ?></button>
                        </form>
                      </li>
                      <li>
                        <form method="post" action="" class="px-3 py-1">
                          <input type="hidden" name="_csrf_token" value="<?= h($csrf) ?>">
                          <input type="hidden" name="action" value="set_status">
                          <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                          <input type="hidden" name="new_status" value="archived">
                          <button class="btn btn-sm btn-link text-danger p-0" type="submit"><?= h(__('t_44e09190ab', 'أرشفة')) ?></button>
                        </form>
                      </li>
                    </ul>
                  </div>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="gdy-card-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
    <div class="text-muted small">
      <?= h(__('t_93313790b8', 'إجمالي السجلات:')) ?> <span class="text-light fw-semibold"><?= (int)$totalRows ?></span> <?= h(__('t_f67df4e2c5', '— الصفحة')) ?> <span class="text-light fw-semibold"><?= (int)$currentPage ?></span> <?= h(__('t_99fb92edad', 'من')) ?> <span class="text-light fw-semibold"><?= (int)$totalPages ?></span>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav aria-label="pagination">
        <ul class="pagination pagination-sm mb-0">
          <?php
            $mk = function(int $p) use ($search, $statusFilter): string {
              $q = [];
              if ($search !== '') $q['q'] = $search;
              if ($statusFilter !== '') $q['status'] = $statusFilter;
              $q['page'] = $p;
              return 'index.php?' . http_build_query($q);
            };
            $prev = max(1, $currentPage - 1);
            $next = min($totalPages, $currentPage + 1);
          ?>
          <li class="page-item <?= $currentPage<=1?'disabled':'' ?>"><a class="page-link" href="<?= h($mk($prev)) ?>"><?= h(__('t_650bd5b508', 'السابق')) ?></a></li>
          <?php
            $start = max(1, $currentPage - 2);
            $end   = min($totalPages, $currentPage + 2);
            if ($start > 1) {
              echo '<li class="page-item"><a class="page-link" href="'.h($mk(1)).'">1</a></li>';
              if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            for ($p=$start; $p<=$end; $p++){
              $active = $p===$currentPage ? 'active' : '';
              echo '<li class="page-item '.$active.'"><a class="page-link" href="'.h($mk($p)).'">'.$p.'</a></li>';
            }
            if ($end < $totalPages) {
              if ($end < $totalPages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
              echo '<li class="page-item"><a class="page-link" href="'.h($mk($totalPages)).'">'.$totalPages.'</a></li>';
            }
          ?>
          <li class="page-item <?= $currentPage>=$totalPages?'disabled':'' ?>"><a class="page-link" href="<?= h($mk($next)) ?>"><?= h(__('t_8435afd9e8', 'التالي')) ?></a></li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
