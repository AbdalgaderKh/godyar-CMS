<?php
declare(strict_types=1);

// /godyar/frontend/controllers/Auth/LoginController.php

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/site_settings.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// هيلبر بسيط للهروب من XSS
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// الرابط الأساسي
$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/godyar';

// لو المستخدم مسجّل دخول بالفعل → رجوع للموقع
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    header('Location: ' . $baseUrl . '/');
    exit;
}

// توليد رمز CSRF خاص بتسجيل الدخول في الواجهة
if (empty($_SESSION['front_login_csrf'])) {
    $_SESSION['front_login_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['front_login_csrf'];

// محاولة قراءة البريد من الكوكي لو مفعّل "تذكّرني"
$rememberedEmail = isset($_COOKIE['front_remember_email'])
    ? (string)$_COOKIE['front_remember_email']
    : '';

// اتصال قاعدة البيانات
$pdo = gdy_pdo_safe();
$error = null;
$email = $rememberedEmail;

// رابط إعادة التوجيه بعد تسجيل الدخول (اختياري)
$redirect = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
if ($redirect !== '' && strpos($redirect, $baseUrl) !== 0) {
    $redirect = $baseUrl . '/';
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من CSRF
        if (!hash_equals($_SESSION['front_login_csrf'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
            throw new Exception('انتهت صلاحية الجلسة، يرجى تحديث الصفحة والمحاولة مرة أخرى.');
        }

        if (!$pdo instanceof PDO) {
            throw new Exception('لا يمكن الاتصال بقاعدة البيانات حالياً.');
        }

        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
        $redirect = isset($_POST['redirect']) ? (string)$_POST['redirect'] : $redirect;

        if ($email === '' || $password === '') {
            throw new Exception('الرجاء إدخال البريد الإلكتروني وكلمة المرور.');
        }

        // إعادة التحقق من أمان redirect في POST
        if ($redirect !== '' && strpos($redirect, $baseUrl) !== 0) {
            $redirect = $baseUrl . '/';
        }

        // جلب المستخدم من جدول users
        $stmt = $pdo->prepare("
            SELECT id, name, username, email, password_hash, password, role, is_admin, avatar, status
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            throw new Exception('بيانات الدخول غير صحيحة.');
        }

        if (!empty($u['status']) && $u['status'] !== 'active') {
            throw new Exception('حسابك غير مفعّل، الرجاء التواصل مع إدارة الموقع.');
        }

        $hashNew = (string)($u['password_hash'] ?? '');
        $hashOld = (string)($u['password'] ?? '');
        $ok      = false;

        // أولوية للحقل الحديث password_hash إن كان مستخدمًا
        if ($hashNew !== '') {
            if (strlen($hashNew) === 32 && preg_match('/^[0-9a-f]{32}$/i', $hashNew)) {
                // دعم MD5 قديم
                $ok = (md5($password) === strtolower($hashNew));
            } else {
                $ok = password_verify($password, $hashNew);
            }
        } elseif ($hashOld !== '') {
            // توافق مع النسخ الأقدم التي تستخدم العمود password
            if (password_verify($password, $hashOld)) {
                $ok = true;
            } elseif (strlen($hashOld) === 32 && preg_match('/^[0-9a-f]{32}$/i', $hashOld)) {
                $ok = (md5($password) === strtolower($hashOld));
            } else {
                // تخزين نصّي مباشر (للتوافق مع نسخ قديمة)
                $ok = hash_equals($hashOld, $password);
            }
        }

        if (!$ok) {
            throw new Exception('بيانات الدخول غير صحيحة.');
        }

        // نجاح الدخول → حفظ المستخدم في الجلسة (مع role حتى يتعرّف الهيدر على حالة الدخول)
        $_SESSION['user'] = [
            'id'       => (int)$u['id'],
            'name'     => $u['name'] ?? ($u['username'] ?? $u['email'] ?? ''),
            'username' => $u['username'] ?? null,
            'email'    => $u['email'] ?? null,
            'role'     => $u['role'] ?? ((int)($u['is_admin'] ?? 0) === 1 ? 'admin' : 'user'),
            'is_admin' => (int)($u['is_admin'] ?? 0),
            'avatar'   => $u['avatar'] ?? null,
            'status'   => $u['status'] ?? null,
        ];

        // تذكّر البريد إن اختار المستخدم ذلك
        if ($remember) {
            setcookie('front_remember_email', $email, [
                'expires'  => time() + (30 * 24 * 60 * 60),
                'path'     => '/godyar',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('front_remember_email', '', time() - 3600, '/godyar');
        }

        // تنظيف CSRF بعد الاستخدام
        unset($_SESSION['front_login_csrf']);

        // إعادة التوجيه بعد الدخول
        if ($redirect !== '') {
            header('Location: ' . $redirect);
        } else {
            header('Location: ' . $baseUrl . '/');
        }
        exit;

    } catch (Throwable $e) {
        $error = $e->getMessage();
        @error_log('[Frontend Login] ' . $e->getMessage());
    }
}

// تحميل إعدادات الهوية للواجهة
$settings        = gdy_load_settings($pdo);
$frontendOptions = gdy_prepare_frontend_options($settings);
extract($frontendOptions, EXTR_OVERWRITE);

?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
  <title>تسجيل الدخول — <?= h($siteName ?? 'Godyar'); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="<?= h($baseUrl); ?>/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="<?= h($baseUrl); ?>/assets/css/site.css" rel="stylesheet">
  <style>
    body{
      min-height:100vh;
      margin:0;
      display:flex;
      align-items:center;
      justify-content:center;
      background:radial-gradient(circle at top,#0ea5e9 0,#020617 55%);
      font-family:'Tajawal','Segoe UI',system-ui,sans-serif;
      color:#e5e7eb;
    }
    .auth-wrapper{
      width:100%;
      max-width:420px;
      padding:1.5rem;
    }
    .auth-card{
      background:rgba(15,23,42,.96);
      border-radius:20px;
      border:1px solid rgba(148,163,184,.45);
      box-shadow:0 24px 60px rgba(0,0,0,.7);
      padding:1.75rem 1.5rem;
    }
    .auth-brand{
      width:54px;
      height:54px;
      border-radius:16px;
      display:grid;
      place-items:center;
      background:linear-gradient(135deg,#22c55e,#0ea5e9);
      color:#0f172a;
      box-shadow:0 18px 40px rgba(34,197,94,.45);
      margin-inline:auto;
      margin-bottom:.75rem;
    }
    .auth-title{
      font-size:1.25rem;
      font-weight:600;
    }
    .auth-subtitle{
      font-size:.85rem;
      color:#9ca3af;
    }
    .auth-input{
      background:#020617;
      border-radius:12px;
      border:1px solid #1f2937;
      color:#e5e7eb;
      font-size:.9rem;
    }
    .auth-input::placeholder{ color:#6b7280; }
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
      padding:.6rem 1rem;
    }
    .btn-auth:hover{ filter:brightness(1.05); }
    .login-meta{
      font-size:.75rem;
      color:#9ca3af;
    }
    .password-toggle-btn{
      border-radius:999px;
      border:0;
      background:transparent;
      color:#9ca3af;
      padding:0 .35rem;
    }
    .password-toggle-btn:hover{ color:#e5e7eb; }
  </style>
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card">
      <div class="text-center mb-3">
        <div class="auth-brand">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
        </div>
        <h1 class="auth-title mb-1">تسجيل الدخول إلى حسابك</h1>
        <p class="auth-subtitle mb-0">
          أدخل بياناتك للوصول إلى حسابك في موقع <?= h($siteName ?? 'Godyar'); ?>.
        </p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= h($_SERVER['PHP_SELF']); ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="redirect" value="<?= h($redirect) ?>">

        <div class="mb-3">
          <label for="email" class="form-label small">البريد الإلكتروني</label>
          <input
            type="email"
            name="email"
            id="email"
            class="form-control auth-input"
            required
            autocomplete="username"
            value="<?= h($email) ?>"
            placeholder="name@example.com"
          >
        </div>

        <div class="mb-2">
          <label for="password" class="form-label small d-flex justify-content-between align-items-center">
            <span>كلمة المرور</span>
            <button type="button" class="password-toggle-btn" data-target="password" data-icon="passwordToggleIcon">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            </button>
          </label>
          <input
            type="password"
            name="password"
            id="password"
            class="form-control auth-input"
            required
            autocomplete="current-password"
          >
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="form-check form-check-sm">
            <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember"
              <?= $rememberedEmail ? 'checked' : '' ?>>
            <label class="form-check-label small" for="remember">
              تذكّر البريد على هذا الجهاز
            </label>
          </div>
          <span class="small text-muted">
            نسيت كلمة المرور؟
          </span>
        </div>

        <button type="submit" class="btn btn-auth w-100 mb-2">
          <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#login"></use></svg>
          دخول إلى حسابي
        </button>

        <div class="login-meta d-flex justify-content-between align-items-center mt-2">
          <span>موقع: <?= h($siteName ?? 'Godyar'); ?></span>
          <span><a href="<?= h($baseUrl); ?>/" class="text-decoration-none" style="color:#9ca3af;">العودة للموقع</a></span>
        </div>
      </form>
    </div>
  </div>

  <script src="<?= h($baseUrl); ?>/assets/js/vendors/bootstrap.bundle.min.js"></script>
  </body>
</html>