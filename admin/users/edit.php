<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/users/edit.php — تعديل مستخدم

require_once __DIR__ . '/../../includes/bootstrap.php';

$authFile = __DIR__ . '/../../includes/auth.php';
if (is_file($authFile)) {
    require_once $authFile;
}

use Godyar\Auth;

$currentPage = 'users';
$pageTitle   = __('t_2b24168f6d', 'تعديل مستخدم');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// التحقق من الدخول
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
    @error_log('[Admin Users Edit] Auth: '.$e->getMessage());
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();

// Helper: get table columns even if db_table_columns() isn't available in this context.
if (!function_exists('gdy_table_columns')) {
    function gdy_table_columns(PDO $pdo, string $table): array {
        try {
            if (function_exists('db_table_columns')) {
                $cols = db_table_columns($pdo, $table);
                if (is_array($cols) && !empty($cols)) return $cols;
            }
            $safeTable = str_replace('`', '', $table);
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$safeTable}`");
            if (!$stmt) return [];
            $cols = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['Field'])) $cols[] = (string)$row['Field'];
            }
            return $cols;
        } catch (Throwable $e) {
            return [];
        }
    }
}
// اكتشاف أعمدة جدول users لضمان التوافق بين الإصدارات (password/password_hash/...)
$userCols = [];
try {
    if ($pdo instanceof PDO) {
        $userCols = gdy_table_columns($pdo, 'users');
    }
} catch (Throwable $e) {
    $userCols = [];
}

// اختيار عمود كلمة المرور الصحيح
$passwordCol = null;
if (!empty($userCols)) {
    foreach (['password_hash','pass_hash','password','passwd','password_digest'] as $c) {
        if (in_array($c, $userCols, true)) { $passwordCol = $c; break; }
    }
}

// اختيار أعمدة timestamps إذا كانت موجودة
$hasUpdatedAt = !empty($userCols) && in_array('updated_at', $userCols, true);

$roles    = ['superadmin','admin','editor','author','user'];
$statuses = ['active','inactive','banned'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$data   = [];
$errors = [];

// CSRF
if (empty($_SESSION['users_csrf'])) {
    $_SESSION['users_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['users_csrf'];

// جلب المستخدم
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        @error_log('[Admin Users Edit] fetch: '.$e->getMessage());
    }
}

if (!$data) {
    require_once __DIR__ . '/../layout/header.php';
    require_once __DIR__ . '/../layout/sidebar.php';
    echo __('t_b859374765', '<div class="admin-content container-fluid py-4"><div class="alert alert-danger">المستخدم غير موجود.</div></div>');
    require_once __DIR__ . '/../layout/footer.php';
    exit;
}

// تجهيز قيم editable
$dataForm = [
    'username' => $data['username'],
    'name'     => $data['name'] ?? '',
    'email'    => $data['email'],
    'role'     => $data['role'],
    'status'   => $data['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? '';
    if (!hash_equals($csrf, $token)) {
        $errors[] = __('t_1961026b59', 'انتهت صلاحية النموذج، أعد تحميل الصفحة وحاول مرة أخرى.');
    } else {
        $dataForm['username'] = trim((string)($_POST['username'] ?? ''));
        $dataForm['name']     = trim((string)($_POST['name'] ?? ''));
        $dataForm['email']    = trim((string)($_POST['email'] ?? ''));
        $dataForm['role']     = trim((string)($_POST['role'] ?? $data['role']));
        $dataForm['status']   = trim((string)($_POST['status'] ?? $data['status']));

        $password        = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($dataForm['username'] === '') {
            $errors[] = __('t_e4b3a035cc', 'اسم المستخدم مطلوب.');
        }
        if ($dataForm['email'] === '' || !filter_var($dataForm['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('t_c7e0030d94', 'بريد إلكتروني صالح مطلوب.');
        }
        if ($password !== '' && strlen($password) < 6) {
            $errors[] = __('t_67de36ecdc', 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل.');
        }
        if ($password !== '' && $password !== $passwordConfirm) {
            $errors[] = __('t_8267fda130', 'تأكيد كلمة المرور غير مطابق.');
        }
        if (!in_array($dataForm['role'], $roles,true)) {
            $errors[] = __('t_e5ede8cea8', 'دور غير صالح.');
        }
        if (!in_array($dataForm['status'], $statuses,true)) {
            $errors[] = __('t_7dbf55664a', 'حالة غير صالحة.');
        }

        // التأكد من عدم تكرار username/email لمستخدم آخر
        if (!$errors && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM users
                    WHERE (username = :u OR email = :e) AND id <> :id
                ");
                $stmt->execute([
                    ':u'  => $dataForm['username'],
                    ':e'  => $dataForm['email'],
                    ':id' => $id,
                ]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = __('t_9e53c1cbe6', 'اسم المستخدم أو البريد الإلكتروني مستخدم من حساب آخر.');
                }
            } catch (Throwable $e) {
                @error_log('[Admin Users Edit] unique check: '.$e->getMessage());
            }
        }

        if (!$errors && $pdo instanceof PDO) {
            try {
                $params = [
                    ':u'  => $dataForm['username'],
                    ':n'  => $dataForm['name'],
                    ':e'  => $dataForm['email'],
                    ':r'  => $dataForm['role'],
                    ':s'  => $dataForm['status'],
                    ':id' => $id,
                ];

                $setPassword = '';
                if ($password !== '') {
                    if ($passwordCol) {
                        $setPassword = ", {$passwordCol} = :p";
                        $params[':p'] = password_hash($password, PASSWORD_DEFAULT);
                    } else {
                        // لا نكسر التحديث بالكامل، لكن نُظهر رسالة واضحة
                        $errors[] = __('t_18d3a5b0f5', 'لا يمكن تحديث كلمة المرور لأن جدول المستخدمين لا يحتوي على عمود مناسب لحفظ كلمة المرور.');
                    }
                }

                // إذا ظهرت أخطاء بعد محاولة تحديد عمود كلمة المرور، لا ننفذ التحديث
                if (!$errors) {
                    $setUpdatedAt = $hasUpdatedAt ? ', updated_at = NOW()' : '';

                    $sql = "
                        UPDATE users
                        SET username = :u,
                            name     = :n,
                            email    = :e,
                            role     = :r,
                            status   = :s
                            {$setPassword}
                            {$setUpdatedAt}
                        WHERE id = :id
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    header('Location: index.php?updated=1');
                    exit;
                }
            } catch (Throwable $e) {
                @error_log('[Admin Users Edit] update: '.$e->getMessage());
                $errors[] = __('t_ebd9a4245e', 'حدث خطأ أثناء تحديث بيانات المستخدم.');
            }
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
:root{
    /* نضغط عرض محتوى صفحة إدارة المستخدمين ليكون مريحاً بجانب السايدبار */
    --gdy-shell-max: min(880px, 100vw - 360px);
}

html, body{
    overflow-x: hidden;
    background: #020617;
    color: #e5e7eb;
}

.admin-content{
    max-width: var(--gdy-shell-max);
    width: 100%;
    margin: 0 auto;
}

/* تقليل الفراغ العمودي الافتراضي داخل صفحات الإدارة */
.admin-content.container-fluid.py-4{
    padding-top: 0.75rem !important;
    padding-bottom: 1rem !important;
}

/* توحيد مسافة رأس الصفحة */
.gdy-page-header{
    margin-bottom: 0.75rem;
}
</style>

<div class="admin-content container-fluid py-4">

  <div class="d-flex justify-content-between align-items-start align-items-md-center mb-3">
    <div>
      <h1 class="h4 mb-1">تعديل مستخدم #<?= (int)$id ?></h1>
      <p class="text-muted mb-0"><?= h($data['username']) ?> - <?= h($data['email']) ?></p>
    </div>
    <div class="mt-2 mt-md-0">
      <a href="index.php" class="btn btn-sm btn-outline-secondary">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_5c3059454f', 'الرجوع للقائمة')) ?>
      </a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
          <li><?= h($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <input type="hidden" name="_token" value="<?= h($csrf) ?>">

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label"><?= h(__('t_f6767f689d', 'اسم المستخدم *')) ?></label>
            <input type="text" name="username" class="form-control" required
                   value="<?= h($dataForm['username']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label"><?= h(__('t_90f9115ac9', 'الاسم الكامل')) ?></label>
            <input type="text" name="name" class="form-control"
                   value="<?= h($dataForm['name']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label"><?= h(__('t_b052caae3b', 'البريد الإلكتروني *')) ?></label>
            <input type="email" name="email" class="form-control" required
                   value="<?= h($dataForm['email']) ?>">
          </div>
        </div>

        <hr>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label"><?= h(__('t_9d5f0f2c2c', 'كلمة المرور الجديدة (اختياري)')) ?></label>
            <input type="password" name="password" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label"><?= h(__('t_6a507e0aa9', 'تأكيد كلمة المرور')) ?></label>
            <input type="password" name="password_confirm" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label"><?= h(__('t_1647921065', 'الدور')) ?></label>
            <select name="role" class="form-select">
              <?php foreach ($roles as $r): ?>
                <option value="<?= h($r) ?>" <?= $dataForm['role']===$r?'selected':'' ?>>
                  <?= h($r) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
            <select name="status" class="form-select">
              <option value="active"   <?= $dataForm['status']==='active'?'selected':'' ?>><?= h(__('t_8caaf95380', 'نشط')) ?></option>
              <option value="inactive" <?= $dataForm['status']==='inactive'?'selected':'' ?>><?= h(__('t_1e0f5f1adc', 'غير نشط')) ?></option>
              <option value="banned"   <?= $dataForm['status']==='banned'?'selected':'' ?>><?= h(__('t_e59b95cb50', 'محظور')) ?></option>
            </select>
          </div>
        </div>

        <div class="mt-4">
          <button type="submit" class="btn btn-primary">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_91d6db7f39', 'حفظ التعديلات')) ?>
          </button>
        </div>
      </form>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
