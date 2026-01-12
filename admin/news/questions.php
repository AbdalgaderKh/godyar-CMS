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

$currentPage = 'questions';
$pageTitle   = 'اسأل الكاتب';

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database not available');
}

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS news_questions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        news_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        name VARCHAR(120) NULL,
        email VARCHAR(190) NULL,
        question TEXT NOT NULL,
        answer TEXT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at DATETIME NULL,
        answered_at DATETIME NULL,
        KEY idx_news (news_id),
        KEY idx_status (status)
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

try {
    // Ensure table exists (some hostings deny CREATE TABLE at runtime)
    $pdo->query("SELECT 1 FROM news_questions LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS news_questions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            news_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            name VARCHAR(120) NULL,
            email VARCHAR(190) NULL,
            question TEXT NOT NULL,
            answer TEXT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at DATETIME NULL,
            answered_at DATETIME NULL,
            KEY idx_news (news_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e2) {
        // Show a friendly hint instead of a fatal error.
        $GLOBALS['__gdy_questions_table_missing'] = true;
    }
}

$flash = ['type'=>'', 'msg'=>''];

if (!empty($GLOBALS['__gdy_questions_table_missing'])) {
    $flash = [
        'type' => 'warning',
        'msg'  => 'جدول "news_questions" غير موجود أو لا يمكن إنشاؤه تلقائيًا. شغّل ملف SQL: database/migrations/2026_01_02_create_news_questions.sql'
    ];
}



if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!$checkCsrf($csrf)) {
        $flash = ['type'=>'danger', 'msg'=>'رمز الحماية غير صحيح.'];
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $answer = trim((string)($_POST['answer'] ?? ''));
        $newStatus = (string)($_POST['status'] ?? '');
        if (!in_array($newStatus, ['pending','approved','rejected',''], true)) $newStatus = '';

        try {
            if ($id <= 0) throw new RuntimeException('id required');

            // permission: writer only on their news questions
            if ($isWriter) {
                $st = $pdo->prepare("SELECT 1 FROM news_questions q JOIN news n ON n.id=q.news_id WHERE q.id=:id AND n.author_id=:u LIMIT 1");
                $st->execute([':id'=>$id, ':u'=>$userId]);
                if (!$st->fetchColumn()) throw new RuntimeException('غير مصرح');
            }

            if ($action === 'delete') {
                $pdo->prepare('DELETE FROM news_questions WHERE id=:id')->execute([':id'=>$id]);
                $flash = ['type'=>'success', 'msg'=>'تم حذف السؤال.'];
            } elseif ($action === 'set_status') {
                if ($newStatus === '') throw new RuntimeException('status required');
                $pdo->prepare('UPDATE news_questions SET status=:s WHERE id=:id')->execute([':s'=>$newStatus, ':id'=>$id]);
                $flash = ['type'=>'success', 'msg'=>'تم تحديث الحالة.'];
            } elseif ($action === 'answer') {
                if ($answer === '') throw new RuntimeException('الرد مطلوب');
                // الإجابة تُعتمد تلقائياً
                $pdo->prepare("UPDATE news_questions SET answer=:a, status='approved', answered_at=NOW() WHERE id=:id")
                    ->execute([':a'=>$answer, ':id'=>$id]);
                $flash = ['type'=>'success', 'msg'=>'تم حفظ الرد.'];
            }
        } catch (Throwable $e) {
            $flash = ['type'=>'danger', 'msg'=>'حدث خطأ: ' . $e->getMessage()];
        }
    }
}

$filterStatus = trim((string)($_GET['status'] ?? ''));
if ($filterStatus !== '' && !in_array($filterStatus, ['pending','approved','rejected'], true)) $filterStatus = '';
$filterNewsId = (int)($_GET['news_id'] ?? 0);

