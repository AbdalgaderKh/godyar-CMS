<?php declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/functions.php';

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

// Ensure tables exist (safe no-op if already exist)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        description TEXT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tags_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {
    // Ignore (some installs may have different schema/engine)
}

// Schema detection
$tagCols = [];
try {
    $st = $pdo->query('SHOW COLUMNS FROM tags');
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        $tagCols[(string)($r['Field'] ?? '')] = true;
    }
} catch (Throwable $e) {
    $tagCols = ['id'=>true,'name'=>true,'slug'=>true];
}
$hasDesc = isset($tagCols['description']);
$hasCreatedAt = isset($tagCols['created_at']);
$hasUpdatedAt = isset($tagCols['updated_at']);

// Helpers
$slugify = static function(string $s): string {
    $t = mb_strtolower(trim($s), 'UTF-8');
    $t = preg_replace('~[^\p{L}\p{N}]+~u', '-', $t) ?? $t;
    $t = trim($t, '-');
    if (mb_strlen($t, 'UTF-8') > 180) $t = mb_substr($t, 0, 180, 'UTF-8');
    return $t !== '' ? $t : ('tag-' . time());
};

$ensureUniqueSlug = static function(PDO $pdo, string $slug, int $ignoreId = 0) use ($slugify): string {
    $base = $slugify($slug);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT id FROM tags WHERE slug = :s';
        $p = [':s' => $slug];
        if ($ignoreId > 0) {
            $sql .= ' AND id <> :id';
            $p[':id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $st = $pdo->prepare($sql);
        $st->execute($p);
        $exists = (int)($st->fetchColumn() ?: 0);
        if ($exists <= 0) return $slug;
        $slug = $base . '-' . $i;
        $i++;
        if ($i > 999) return $base . '-' . time();
    }
};

// Filters
$q = trim((string)($_GET['q'] ?? ''));
$usageFilter = (string)($_GET['usage'] ?? ''); // used | unused | ''
$sort = (string)($_GET['sort'] ?? 'usage_desc');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Export CSV
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(t.name LIKE :q OR t.slug LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT t.id, t.name, t.slug" . ($hasDesc ? ", t.description" : "") . ", COUNT(nt.news_id) AS usage_count
            FROM tags t
            LEFT JOIN news_tags nt ON nt.tag_id = t.id
            {$whereSql}
            GROUP BY t.id, t.name, t.slug" . ($hasDesc ? ", t.description" : "") . "";

    if ($usageFilter === 'used') {
        $sql .= ' HAVING usage_count > 0';
    } elseif ($usageFilter === 'unused') {
        $sql .= ' HAVING usage_count = 0';
    }

    if ($sort === 'name_asc') $sql .= ' ORDER BY t.name ASC';
    elseif ($sort === 'name_desc') $sql .= ' ORDER BY t.name DESC';
    elseif ($sort === 'usage_asc') $sql .= ' ORDER BY usage_count ASC, t.name ASC';
    else $sql .= ' ORDER BY usage_count DESC, t.name ASC';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tags.csv"');
    $out = fopen('php://output', 'w');
    $head = ['id','name','slug','usage_count'];
    if ($hasDesc) $head[] = 'description';
    fputcsv($out, $head);
    foreach ($rows as $r) {
        $line = [(int)($r['id'] ?? 0), (string)($r['name'] ?? ''), (string)($r['slug'] ?? ''), (int)($r['usage_count'] ?? 0)];
        if ($hasDesc) $line[] = (string)($r['description'] ?? '');
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

// Flash
$flashSuccess = '';
$flashError = '';

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
                $slug = trim((string)($_POST['slug'] ?? ''));
                $desc = trim((string)($_POST['description'] ?? ''));
                if ($name === '') throw new RuntimeException(__('t_8db2bd4c00', 'اسم الوسم مطلوب.'));
                if ($slug === '') $slug = $slugify($name);
                $slug = $ensureUniqueSlug($pdo, $slug);

                $cols = ['name','slug'];
                $vals = [':n',':s'];
                $params = [':n'=>$name, ':s'=>$slug];
                if ($hasDesc) { $cols[]='description'; $vals[]=':d'; $params[':d']=$desc; }

                $sql = 'INSERT INTO tags (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $flashSuccess = __('t_f70506ff4e', 'تم إنشاء الوسم بنجاح.');

            } elseif ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $slug = trim((string)($_POST['slug'] ?? ''));
                $desc = trim((string)($_POST['description'] ?? ''));
                if ($id <= 0) throw new RuntimeException(__('t_667fb96152', 'وسم غير صالح.'));
                if ($name === '') throw new RuntimeException(__('t_8db2bd4c00', 'اسم الوسم مطلوب.'));
                if ($slug === '') $slug = $slugify($name);
                $slug = $ensureUniqueSlug($pdo, $slug, $id);

                $sets = ['name = :n', 'slug = :s'];
                $params = [':n'=>$name, ':s'=>$slug, ':id'=>$id];
                if ($hasDesc) { $sets[]='description = :d'; $params[':d']=$desc; }
                $sql = 'UPDATE tags SET ' . implode(', ', $sets) . ' WHERE id = :id';
                $st = $pdo->prepare($sql);
                $st->execute($params);
                $flashSuccess = __('t_a23ebbc38b', 'تم تحديث الوسم بنجاح.');

            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new RuntimeException(__('t_667fb96152', 'وسم غير صالح.'));
                // Remove relations first
                try {
                    $pdo->prepare('DELETE FROM news_tags WHERE tag_id = :id')->execute([':id'=>$id]);
                } catch (Throwable $e) {
                    // ignore if table missing
                }
                $pdo->prepare('DELETE FROM tags WHERE id = :id')->execute([':id'=>$id]);
                $flashSuccess = __('t_1e84287102', 'تم حذف الوسم.');

            } elseif ($action === 'bulk_delete') {
                $ids = $_POST['ids'] ?? [];
                if (!is_array($ids)) $ids = [];
                $idList = array_values(array_filter(array_map('intval', $ids), static fn($v) => $v > 0));
                if (!$idList) throw new RuntimeException(__('t_8b6de62ba8', 'لم يتم تحديد أي وسوم.'));
                $in = implode(',', array_fill(0, count($idList), '?'));
                // delete relations then tags
                try {
                    $pdo->prepare("DELETE FROM news_tags WHERE tag_id IN ($in)")->execute($idList);
                } catch (Throwable $e) {}
                $pdo->prepare("DELETE FROM tags WHERE id IN ($in)")->execute($idList);
                $flashSuccess = __('t_c42ff006de', 'تم حذف الوسوم المحددة.');

            } elseif ($action === 'merge') {
                $fromId = (int)($_POST['from_id'] ?? 0);
                $toId   = (int)($_POST['to_id'] ?? 0);
                if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) {
                    throw new RuntimeException(__('t_95a74eeb65', 'اختيار غير صالح لدمج الوسوم.'));
                }
                // Move relations safely (avoid duplicates if unique key exists)
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('INSERT IGNORE INTO news_tags (news_id, tag_id) SELECT news_id, :to FROM news_tags WHERE tag_id = :from')
                        ->execute([':to'=>$toId, ':from'=>$fromId]);
                    $pdo->prepare('DELETE FROM news_tags WHERE tag_id = :from')->execute([':from'=>$fromId]);
                    $pdo->prepare('DELETE FROM tags WHERE id = :from')->execute([':from'=>$fromId]);
                    $pdo->commit();
                    $flashSuccess = __('t_1e7f8bd98a', 'تم دمج الوسم بنجاح.');
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }

            } elseif ($action === 'cleanup_unused') {
                // Delete unused tags
                $pdo->exec('DELETE t FROM tags t LEFT JOIN news_tags nt ON nt.tag_id = t.id WHERE nt.tag_id IS NULL');
                $flashSuccess = __('t_1114f89fa6', 'تم حذف الوسوم غير المستخدمة.');
            }
        } catch (Throwable $e) {
            @error_log('[tags index] ' . $e->getMessage());
            $flashError = $e->getMessage();
        }
    }
}

