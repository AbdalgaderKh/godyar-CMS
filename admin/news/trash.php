<?php
declare(strict_types=1);

// GDY_BUILD: v9

require_once __DIR__ . '/../_admin_guard.php';
// godyar/admin/news/trash.php — سلة المحذوفات للأخبار

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

Auth::requirePermission('posts.view');

$isWriter = Auth::isWriter();
$userId   = (int)($_SESSION['user']['id'] ?? 0);

// 🚫 الكاتب/المؤلف لا يستخدم سلة المحذوفات
if ($isWriter) {
    header('Location: index.php');
    exit;
}

$currentPage = 'posts';
$pageTitle   = __('t_3d903708f0', 'سلة المحذوفات — الأخبار');

$pdo = gdy_pdo_safe();

$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// بحث متقدم وفلاتر جودة
$inContent  = ((int)($_GET['in_content'] ?? 0) === 1);
$noImage    = ((int)($_GET['no_image'] ?? 0) === 1);
$noDesc     = ((int)($_GET['no_desc'] ?? 0) === 1);
$noKeywords = ((int)($_GET['no_keywords'] ?? 0) === 1);

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where  = "deleted_at IS NOT NULL";
$params = [];

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


if ($noImage) {
    $where .= " AND (image IS NULL OR image = '')";
}
if ($noDesc) {
    $where .= " AND (seo_description IS NULL OR seo_description = '')";
}
if ($noKeywords) {
    $where .= " AND (seo_keywords IS NULL OR seo_keywords = '')";
}

// عدد السجلات
$total = 0;
try {
    $sql = "SELECT COUNT(*) FROM news WHERE {$where}";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    @error_log('[Admin News Trash] count: ' . $e->getMessage());
}

