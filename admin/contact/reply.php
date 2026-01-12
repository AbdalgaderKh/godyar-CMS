<?php


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'contact';
$pageTitle   = __('t_35e98a991a', 'الرد على رسالة تواصل');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// 1) التحقق من تسجيل الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class,'isLoggedIn')) {
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
    @error_log('[Admin Contact Reply] Auth: '.$e->getMessage());
    header('Location: ../login.php');
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database connection not available.');
}

// جلب ID الرسالة
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// 2) جلب الرسالة
$row = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    @error_log('[Admin Contact Reply] fetch: '.$e->getMessage());
}

if (!$row) {
    require_once __DIR__ . '/../layout/header.php';
    require_once __DIR__ . '/../layout/sidebar.php';
    echo __('t_9aff8eacf6', '<div class="admin-content container-fluid py-4"><div class="alert alert-danger">الرسالة غير موجودة.</div></div>');
    require_once __DIR__ . '/../layout/footer.php';
    exit;
}

// 3) إعداد CSRF بسيط
if (empty($_SESSION['contact_reply_csrf'])) {
    $_SESSION['contact_reply_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['contact_reply_csrf'];

$errors  = [];
$success = false;

// جلب بيانات المستخدم الحالي إن وُجدت
$currentUser = [];
if (class_exists(Auth::class) && method_exists(Auth::class, 'user')) {
    try {
        $currentUser = Auth::user() ?? [];
    } catch (Throwable $e) {
        $currentUser = $_SESSION['user'] ?? [];
    }
} else {
    $currentUser = $_SESSION['user'] ?? [];
}

// عنوان افتراضي للرد
$replySubject = __('t_f05a61edf6', 'رد على رسالتك: ') . (string)($row['subject'] ?? '');

// 4) معالجة POST (إرسال الرد)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? '';
    if (!hash_equals($csrfToken, $token)) {
        $errors[] = __('t_7339ca256b', 'انتهت صلاحية النموذج، يرجى إعادة تحميل الصفحة ثم المحاولة مرة أخرى.');
    } else {
        $replyBody = trim((string)($_POST['reply_message'] ?? ''));
        if ($replyBody === '') {
            $errors[] = __('t_e2ecaa68d3', 'نص الرد مطلوب.');
        }

        if (!$errors) {
            $to      = (string)($row['email'] ?? '');
            if ($to === '') {
                $errors[] = __('t_ef56189125', 'لا يمكن إرسال البريد لأن حقل البريد الإلكتروني فارغ في الرسالة.');
            } else {
                $subject = $replySubject;

                // إعداد بريد المرسل
                $defaultFrom = 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

                // التعامل مع env() إن كانت موجودة، وإلا fallback
                if (function_exists('env')) {
                    $fromEmail = (string)env('MAIL_FROM_ADDRESS', $defaultFrom);
                    $fromName  = (string)env('MAIL_FROM_NAME', __('t_f71ce920b0', 'إدارة الموقع'));
                } else {
                    $fromEmail = $defaultFrom;
                    $fromName  = __('t_f71ce920b0', 'إدارة الموقع');
                }

                // هيدر البريد
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
                $headers .= 'From: ' . sprintf('"%s" <%s>', mb_encode_mimeheader($fromName, 'UTF-8'), $fromEmail) . "\r\n";
                $headers .= "Reply-To: " . $fromEmail . "\r\n";

                // جسم البريد
                $body  = __('t_7e922ad95c', "السلام عليكم ") . (string)($row['name'] ?? '') . ",\n\n";
                $body .= $replyBody . "\n\n";
                $body .= "----------------------\n";
                $body .= __('t_9e14fb3581', "نسخة من رسالتك الأصلية:\n");
                $body .= __('t_d62578122d', "العنوان: ") . (string)($row['subject'] ?? '') . "\n";
                $body .= __('t_d712c5a0d4', "الرسالة:\n") . (string)($row['message'] ?? '') . "\n";
                $body .= "----------------------\n";
                $body .= __('t_2bc39ee8e6', "مع تحيات إدارة الموقع.\n");

                $sent = false;
                try {
                    // قد لا تكون mail() مفعّلة على الخادم
                    $sent = @mail(
                        $to,
                        '=?UTF-8?B?' . base64_encode($subject) . '?=',
                        $body,
                        $headers
                    );
                } catch (Throwable $e) {
                    @error_log('[Admin Contact Reply] mail error: '.$e->getMessage());
                }

                // تحديث حالة الرسالة في قاعدة البيانات
                try {
                    $stmt = $pdo->prepare("
                        UPDATE contact_messages
                        SET status = 'replied',
                            updated_at = NOW()
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $stmt->execute([':id' => $id]);
                    $success = true;

                    // إعادة توليد CSRF لتفادي إعادة الإرسال
                    $_SESSION['contact_reply_csrf'] = bin2hex(random_bytes(16));
                    $csrfToken = $_SESSION['contact_reply_csrf'];
                } catch (Throwable $e) {
                    @error_log('[Admin Contact Reply] update DB: '.$e->getMessage());
                    $errors[] = __('t_616f4954e6', 'تمت محاولة إرسال البريد لكن حدث خطأ أثناء تحديث حالة الرسالة في قاعدة البيانات.');
                }

                if (!$sent) {
                    $errors[] = __('t_846b05e381', 'تم حفظ الرد كـ "تم الرد"، لكن قد لا يكون البريد قد أُرسل (دالة mail قد لا تكون مفعّلة على الخادم).');
                }
            }
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
:root{
    --gdy-shell-max: min(880px, 100vw - 360px);
}
html, body{
    overflow-x:hidden;
    background:#020617;
    color:#e5e7eb;
}
.admin-content{
    max-width: var(--gdy-shell-max);
    width:100%;
    margin:0 auto;
}
.admin-content.container-fluid.py-4{
    padding-top:0.75rem !important;
    padding-bottom:1rem !important;
}
.gdy-page-header{
    margin-bottom:0.75rem;
}
.gdy-glass-card{
    background:rgba(15,23,42,0.96);
    border-radius:16px;
    border:1px solid rgba(31,41,55,0.9);
}
</style>

<div class="admin-content container-fluid py-4">

  <!-- رأس الصفحة بدون أزرار الرجوع/القائمة -->
  <div class="admin-content gdy-page-header mb-3">
    <h1 class="h4 mb-1 text-white">الرد على رسالة #<?= (int)$row['id'] ?></h1>
    <p class="text-muted mb-0 small">
      إلى: <?= h($row['name'] ?? '') ?> &lt;<?= h($row['email'] ?? '') ?>&gt;
    </p>
  </div>

  <?php if ($success && !$errors): ?>
    <div class="alert alert-success">
      <?= h(__('t_05e43a440b', 'تم حفظ الرد وتحديث حالة الرسالة إلى')) ?> <strong><?= h(__('t_37d8352070', 'تم الرد')) ?></strong>.
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
          <li><?= h($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm gdy-glass-card mb-3">
        <div class="card-header">
          <h2 class="h6 mb-0"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_4787d5c82a', 'نموذج الرد')) ?></h2>
        </div>
        <div class="card-body">
          <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <input type="hidden" name="_token" value="<?= h($csrfToken) ?>">

            <div class="mb-3">
              <label class="form-label"><?= h(__('t_0985d27cd3', 'إلى')) ?></label>
              <input type="text"
                     class="form-control form-control-sm"
                     value="<?= h(($row['name'] ?? '') . ' <' . ($row['email'] ?? '') . '>') ?>"
                     disabled>
            </div>

            <div class="mb-3">
              <label class="form-label"><?= h(__('t_98e4abe64d', 'عنوان الرسالة')) ?></label>
              <input type="text"
                     class="form-control form-control-sm"
                     value="<?= h($replySubject) ?>"
                     disabled>
            </div>

            <div class="mb-3">
              <label class="form-label"><?= h(__('t_9022e08be3', 'نص الرد')) ?></label>
              <textarea name="reply_message" rows="6" class="form-control" required></textarea>
            </div>

            <!-- هنا الأزرار الثلاثة بجوار زر إرسال الرد -->
            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-success">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_6498ff15e8', 'إرسال الرد')) ?>
              </button>

              <a href="view.php?id=<?= (int)$row['id'] ?>" class="btn btn-outline-light">
                <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_141defec85', 'الرجوع للرسالة')) ?>
              </a>

              <a href="index.php" class="btn btn-outline-secondary">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_5ca9cfef90', 'قائمة الرسائل')) ?>
              </a>
            </div>

          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm gdy-glass-card mb-3">
        <div class="card-header">
          <h2 class="h6 mb-0"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_55a71fa0ae', 'معاينة الرسالة الأصلية')) ?></h2>
        </div>
        <div class="card-body small">
          <p class="mb-1"><strong><?= h(__('t_d62578122d', 'العنوان:')) ?></strong> <?= h($row['subject'] ?? '') ?></p>
          <p class="mb-1"><strong><?= h(__('t_565263bfb7', 'تاريخ الإرسال:')) ?></strong> <?= h($row['created_at'] ?? '') ?></p>
          <hr>
          <div style="max-height: 260px; overflow:auto; border-radius:.5rem; background:#0f172a; padding:.75rem;">
            <pre class="mb-0" style="white-space: pre-wrap; color:#e5e7eb; font-family:inherit; font-size:.85rem;">
<?= h($row['message'] ?? '') ?>
            </pre>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
