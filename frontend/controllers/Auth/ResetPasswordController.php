<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/godyar';
$pdo = gdy_pdo_safe();
$error   = null;
$success = null;

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$password = '';
$passwordConfirm = '';

if (empty($_SESSION['front_reset_csrf'])) {
    $_SESSION['front_reset_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['front_reset_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['front_reset_csrf'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
            throw new Exception('انتهت صلاحية الجلسة، يرجى تحديث الصفحة والمحاولة مرة أخرى.');
        }

        if (!$pdo instanceof PDO) {
            throw new Exception('لا يمكن الاتصال بقاعدة البيانات حالياً.');
        }

        $token = trim((string)($_POST['token'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirmation'] ?? '');

        if ($token === '') {
            throw new Exception('رابط الاستعادة غير صالح.');
        }

        if ($password === '' || $passwordConfirm === '') {
            throw new Exception('الرجاء إدخال كلمة المرور الجديدة وتأكيدها.');
        }

        if ($password !== $passwordConfirm) {
            throw new Exception('كلمتا المرور غير متطابقتين.');
        }

        if (strlen($password) < 6) {
            throw new Exception('يجب أن تكون كلمة المرور 6 أحرف على الأقل.');
        }

        // تحقق من وجود جدول password_resets
        $check = $pdo->query("SHOW TABLES LIKE 'password_resets'");
        if (!$check || !$check->fetchColumn()) {
            throw new Exception('نظام استعادة كلمة المرور غير مهيّأ بعد (جدول password_resets مفقود).');
        }

        // البحث عن الطلب وعدم مرور أكثر من 24 ساعة
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND created_at >= (NOW() - INTERVAL 1 DAY) LIMIT 1");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('رابط الاستعادة غير صالح أو منتهي الصلاحية.');
        }

        $email = (string)$row['email'];

        // تحديث كلمة مرور المستخدم
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email LIMIT 1");
        $stmt->execute([
            ':hash'  => $hash,
            ':email' => $email,
        ]);

        // حذف جميع طلبات الاستعادة لهذا البريد
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
        $stmt->execute([':email' => $email]);

        $success = 'تم تحديث كلمة المرور بنجاح، يمكنك الآن تسجيل الدخول بكلمتك الجديدة.';
        unset($_SESSION['front_reset_csrf']);

    } catch (Throwable $e) {
        $error = $e->getMessage();
        @error_log('[Frontend ResetPassword] ' . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title>تعيين كلمة مرور جديدة — موقع Godyar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="<?= h($baseUrl); ?>/assets/css/vendors/bootstrap.min.css">
  <link rel="stylesheet" href="<?= h($baseUrl); ?>/assets/css/vendors/font-awesome.css">
  <link rel="stylesheet" href="<?= h($baseUrl); ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= h($baseUrl); ?>/assets/css/responsive.css">

  <style>
    body{
      min-height:100vh;
      margin:0;
      display:flex;
      align-items:center;
      justify-content:center;
      background:radial-gradient(circle at top,#22c55e 0,#020617 55%);
      font-family:'Cairo','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif;
      color:#e5e7eb;
    }
    .auth-wrapper{
      width:100%;
      max-width:440px;
      padding:1.5rem;
    }
    .auth-card{
      background:rgba(15,23,42,.96);
      border-radius:18px;
      border:1px solid rgba(148,163,184,.4);
      box-shadow:0 24px 60px rgba(0,0,0,.75);
      padding:1.7rem 1.7rem;
    }
    .auth-brand{
      width:52px;
      height:52px;
      border-radius:16px;
      display:grid;
      place-items:center;
      background:linear-gradient(135deg,#22c55e,#0ea5e9);
      color:#0f172a;
      box-shadow:0 18px 40px rgba(34,197,94,.55);
      margin-inline:auto;
      margin-bottom:.5rem;
    }
    .auth-title{
      font-size:1.25rem;
      font-weight:600;
    }
    .auth-subtitle{
      font-size:.85rem;
      color:#9ca3af;
    }
    .form-label{
      font-size:.8rem;
      margin-bottom:.3rem;
    }
    .auth-input{
      background:#020617;
      border-radius:12px;
      border:1px solid #1f2937;
      color:#e5e7eb;
      font-size:.9rem;
    }
    .auth-input::placeholder{
      color:#6b7280;
    }
    .auth-input:focus{
      background:#020617;
      border-color:#22c55e;
      box-shadow:0 0 0 1px rgba(34,197,94,.6);
      color:#e5e7eb;
    }
    .btn-auth{
      border-radius:999px;
      background:linear-gradient(135deg,#22c55e,#0ea5e9);
      border:0;
      font-weight:600;
      font-size:.95rem;
      padding:.55rem 1rem;
    }
    .btn-auth:hover{
      filter:brightness(1.05);
    }
    .auth-footer{
      font-size:.78rem;
      color:#9ca3af;
      margin-top:.75rem;
    }
  </style>
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card">
      <div class="text-center mb-3">
        <div class="auth-brand">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        </div>
        <h1 class="auth-title mb-1">تعيين كلمة مرور جديدة</h1>
        <p class="auth-subtitle mb-0">
          اختر كلمة مرور قوية لحماية حسابك.
        </p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= h($error) ?></div>
      <?php elseif ($success): ?>
        <div class="alert alert-success py-2 small mb-3"><?= h($success) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= h($_SERVER['PHP_SELF']); ?>?token=<?= urlencode($token) ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">

        <div class="mb-3">
          <label for="password" class="form-label">كلمة المرور الجديدة</label>
          <input
            type="password"
            name="password"
            id="password"
            class="form-control auth-input"
            required
            autocomplete="new-password"
          >
        </div>

        <div class="mb-3">
          <label for="password_confirmation" class="form-label">تأكيد كلمة المرور</label>
          <input
            type="password"
            name="password_confirmation"
            id="password_confirmation"
            class="form-control auth-input"
            required
            autocomplete="new-password"
          >
        </div>

        <button type="submit" class="btn btn-auth w-100 mb-2">
          <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          حفظ كلمة المرور الجديدة
        </button>

        <div class="auth-footer d-flex justify-content-between align-items-center">
          <a href="<?= h($baseUrl); ?>/frontend/controllers/Auth/LoginController.php" class="text-decoration-none" style="color:#e5e7eb;">العودة لتسجيل الدخول</a>
          <a href="<?= h($baseUrl); ?>/" class="text-decoration-none" style="color:#9ca3af;">العودة للموقع</a>
        </div>
      </form>
    </div>
  </div>

  <script src="<?= h($baseUrl); ?>/assets/js/vendors/bootstrap.bundle.min.js"></script>
  </body>
</html>
