<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
// admin/news/review.php — طابور مراجعة الأخبار (اعتماد/نشر/أرشفة/إرجاع لمسودة)

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_news_helpers.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

// للمدير/المحرر فقط
$isWriter = Auth::isWriter();
if ($isWriter) {
    header('Location: index.php');
    exit;
}

$userId = (int)($_SESSION['user']['id'] ?? 0);

$currentPage = 'posts_review';
$pageTitle   = __('t_9b72a3d4a9', 'مراجعة الأخبار');

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    exit('Database not available');
}

// تأكد من وجود جداول الملاحظات/السجل إن لم تكن أُنشئت بعد
try {
    gdy_ensure_news_notes_table($pdo);
    gdy_ensure_news_revisions_table($pdo);
} catch (Throwable $e) {
    // لا نوقف الصفحة
}

// Helpers
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$flashSuccess = '';
$flashError   = '';

$csrfToken = function_exists('generate_csrf_token') ? (string)generate_csrf_token() : '';

$checkCsrf = function (string $token): bool {
    if (function_exists('verify_csrf_token')) {
        return (bool)verify_csrf_token($token);
    }
    if (function_exists('validate_csrf_token')) {
        return (bool)validate_csrf_token($token);
    }
    return true; // fallback
};

// Schema-aware columns
$newsCols = [];
try {
    $newsCols = function_exists('gdy_db_columns') ? gdy_db_columns($pdo, 'news') : [];
} catch (Throwable $e) {
    $newsCols = [];
}

$hasStatus      = isset($newsCols['status']) || true; // أغلب النسخ لديها status
$hasPublishedAt = isset($newsCols['published_at']);
$hasUpdatedAt   = isset($newsCols['updated_at']);

// --------------------
// معالجة إجراءات POST
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $csrf   = (string)($_POST['csrf_token'] ?? '');

    if (!$checkCsrf($csrf)) {
        $flashError = __('t_f1b80ef76e', 'تعذر التحقق من رمز الحماية. حدّث الصفحة ثم حاول مرة أخرى.');
    } else {
        $idsRaw = $_POST['ids'] ?? [];
        if (!is_array($idsRaw)) $idsRaw = [$idsRaw];

        $ids = [];
        foreach ($idsRaw as $id) {
            $id = (int)$id;
            if ($id > 0) $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));

        $note = trim((string)($_POST['note'] ?? ''));

        $allowedActions = ['approve','publish','draft','archive'];
        if (!in_array($action, $allowedActions, true)) {
            $flashError = __('t_39b2b1f2a8', 'إجراء غير صالح.');
        } elseif (empty($ids)) {
            $flashError = __('t_4b7d2f5f3c', 'اختر خبرًا واحدًا على الأقل.');
        } else {
            $okCount = 0;
            $failCount = 0;

            foreach ($ids as $nid) {
                try {
                    // Fetch current row
                    $st = $pdo->prepare("SELECT * FROM news WHERE id = :id LIMIT 1");
                    $st->execute([':id' => $nid]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    if (!$row) { $failCount++; continue; }

                    // capture revision قبل أي تغيير
                    try {
                        $tagStr = '';
                        if (function_exists('gdy_get_news_tags_string')) {
                            $tagStr = (string)gdy_get_news_tags_string($pdo, (int)$nid);
                        }
                        gdy_capture_news_revision($pdo, (int)$nid, $userId > 0 ? $userId : null, $action, $row, $tagStr);
                    } catch (Throwable $e) {
                        // تجاهل
                    }

                    $newStatus = 'pending';
                    if ($action === 'approve') $newStatus = 'approved';
                    if ($action === 'publish') $newStatus = 'published';
                    if ($action === 'draft')   $newStatus = 'draft';
                    if ($action === 'archive') $newStatus = 'archived';

                    // Build update statement
                    $sets = [];
                    $params = [':id' => $nid];

                    if (isset($newsCols['status'])) {
                        $sets[] = "status = :status";
                        $params[':status'] = $newStatus;
                    }

                    if ($action === 'publish') {
                        if ($hasPublishedAt) {
                            $sets[] = "published_at = NOW()";
                        } elseif (isset($newsCols['publish_at'])) {
                            $sets[] = "publish_at = NOW()";
                        }
                    }

                    if ($hasUpdatedAt) {
                        $sets[] = "updated_at = NOW()";
                    }

                    if (!$sets) { $failCount++; continue; }

                    $sql = "UPDATE news SET " . implode(', ', $sets) . " WHERE id = :id";
                    $up = $pdo->prepare($sql);
                    foreach ($params as $k => $v) {
                        if ($k === ':id') {
                            $up->bindValue($k, (int)$v, PDO::PARAM_INT);
                        } elseif ($k === ':status') {
                            $up->bindValue($k, (string)$v, PDO::PARAM_STR);
                        } else {
                            $up->bindValue($k, $v);
                        }
                    }
                    $up->execute();

                    // إضافة ملاحظة تحرير عند الإرجاع للمسودة/الأرشفة (أو عند وجود سبب)
                    if ($note !== '') {
                        try {
                            $prefix = '';
                            if ($action === 'draft')   $prefix = __('t_9bb6a0a6a2', 'إرجاع لمسودة: ');
                            if ($action === 'archive') $prefix = __('t_9c3a59c7cc', 'أرشفة: ');
                            if ($action === 'approve') $prefix = __('t_5b6f0a8a12', 'اعتماد: ');
                            if ($action === 'publish') $prefix = __('t_f2ddc88f1c', 'نشر: ');

                            gdy_add_news_note($pdo, (int)$nid, $userId > 0 ? $userId : null, $prefix . $note);
                        } catch (Throwable $e) {
                            // تجاهل
                        }
                    }

                    $okCount++;
                } catch (Throwable $e) {
                    $failCount++;
                    @error_log('[Review Queue] action failed: ' . $e->getMessage());
                }
            }

            if ($okCount > 0) {
                $flashSuccess = __('t_650c8c2b3b', 'تم تنفيذ الإجراء بنجاح على ') . $okCount . __('t_6d7b3b9f9f', ' خبر/أخبار.');
            }
            if ($failCount > 0) {
                $flashError = __('t_9c01a5945a', 'تعذر تطبيق الإجراء على ') . $failCount . __('t_7d4b8f6c2b', ' عنصر/عناصر.');
            }
        }
    }
}

