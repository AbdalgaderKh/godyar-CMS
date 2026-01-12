<?php declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/functions.php';

use Godyar\Feeds\RssReader;
use Godyar\Services\FeedImportService;

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    exit(__('t_ed202270ee', 'قاعدة البيانات غير متاحة حالياً.'));
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Ensure feeds table exists (safe no-op if already exists)
try {
    $pdo->exec("\nCREATE TABLE IF NOT EXISTS feeds (\n  id INT PRIMARY KEY AUTO_INCREMENT,\n  name VARCHAR(255) NOT NULL,\n  url VARCHAR(500) NOT NULL,\n  category_id INT NULL,\n  is_active BOOLEAN DEFAULT 1,\n  fetch_interval_minutes INT DEFAULT 60,\n  last_fetched_at DATETIME NULL,\n  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n");
} catch (Throwable $e) {
    // ignore
}

// Categories
$cats = [];
try {
    $cats = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $cats = [];
}
$catsMap = [];
foreach ($cats as $c) {
    $catsMap[(int)$c['id']] = (string)$c['name'];
}

// Filters
$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? ''); // active | stopped | ''
$catFilter = (string)($_GET['category_id'] ?? '');
$dueOnly = (string)($_GET['due'] ?? '') === '1';
$sort = (string)($_GET['sort'] ?? 'new');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Export CSV
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(f.name LIKE :q OR f.url LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    if ($status === 'active') {
        $where[] = 'f.is_active = 1';
    } elseif ($status === 'stopped') {
        $where[] = 'f.is_active = 0';
    }
    if ($catFilter !== '' && ctype_digit($catFilter)) {
        $where[] = 'f.category_id = :cid';
        $params[':cid'] = (int)$catFilter;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT f.id, f.name, f.url, f.category_id, f.is_active, f.fetch_interval_minutes, f.last_fetched_at, f.created_at FROM feeds f {$whereSql} ORDER BY f.id DESC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="feeds.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','name','url','category','is_active','fetch_interval_minutes','last_fetched_at','created_at']);
    foreach ($rows as $r) {
        $cid = (int)($r['category_id'] ?? 0);
        fputcsv($out, [
            (int)($r['id'] ?? 0),
            (string)($r['name'] ?? ''),
            (string)($r['url'] ?? ''),
            (string)($catsMap[$cid] ?? '—'),
            (int)($r['is_active'] ?? 0),
            (int)($r['fetch_interval_minutes'] ?? 60),
            (string)($r['last_fetched_at'] ?? ''),
            (string)($r['created_at'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

// Flash
$flashSuccess = '';
$flashError = '';
$flashInfo = '';

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!verify_csrf_token($csrf)) {
        $flashError = __('t_0f296c4fe0', 'فشل التحقق الأمني، يرجى إعادة المحاولة.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $name = trim((string)($_POST['name'] ?? ''));
                $url  = trim((string)($_POST['url'] ?? ''));
                $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
                $interval = max(5, (int)($_POST['fetch_interval_minutes'] ?? 60));
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                if ($name === '' || $url === '') throw new RuntimeException(__('t_162058200f', 'الاسم ورابط RSS مطلوبان.'));
                $st = $pdo->prepare('INSERT INTO feeds (name,url,category_id,is_active,fetch_interval_minutes) VALUES (:n,:u,:c,:a,:i)');
                $st->execute([':n'=>$name, ':u'=>$url, ':c'=>$categoryId, ':a'=>$isActive, ':i'=>$interval]);
                $flashSuccess = __('t_db3376bf13', 'تمت إضافة المصدر بنجاح.');

            } elseif ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $url  = trim((string)($_POST['url'] ?? ''));
                $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
                $interval = max(5, (int)($_POST['fetch_interval_minutes'] ?? 60));
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                if ($id <= 0) throw new RuntimeException(__('t_17ae6e50d5', 'مصدر غير صالح.'));
                if ($name === '' || $url === '') throw new RuntimeException(__('t_162058200f', 'الاسم ورابط RSS مطلوبان.'));
                $st = $pdo->prepare('UPDATE feeds SET name=:n, url=:u, category_id=:c, is_active=:a, fetch_interval_minutes=:i WHERE id=:id');
                $st->execute([':n'=>$name, ':u'=>$url, ':c'=>$categoryId, ':a'=>$isActive, ':i'=>$interval, ':id'=>$id]);
                $flashSuccess = __('t_30a2ff105a', 'تم تحديث المصدر.');

            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new RuntimeException(__('t_17ae6e50d5', 'مصدر غير صالح.'));
                $pdo->prepare('DELETE FROM feeds WHERE id=:id')->execute([':id'=>$id]);
                $flashSuccess = __('t_abf320b86d', 'تم حذف المصدر.');

            } elseif ($action === 'test') {
                $id = (int)($_POST['id'] ?? 0);
                $st = $pdo->prepare('SELECT * FROM feeds WHERE id=:id LIMIT 1');
                $st->execute([':id'=>$id]);
                $f = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$f) throw new RuntimeException(__('t_69d011164e', 'المصدر غير موجود.'));
                $items = RssReader::fetch((string)$f['url'], 3);
                if ($items && is_array($items)) {
                    $first = (string)($items[0]['title'] ?? '');
                    $flashSuccess = __('t_abb38a77f7', 'نجح الاتصال بالمصدر. العناصر المقروءة الآن: ') . count($items) . ($first ? __('t_59a1291925', ' — مثال: ') . $first : '');
                } else {
                    $flashError = __('t_42903c1300', 'تعذر قراءة RSS. تأكد أن الرابط RSS/Atom صحيح وغير محمي.');
                }

            } elseif ($action === 'fetch_now') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new RuntimeException(__('t_17ae6e50d5', 'مصدر غير صالح.'));
                $svc = new FeedImportService($pdo);
                $res = $svc->runForFeedId($id, 12);
                $flashSuccess = __('t_1d83c69dc2', 'تم الجلب اليدوي: تم استيراد ') . (int)($res['imported'] ?? 0) . __('t_11d6c8485e', ' وتخطي ') . (int)($res['skipped'] ?? 0) . __('t_4e3f8ea82f', ' من العناصر.');

            } elseif ($action === 'bulk') {
                $bulkAction = (string)($_POST['bulk_action'] ?? '');
                $ids = $_POST['ids'] ?? [];
                if (!is_array($ids)) $ids = [];
                $idList = array_values(array_filter(array_map('intval', $ids), static fn($v) => $v > 0));
                if (!$idList) throw new RuntimeException(__('t_19a9ea0660', 'لم يتم تحديد أي مصادر.'));

                $in = implode(',', array_fill(0, count($idList), '?'));
                if ($bulkAction === 'enable') {
                    $pdo->prepare("UPDATE feeds SET is_active=1 WHERE id IN ($in)")->execute($idList);
                    $flashSuccess = __('t_627469da7c', 'تم تفعيل المصادر المحددة.');
                } elseif ($bulkAction === 'disable') {
                    $pdo->prepare("UPDATE feeds SET is_active=0 WHERE id IN ($in)")->execute($idList);
                    $flashSuccess = __('t_a7b5d66e42', 'تم إيقاف المصادر المحددة.');
                } elseif ($bulkAction === 'delete') {
                    $pdo->prepare("DELETE FROM feeds WHERE id IN ($in)")->execute($idList);
                    $flashSuccess = __('t_8c879e5073', 'تم حذف المصادر المحددة.');
                } elseif ($bulkAction === 'fetch_now') {
                    $svc = new FeedImportService($pdo);
                    $imported = 0; $skipped = 0;
                    foreach ($idList as $fid) {
                        $r = $svc->runForFeedId((int)$fid, 12);
                        $imported += (int)($r['imported'] ?? 0);
                        $skipped  += (int)($r['skipped'] ?? 0);
                    }
                    $flashSuccess = __('t_b96c6d0d02', 'تم الجلب اليدوي للمحدد: استيراد ') . $imported . __('t_11d6c8485e', ' وتخطي ') . $skipped . '.';
                } else {
                    throw new RuntimeException(__('t_3742196a8c', 'إجراء جماعي غير صالح.'));
                }
            }
        } catch (Throwable $e) {
            @error_log('[feeds index] ' . $e->getMessage());
            $flashError = $e->getMessage();
        }
    }
}

// Stats + due computation
$stats = ['total'=>0,'active'=>0,'stopped'=>0,'due'=>0];
try {
    $st = $pdo->query('SELECT is_active, COUNT(*) c FROM feeds GROUP BY is_active');
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        $c = (int)($r['c'] ?? 0);
        $stats['total'] += $c;
        if ((int)($r['is_active'] ?? 0) === 1) $stats['active'] += $c; else $stats['stopped'] += $c;
    }
} catch (Throwable $e) {}