// جلب البيانات
$items = [];
try {
    $sql = "SELECT id, title, slug, published_at, created_at, deleted_at, image, seo_title, seo_description, seo_keywords, SUBSTRING(content,1,4000) AS content_snip
            FROM news
            WHERE {$where}
            ORDER BY deleted_at DESC, id DESC
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
    @error_log('[Admin News Trash] list: ' . $e->getMessage());
}

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
  /* ✅ ضبط عرض الصفحة داخل نطاق الشاشة (التصميم الموحد للعرض) */
  html, body { overflow-x: hidden; }

  .admin-content{
    max-width: 100% !important;
    width: 100% !important;
    overflow-x: hidden;
  }

  /* منع الهيدر من كسر العرض على الشاشات الصغيرة */
  .gdy-page-header{
    flex-wrap: wrap;
    gap: .75rem;
  }
  .gdy-page-header > div{ min-width: 0; }

  /* حصر التمرير الأفقي داخل الجدول فقط */
  .table-responsive{
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  /* منع تمدد الأعمدة بسبب نص طويل */
  .table{
    table-layout: fixed;
  }
  .table th, .table td{
    white-space: normal;
    word-break: break-word;
    vertical-align: middle;
  }

  /* slug قد يكون طويل جداً */
  .table td code{
    display: inline-block;
    max-width: 100%;
    white-space: normal;
    word-break: break-all;
  }

  /* أزرار الإجراءات لا تكسر التصميم */
  .actions-wrap{
    display:flex;
    flex-wrap:wrap;
    gap:.35rem;
    justify-content:center;
  }
  .actions-wrap .btn{ white-space: nowrap; }
</style>

<div class="admin-content container-fluid py-4">
  <div class="gdy-page-header d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1"><?= h(__('t_3d903708f0', 'سلة المحذوفات — الأخبار')) ?></h1>
      <p class="text-muted mb-0 small"><?= h(__('t_138b3fd7ad', 'الأخبار التي تم حذفها (حذف ناعم) ويمكن استعادتها أو حذفها نهائياً.')) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="index.php" class="btn btn-outline-light btn-sm">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_c95e4d9e70', 'عودة لقائمة الأخبار')) ?>
      </a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="col-sm-6 col-md-4 col-lg-3">
      <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
             placeholder="<?= h(__('t_f3ac22dd80', 'بحث (العنوان/الرابط)...')) ?>">
    </div>
    <div class="col-12">
      <div class="d-flex flex-wrap gap-3 align-items-center small">
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
    <div class="col-auto">
      <button type="submit" class="btn btn-outline-secondary btn-sm">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg> <?= h(__('t_ab79fc1485', 'بحث')) ?>
      </button>
    </div>
  </form>

  <div class="card shadow-sm glass-card gdy-card">
    <div class="card-body p-0">
      <?php if (empty($items)): ?>
        <p class="p-3 text-muted mb-0"><?= h(__('t_c1c961de79', 'لا توجد عناصر في سلة المحذوفات حالياً.')) ?></p>
      <?php else: ?>
      <div class="gdy-bulkbar px-3 py-2 bg-dark text-light border-bottom border-secondary d-flex align-items-center gap-2 flex-wrap">
        <select id="bulkAction" class="form-select form-select-sm bg-dark border-secondary text-light" style="max-width:220px">
          <option value="">إجراءات جماعية...</option>
          <option value="restore">استعادة جماعية</option>
          <option value="destroy">حذف نهائي</option>
        </select>
        <button type="button" id="bulkApply" class="btn btn-primary btn-sm" disabled>
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> تطبيق
        </button>
        <button type="button" id="bulkClear" class="btn btn-outline-light btn-sm" disabled>
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إلغاء التحديد
        </button>
        <span class="ms-auto"><span id="selectedCount">0</span> محدد</span>
      </div>
      <div id="selectAllResultsBar" class="gdy-selectall-results d-none px-3 py-2 small bg-dark text-light border-bottom border-secondary d-flex align-items-center gap-2">
        <span id="selectAllMsg"></span>
        <button type="button" id="selectAllResultsBtn" class="btn btn-sm btn-outline-warning ms-2">تحديد كل النتائج (<span id="selectAllTotal">0</span>)</button>
        <button type="button" id="clearAllResultsBtn" class="btn btn-sm btn-outline-light ms-2 d-none">إلغاء تحديد كل النتائج</button>
        <span class="ms-auto" id="bulkProgress"></span>
      </div>
      <div class="table-responsive">
          <table class="table table-sm table-hover table-striped mb-0 align-middle text-center">
            <thead class="table-dark">
              <tr>
                <th style="width:38px"><input type="checkbox" id="checkAll" title="تحديد الكل"></th>
                <th style="width:70px">#</th>
                <th style="width:38%"><?= h(__('t_6dc6588082', 'العنوان')) ?></th>
                <th style="width:20%"><?= h(__('t_615e66bc1b', 'الرابط')) ?></th>
                <th style="width:140px"><?= h(__('t_4da1d32d5f', 'تاريخ النشر')) ?></th>
                <th style="width:140px"><?= h(__('t_44b0275acc', 'تاريخ الحذف')) ?></th>
                <th style="width:260px">SEO</th>
                <th style="width:260px"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $row): ?>
                <tr>
                  <td><input type="checkbox" class="form-check-input row-check" value="<?= (int)$row['id'] ?>" aria-label="select"></td>
                  <td><?= (int)$row['id'] ?></td>
                  <td class="text-start"><?= h($row['title'] ?? '') ?></td>
                  <td><code class="small"><?= h($row['slug'] ?? '') ?></code></td>
                  <td><small><?= h($row['published_at'] ?? $row['created_at'] ?? '') ?></small></td>
                  <td><small><?= h($row['deleted_at'] ?? '') ?></small></td>
                  <?php
                    $title = (string)($row['title'] ?? '');
                    $slug  = (string)($row['slug'] ?? '');
                    $seoTitle = (string)($row['seo_title'] ?? '');
                    $seoDesc  = (string)($row['seo_description'] ?? '');
                    $seoKw    = (string)($row['seo_keywords'] ?? '');
                    $imgMain  = (string)($row['image'] ?? '');
                    $contentSnip = (string)($row['content_snip'] ?? '');

                    $len = function (string $s): int {
                        if ($s === '') return 0;
                        if (function_exists('mb_strlen')) return (int)mb_strlen($s, 'UTF-8');
                        return (int)strlen($s);
                    };

                    $titleLen = $len($title);
                    $seoDescLen = $len($seoDesc);
                    $kwCount = 0;
                    if ($seoKw !== '') {
                        $parts = preg_split('/[\n,]+/u', $seoKw) ?: [];
                        $parts = array_values(array_filter(array_map('trim', $parts)));
                        $kwCount = count($parts);
                    }
                    $slugOk = ($slug !== '' && $len($slug) <= 80 && preg_match('~^[a-z0-9/_-]+$~i', $slug));
                    $altOk = true;
                    if ($contentSnip !== '' && preg_match_all('/<img\b[^>]*>/i', $contentSnip, $mImgs)) {
                        foreach (($mImgs[0] ?? []) as $imgTag) {
                            if (!preg_match('/\balt\s*=\s*([\"\']).*?\1/i', $imgTag)) { $altOk = false; break; }
                        }
                    }
                    $titleLenOk = ($titleLen >= 15 && $titleLen <= 70);
                    $descLenOk  = ($seoDescLen >= 50 && $seoDescLen <= 170);
                    $kwOk       = ($kwCount >= 3);
                    $imgOk      = ($imgMain !== '');
                  ?>
                  <td class="text-start">
                    <div class="d-flex flex-wrap gap-1">
                      <span class="badge <?= $titleLenOk ? 'bg-success' : 'bg-warning text-dark' ?>" title="طول العنوان"><?= (int)$titleLen ?></span>
                      <span class="badge <?= $descLenOk ? 'bg-success' : 'bg-warning text-dark' ?>" title="وصف SEO"><?= (int)$seoDescLen ?></span>
                      <span class="badge <?= $kwOk ? 'bg-success' : 'bg-warning text-dark' ?>" title="عدد الكلمات المفتاحية"><?= (int)$kwCount ?></span>
                      <span class="badge <?= $slugOk ? 'bg-success' : 'bg-danger' ?>" title="Slug"><?= $slugOk ? 'OK' : '!' ?></span>
                      <span class="badge <?= $altOk ? 'bg-success' : 'bg-danger' ?>" title="Alt للصور"><?= $altOk ? 'ALT' : 'NO ALT' ?></span>
                      <span class="badge <?= $imgOk ? 'bg-success' : 'bg-danger' ?>" title="صورة"><?= $imgOk ? 'IMG' : 'NO IMG' ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="actions-wrap">
                      <a href="restore.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-success">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_763957a449', 'استعادة')) ?>
                      </a>
                      <a href="destroy.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-danger"
                         data-confirm=<?= json_encode(__('t_7276017c1d', 'سيتم حذف الخبر نهائياً ولا يمكن التراجع. هل أنت متأكد؟'), JSON_UNESCAPED_UNICODE) ?>>
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_f0e389eb4c', 'حذف نهائي')) ?>
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
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&amp;q=<?= urlencode($search) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var CSRF = <?= json_encode(generate_csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  var TOTAL = <?= (int)$total ?>;
  var FILTERS = <?= json_encode([
      'q' => $search,
      'in_content' => $inContent ? 1 : 0,
      'no_image' => $noImage ? 1 : 0,
      'no_desc' => $noDesc ? 1 : 0,
      'no_keywords' => $noKeywords ? 1 : 0,
      'trash' => 1,
  ], JSON_UNESCAPED_UNICODE) ?>;

  var checkAll = document.getElementById('checkAll');
  var rowChecks = Array.from(document.querySelectorAll('.row-check'));
  var bulkApply = document.getElementById('bulkApply');
  var bulkClear = document.getElementById('bulkClear');
  var bulkAction = document.getElementById('bulkAction');
  var selectedCount = document.getElementById('selectedCount');

  var selectAllBar = document.getElementById('selectAllResultsBar');
  var selectAllMsg = document.getElementById('selectAllMsg');
  var selectAllBtn = document.getElementById('selectAllResultsBtn');
  var clearAllBtn  = document.getElementById('clearAllResultsBtn');
  var selectAllTotal = document.getElementById('selectAllTotal');
  var bulkProgress = document.getElementById('bulkProgress');

  var modeAll = false;
  var excluded = new Set();

  function getSelectedIds() {
    return rowChecks.filter(c => c.checked).map(c => parseInt(c.value || '0', 10)).filter(Boolean);
  }
  function countSelected() {
    if (modeAll) return Math.max(0, TOTAL - excluded.size);
    return getSelectedIds().length;
  }
  function showSelectAllPrompt() {
    if (!selectAllBar) return;
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
    if (checkAll) {
      var all = rowChecks.length && rowChecks.every(c => c.checked);
      var none = rowChecks.length && rowChecks.every(c => !c.checked);
      checkAll.checked = !!all;
      checkAll.indeterminate = !all && !none;
    }
    var c = countSelected();
    if (selectedCount) selectedCount.textContent = String(c);
    if (bulkApply) bulkApply.disabled = (c === 0 || !bulkAction || !bulkAction.value);
    if (bulkClear) bulkClear.disabled = (c === 0 && !modeAll);

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

  if (checkAll) {
    checkAll.addEventListener('change', function(){
      modeAll = false;
      excluded.clear();
      rowChecks.forEach(c => c.checked = checkAll.checked);
      syncUI();
    });
  }
  rowChecks.forEach(function(c){
    c.addEventListener('change', function(){
      if (modeAll) {
        var id = parseInt(c.value || '0', 10);
        if (!id) return;
        if (c.checked) excluded.delete(id); else excluded.add(id);
      }
      syncUI();
    });
  });

  if (bulkAction) bulkAction.addEventListener('change', syncUI);

  if (bulkClear) bulkClear.addEventListener('click', function(){
    modeAll = false;
    excluded.clear();
    rowChecks.forEach(c => c.checked = false);
    if (bulkAction) bulkAction.value = '';
    syncUI();
  });

  if (selectAllBtn) selectAllBtn.addEventListener('click', function(){
    modeAll = true;
    excluded.clear();
    rowChecks.forEach(c => c.checked = true);
    syncUI();
  });
  if (clearAllBtn) clearAllBtn.addEventListener('click', function(){
    modeAll = false;
    excluded.clear();
    rowChecks.forEach(c => c.checked = false);
    syncUI();
  });

  async function postBulk(payload) {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.keys(payload).forEach(function(k){
      var v = payload[k];
      if (Array.isArray(v)) v.forEach(function(x){ fd.append(k + '[]', String(x)); });
      else if (typeof v === 'object' && v !== null) fd.append(k, JSON.stringify(v));
      else fd.append(k, String(v));
    });
    var res = await fetch('bulk.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    var data = {};
    try { data = await res.json(); } catch (e) {}
    if (!res.ok || !data.ok) throw new Error(data.msg || 'Bulk failed');
    return data;
  }

  async function runBulk(action) {
    if (!bulkProgress) return;
    bulkProgress.textContent = '...';
    var processed = 0;
    var cursor = 0;

    while (true) {
      var payload = { action: action };
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

  if (bulkApply) bulkApply.addEventListener('click', async function(){
    if (!bulkAction || !bulkAction.value) return;
    var act = bulkAction.value;
    if (!confirm('تأكيد تنفيذ الإجراء؟')) return;
    try {
      await runBulk(act);
      location.reload();
    } catch (e) {
      alert(e.message || 'Error');
    }
  });

  syncUI();
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