// Stats
$stats = ['total'=>0,'used'=>0,'unused'=>0];
try {
    $sql = "SELECT COUNT(*) AS total,
                   SUM(CASE WHEN x.usage_count > 0 THEN 1 ELSE 0 END) AS used,
                   SUM(CASE WHEN x.usage_count = 0 THEN 1 ELSE 0 END) AS unused
            FROM (
              SELECT t.id, COUNT(nt.news_id) AS usage_count
              FROM tags t
              LEFT JOIN news_tags nt ON nt.tag_id = t.id
              GROUP BY t.id
            ) x";
    $r = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['total'] = (int)($r['total'] ?? 0);
    $stats['used'] = (int)($r['used'] ?? 0);
    $stats['unused'] = (int)($r['unused'] ?? 0);
} catch (Throwable $e) {
    // ignore
}

// Build WHERE/HAVING
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(t.name LIKE :q OR t.slug LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$havingSql = '';
if ($usageFilter === 'used') $havingSql = 'HAVING usage_count > 0';
elseif ($usageFilter === 'unused') $havingSql = 'HAVING usage_count = 0';

// Count rows
$totalRows = 0;
try {
    $sqlCnt = "SELECT COUNT(*) FROM (
                SELECT t.id, COUNT(nt.news_id) AS usage_count
                FROM tags t
                LEFT JOIN news_tags nt ON nt.tag_id = t.id
                {$whereSql}
                GROUP BY t.id
                {$havingSql}
              ) z";
    $st = $pdo->prepare($sqlCnt);
    $st->execute($params);
    $totalRows = (int)$st->fetchColumn();
} catch (Throwable $e) {
    $totalRows = 0;
}

