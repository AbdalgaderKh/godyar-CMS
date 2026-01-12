<?php
declare(strict_types=1);

/**
 * admin/comments/index.php
 * إدارة التعليقات: اعتماد/رفض/حذف + فلترة (قيد المراجعة/معتمد/مرفوض)
 */

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

$currentPage = 'comments';
$pageTitle   = __('t_422df4da8b', 'التعليقات');

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function gdy_spam_score(string $comment, string $name = '', string $email = ''): int {
    $s = 0;
    $txt = strtolower($comment);
    $links = substr_count($txt, 'http') + substr_count($txt, 'www.');
    if ($links > 0) $s += min(60, $links * 20);
    if (preg_match('/\b(buy|cheap|viagra|casino|loan|bitcoin|forex)\b/i', $comment)) $s += 30;
    if (preg_match('/[A-Z]{12,}/', $comment)) $s += 10;
    if (preg_match('/(.)\1{7,}/u', $comment)) $s += 15; // repeated char
    if (mb_strlen($comment, 'UTF-8') < 8) $s += 10;
    if ($email !== '' && preg_match('/@(mail\.ru|qq\.com|yandex\.ru)$/i', $email)) $s += 10;
    if ($name !== '' && preg_match('/^[0-9\W_]+$/u', $name)) $s += 10;
    return max(0, min(100, $s));
}

}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database connection not available.');
}

// helpers (CSRF / flash) إن وجدت في admin_layout.php
if (file_exists(__DIR__ . '/../includes/admin_layout.php')) {
    require_once __DIR__ . '/../includes/admin_layout.php';
}
$csrf = function_exists('admin_generate_csrf_token') ? admin_generate_csrf_token() : '';
$flash = null;

// تحقق وجود جدول comments
$tableExists = false;
try {
    $qTbl = $pdo->query("SHOW TABLES LIKE 'comments'");
    $tableExists = $qTbl ? (bool)$qTbl->fetchColumn() : false;
} catch (Throwable $e) {
    $tableExists = false;
}

if (!$tableExists) {
    $pageHead = '';
    $pageScripts = '';
    require_once __DIR__ . '/../layout/header.php';
    require_once __DIR__ . '/../layout/sidebar.php';
    ?>
    <main class="gdy-admin-page">
      <div class="alert alert-warning">
        <b><?= h(__('t_558f159797', 'جدول التعليقات غير موجود.')) ?></b>
        <div class="mt-2"><?= h(__('t_a33e702b5e', 'نفّذ ملف SQL التالي مرة واحدة في phpMyAdmin:')) ?></div>
        <div class="mt-2">
          <code>/admin/db/migrations/2025_12_13_comments.sql</code>
        </div>
      </div>
    </main>
    <?php
    require_once __DIR__ . '/../layout/footer.php';
    exit;
}

$role = (string)($_SESSION['user']['role'] ?? 'guest');
if (in_array($role, ['writer','author'], true)) {
    http_response_code(403);
    die('Forbidden');
}

// معالجة إجراءات (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $tokenOk = true;
    if ($csrf !== '') {
        $tokenOk = function_exists('admin_verify_csrf_token') ? admin_verify_csrf_token((string)($_POST['csrf'] ?? '')) : true;
    }
    if (!$tokenOk) {
        $flash = ['type' => 'danger', 'msg' => __('t_7b9721e0da', 'رمز الحماية غير صالح. حدّث الصفحة وحاول مرة أخرى.')];
    } else {
        $id = (int)$_POST['id'];
        $action = (string)$_POST['action'];

        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE comments SET status='approved' WHERE id=:id");
                $stmt->execute([':id' => $id]);
                $flash = ['type' => 'success', 'msg' => __('t_d1fc0654bc', 'تم اعتماد التعليق.')];
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE comments SET status='rejected' WHERE id=:id");
                $stmt->execute([':id' => $id]);
                $flash = ['type' => 'warning', 'msg' => __('t_828a510a3e', 'تم رفض التعليق.')];
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM comments WHERE id=:id");
                $stmt->execute([':id' => $id]);
                $flash = ['type' => 'secondary', 'msg' => __('t_17a1b66879', 'تم حذف التعليق.')];
            }
        } catch (Throwable $e) {
            @error_log('[comments] action error: ' . $e->getMessage());
            $flash = ['type' => 'danger', 'msg' => __('t_82d19eb55e', 'تعذر تنفيذ العملية.')];
        }
    }
}