$where = [];
$bind = [];
if ($filterStatus !== '') { $where[] = 'q.status = :s'; $bind[':s'] = $filterStatus; }
if ($filterNewsId > 0) { $where[] = 'q.news_id = :n'; $bind[':n'] = $filterNewsId; }
if ($isWriter) { $where[] = 'n.author_id = :u'; $bind[':u'] = $userId; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = [];
if (empty($GLOBALS['__gdy_questions_table_missing'])) {
    try {
    $sql = "SELECT q.*, n.title AS news_title
            FROM news_questions q
            JOIN news n ON n.id=q.news_id
            $whereSql
            ORDER BY q.id DESC
            LIMIT 200";
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $flash = ['type'=>'warning', 'msg'=>'تعذر قراءة جدول الأسئلة. ' . $e->getMessage()];
        $rows = [];
    }
}

require __DIR__ . '/header.php';
require __DIR__ . '/sidebar.php';
?>

<main class="container-fluid">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 m-0">اسأل الكاتب</h1>
    <form class="d-flex flex-wrap gap-2" method="get" action="questions.php">
      <input type="number" class="form-control form-control-sm" name="news_id" placeholder="ID المقال" value="<?= (int)$filterNewsId ?>" style="max-width:140px">
      <select class="form-select form-select-sm" name="status" style="max-width:160px">
        <option value="">كل الحالات</option>
        <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>قيد المراجعة</option>
        <option value="approved" <?= $filterStatus==='approved'?'selected':'' ?>>مقبول</option>
        <option value="rejected" <?= $filterStatus==='rejected'?'selected':'' ?>>مرفوض</option>
      </select>
      <button class="btn btn-sm btn-outline-secondary">تصفية</button>
    </form>
  </div>

  <?php if ($flash['msg'] !== ''): ?>
    <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:80px">#</th>
            <th style="width:260px">المقال</th>
            <th>السؤال</th>
            <th style="width:140px">الحالة</th>
            <th style="width:340px">الرد</th>
            <th style="width:180px"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td>
              <div class="fw-semibold"><?= h((string)$r['news_title']) ?></div>
              <div class="small text-muted">ID: <?= (int)$r['news_id'] ?></div>
            </td>
            <td>
              <div class="small text-muted mb-1">
                <?= h(trim((string)($r['name'] ?? ''))) ?>
                <?= ($r['email']? '• ' . h((string)$r['email']) : '') ?>
              </div>
              <div><?= nl2br(h((string)$r['question'])) ?></div>
            </td>
            <td>
              <?php $stt = (string)$r['status']; ?>
              <?php if ($stt==='pending'): ?>
                <span class="badge text-bg-warning">قيد المراجعة</span>
              <?php elseif ($stt==='approved'): ?>
                <span class="badge text-bg-success">مقبول</span>
              <?php else: ?>
                <span class="badge text-bg-secondary">مرفوض</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (trim((string)$r['answer']) !== ''): ?>
                <div><?= nl2br(h((string)$r['answer'])) ?></div>
                <div class="small text-muted mt-1"><?= h((string)($r['answered_at'] ?? '')) ?></div>
              <?php else: ?>
                <form method="post" action="questions.php" class="d-flex gap-2">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                  <input type="hidden" name="action" value="answer">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <textarea class="form-control form-control-sm" name="answer" rows="2" placeholder="اكتب الرد..."></textarea>
                  <button class="btn btn-sm btn-success" type="submit">حفظ</button>
                </form>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <div class="d-flex justify-content-end gap-2 flex-wrap">
                <form method="post" action="questions.php">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="status" value="approved">
                  <button class="btn btn-sm btn-outline-success" type="submit">قبول</button>
                </form>
                <form method="post" action="questions.php">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="status" value="rejected">
                  <button class="btn btn-sm btn-outline-secondary" type="submit">رفض</button>
                </form>
                <form method="post" action="questions.php" data-confirm='حذف السؤال؟'>
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">حذف</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">لا توجد أسئلة</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3 small text-muted">
    الأسئلة المنشورة للمستخدمين هي <b>approved</b> فقط. عند إضافة رد يتم اعتماد السؤال تلقائياً.
  </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>