// --------------------
// فلاتر + Pagination
// --------------------
$status = trim((string)($_GET['status'] ?? 'pending'));
$allowedStatuses = ['pending','approved','draft','published','archived',''];
if (!in_array($status, $allowedStatuses, true)) $status = 'pending';

$categoryId = (int)($_GET['category_id'] ?? 0);
$feedId     = (int)($_GET['feed_id'] ?? 0);
$q          = trim((string)($_GET['q'] ?? ''));

$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// categories
$categories = [];
try {
    $st = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $categories = [];
}

// feeds (optional)
$feeds = [];
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'feeds'");
    if ($chk && $chk->fetchColumn()) {
        $st = $pdo->query("SELECT id, name FROM feeds ORDER BY name ASC");
        $feeds = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }
} catch (Throwable $e) {
    $feeds = [];
}

$where = [];
$params = [];

if ($status !== '') {
    $where[] = 'n.status = :status';
    $params[':status'] = $status;
}
if ($categoryId > 0) {
    $where[] = 'n.category_id = :cid';
    $params[':cid'] = $categoryId;
}
if ($q !== '') {
    $where[] = '(n.title LIKE :q OR n.slug LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

$joinImports = "LEFT JOIN news_imports ni ON ni.news_id = n.id
               LEFT JOIN feeds f ON f.id = ni.feed_id";
if ($feedId > 0) {
    $where[] = 'f.id = :fid';
    $params[':fid'] = $feedId;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// count
$total = 0;
try {
    $sqlCount = "SELECT COUNT(*)
                 FROM news n
                 $joinImports
                 $whereSql";
    $st = $pdo->prepare($sqlCount);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->execute();
    $total = (int)$st->fetchColumn();
} catch (Throwable $e) {
    $total = 0;
}

$rows = [];
try {
    $sql = "SELECT n.id, n.title, n.slug, n.status, n.created_at,
                   n.category_id, COALESCE(c.name,'') AS category_name,
                   COALESCE(u.name, u.username, u.email, '') AS author_name,
                   COALESCE(f.name,'') AS feed_name,
                   ni.feed_id
            FROM news n
            LEFT JOIN categories c ON c.id = n.category_id
            LEFT JOIN users u ON u.id = n.author_id
            $joinImports
            $whereSql
            ORDER BY n.id DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $rows = [];
}

$pages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
$pages = max(1, $pages);

// Build url helper
$buildUrl = function(array $overrides = []) use ($status, $categoryId, $feedId, $q, $page): string {
    $p = [
        'status' => $status,
        'category_id' => $categoryId,
        'feed_id' => $feedId,
        'q' => $q,
        'page' => $page,
    ];
    foreach ($overrides as $k => $v) {
        $p[$k] = $v;
    }
    // remove empties
    foreach ($p as $k => $v) {
        if ($v === '' || $v === 0 || $v === null) {
            unset($p[$k]);
        }
    }
    return 'review.php?' . http_build_query($p);
};

// assets + layout
$__base = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
.admin-content.gdy-page{background: linear-gradient(135deg,#0f172a 0%, #020617 100%); min-height:100vh; color:#e5e7eb; font-family:"Cairo",system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;}
@media (min-width: 992px){ .admin-content{ margin-right:260px !important; } }
.gdy-wrap{max-width: 1200px; margin:0 auto; padding:1.5rem 1rem 2rem;}
.gdy-header{background: linear-gradient(135deg,#0ea5e9,#0369a1); color:#fff; padding:1.25rem 1.5rem; border-radius:1rem; box-shadow:0 10px 30px rgba(15,23,42,.6); display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem;}
.gdy-header h1{margin:0; font-size:1.25rem; font-weight:800;}
.gdy-header p{margin: .25rem 0 0; opacity:.9; font-size:.9rem;}
.gdy-card{margin-top:1rem; background: rgba(15,23,42,.92); border-radius: 1rem; border:1px solid rgba(148,163,184,.45); box-shadow:0 15px 45px rgba(15,23,42,.9); overflow:hidden;}
.gdy-card-header{padding:1rem 1.25rem; border-bottom:1px solid rgba(148,163,184,.25); display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; justify-content:space-between;}
.gdy-card-body{padding:1rem 1.25rem;}
.gdy-filter{display:flex; gap:.5rem; flex-wrap:wrap; align-items:end;}
.gdy-filter .form-select, .gdy-filter .form-control{background: rgba(15,23,42,.9); color:#e5e7eb; border-color: rgba(148,163,184,.45); border-radius: .75rem; font-size:.85rem;}
.gdy-table{width:100%;}
.gdy-table th, .gdy-table td{vertical-align: middle;}
.gdy-badge{padding:.25rem .5rem; border-radius:999px; font-size:.72rem; border:1px solid rgba(148,163,184,.45); display:inline-flex; align-items:center; gap:.35rem;}
.gdy-status{font-weight:700;}
.gdy-actions{display:flex; gap:.35rem; flex-wrap:wrap;}
.gdy-actions .btn{border-radius:.7rem; font-size:.8rem; padding:.35rem .55rem;}
</style>

<div class="admin-content gdy-page">
  <div class="gdy-wrap">

    <div class="gdy-header">
      <div>
        <h1><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($pageTitle) ?></h1>
        <p><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_2c54b3d343', 'راجع الأخبار المستوردة/المكتوبة ثم اعتمدها أو انشرها بسرعة.')) ?></p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-light" href="index.php"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_f2401e0914','قائمة الأخبار')) ?></a>
        <a class="btn btn-success" href="create.php"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_0d1f6ecf66','إضافة خبر جديد')) ?></a>
      </div>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success mt-3 mb-0"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger mt-3 mb-0"><?= h($flashError) ?></div>
    <?php endif; ?>

    <div class="gdy-card">
      <div class="gdy-card-header">
        <div>
          <div class="fw-bold"><svg class="gdy-icon me-1 text-info" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_32a45d45a2','الفلاتر')) ?></div>
          <div class="text-muted small"><?= h(__('t_0b55c27b0c','إجمالي النتائج:')) ?> <strong><?= (int)$total ?></strong></div>
        </div>

        <form class="gdy-filter" method="get" action="review.php">
          <div>
            <label class="form-label small mb-1"><?= h(__('t_1253eb5642','الحالة')) ?></label>
            <select name="status" class="form-select form-select-sm">
              <option value="pending"  <?= $status==='pending'?'selected':'' ?>><?= h(__('t_e9210fb9c2','بانتظار المراجعة')) ?></option>
              <option value="approved" <?= $status==='approved'?'selected':'' ?>><?= h(__('t_5e19b0c9c7','معتمد')) ?></option>
              <option value="draft"    <?= $status==='draft'?'selected':'' ?>><?= h(__('t_9071af8f2d','مسودة')) ?></option>
              <option value="published"<?= $status==='published'?'selected':'' ?>><?= h(__('t_ecfb62b400','منشور')) ?></option>
              <option value="archived" <?= $status==='archived'?'selected':'' ?>><?= h(__('t_6d998a9b54','مؤرشف')) ?></option>
              <option value="" <?= $status===''?'selected':'' ?>><?= h(__('t_06c54f8086','الكل')) ?></option>
            </select>
          </div>

          <div>
            <label class="form-label small mb-1"><?= h(__('t_cf14329701','التصنيف')) ?></label>
            <select name="category_id" class="form-select form-select-sm">
              <option value="0"><?= h(__('t_06c54f8086','الكل')) ?></option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$categoryId?'selected':'' ?>><?= h((string)$c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if (!empty($feeds)): ?>
            <div>
              <label class="form-label small mb-1"><?= h(__('t_8a5d4d9e31','المصدر')) ?></label>
              <select name="feed_id" class="form-select form-select-sm">
                <option value="0"><?= h(__('t_06c54f8086','الكل')) ?></option>
                <?php foreach ($feeds as $f): ?>
                  <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id']===$feedId?'selected':'' ?>><?= h((string)$f['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div>
            <label class="form-label small mb-1"><?= h(__('t_3e5a0d5c6a','بحث')) ?></label>
            <input name="q" class="form-control form-control-sm" value="<?= h($q) ?>" placeholder="<?= h(__('t_46c2b7cc10','عنوان أو رابط…')) ?>" />
          </div>

          <div>
            <button class="btn btn-info btn-sm" type="submit"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> <?= h(__('t_7d7c2d59cc','تطبيق')) ?></button>
          </div>
        </form>
      </div>

      <div class="gdy-card-body">
        <form method="post" action="<?= h($buildUrl(['page'=>$page])) ?>" id="bulkForm">
          <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>" />

          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <select name="action" class="form-select form-select-sm" style="max-width:220px;">
                <option value="">— <?= h(__('t_3b0c1b0b1b','إجراء جماعي')) ?> —</option>
                <option value="approve"><?= h(__('t_5e19b0c9c7','اعتماد')) ?></option>
                <option value="publish"><?= h(__('t_ecfb62b400','نشر')) ?></option>
                <option value="draft"><?= h(__('t_9bb6a0a6a2','إرجاع لمسودة')) ?></option>
                <option value="archive"><?= h(__('t_6d998a9b54','أرشفة')) ?></option>
              </select>
              <input type="text" name="note" class="form-control form-control-sm" style="max-width:360px;" placeholder="<?= h(__('t_6d0ef8a7aa','سبب/ملاحظة (اختياري)…')) ?>" />
              <button type="submit" class="btn btn-success btn-sm" data-confirm=<?= json_encode(__('t_64027a8a52', 'تأكيد تنفيذ الإجراء على العناصر المحددة؟'), JSON_UNESCAPED_UNICODE) ?>>
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_7d7c2d59cc','تطبيق')) ?>
              </button>
            </div>

            <div class="text-muted small">
              <span class="me-2"><input type="checkbox" id="checkAll" /> <?= h(__('t_2df3f2cc7d','تحديد الكل')) ?></span>
              <span><?= h(__('t_4fb6d3e8b1','المحدد:')) ?> <strong id="selectedCount">0</strong></span>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle gdy-table">
              <thead>
                <tr>
                  <th style="width:38px;"></th>
                  <th><?= h(__('t_6dc6588082','العنوان')) ?></th>
                  <th style="width:140px;" class="text-center"><?= h(__('t_cf14329701','التصنيف')) ?></th>
                  <th style="width:160px;" class="text-center"><?= h(__('t_8a5d4d9e31','المصدر')) ?></th>
                  <th style="width:120px;" class="text-center"><?= h(__('t_1253eb5642','الحالة')) ?></th>
                  <th style="width:150px;" class="text-center"><?= h(__('t_93ef0b14b7','تاريخ')) ?></th>
                  <th style="width:260px;" class="text-center"><?= h(__('t_07f6e3a5a6','إجراءات')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="7" class="text-center text-muted py-4"><?= h(__('t_9f9b1a2d4c','لا توجد نتائج مطابقة.')) ?></td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $r):
                  $nid = (int)($r['id'] ?? 0);
                  $st  = (string)($r['status'] ?? '');
                  $title = (string)($r['title'] ?? '');
                  $cat   = (string)($r['category_name'] ?? '');
                  $feed  = (string)($r['feed_name'] ?? '');
                  $dt    = (string)($r['created_at'] ?? '');

                  $statusLabel = $st;
                  if ($st === 'pending')   $statusLabel = __('t_e9210fb9c2','بانتظار المراجعة');
                  if ($st === 'approved')  $statusLabel = __('t_5e19b0c9c7','معتمد');
                  if ($st === 'draft')     $statusLabel = __('t_9071af8f2d','مسودة');
                  if ($st === 'published') $statusLabel = __('t_ecfb62b400','منشور');
                  if ($st === 'archived')  $statusLabel = __('t_6d998a9b54','مؤرشف');
                ?>
                  <tr>
                    <td>
                      <input class="form-check-input rowCheck" type="checkbox" name="ids[]" value="<?= $nid ?>" />
                    </td>
                    <td>
                      <div class="fw-bold">
                        <a class="text-decoration-none text-light" href="edit.php?id=<?= $nid ?>">
                          <?= h($title) ?>
                        </a>
                      </div>
                      <div class="small text-muted" style="direction:ltr;">
                        <?= h((string)($r['slug'] ?? '')) ?>
                      </div>
                    </td>
                    <td class="text-center"><span class="gdy-badge"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($cat ?: '—') ?></span></td>
                    <td class="text-center"><span class="gdy-badge"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($feed ?: '—') ?></span></td>
                    <td class="text-center"><span class="gdy-badge gdy-status"><?= h($statusLabel) ?></span></td>
                    <td class="text-center"><span class="small text-muted"><?= h($dt ?: '—') ?></span></td>
                    <td class="text-center">
                      <div class="gdy-actions justify-content-center">
                        <button type="button" class="btn btn-outline-secondary js-preview" data-id="<?= $nid ?>"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_preview','معاينة')) ?></button>
                        <a class="btn btn-outline-info" href="edit.php?id=<?= $nid ?>"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_0b90f1a1f7','تعديل')) ?></a>

                        <button class="btn btn-outline-success" type="submit" name="action" value="approve" formaction="<?= h($buildUrl(['page'=>$page])) ?>" formmethod="post" data-action="row-select">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_5e19b0c9c7','اعتماد')) ?>
                        </button>

                        <button class="btn btn-success" type="submit" name="action" value="publish" formaction="<?= h($buildUrl(['page'=>$page])) ?>" formmethod="post" data-action="row-select">
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_ecfb62b400','نشر')) ?>
                        </button>

                        <button class="btn btn-outline-warning" type="submit" name="action" value="draft" formaction="<?= h($buildUrl(['page'=>$page])) ?>" formmethod="post" data-action="row-select" data-confirm=<?= json_encode(__('t_44e8416493', 'إرجاع هذا الخبر لمسودة؟'), JSON_UNESCAPED_UNICODE) ?>>
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_9bb6a0a6a2','مسودة')) ?>
                        </button>

                        <button class="btn btn-outline-danger" type="submit" name="action" value="archive" formaction="<?= h($buildUrl(['page'=>$page])) ?>" formmethod="post" data-action="row-select" data-confirm=<?= json_encode(__('t_c922c6d23e', 'أرشفة هذا الخبر؟'), JSON_UNESCAPED_UNICODE) ?>>
                          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if ($pages > 1): ?>
            <nav class="mt-3" aria-label="pagination">
              <ul class="pagination pagination-sm justify-content-center">
                <?php
                  $prev = max(1, $page - 1);
                  $next = min($pages, $page + 1);
                ?>
                <li class="page-item <?= $page<=1?'disabled':'' ?>">
                  <a class="page-link" href="<?= h($buildUrl(['page'=>$prev])) ?>">&laquo;</a>
                </li>
                <?php
                  $start = max(1, $page - 3);
                  $end   = min($pages, $page + 3);
                  for ($i=$start; $i<=$end; $i++):
                ?>
                  <li class="page-item <?= $i===$page?'active':'' ?>">
                    <a class="page-link" href="<?= h($buildUrl(['page'=>$i])) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= $page>=$pages?'disabled':'' ?>">
                  <a class="page-link" href="<?= h($buildUrl(['page'=>$next])) ?>">&raquo;</a>
                </li>
              </ul>
            </nav>
          <?php endif; ?>
        </form>
      </div>
    </div>

  </div>
</div>


<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content bg-dark text-light" style="border:1px solid rgba(148,163,184,.35);">
      <div class="modal-header" style="border-bottom:1px solid rgba(148,163,184,.25);">
        <h5 class="modal-title" id="previewModalTitle"><?= h(__('t_preview','معاينة')) ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= h(__('t_close','إغلاق')) ?>"></button>
      </div>
      <div class="modal-body">
        <div id="previewModalBody">
          <div class="text-center py-5 text-muted">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_loading','جاري التحميل...')) ?>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid rgba(148,163,184,.25);">
        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">
          <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_close','إغلاق')) ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var checkAll = document.getElementById('checkAll');
  var checks = Array.prototype.slice.call(document.querySelectorAll('.rowCheck'));
  var selectedCount = document.getElementById('selectedCount');

  function updateCount(){
    var c = 0;
    checks.forEach(function(ch){ if(ch.checked) c++; });
    if (selectedCount) selectedCount.textContent = String(c);
  }

  if (checkAll) {
    checkAll.addEventListener('change', function(){
      checks.forEach(function(ch){ ch.checked = checkAll.checked; });
      updateCount();
    });
  }

  checks.forEach(function(ch){
    ch.addEventListener('change', function(){
      if (checkAll) {
        checkAll.checked = checks.length && checks.every(function(x){ return x.checked; });
      }
      updateCount();
    });
  });


  // Preview modal
  var modalEl = document.getElementById('previewModal');
  var modalTitleEl = document.getElementById('previewModalTitle');
  var modalBodyEl  = document.getElementById('previewModalBody');
  var modal = null;
  if (modalEl && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
    modal = new window.bootstrap.Modal(modalEl);
  }

  function setLoading(){
    if (!modalBodyEl) return;
    modalBodyEl.innerHTML = '<div class="text-center py-5 text-muted"><svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_loading','جاري التحميل...')) ?></div>';
  }

  function escapeHtml(str){
    return String(str||'').replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  Array.prototype.slice.call(document.querySelectorAll('.js-preview')).forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = btn.getAttribute('data-id');
      if (!id) return;
      if (modalTitleEl) modalTitleEl.textContent = '<?= h(__('t_preview','معاينة')) ?>';
      setLoading();
      if (modal) modal.show();

      fetch('review_preview.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (!modalBodyEl) return;
          if (!data || !data.ok) {
            modalBodyEl.innerHTML = '<div class="alert alert-danger mb-0"><?= h(__('t_error_loading_preview','تعذر تحميل المعاينة.')) ?></div>';
            return;
          }
          if (modalTitleEl) modalTitleEl.textContent = data.title ? data.title : '<?= h(__('t_preview','معاينة')) ?>';
          modalBodyEl.innerHTML = data.html || '<div class="text-muted">—</div>';
        })
        .catch(function(){
          if (!modalBodyEl) return;
          modalBodyEl.innerHTML = '<div class="alert alert-danger mb-0"><?= h(__('t_error_loading_preview','تعذر تحميل المعاينة.')) ?></div>';
        });
    });
  });


  updateCount();
})();
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