// فلترة
$status = trim((string)($_GET['status'] ?? 'pending'));
if (!in_array($status, ['pending','approved','rejected','all'], true)) {
    $status = 'pending';
}
$q = trim((string)($_GET['q'] ?? ''));

$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// إحصاءات سريعة
$counts = ['pending'=>0,'approved'=>0,'rejected'=>0,'all'=>0];
try {
    $rows = $pdo->query("SELECT status, COUNT(*) c FROM comments GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
        $st = (string)($r['status'] ?? '');
        $counts[$st] = (int)($r['c'] ?? 0);
    }
    $counts['all'] = $counts['pending'] + $counts['approved'] + $counts['rejected'];
} catch (Throwable $e) {}

// تحميل قائمة التعليقات
$where = [];
$params = [];

if ($status !== 'all') {
    $where[] = "c.status = :status";
    $params[':status'] = $status;
}
if ($q !== '') {
    $where[] = "(c.name LIKE :q OR c.email LIKE :q OR c.body LIKE :q OR n.title LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments c LEFT JOIN news n ON n.id=c.news_id $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    @error_log('[comments] count error: ' . $e->getMessage());
}

$items = [];
try {
    $sql = "
      SELECT c.*, n.title AS news_title, n.slug AS news_slug
      FROM comments c
      LEFT JOIN news n ON n.id = c.news_id
      $whereSql
      ORDER BY c.created_at DESC, c.id DESC
      LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[comments] list error: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($total / $perPage));

// ✅ Head
$pageHead = '';
$pageScripts = '';
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<main class="gdy-admin-page">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h1 class="h5 m-0"><?= h(__('t_422df4da8b', 'التعليقات')) ?></h1>
      <div class="text-muted small mt-1"><?= h(__('t_c7b339b071', 'إدارة التعليقات واعتمادها أو رفضها قبل النشر.')) ?></div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-sm <?= $status==='pending'?'btn-primary':'btn-outline-primary' ?>" href="?status=pending">قيد المراجعة (<?= (int)$counts['pending'] ?>)</a>
      <a class="btn btn-sm <?= $status==='approved'?'btn-success':'btn-outline-success' ?>" href="?status=approved">معتمد (<?= (int)$counts['approved'] ?>)</a>
      <a class="btn btn-sm <?= $status==='rejected'?'btn-danger':'btn-outline-danger' ?>" href="?status=rejected">مرفوض (<?= (int)$counts['rejected'] ?>)</a>
      <a class="btn btn-sm <?= $status==='all'?'btn-dark':'btn-outline-dark' ?>" href="?status=all">الكل (<?= (int)$counts['all'] ?>)</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <form class="row g-2 align-items-center mb-3" method="get">
    <input type="hidden" name="status" value="<?= h($status) ?>">
    <div class="col-12 col-md-6">
      <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>" placeholder="<?= h(__('t_c5d269b34b', 'بحث بالاسم/الإيميل/النص/عنوان الخبر')) ?>">
    </div>
    <div class="col-12 col-md-auto">
      <button class="btn btn-sm btn-outline-secondary"><?= h(__('t_ab79fc1485', 'بحث')) ?></button>
      <a class="btn btn-sm btn-outline-dark" href="?status=<?= h($status) ?>"><?= h(__('t_ec2ce8be93', 'مسح')) ?></a>
    </div>
  </form>

  <?php
    // Saved Filters UI
    require_once __DIR__ . '/../includes/saved_filters_ui.php';
          echo gdy_saved_filters_ui('comments');