// Build list query
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(f.name LIKE :q OR f.url LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($status === 'active') {
    $where[] = 'f.is_active = 1';
} elseif ($status === 'stopped') {
    $where[] = 'f.is_active = 0';
}
if ($catFilter !== '' && ctype_digit($catFilter)) {
    $where[] = 'f.category_id = :cid';
    $params[':cid'] = (int)$catFilter;
}
if ($dueOnly) {
    // MySQL-compatible due condition (keep counts/pagination accurate)
    $where[] = "f.is_active = 1 AND (f.last_fetched_at IS NULL OR TIMESTAMPDIFF(MINUTE, f.last_fetched_at, NOW()) >= f.fetch_interval_minutes)";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Sorting
$orderBy = 'f.id DESC';
if ($sort === 'name') $orderBy = 'f.name ASC, f.id DESC';
elseif ($sort === 'last') $orderBy = 'f.last_fetched_at DESC, f.id DESC';

// Count
$totalRows = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM feeds f {$whereSql}");
    $st->execute($params);
    $totalRows = (int)$st->fetchColumn();
} catch (Throwable $e) { $totalRows = 0; }

$feeds = [];
try {
    $sql = "SELECT f.* FROM feeds f {$whereSql} ORDER BY {$orderBy} LIMIT :lim OFFSET :off";
    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    $feeds = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[feeds index] list error: ' . $e->getMessage());
    $feeds = [];
}

// Due count (global) + per-row due flag
$now = time();
try {
    $all = $pdo->query('SELECT id, fetch_interval_minutes, last_fetched_at, is_active FROM feeds')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($all as $f) {
        if ((int)($f['is_active'] ?? 0) !== 1) continue;
        $interval = (int)($f['fetch_interval_minutes'] ?? 60);
        if ($interval < 5) $interval = 5;
        $last = !empty($f['last_fetched_at']) ? strtotime((string)$f['last_fetched_at']) : 0;
        $isDue = ($last <= 0) || (($now - $last) >= ($interval * 60));
        if ($isDue) $stats['due']++;
    }
} catch (Throwable $e) {}

// Note: dueOnly is handled in SQL for accurate counts/pagination.

$totalPages = max(1, (int)ceil($totalRows / $perPage));

$currentPage = 'feeds';
$pageTitle = __('t_89dc462268', 'مصادر RSS');
$pageSubtitle = __('t_2b69fc4aad', 'استيراد أخبار كمَسودّات من RSS/Atom — تُراجع ثم تُنشر من المدير');
$adminBase = (function_exists('base_url') ? rtrim(base_url(), '/') : '') . '/admin';
$breadcrumbs = [
    __('t_3aa8578699', 'الرئيسية') => $adminBase . '/index.php',
    __('t_14dc3e9c61', 'الأخبار') => null,
    __('t_89dc462268', 'مصادر RSS') => null,
];

$mkQs = static function(array $extra = []) use ($q,$status,$catFilter,$dueOnly,$sort): string {
    $arr = [];
    if ($q !== '') $arr['q'] = $q;
    if ($status !== '') $arr['status'] = $status;
    if ($catFilter !== '') $arr['category_id'] = $catFilter;
    if ($dueOnly) $arr['due'] = '1';
    if ($sort !== '') $arr['sort'] = $sort;
    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') continue;
        $arr[$k] = $v;
    }
    return http_build_query($arr);
};