$items = [];
try {
    $select = "t.id, t.name, t.slug";
    if ($hasDesc) $select .= ", t.description";
    if ($hasUpdatedAt) $select .= ", t.updated_at";
    elseif ($hasCreatedAt) $select .= ", t.created_at";

    $sql = "SELECT {$select}, COUNT(nt.news_id) AS usage_count
            FROM tags t
            LEFT JOIN news_tags nt ON nt.tag_id = t.id
            {$whereSql}
            GROUP BY t.id";
    if ($hasDesc) $sql .= ", t.description";
    if ($hasUpdatedAt) $sql .= ", t.updated_at";
    elseif ($hasCreatedAt) $sql .= ", t.created_at";
    $sql .= "\n{$havingSql}\n";

    if ($sort === 'name_asc') $sql .= ' ORDER BY t.name ASC';
    elseif ($sort === 'name_desc') $sql .= ' ORDER BY t.name DESC';
    elseif ($sort === 'usage_asc') $sql .= ' ORDER BY usage_count ASC, t.name ASC';
    else $sql .= ' ORDER BY usage_count DESC, t.name ASC';
    $sql .= ' LIMIT :lim OFFSET :off';

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    @error_log('[tags index] list error: ' . $e->getMessage());
    $items = [];
}

// For merge dropdowns (use full list, not only current page)
$allTags = [];
try {
    $st = $pdo->query('SELECT id, name, slug FROM tags ORDER BY name ASC');
    $allTags = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {
    $allTags = $items;
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));

$currentPage = 'tags';
$pageTitle = __('t_84c1b773c5', 'الوسوم');
$pageSubtitle = __('t_6499983c46', 'إدارة وسوم الأخبار (إنشاء، تعديل، دمج، وتنظيف)');
$savedFiltersPageKey = 'tags';
$adminBase = (function_exists('base_url') ? rtrim(base_url(), '/') : '') . '/admin';
$breadcrumbs = [
    __('t_3aa8578699', 'الرئيسية') => $adminBase . '/index.php',
    __('t_84c1b773c5', 'الوسوم') => null,
];

$mkQs = static function(array $extra = []) use ($q,$usageFilter,$sort,$page): string {
    $arr = [];
    if ($q !== '') $arr['q'] = $q;
    if ($usageFilter !== '') $arr['usage'] = $usageFilter;
    if ($sort !== '') $arr['sort'] = $sort;
    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') continue;
        $arr[$k] = $v;
    }
    return http_build_query($arr);
};

$pageActionsHtml = '';
$pageActionsHtml .= __('t_b8a588853d', '<button type="button" class="btn btn-gdy btn-gdy-primary" data-bs-toggle="modal" data-bs-target="#tagCreateModal"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إضافة وسم</button>');
$pageActionsHtml .= __('t_7a47a46503', '<button type="button" class="btn btn-gdy btn-gdy-ghost" data-bs-toggle="modal" data-bs-target="#tagMergeModal"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> دمج وسوم</button>');
$pageActionsHtml .= '<a class="btn btn-gdy btn-gdy-ghost" href="index.php?' . h($mkQs(['download'=>'csv'])) . __('t_1352c97777', '"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تصدير CSV</a>');