?>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:90px;"><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
            <th><?= h(__('t_533ba29a76', 'التعليق')) ?></th>
            <th style="width:220px;"><?= h(__('t_213a03802a', 'الخبر')) ?></th>
            <th style="width:160px;"><?= h(__('t_901675875a', 'المرسل')) ?></th>
            <th style="width:140px;"><?= h(__('t_8456f22b47', 'التاريخ')) ?></th>
            <th style="width:90px;"><?= h(__("t_admin_spam", "سبام")) ?></th>
            <th style="width:220px;"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$items): ?>
            <tr><td colspan="6" class="text-center text-muted py-4"><?= h(__('t_b4e385fa98', 'لا توجد تعليقات.')) ?></td></tr>
          <?php endif; ?>

          <?php foreach ($items as $c): ?>
            <?php
              $st = (string)($c['status'] ?? 'pending');
              $badge = $st==='approved'?'success':($st==='rejected'?'danger':'warning');
              $newsTitle = (string)($c['news_title'] ?? '—');
              $newsUrl = '';
              $slug = (string)($c['news_slug'] ?? '');
              if ($slug !== '') {
                  $newsUrl = '../..' . '/news/id/' . (int)$id;
              } else {
                  $nid = (int)($c['news_id'] ?? 0);
                  if ($nid > 0) $newsUrl = '../..' . '/news/id/' . $nid;
              }
            ?>
            <tr>
              <td><span class="badge bg-<?= h($badge) ?>"><?= h($st) ?></span></td>
              <td>
                <div class="fw-semibold"><?= h(mb_substr((string)$c['body'], 0, 140, 'UTF-8')) ?><?= mb_strlen((string)$c['body'], 'UTF-8')>140?'…':'' ?></div>
                <div class="text-muted small">IP: <?= h((string)($c['ip'] ?? '')) ?></div>
              </td>
              <td>
                <?php if ($newsUrl): ?>
                  <a href="<?= h($newsUrl) ?>" target="_blank" class="text-decoration-none"><?= h(mb_substr($newsTitle,0,80,'UTF-8')) ?></a>
                <?php else: ?>
                  <?= h($newsTitle) ?>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold"><?= h((string)($c['name'] ?? '')) ?></div>
                <div class="text-muted small"><?= h((string)($c['email'] ?? '')) ?></div>
              </td>
              <td class="text-muted small">
                <?= h((string)($c['created_at'] ?? '')) ?>
              </td>
              <td>
                <form method="post" class="d-flex flex-wrap gap-1">
                  <?php if ($csrf !== ''): ?>
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <?php endif; ?>
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <?php if ($st !== 'approved'): ?>
                    <button class="btn btn-sm btn-success" name="action" value="approve"><?= h(__('t_51cc4e6944', 'اعتماد')) ?></button>
                  <?php endif; ?>
                  <?php if ($st !== 'rejected'): ?>
                    <button class="btn btn-sm btn-warning" name="action" value="reject"><?= h(__('t_b7dee9747a', 'رفض')) ?></button>
                  <?php endif; ?>
                  <button class="btn btn-sm btn-outline-danger" name="action" value="delete" data-confirm='حذف التعليق نهائيًا؟'><?= h(__('t_3b9854e1bb', 'حذف')) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="card-body border-top d-flex align-items-center justify-content-between">
        <div class="text-muted small">النتائج: <?= (int)$total ?></div>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php
              $qs = ['status'=>$status];
              if ($q !== '') $qs['q'] = $q;
              $base = '?' . http_build_query($qs);
            ?>
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
              <a class="page-link" href="<?= h($base . '&page=' . max(1,$page-1)) ?>"><?= h(__('t_650bd5b508', 'السابق')) ?></a>
            </li>
            <li class="page-item disabled"><span class="page-link"><?= (int)$page ?> / <?= (int)$totalPages ?></span></li>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
              <a class="page-link" href="<?= h($base . '&page=' . min($totalPages,$page+1)) ?>"><?= h(__('t_8435afd9e8', 'التالي')) ?></a>
            </li>
          </ul>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
