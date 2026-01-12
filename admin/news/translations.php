<?php
declare(strict_types=1);

// GDY_BUILD: v10

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$isWriter = Auth::isWriter();
$userId   = (int)($_SESSION['user']['id'] ?? 0);

$currentPage = 'translations';
$pageTitle   = 'إدارة الترجمات';

// Page meta for unified admin layout
$pageSubtitle = 'ترجمة الأخبار إلى لغات متعددة (EN / FR)';
$__base = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';
$__adminBase = $__base . '/admin';
$breadcrumbs = [
    'الرئيسية' => $__adminBase . '/index.php',
    'الأخبار'  => $__adminBase . '/news/index.php',
    'الترجمات' => null,
];

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database not available');
}

// News owner column differs between installs (author_id vs user_id)
$newsOwnerCol = 'author_id';
try {
    $chk = $pdo->query("SHOW COLUMNS FROM news LIKE 'user_id'");
    if ($chk && $chk->fetch(PDO::FETCH_ASSOC)) {
        $newsOwnerCol = 'user_id';
    }
} catch (Throwable $e) {
    // keep default
}

// Ensure table exists (best effort)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS news_translations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        news_id INT UNSIGNED NOT NULL,
        lang VARCHAR(10) NOT NULL,
        title VARCHAR(255) NULL,
        content MEDIUMTEXT NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'draft',
        created_at DATETIME NULL,
        KEY idx_news_lang (news_id, lang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    // ignore
}

$csrfToken = function_exists('generate_csrf_token') ? (string)generate_csrf_token() : '';
$checkCsrf = static function (string $token): bool {
    if (function_exists('verify_csrf_token')) return (bool)verify_csrf_token($token);
    if (function_exists('validate_csrf_token')) return (bool)validate_csrf_token($token);
    return $token !== '';
};

// Actions
$flash = ['type'=>'', 'msg'=>''];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!$checkCsrf($csrf)) {
        $flash = ['type'=>'danger', 'msg'=>'رمز الحماية غير صحيح. حدّث الصفحة وحاول مرة أخرى.'];
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);
        $newsId = (int)($_POST['news_id'] ?? 0);
        $lang   = strtolower(trim((string)($_POST['lang'] ?? '')));
        $status = trim((string)($_POST['status'] ?? 'draft'));
        $title  = trim((string)($_POST['title'] ?? ''));
        $content = (string)($_POST['content'] ?? '');

        if (!in_array($lang, ['en','fr'], true)) $lang = 'en';
        if (!in_array($status, ['draft','published'], true)) $status = 'draft';

        try {
            if ($action === 'save') {
                if ($id > 0) {
                    // Writers: only for their news
                    if ($isWriter) {
                        $st = $pdo->prepare("SELECT 1 FROM news n JOIN news_translations t ON t.news_id=n.id WHERE t.id=:id AND n.$newsOwnerCol=:u LIMIT 1");
                        $st->execute([':id'=>$id, ':u'=>$userId]);
                        if (!$st->fetchColumn()) throw new RuntimeException('غير مصرح');
                    }
                    $st = $pdo->prepare("UPDATE news_translations SET lang=:lang, title=:t, content=:c, status=:s WHERE id=:id");
                    $st->execute([
                        ':lang'=>$lang,
                        ':t'=>($title!==''?$title:null),
                        ':c'=>(trim(strip_tags($content))!==''?$content:null),
                        ':s'=>$status,
                        ':id'=>$id,
                    ]);
                    $flash = ['type'=>'success','msg'=>'تم حفظ الترجمة.'];
                } else {
                    if ($newsId <= 0) throw new RuntimeException('news_id required');
                    if ($isWriter) {
                        $st = $pdo->prepare("SELECT 1 FROM news WHERE id=:id AND $newsOwnerCol=:u LIMIT 1");
                        $st->execute([':id'=>$newsId, ':u'=>$userId]);
                        if (!$st->fetchColumn()) throw new RuntimeException('غير مصرح');
                    }
                    $st = $pdo->prepare("INSERT INTO news_translations (news_id,lang,title,content,status,created_at)
                                         VALUES (:n,:lang,:t,:c,:s,NOW())");
                    $st->execute([
                        ':n'=>$newsId,
                        ':lang'=>$lang,
                        ':t'=>($title!==''?$title:null),
                        ':c'=>(trim(strip_tags($content))!==''?$content:null),
                        ':s'=>$status,
                    ]);
                    $flash = ['type'=>'success','msg'=>'تم إنشاء الترجمة.'];
                    $id = (int)$pdo->lastInsertId();
                }
                header('Location: translations.php?edit=' . (int)$id);
                exit;
            }

            if ($action === 'delete' && $id > 0) {
                if ($isWriter) {
                    $st = $pdo->prepare("SELECT 1 FROM news n JOIN news_translations t ON t.news_id=n.id WHERE t.id=:id AND n.$newsOwnerCol=:u LIMIT 1");
                    $st->execute([':id'=>$id, ':u'=>$userId]);
                    if (!$st->fetchColumn()) throw new RuntimeException('غير مصرح');
                }
                $st = $pdo->prepare("DELETE FROM news_translations WHERE id=:id");
                $st->execute([':id'=>$id]);
                $flash = ['type'=>'success','msg'=>'تم حذف الترجمة.'];
                header('Location: translations.php');
                exit;
            }
        } catch (Throwable $e) {
            $flash = ['type'=>'danger','msg'=>'حدث خطأ: ' . $e->getMessage()];
        }
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$filterLang = strtolower(trim((string)($_GET['lang'] ?? '')));
$filterStatus = trim((string)($_GET['status'] ?? ''));
if ($filterLang !== '' && !in_array($filterLang, ['en','fr'], true)) $filterLang = '';
if ($filterStatus !== '' && !in_array($filterStatus, ['draft','published'], true)) $filterStatus = '';

// Fetch edit row
$editRow = null;
if ($editId > 0) {
    $sql = "SELECT t.*, n.title AS news_title, n.slug AS news_slug, n.$newsOwnerCol AS news_owner_id, n.status AS status
            FROM news_translations t
            JOIN news n ON n.id = t.news_id
            WHERE t.id = :id LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':id'=>$editId]);
    $editRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($editRow && $isWriter && (int)$editRow['news_owner_id'] !== $userId) {
        $editRow = null;
        $editId = 0;
    }
}

