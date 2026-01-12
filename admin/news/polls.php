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

$currentPage = 'polls';
$pageTitle   = 'إدارة الاستطلاعات';

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database not available');
}

// Ensure tables exist (best effort)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS news_polls (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        news_id INT UNSIGNED NOT NULL,
        question VARCHAR(255) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NULL,
        KEY idx_news (news_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS news_poll_options (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        poll_id INT UNSIGNED NOT NULL,
        label VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        KEY idx_poll (poll_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS news_poll_votes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        poll_id INT UNSIGNED NOT NULL,
        option_id INT UNSIGNED NOT NULL,
        voter_key VARCHAR(80) NOT NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL,
        UNIQUE KEY uniq_vote (poll_id, voter_key),
        KEY idx_option (option_id)
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

$flash = ['type'=>'', 'msg'=>''];

// POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!$checkCsrf($csrf)) {
        $flash = ['type'=>'danger', 'msg'=>'رمز الحماية غير صحيح.'];
    } else {
        $action = (string)($_POST['action'] ?? '');
        $pollId = (int)($_POST['poll_id'] ?? 0);
        $newsId = (int)($_POST['news_id'] ?? 0);
        $question = trim((string)($_POST['question'] ?? ''));
        $isActive = ((int)($_POST['is_active'] ?? 0) === 1) ? 1 : 0;

        try {
            if ($action === 'create') {
                if ($newsId <= 0) throw new RuntimeException('news_id required');
                if ($question === '') throw new RuntimeException('السؤال مطلوب');

                if ($isWriter) {
                    $ok = false;
                    try {
                        // بعض النسخ تستخدم author_id بدلاً من user_id
                        $st = $pdo->prepare('SELECT 1 FROM news WHERE id=:id AND author_id=:u LIMIT 1');
                        $st->execute([':id'=>$newsId, ':u'=>$userId]);
                        $ok = (bool)$st->fetchColumn();
                    } catch (Throwable $e) {
                        $ok = false;
                    }
                    if (!$ok) {
                        try {
                            $st = $pdo->prepare('SELECT 1 FROM news WHERE id=:id AND user_id=:u LIMIT 1');
                            $st->execute([':id'=>$newsId, ':u'=>$userId]);
                            $ok = (bool)$st->fetchColumn();
                        } catch (Throwable $e) {
                            $ok = false;
                        }
                    }
                    if (!$ok) throw new RuntimeException('غير مصرح');
                }

                $pdo->beginTransaction();
                $st = $pdo->prepare("INSERT INTO news_polls (news_id, question, is_active, created_at) VALUES (:n,:q,:a,NOW())");
                $st->execute([':n'=>$newsId, ':q'=>$question, ':a'=>$isActive]);
                $pollId = (int)$pdo->lastInsertId();

                $optsText = (string)($_POST['options'] ?? '');
                $opts = preg_split('~\r?\n~', trim($optsText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $order = 0;
                foreach ($opts as $opt) {
                    $opt = trim((string)$opt);
                    if ($opt === '') continue;
                    $st = $pdo->prepare("INSERT INTO news_poll_options (poll_id,label,sort_order) VALUES (:p,:l,:o)");
                    $st->execute([':p'=>$pollId, ':l'=>$opt, ':o'=>$order]);
                    $order += 10;
                }
                $pdo->commit();
                header('Location: polls.php?edit=' . $pollId);
                exit;
            }

            if ($action === 'save' && $pollId > 0) {
                // permission
                if ($isWriter) {
                    $st = $pdo->prepare('SELECT 1 FROM news_polls p JOIN news n ON n.id=p.news_id WHERE p.id=:p AND n.author_id=:u LIMIT 1');
                    $st->execute([':p'=>$pollId, ':u'=>$userId]);
                    if (!$st->fetchColumn()) throw new RuntimeException('غير مصرح');
                }

                $st = $pdo->prepare('UPDATE news_polls SET question=:q, is_active=:a WHERE id=:p');
                $st->execute([':q'=>$question, ':a'=>$isActive, ':p'=>$pollId]);

                // update options
                $optIds = $_POST['opt_id'] ?? [];
                $optLabels = $_POST['opt_label'] ?? [];
                $optOrders = $_POST['opt_order'] ?? [];
                if (is_array($optIds)) {
                    for ($i=0; $i<count($optIds); $i++) {
                        $oid = (int)($optIds[$i] ?? 0);
                        $lbl = trim((string)($optLabels[$i] ?? ''));
                        $ord = (int)($optOrders[$i] ?? 0);
                        if ($oid <= 0) continue;
                        if ($lbl === '') {
                            $pdo->prepare('DELETE FROM news_poll_options WHERE id=:id')->execute([':id'=>$oid]);
                            continue;
                        }
                        $pdo->prepare('UPDATE news_poll_options SET label=:l, sort_order=:o WHERE id=:id')
                            ->execute([':l'=>$lbl, ':o'=>$ord, ':id'=>$oid]);
                    }
                }

                // new option
                $newOpt = trim((string)($_POST['new_option'] ?? ''));
                if ($newOpt !== '') {
                    $pdo->prepare('INSERT INTO news_poll_options (poll_id,label,sort_order) VALUES (:p,:l,:o)')
                        ->execute([':p'=>$pollId, ':l'=>$newOpt, ':o'=>9999]);
                }

                $flash = ['type'=>'success', 'msg'=>'تم حفظ الاستطلاع.'];
            }

            if ($action === 'reset_votes' && $pollId > 0) {
                if ($isWriter) {
                    $st = $pdo->prepare('SELECT 1 FROM news_polls p JOIN news n ON n.id=p.news_id WHERE p.id=:p AND n.author_id=:u LIMIT 1');
                    $st->execute([':p'=>$pollId, ':u'=>$userId]);
                    if (!$st->fetchColumn()) throw new RuntimeException('غير مصرح');
                }
                $pdo->prepare('DELETE FROM news_poll_votes WHERE poll_id=:p')->execute([':p'=>$pollId]);
                $flash = ['type'=>'success', 'msg'=>'تم تصفير الأصوات.'];
            }

            if ($action === 'delete' && $pollId > 0) {
                if ($isWriter) {
                    $st = $pdo->prepare('SELECT 1 FROM news_polls p JOIN news n ON n.id=p.news_id WHERE p.id=:p AND n.author_id=:u LIMIT 1');
                    $st->execute([':p'=>$pollId, ':u'=>$userId]);
                    if (!$st->fetchColumn()) throw new RuntimeException('غير مصرح');
                }
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM news_poll_votes WHERE poll_id=:p')->execute([':p'=>$pollId]);
                $pdo->prepare('DELETE FROM news_poll_options WHERE poll_id=:p')->execute([':p'=>$pollId]);
                $pdo->prepare('DELETE FROM news_polls WHERE id=:p')->execute([':p'=>$pollId]);
                $pdo->commit();
                header('Location: polls.php');
                exit;
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $flash = ['type'=>'danger', 'msg'=>'حدث خطأ: ' . $e->getMessage()];
        }
    }
}

$editId = (int)($_GET['edit'] ?? 0);

// Load edit poll
$editPoll = null;
$editOpts = [];
$editVotes = 0;
if ($editId > 0) {
    $sql = "SELECT p.*, n.title AS news_title, n.author_id AS news_author_id
            FROM news_polls p
            JOIN news n ON n.id=p.news_id
            WHERE p.id=:id LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':id'=>$editId]);
    $editPoll = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($editPoll && $isWriter && (int)$editPoll['news_author_id'] !== $userId) {
        $editPoll = null;
        $editId = 0;
    }
    if ($editPoll) {
        $st = $pdo->prepare('SELECT * FROM news_poll_options WHERE poll_id=:p ORDER BY sort_order ASC, id ASC');
        $st->execute([':p'=>$editId]);
        $editOpts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $st = $pdo->prepare('SELECT COUNT(*) FROM news_poll_votes WHERE poll_id=:p');
        $st->execute([':p'=>$editId]);
        $editVotes = (int)$st->fetchColumn();
    }
}

// List polls
$where = [];
$bind = [];
if ($isWriter) { $where[] = 'n.author_id = :u'; $bind[':u'] = $userId; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT p.id, p.news_id, p.question, p.is_active, p.created_at,
               n.title AS news_title
        FROM news_polls p
        JOIN news n ON n.id=p.news_id
        $whereSql
        ORDER BY p.id DESC
        LIMIT 200";
$st = $pdo->prepare($sql);
$st->execute($bind);
$polls = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

require __DIR__ . '/header.php';
require __DIR__ . '/sidebar.php';
?>

<main class="container-fluid">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">إدارة الاستطلاعات</h1>
    <div class="text-muted small">استطلاع واحد فعّال لكل مقال (آخر استطلاع يُعرض)</div>
  </div>

  <?php if ($flash['msg'] !== ''): ?>
    <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header fw-bold">الاستطلاعات</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:80px">#</th>
                <th>المقال</th>
                <th>السؤال</th>
                <th style="width:90px">الحالة</th>
                <th style="width:100px"></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($polls as $p): ?>
              <tr>
                <td><?= (int)$p['id'] ?></td>
                <td>
                  <div class="fw-semibold"><?= h((string)$p['news_title']) ?></div>
                  <div class="small text-muted">ID: <?= (int)$p['news_id'] ?></div>
                </td>
                <td><?= h((string)$p['question']) ?></td>
                <td>
                  <?php if ((int)$p['is_active'] === 1): ?>
                    <span class="badge text-bg-success">فعّال</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">موقوف</span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><a class="btn btn-sm btn-primary" href="polls.php?edit=<?= (int)$p['id'] ?>">تحرير</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$polls): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">لا توجد استطلاعات بعد.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header fw-bold"><?= $editPoll ? 'تحرير استطلاع' : 'إنشاء استطلاع' ?></div>
        <div class="card-body">

          <?php if (!$editPoll): ?>
            <form method="post" action="polls.php">
              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
              <input type="hidden" name="action" value="create">

              <div class="mb-2">
                <label class="form-label">ID المقال</label>
                <input class="form-control" type="number" name="news_id" min="1" required>
              </div>

              <div class="mb-2">
                <label class="form-label">سؤال الاستطلاع</label>
                <input class="form-control" name="question" required>
              </div>

              <div class="mb-2">
                <label class="form-label">الخيارات (سطر لكل خيار)</label>
                <textarea class="form-control" name="options" rows="5" placeholder="نعم\nلا\nلا أعلم"></textarea>
              </div>

              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="isActive" name="is_active" checked>
                <label class="form-check-label" for="isActive">فعّال</label>
              </div>

              <button class="btn btn-success">إنشاء</button>
            </form>
          <?php else: ?>
            <form method="post" action="polls.php">
              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="poll_id" value="<?= (int)$editPoll['id'] ?>">

              <div class="mb-2">
                <label class="form-label">المقال</label>
                <div class="border rounded p-2 bg-light">
                  <div class="fw-semibold"><?= h((string)$editPoll['news_title']) ?></div>
                  <div class="small text-muted">ID: <?= (int)$editPoll['news_id'] ?> • الأصوات: <?= (int)$editVotes ?></div>
                </div>
              </div>

              <div class="mb-2">
                <label class="form-label">السؤال</label>
                <input class="form-control" name="question" value="<?= h((string)$editPoll['question']) ?>" required>
              </div>

              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="isActive2" name="is_active" <?= ((int)$editPoll['is_active']===1?'checked':'') ?>>
                <label class="form-check-label" for="isActive2">فعّال</label>
              </div>

              <div class="mb-2">
                <label class="form-label">الخيارات</label>
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead><tr><th>#</th><th>الخيار</th><th style="width:90px">الترتيب</th></tr></thead>
                    <tbody>
                    <?php foreach ($editOpts as $o): ?>
                      <tr>
                        <td><?= (int)$o['id'] ?><input type="hidden" name="opt_id[]" value="<?= (int)$o['id'] ?>"></td>
                        <td><input class="form-control form-control-sm" name="opt_label[]" value="<?= h((string)$o['label']) ?>"></td>
                        <td><input class="form-control form-control-sm" type="number" name="opt_order[]" value="<?= (int)$o['sort_order'] ?>"></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$editOpts): ?>
                      <tr><td colspan="3" class="text-muted">لا توجد خيارات بعد. أضف خياراً جديداً.</td></tr>
                    <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                <div class="input-group">
                  <span class="input-group-text">+</span>
                  <input class="form-control" name="new_option" placeholder="إضافة خيار جديد">
                </div>
                <div class="small text-muted mt-1">لحذف خيار: اتركه فارغاً ثم احفظ.</div>
              </div>

              <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-success">حفظ</button>
                <button class="btn btn-outline-warning" name="action" value="reset_votes" data-confirm='تصفير الأصوات؟'>تصفير الأصوات</button>
                <button class="btn btn-outline-danger" name="action" value="delete" data-confirm='حذف الاستطلاع نهائياً؟'>حذف</button>
              </div>
            </form>
          <?php endif; ?>

          <div class="mt-3 small text-muted">
            <div>الحماية من التحايل: التصويت يعتمد على <b>Cookie + IP</b> (للزوار) أو <b>معرّف المستخدم</b> (للمسجلين).</div>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>
