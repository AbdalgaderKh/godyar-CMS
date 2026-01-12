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
$error = null;
$success = null;
$email = '';

if (empty($_SESSION['front_forgot_csrf'])) {
    $_SESSION['front_forgot_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['front_forgot_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['front_forgot_csrf'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
            throw new Exception('انتهت صلاحية الجلسة، يرجى تحديث الصفحة والمحاولة مرة أخرى.');
        }

        if (!$pdo instanceof PDO) {
            throw new Exception('لا يمكن الاتصال بقاعدة البيانات حالياً.');
        }

        $email = trim((string)($_POST['email'] ?? ''));

        if ($email === '') {
            throw new Exception('الرجاء إدخال البريد الإلكتروني.');
        }

        // نتحقق من وجود المستخدم لكن لا نكشف التفاصيل للمستخدم النهائي
        $stmt = $pdo->prepare("SELECT id, email, status FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // حتى لو لم يوجد المستخدم، نُرجع نفس الرسالة لتفادي كشف المستخدمين
        if (!$user || (!empty($user['status']) && $user['status'] !== 'active')) {
            $success = 'تم إرسال رابط استعادة كلمة المرور (إن كان البريد مسجلاً لدينا).';
        } else {
            // نتأكد أن جدول password_resets موجود
            $check = $pdo->query("SHOW TABLES LIKE 'password_resets'");
            if (!$check || !$check->fetchColumn()) {
                throw new Exception('نظام استعادة كلمة المرور غير مهيّأ بعد (جدول password_resets مفقود).');
            }

            $token = bin2hex(random_bytes(32));
            $ip    = $_SERVER['REMOTE_ADDR'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, ip_address) VALUES (:email, :token, :ip)");
            $stmt->execute([
                ':email' => $user['email'],
                ':token' => $token,
                ':ip'    => $ip,
            ]);

            $resetUrl = $baseUrl . '/frontend/controllers/Auth/ResetPasswordController.php?token=' . urlencode($token);

            // إرسال البريد - يمكن تعديل الإعدادات حسب البيئة
            $subject = 'استعادة كلمة المرور - موقع Godyar';
            $body    = "مرحباً،\n\nلقد تم طلب استعادة كلمة المرور لحسابك في موقع Godyar.\n
لإعادة تعيين كلمة المرور، يرجى الضغط على الرابط التالي أو نسخه في المتصفح:\n\n{$resetUrl}\n\n
إذا لم تقم بهذا الطلب، يمكنك تجاهل هذه الرسالة.\n\nمع التحية.";

            $headers = 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
            $headers .= 'X-Mailer: PHP/' . phpversion();

            // نحاول الإرسال لكن لا نوقف النظام لو فشل
            try {
                @mail($user['email'], $subject, $body, $headers);
            } catch (Throwable $e) {
                @error_log('[Frontend ForgotPassword] mail error: ' . $e->getMessage());
            }

            $success = 'تم إرسال رابط استعادة كلمة المرور إلى بريدك الإلكتروني (إن كان مسجلاً لدينا).';
        }

        unset($_SESSION['front_forgot_csrf']);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        @error_log('[Frontend ForgotPassword] ' . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title>استعادة كلمة المرور — موقع Godyar</title>
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
        <h1 class="auth-title mb-1">استعادة كلمة المرور</h1>
        <p class="auth-subtitle mb-0">
          أدخل بريدك الإلكتروني وسنرسل لك رابطًا لإعادة تعيين كلمة المرور.
        </p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= h($error) ?></div>
      <?php elseif ($success): ?>
        <div class="alert alert-success py-2 small mb-3"><?= h($success) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= h($_SERVER['PHP_SELF']); ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

        <div class="mb-3">
          <label for="email" class="form-label">البريد الإلكتروني</label>
          <input
            type="email"
            name="email"
            id="email"
            class="form-control auth-input"
            required
            autocomplete="email"
            value="<?= h($email) ?>"
            placeholder="example@domain.com"
          >
        </div>

        <button type="submit" class="btn btn-auth w-100 mb-2">
          <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          إرسال رابط الاستعادة
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