// List
$where = [];
$bind = [];
if ($filterLang !== '') { $where[] = 't.lang = :lang'; $bind[':lang'] = $filterLang; }
if ($filterStatus !== '') { $where[] = 'n.status = :s'; $bind[':s'] = $filterStatus; }
if ($isWriter) { $where[] = 'n.' . $newsOwnerCol . ' = :u'; $bind[':u'] = $userId; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT t.id, t.news_id, t.lang, n.status AS status, t.created_at,
               COALESCE(t.title, '') AS tr_title,
               n.title AS news_title, n.slug AS news_slug
        FROM news_translations t
        JOIN news n ON n.id = t.news_id
        $whereSql
        ORDER BY t.id DESC
        LIMIT 200";
$st = $pdo->prepare($sql);
$st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

require __DIR__ . '/../layout/app_start.php';
?>

<?php if ($flash['msg'] !== ''): ?>
  <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<div class="row g-3">


    <div class="col-12 col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div class="fw-bold">الترجمات الأخيرة</div>
          <form class="d-flex gap-2" method="get" action="translations.php">
            <select name="lang" class="form-select form-select-sm" style="min-width:120px">
              <option value="">كل اللغات</option>
              <option value="en" <?= $filterLang==='en'?'selected':'' ?>>EN</option>
              <option value="fr" <?= $filterLang==='fr'?'selected':'' ?>>FR</option>
            </select>
            <select name="status" class="form-select form-select-sm" style="min-width:140px">
              <option value="">كل الحالات</option>
              <option value="draft" <?= $filterStatus==='draft'?'selected':'' ?>>مسودة</option>
              <option value="published" <?= $filterStatus==='published'?'selected':'' ?>>منشور</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary">تصفية</button>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:80px">#</th>
                <th>المقال</th>
                <th style="width:70px">اللغة</th>
                <th style="width:110px">الحالة</th>
                <th style="width:110px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td>
                    <div class="fw-semibold"><?= h((string)$r['news_title']) ?></div>
                    <div class="small text-muted">ID: <?= (int)$r['news_id'] ?> • <?= h((string)$r['tr_title']) ?></div>
                  </td>
                  <td><span class="badge text-bg-secondary"><?= h(strtoupper((string)$r['lang'])) ?></span></td>
                  <td>
                    <?php if ((string)$r['status'] === 'published'): ?>
                      <span class="badge text-bg-success">منشور</span>
                    <?php else: ?>
                      <span class="badge text-bg-warning">مسودة</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-primary" href="translations.php?edit=<?= (int)$r['id'] ?>">تحرير</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">لا توجد ترجمات</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="fw-bold"><?= $editRow ? 'تحرير ترجمة' : 'إنشاء ترجمة' ?></div>
          <div class="small text-muted">* يدعم توليد آلي (زر)</div>
        </div>
        <div class="card-body">
          <form method="post" action="translations.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">

            <div class="mb-2">
              <label class="form-label">ID المقال</label>
              <input class="form-control" name="news_id" type="number" min="1" value="<?= (int)($editRow['news_id'] ?? (int)($_GET['news_id'] ?? 0)) ?>" <?= $editRow ? 'readonly' : '' ?> required>
              <?php if ($editRow): ?>
                <div class="small text-muted mt-1"><?= h((string)$editRow['news_title']) ?></div>
              <?php endif; ?>
            </div>

            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">اللغة</label>
                <select class="form-select" name="lang">
                  <option value="en" <?= (($editRow['lang'] ?? '')==='en'?'selected':'') ?>>EN</option>
                  <option value="fr" <?= (($editRow['lang'] ?? '')==='fr'?'selected':'') ?>>FR</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">الحالة</label>
                <select class="form-select" name="status">
                  <option value="draft" <?= (($editRow['status'] ?? '')!=='published'?'selected':'') ?>>مسودة</option>
                  <option value="published" <?= (($editRow['status'] ?? '')==='published'?'selected':'') ?>>منشور</option>
                </select>
              </div>
            </div>

            <div class="mb-2 mt-2">
              <label class="form-label">العنوان</label>
              <input class="form-control" name="title" value="<?= h((string)($editRow['title'] ?? '')) ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">المحتوى (HTML)</label>
              <textarea class="form-control" name="content" rows="10"><?= h((string)($editRow['content'] ?? '')) ?></textarea>
            </div>

            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-success">حفظ</button>
              <?php if ($editRow): ?>
                <button type="button" class="btn btn-outline-primary" id="btnAutoTranslate">توليد ترجمة آليًا</button>
              <?php endif; ?>
            </div>
          </form>

          <?php if ($editRow): ?>
            <form method="post" action="translations.php" class="mt-2" data-confirm='حذف الترجمة نهائيًا؟'>
              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
              <button class="btn btn-outline-danger" type="submit">حذف الترجمة</button>
            </form>
          <?php endif; ?>

          <div class="mt-3 small text-muted">
            <div>زر “توليد ترجمة آليًا” يستخدم API: <code>/api/news/translate</code> ويضع النتيجة في الحقول ثم يمكنك حفظها كمسودة/منشور.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var btn = document.getElementById('btnAutoTranslate');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    try {
      var nid = document.querySelector('input[name="news_id"]').value;
      var lang = document.querySelector('select[name="lang"]').value;
      if (!nid) return alert('حدد ID المقال');
      btn.disabled = true;
      btn.textContent = 'جاري الترجمة...';
      const r = await fetch('/api/news/translate?news_id=' + encodeURIComponent(nid) + '&lang=' + encodeURIComponent(lang), {credentials:'same-origin'});
      const j = await r.json();
      if (!j || !j.ok) throw new Error((j && j.error) ? j.error : 'فشل');
      if (j.title) document.querySelector('input[name="title"]').value = j.title;
      if (j.content) document.querySelector('textarea[name="content"]').value = j.content;
      alert('تم توليد الترجمة. اضغط حفظ لتخزينها.');
    } catch(e){
      alert('تعذر الترجمة: ' + e.message);
    } finally {
      btn.disabled = false;
      btn.textContent = 'توليد ترجمة آليًا';
    }
  });
})();
</script>

<?php require __DIR__ . '/../layout/app_end.php'; ?>