require_once __DIR__ . '/../layout/app_start.php';


require_once __DIR__ . '/../includes/saved_filters_ui.php';
echo gdy_saved_filters_ui('tags');
$csrf = csrf_token();

// Public URL helper for tag pages
$siteBase = function_exists('base_url') ? rtrim(base_url(), '/') : '';
$tagUrl = static fn(string $slug) => ($siteBase ? $siteBase : '') . '/tag/' . rawurlencode($slug);
?>

<div class="gdy-card mb-3">
  <div class="gdy-card-header d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
    <div class="d-flex flex-wrap gap-2">
      <span class="badge rounded-pill bg-secondary">الكل: <?= (int)$stats['total'] ?></span>
      <span class="badge rounded-pill bg-success">مستخدمة: <?= (int)$stats['used'] ?></span>
      <span class="badge rounded-pill bg-warning text-dark">غير مستخدمة: <?= (int)$stats['unused'] ?></span>
    </div>

    <form class="row g-2 align-items-end w-100 w-lg-auto" method="get" action="">
      <div class="col-12 col-md-5">
        <label class="form-label text-muted mb-1"><?= h(__('t_ab79fc1485', 'بحث')) ?></label>
        <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="<?= h(__('t_9a81271c03', 'اسم الوسم / slug')) ?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label text-muted mb-1"><?= h(__('t_5c9c7ec1ff', 'الاستخدام')) ?></label>
        <select class="form-select" name="usage">
          <option value="" <?= $usageFilter===''?'selected':'' ?>><?= h(__('t_6d08f19681', 'الكل')) ?></option>
          <option value="used" <?= $usageFilter==='used'?'selected':'' ?>><?= h(__('t_278d0a142c', 'مستخدمة')) ?></option>
          <option value="unused" <?= $usageFilter==='unused'?'selected':'' ?>><?= h(__('t_6bbcd06009', 'غير مستخدمة')) ?></option>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label text-muted mb-1"><?= h(__('t_ddda59289a', 'الترتيب')) ?></label>
        <select class="form-select" name="sort">
          <option value="usage_desc" <?= $sort==='usage_desc'?'selected':'' ?>><?= h(__('t_1a7bcce35f', 'الأكثر استخداماً')) ?></option>
          <option value="usage_asc" <?= $sort==='usage_asc'?'selected':'' ?>><?= h(__('t_5a7e6ce44e', 'الأقل استخداماً')) ?></option>
          <option value="name_asc" <?= $sort==='name_asc'?'selected':'' ?>><?= h(__('t_2c8c7eb98d', 'الاسم (أ-ي)')) ?></option>
          <option value="name_desc" <?= $sort==='name_desc'?'selected':'' ?>><?= h(__('t_7b22353a36', 'الاسم (ي-أ)')) ?></option>
        </select>
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button class="btn btn-gdy btn-gdy-primary w-100" type="submit"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> <?= h(__('t_abe3151c63', 'تطبيق')) ?></button>
        <a class="btn btn-gdy btn-gdy-ghost w-100" href="index.php"><?= h(__('t_ec2ce8be93', 'مسح')) ?></a>
      </div>
    </form>
  </div>

  <div class="gdy-card-body">
    <?php if ($flashSuccess): ?>
      <div class="alert alert-success">✅ <?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger">⚠️ <?= h($flashError) ?></div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
      <div class="text-muted small">
        <?= h(__('t_f220156b1c', 'استخدم')) ?> <strong><?= h(__('t_573cd1dfba', 'دمج وسوم')) ?></strong> <?= h(__('t_7135098e58', 'عند وجود وسوم متشابهة (مثل: AI / الذكاء-الاصطناعي) لتوحيدها.')) ?>
      </div>
      <form method="post" class="m-0" data-confirm='حذف كل الوسوم غير المستخدمة؟'>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="cleanup_unused">
        <button class="btn btn-sm btn-gdy btn-gdy-ghost" type="submit"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_626532ff27', 'تنظيف غير المستخدمة')) ?></button>
      </form>
    </div>

    <form method="post" id="bulkForm">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="bulk_delete">

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th style="width:44px"><input type="checkbox" id="checkAll"></th>
              <th style="width:72px">#</th>
              <th><?= h(__('t_ec99e3d757', 'الوسم')) ?></th>
              <th style="width:140px">slug</th>
              <th style="width:110px"><?= h(__('t_5c9c7ec1ff', 'الاستخدام')) ?></th>
              <th style="width:190px"><?= h(__('t_4041e7805b', 'آخر تحديث')) ?></th>
              <th style="width:260px" class="text-end"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$items): ?>
              <tr><td colspan="7" class="text-center text-muted py-4"><?= h(__('t_091e7da517', 'لا توجد وسوم مطابقة.')) ?></td></tr>
            <?php else: ?>
              <?php foreach ($items as $t):
                $id = (int)($t['id'] ?? 0);
                $usage = (int)($t['usage_count'] ?? 0);
                $dt = '';
                if ($hasUpdatedAt) $dt = (string)($t['updated_at'] ?? '');
                elseif ($hasCreatedAt) $dt = (string)($t['created_at'] ?? '');
              ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?= $id ?>" class="rowCheck"></td>
                <td><?= $id ?></td>
                <td>
                  <div class="fw-bold">#<?= h((string)($t['name'] ?? '')) ?></div>
                  <?php if ($hasDesc && !empty($t['description'])): ?>
                    <div class="text-muted small" style="max-width: 60ch;">
                      <?= h(mb_strimwidth((string)$t['description'], 0, 140, '…', 'UTF-8')) ?>
                    </div>
                  <?php endif; ?>
                  <div class="small mt-1">
                    <a href="<?= h($tagUrl((string)($t['slug'] ?? ''))) ?>" target="_blank" rel="noopener" style="text-decoration:none;">
                      <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_7946c4d1dc', 'فتح صفحة الوسم')) ?>
                    </a>
                  </div>
                </td>
                <td class="text-muted small"><code><?= h((string)($t['slug'] ?? '')) ?></code></td>
                <td>
                  <?php if ($usage > 0): ?>
                    <span class="badge bg-success rounded-pill px-3 py-2"><?= $usage ?></span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2">0</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted small"><?= h($dt) ?></td>
                <td class="text-end">
                  <div class="d-flex flex-wrap justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-gdy btn-gdy-ghost" data-bs-toggle="modal" data-bs-target="#tagEditModal<?= $id ?>"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_759fdc242e', 'تعديل')) ?></button>
                    <button type="button" class="btn btn-sm btn-gdy btn-gdy-ghost" data-copy="<?= h($tagUrl((string)($t['slug'] ?? ''))) ?>"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_0d8af0ab07', 'نسخ الرابط')) ?></button>

                    <form method="post" class="m-0" data-confirm='حذف الوسم؟ سيتم إزالة ربطه من الأخبار أيضاً.'>
                      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <button class="btn btn-sm btn-gdy btn-gdy-danger" type="submit"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_3b9854e1bb', 'حذف')) ?></button>
                    </form>
                  </div>
                </td>
              </tr>

              <!-- Edit Modal -->
              <div class="modal fade" id="tagEditModal<?= $id ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content bg-dark text-light">
                    <div class="modal-header">
                      <h5 class="modal-title"><?= h(__('t_2740e5007d', 'تعديل وسم')) ?></h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post">
                      <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="mb-3">
                          <label class="form-label"><?= h(__('t_2e8b171b46', 'الاسم')) ?></label>
                          <input class="form-control" name="name" value="<?= h((string)($t['name'] ?? '')) ?>" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Slug</label>
                          <input class="form-control" name="slug" value="<?= h((string)($t['slug'] ?? '')) ?>" placeholder="<?= h(__('t_524cb09b95', 'اتركه فارغًا لتوليده تلقائيًا')) ?>">
                          <div class="form-text text-muted"><?= h(__('t_efaa47290d', 'يفضل عدم تغييره إن كان الوسم مستخدمًا للحفاظ على الروابط.')) ?></div>
                        </div>
                        <?php if ($hasDesc): ?>
                          <div class="mb-3">
                            <label class="form-label"><?= h(__('t_ac07b993ab', 'وصف')) ?></label>
                            <textarea class="form-control" name="description" rows="3"><?= h((string)($t['description'] ?? '')) ?></textarea>
                          </div>
                        <?php endif; ?>
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

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-3">
        <div class="text-muted small">
          <?= h(__('t_93313790b8', 'إجمالي السجلات:')) ?> <span class="text-light fw-semibold"><?= (int)$totalRows ?></span> <?= h(__('t_f67df4e2c5', '— الصفحة')) ?> <span class="text-light fw-semibold"><?= (int)$page ?></span> <?= h(__('t_99fb92edad', 'من')) ?> <span class="text-light fw-semibold"><?= (int)$totalPages ?></span>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-gdy btn-gdy-danger" data-confirm='حذف الوسوم المحددة؟ سيتم إزالة ربطها من الأخبار.'><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_fa91840c47', 'حذف المحدد')) ?></button>
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
<div class="modal fade" id="tagCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><?= h(__('t_bbc5680b7c', 'إضافة وسم جديد')) ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label"><?= h(__('t_2e8b171b46', 'الاسم')) ?></label>
            <input class="form-control" name="name" required placeholder="<?= h(__('t_cfa0ab860f', 'مثال: تقنية / AI / رياضة')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label"><?= h(__('t_e691660aab', 'Slug (اختياري)')) ?></label>
            <input class="form-control" name="slug" placeholder="<?= h(__('t_524cb09b95', 'اتركه فارغًا لتوليده تلقائيًا')) ?>">
          </div>
          <?php if ($hasDesc): ?>
            <div class="mb-3">
              <label class="form-label"><?= h(__('t_d3581d718b', 'وصف (اختياري)')) ?></label>
              <textarea class="form-control" name="description" rows="3" placeholder="<?= h(__('t_817a9f87b8', 'وصف قصير للوسم')) ?>"></textarea>
            </div>
          <?php endif; ?>
          <div class="form-text text-muted"><?= h(__('t_b1d7cb63cc', 'سيتم ضمان عدم تكرار الـ slug تلقائيًا.')) ?></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-gdy btn-gdy-ghost" data-bs-dismiss="modal"><?= h(__('t_9932cca009', 'إغلاق')) ?></button>
          <button class="btn btn-gdy btn-gdy-primary" type="submit"><?= h(__('t_871a087a1d', 'حفظ')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Merge Modal -->
<div class="modal fade" id="tagMergeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title"><?= h(__('t_573cd1dfba', 'دمج وسوم')) ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" data-confirm='سيتم نقل كل الأخبار من الوسم (من) إلى الوسم (إلى) ثم حذف الوسم القديم. متابعة؟'>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="merge">
          <div class="mb-3">
            <label class="form-label"><?= h(__('t_4fbcb89a50', 'دمج من')) ?></label>
            <select class="form-select" name="from_id" required>
              <option value=""><?= h(__('t_190b8ce979', 'اختر وسمًا')) ?></option>
              <?php foreach ($allTags as $t): ?>
                <option value="<?= (int)$t['id'] ?>">#<?= h((string)$t['name']) ?> (<?= h((string)$t['slug']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label"><?= h(__('t_0985d27cd3', 'إلى')) ?></label>
            <select class="form-select" name="to_id" required>
              <option value=""><?= h(__('t_190b8ce979', 'اختر وسمًا')) ?></option>
              <?php foreach ($allTags as $t): ?>
                <option value="<?= (int)$t['id'] ?>">#<?= h((string)$t['name']) ?> (<?= h((string)$t['slug']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-text text-muted"><?= h(__('t_a804c83325', 'نصيحة: اختر الوسم الأكثر استخدامًا كهدف (إلى) للحفاظ على صفحة الوسم الأقوى.')) ?></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-gdy btn-gdy-ghost" data-bs-dismiss="modal"><?= h(__('t_9932cca009', 'إغلاق')) ?></button>
          <button class="btn btn-gdy btn-gdy-primary" type="submit"><?= h(__('t_34658b61e8', 'تنفيذ الدمج')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const all = document.getElementById('checkAll');
  const rows = () => Array.from(document.querySelectorAll('.rowCheck'));
  if (all) {
    all.addEventListener('change', () => rows().forEach(cb => cb.checked = all.checked));
  }

  // Copy buttons
  document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const txt = btn.getAttribute('data-copy') || '';
      try {
        await navigator.clipboard.writeText(txt);
        btn.innerHTML = '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تم النسخ';
        setTimeout(() => btn.innerHTML = '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> نسخ الرابط', 1400);
      } catch (e) {
        alert('تعذر النسخ. انسخ يدويًا: ' + txt);
      }
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