$pageActionsHtml = '';
$pageActionsHtml .= __('t_fecda52589', '<button type="button" class="btn btn-gdy btn-gdy-primary" data-bs-toggle="modal" data-bs-target="#feedCreateModal"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إضافة مصدر</button>');
$pageActionsHtml .= '<a class="btn btn-gdy btn-gdy-ghost" href="index.php?' . h($mkQs(['download'=>'csv'])) . __('t_1352c97777', '"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تصدير CSV</a>');

require_once __DIR__ . '/../layout/app_start.php';

$csrf = csrf_token();
?>

<div class="gdy-card mb-3">
  <div class="gdy-card-header d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
    <div class="d-flex flex-wrap gap-2">
      <span class="badge rounded-pill bg-secondary">الكل: <?= (int)$stats['total'] ?></span>
      <span class="badge rounded-pill bg-success">مفعل: <?= (int)$stats['active'] ?></span>
      <span class="badge rounded-pill bg-warning text-dark">موقوف: <?= (int)$stats['stopped'] ?></span>
      <span class="badge rounded-pill bg-info text-dark">جاهز للجلب: <?= (int)$stats['due'] ?></span>
    </div>

    <form class="row g-2 align-items-end w-100 w-lg-auto" method="get" action="">
      <div class="col-12 col-md-4">
        <label class="form-label text-muted mb-1"><?= h(__('t_ab79fc1485', 'بحث')) ?></label>
        <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="<?= h(__('t_99be6d1edb', 'اسم المصدر / الرابط')) ?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label text-muted mb-1"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
        <select class="form-select" name="status">
          <option value="" <?= $status===''?'selected':'' ?>><?= h(__('t_6d08f19681', 'الكل')) ?></option>
          <option value="active" <?= $status==='active'?'selected':'' ?>><?= h(__('t_918499f2af', 'مفعل')) ?></option>
          <option value="stopped" <?= $status==='stopped'?'selected':'' ?>><?= h(__('t_75e3d97ed8', 'موقوف')) ?></option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label text-muted mb-1"><?= h(__('t_dd6d2be22a', 'القسم')) ?></label>
        <select class="form-select" name="category_id">
          <option value="" <?= $catFilter===''?'selected':'' ?>><?= h(__('t_6d08f19681', 'الكل')) ?></option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((string)$catFilter === (string)$c['id'])?'selected':'' ?>><?= h((string)$c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label text-muted mb-1"><?= h(__('t_ddda59289a', 'الترتيب')) ?></label>
        <select class="form-select" name="sort">
          <option value="new" <?= $sort==='new'?'selected':'' ?>><?= h(__('t_55ae35c77d', 'الأحدث')) ?></option>
          <option value="name" <?= $sort==='name'?'selected':'' ?>><?= h(__('t_2e8b171b46', 'الاسم')) ?></option>
          <option value="last" <?= $sort==='last'?'selected':'' ?>><?= h(__('t_22d308e0e4', 'آخر جلب')) ?></option>
        </select>
      </div>
      <div class="col-12 d-flex align-items-center gap-2 mt-2">
        <label class="d-flex align-items-center gap-2 text-muted small" style="user-select:none;">
          <input type="checkbox" name="due" value="1" <?= $dueOnly?'checked':'' ?>>
          <?= h(__('t_91900f9b30', 'عرض الجاهز للجلب فقط')) ?>
        </label>
        <div class="ms-auto d-flex gap-2">
          <button class="btn btn-gdy btn-gdy-primary" type="submit"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> <?= h(__('t_abe3151c63', 'تطبيق')) ?></button>
          <a class="btn btn-gdy btn-gdy-ghost" href="index.php"><?= h(__('t_ec2ce8be93', 'مسح')) ?></a>
        </div>
      </div>
    </form>
  </div>

  <div class="gdy-card-body">
    <?php if ($flashSuccess): ?>
      <div class="alert alert-success">✅ <?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashInfo): ?>
      <div class="alert alert-info"><?= h($flashInfo) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger">⚠️ <?= h($flashError) ?></div>
    <?php endif; ?>

    <div class="gdy-card mb-3">
      <div class="gdy-card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
          <div>
            <div class="fw-semibold"><?= h(__('t_479372c63a', 'تشغيل الاستيراد التلقائي')) ?></div>
            <div class="text-muted small"><?= h(__('t_3eab172c9a', 'فعّل كرون')) ?> <code>cron/import_feeds.php</code> <?= h(__('t_b72e9506c8', 'كل 10–20 دقيقة. الأخبار المستوردة تُحفظ')) ?> <strong><?= h(__('t_6d7fba52f9', 'كمسودّة')) ?></strong> <?= h(__('t_9340af9e8e', 'ليعتمدها مدير النظام وينشرها.')) ?></div>
          </div>
          <div class="text-muted small"><?= h(__('t_faa6688e17', 'المسار:')) ?> <code><?= h(__('t_e3c3510ce1', 'الأخبار → إدارة الأخبار')) ?></code></div>
        </div>
      </div>
    </div>

    <form method="post" id="bulkForm">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="bulk">

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-2">
        <div class="text-muted small"><?= h(__('t_9145fef6c7', 'حدد مصادر ثم اختر إجراء جماعي.')) ?></div>
        <div class="d-flex gap-2">
          <select class="form-select form-select-sm" name="bulk_action" style="max-width:220px" required>
            <option value=""><?= h(__('t_6c90ba82db', 'إجراء جماعي…')) ?></option>
            <option value="enable"><?= h(__('t_8403358516', 'تفعيل')) ?></option>
            <option value="disable"><?= h(__('t_848ae5bb1e', 'إيقاف')) ?></option>
            <option value="fetch_now"><?= h(__('t_8dc4612dfd', 'جلب الآن')) ?></option>
            <option value="delete"><?= h(__('t_3b9854e1bb', 'حذف')) ?></option>
          </select>
          <button class="btn btn-sm btn-gdy btn-gdy-primary" type="submit" data-confirm='تنفيذ الإجراء على المحدد؟'><?= h(__('t_7651effd37', 'تنفيذ')) ?></button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th style="width:44px"><input type="checkbox" id="checkAll"></th>
              <th style="width:72px">#</th>
              <th><?= h(__('t_58cd4dec6b', 'المصدر')) ?></th>
              <th style="width:170px"><?= h(__('t_dd6d2be22a', 'القسم')) ?></th>
              <th style="width:120px"><?= h(__('t_942ec099ec', 'الفاصل')) ?></th>
              <th style="width:190px"><?= h(__('t_22d308e0e4', 'آخر جلب')) ?></th>
              <th style="width:110px"><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
              <th style="width:360px" class="text-end"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$feeds): ?>
              <tr><td colspan="8" class="text-center text-muted py-4"><?= h(__('t_0d9d4929f8', 'لا توجد مصادر مطابقة.')) ?></td></tr>
            <?php else: ?>
              <?php foreach ($feeds as $f):
                $id = (int)($f['id'] ?? 0);
                $cid = (int)($f['category_id'] ?? 0);
                $interval = (int)($f['fetch_interval_minutes'] ?? 60);
                if ($interval < 5) $interval = 5;
                $last = !empty($f['last_fetched_at']) ? strtotime((string)$f['last_fetched_at']) : 0;
                $isDue = ((int)($f['is_active'] ?? 0) === 1) && (($last <= 0) || (($now - $last) >= ($interval * 60)));
              ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?= $id ?>" class="rowCheck"></td>
                <td><?= $id ?></td>
                <td>
                  <div class="fw-bold d-flex align-items-center gap-2">
                    <?= h((string)($f['name'] ?? '')) ?>
                    <?php if ($isDue): ?>
                      <span class="badge bg-info text-dark rounded-pill"><?= h(__('t_d7fe42c8a3', 'جاهز')) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="text-muted small" style="word-break:break-all;"><a href="<?= h((string)($f['url'] ?? '')) ?>" target="_blank" rel="noopener" style="text-decoration:none;"><?= h((string)($f['url'] ?? '')) ?></a></div>
                </td>
                <td><?= h($catsMap[$cid] ?? '—') ?></td>
                <td class="text-muted"><?= $interval ?> د</td>
                <td class="text-muted small"><?= h((string)($f['last_fetched_at'] ?? '')) ?></td>
                <td>
                  <?php if ((int)($f['is_active'] ?? 0) === 1): ?>
                    <span class="badge bg-success rounded-pill px-3 py-2"><?= h(__('t_918499f2af', 'مفعل')) ?></span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><?= h(__('t_75e3d97ed8', 'موقوف')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <div class="d-flex flex-wrap justify-content-end gap-2">
                    <form method="post" class="m-0">
                      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="test">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <button class="btn btn-sm btn-gdy btn-gdy-ghost" type="submit"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_52c549f995', 'اختبار')) ?></button>
                    </form>
                    <form method="post" class="m-0" data-confirm='جلب يدوي الآن؟ سيتم إنشاء مسودات جديدة إن وجدت عناصر جديدة.'>
                      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="fetch_now">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <button class="btn btn-sm btn-gdy btn-gdy-primary" type="submit"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_8dc4612dfd', 'جلب الآن')) ?></button>
                    </form>

                    <button type="button" class="btn btn-sm btn-gdy btn-gdy-ghost" data-bs-toggle="modal" data-bs-target="#feedEditModal<?= $id ?>"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_759fdc242e', 'تعديل')) ?></button>

                    <form method="post" class="m-0" data-confirm='حذف المصدر؟'>
                      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <button class="btn btn-sm btn-gdy btn-gdy-danger" type="submit"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_3b9854e1bb', 'حذف')) ?></button>
                    </form>
                  </div>
                </td>
              </tr>

              <!-- Edit Modal -->
              <div class="modal fade" id="feedEditModal<?= $id ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content bg-dark text-light">
                    <div class="modal-header">
                      <h5 class="modal-title"><?= h(__('t_dabcd5ef6a', 'تعديل مصدر')) ?></h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post">
                      <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="row g-3">
                          <div class="col-12 col-md-6">
                            <label class="form-label"><?= h(__('t_2e8b171b46', 'الاسم')) ?></label>
                            <input class="form-control" name="name" value="<?= h((string)($f['name'] ?? '')) ?>" required>
                          </div>
                          <div class="col-12 col-md-6">
                            <label class="form-label"><?= h(__('t_dd6d2be22a', 'القسم')) ?></label>
                            <select class="form-select" name="category_id">
                              <option value=""><?= h(__('t_e4f539cbbc', 'بدون')) ?></option>
                              <?php foreach ($cats as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ((string)($f['category_id'] ?? '') === (string)$c['id'])?'selected':'' ?>><?= h((string)$c['name']) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="col-12">
                            <label class="form-label"><?= h(__('t_af42ad0ce1', 'رابط RSS')) ?></label>
                            <input class="form-control" name="url" value="<?= h((string)($f['url'] ?? '')) ?>" required>
                          </div>
                          <div class="col-12 col-md-4">
                            <label class="form-label"><?= h(__('t_9664c8c3e8', 'الفاصل (دقائق)')) ?></label>
                            <input type="number" class="form-control" name="fetch_interval_minutes" value="<?= (int)($f['fetch_interval_minutes'] ?? 60) ?>" min="5">
                          </div>
                          <div class="col-12 col-md-8 d-flex align-items-end">
                            <label class="d-flex align-items-center gap-2 mb-2">
                              <input type="checkbox" name="is_active" <?= ((int)($f['is_active'] ?? 0) === 1)?'checked':'' ?>>
                              <?= h(__('t_918499f2af', 'مفعل')) ?>
                            </label>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-gdy btn-gdy-ghost" data-bs-dismiss="modal"><?= h(__('t_9932cca009', 'إغلاق')) ?></button>
                        <button class="btn btn-gdy btn-gdy-primary" type="submit"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="gdy-card-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-3">
        <div class="text-muted small">
          <?= h(__('t_93313790b8', 'إجمالي السجلات:')) ?> <span class="text-light fw-semibold"><?= (int)$totalRows ?></span> <?= h(__('t_f67df4e2c5', '— الصفحة')) ?> <span class="text-light fw-semibold"><?= (int)$page ?></span> <?= h(__('t_99fb92edad', 'من')) ?> <span class="text-light fw-semibold"><?= (int)$totalPages ?></span>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav aria-label="pagination">
            <ul class="pagination pagination-sm mb-0">
              <?php
                $mk = static function(int $p) use ($mkQs): string {
                  return 'index.php?' . $mkQs(['page' => $p]);
                };
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
              ?>
              <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= h($mk($prev)) ?>"><?= h(__('t_650bd5b508', 'السابق')) ?></a></li>
              <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                if ($start > 1) {
                  echo '<li class="page-item"><a class="page-link" href="'.h($mk(1)).'">1</a></li>';
                  if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }
                for ($p=$start; $p<=$end; $p++){
                  $active = $p===$page ? 'active' : '';
                  echo '<li class="page-item '.$active.'"><a class="page-link" href="'.h($mk($p)).'">'.$p.'</a></li>';
                }
                if ($end < $totalPages) {
                  if ($end < $totalPages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                  echo '<li class="page-item"><a class="page-link" href="'.h($mk($totalPages)).'">'.$totalPages.'</a></li>';
                }
              ?>
              <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="<?= h($mk($next)) ?>"><?= h(__('t_8435afd9e8', 'التالي')) ?></a></li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="feedCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><?= h(__('t_b4878dffad', 'إضافة مصدر RSS جديد')) ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="create">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label"><?= h(__('t_2e8b171b46', 'الاسم')) ?></label>
              <input class="form-control" name="name" required placeholder="BBC Arabic / CNN ...">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label"><?= h(__('t_dd6d2be22a', 'القسم')) ?></label>
              <select class="form-select" name="category_id">
                <option value=""><?= h(__('t_e4f539cbbc', 'بدون')) ?></option>
                <?php foreach ($cats as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= h((string)$c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label"><?= h(__('t_76814f8469', 'رابط RSS/Atom')) ?></label>
              <input class="form-control" name="url" required placeholder="https://.../rss.xml">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label"><?= h(__('t_9664c8c3e8', 'الفاصل (دقائق)')) ?></label>
              <input type="number" class="form-control" name="fetch_interval_minutes" value="60" min="5">
            </div>
            <div class="col-12 col-md-8 d-flex align-items-end">
              <label class="d-flex align-items-center gap-2 mb-2">
                <input type="checkbox" name="is_active" checked>
                <?= h(__('t_918499f2af', 'مفعل')) ?>
              </label>
            </div>
          </div>
          <div class="form-text text-muted mt-2"><?= h(__('t_6972aa57f9', 'ملاحظة: النظام يستورد')) ?> <strong><?= h(__('t_b8b84bfa35', 'العنوان + ملخص RSS')) ?></strong> <?= h(__('t_bb5933facf', 'مع رابط للمصدر (بدون سحب المقال كاملًا). سيتم حفظ الخبر كمسودة.')) ?></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-gdy btn-gdy-ghost" data-bs-dismiss="modal"><?= h(__('t_9932cca009', 'إغلاق')) ?></button>
          <button class="btn btn-gdy btn-gdy-primary" type="submit"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const all = document.getElementById('checkAll');
  const rows = () => Array.from(document.querySelectorAll('.rowCheck'));
  if (all) all.addEventListener('change', () => rows().forEach(cb => cb.checked = all.checked));
})();
</script>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
