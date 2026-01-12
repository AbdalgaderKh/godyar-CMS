<?php
declare(strict_types=1);


require_once __DIR__ . '/_role_guard.php';
// admin/login.php — شاشة تسجيل دخول احترافية مع بعض ميزات الأمان

require_once __DIR__ . '/../includes/bootstrap.php';
// Ultra Pack helpers
$__auditDb = __DIR__ . '/includes/audit_db.php';
if (is_file($__auditDb)) { require_once $__auditDb; }

require_once __DIR__ . '/../includes/rate_limit.php';

// i18n
$__i18n = __DIR__ . '/i18n.php';
if (is_file($__i18n)) { require_once $__i18n; }


// هيلبر للهروب من الـ XSS
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// نتأكد أن الجلسة شغالة (غالبًا البوتستراب شغّلها، لكن للاحتياط)
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// توليد رمز CSRF بسيط
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['admin_csrf_token'];

// قراءة كعكة تذكّر البريد إن وجدت
$rememberedEmail = isset($_COOKIE['admin_remember_email']) ? (string)$_COOKIE['admin_remember_email'] : '';

// ✅ أزلنا إعادة التوجيه التلقائي هنا (لو كان موجود سابقاً)

// نحاول أخذ PDO من البوتستراب
$pdo = gdy_pdo_safe();
$error = null;
$email = $rememberedEmail;

// معالجة محاولة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // IP-based rate limiting (8 attempts / 10 minutes)
    if (!gody_rate_limit('admin_login', 8, 600)) {
        $wait = gody_rate_limit_retry_after('admin_login');
        $error = __('t_a53a1444da', 'محاولات كثيرة. حاول بعد ') . $wait . __('t_1412289e7a', ' ثانية.');
    }

    if (!$error) {
        try {
        if (!hash_equals($_SESSION['admin_csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
            throw new Exception(__('t_f23e5752ec', 'انتهت صلاحية الجلسة، يرجى تحديث الصفحة والمحاولة من جديد.'));
        }

        if (!$pdo instanceof PDO) {
            throw new Exception(__('t_f761e3cdf2', 'لا يمكن الاتصال بقاعدة البيانات حالياً.'));
        }

        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

        if ($email === '' || $password === '') {
            throw new Exception(__('t_ea0ab1334e', 'الرجاء إدخال البريد الإلكتروني وكلمة المرور.'));
        }

        // نقرأ من جدول users
        $sql = "SELECT id, username, email, password_hash, role, status, twofa_enabled, twofa_secret, session_version
                FROM users
                WHERE email = :email
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // التحقق من وجود المستخدم وصلاحيته للدخول للوحة التحكم
$role = (string)($user['role'] ?? '');
$allowedRoles = ['admin', 'editor', 'writer', 'author', 'super_admin'];
if (!$user || !in_array($role, $allowedRoles, true)) {
    throw new Exception(__('t_81ba8a03a2', 'بيانات الدخول غير صحيحة أو لا تملك صلاحية الدخول للوحة التحكم.'));
}


        if (!empty($user['status']) && $user['status'] !== 'active') {
            throw new Exception(__('t_aadb50b501', 'حسابك غير مفعّل، الرجاء التواصل مع الإدارة.'));
        }

        $hash = (string)($user['password_hash'] ?? '');

        if ($hash === '') {
            throw new Exception(__('t_a10f0c96ca', 'بيانات الدخول غير صحيحة.'));
        }

        $ok = false;

        // دعم md5 قديم باستخدام preg_match
        if (strlen($hash) === 32 && preg_match('/^[0-9a-f]{32}$/i', $hash)) {
            $ok = (md5($password) === strtolower($hash));
        } else {
            $ok = password_verify($password, $hash);
        }

        if (!$ok) {
            throw new Exception(__('t_a10f0c96ca', 'بيانات الدخول غير صحيحة.'));
        }

        
        // 2FA: إذا كان مفعلًا على الحساب، نطلب رمز المصادقة قبل دخول اللوحة
        $twofaEnabled = (int)($user['twofa_enabled'] ?? 0) === 1;
        $twofaSecret  = (string)($user['twofa_secret'] ?? '');
        if ($twofaEnabled && $twofaSecret !== '') {
            // نحفظ بيانات مؤقتة فقط، ثم نحول لصفحة التحقق
            $_SESSION['twofa_pending'] = [
                'id'       => (int)$user['id'],
                'email'    => (string)($user['email'] ?? ''),
                'role'     => (string)($user['role'] ?? ''),
                'username' => (string)($user['username'] ?? ''),
            ];

            // Audit (password ok, pending 2fa)
            if (function_exists('admin_audit_db')) {
                admin_audit_db('login_2fa_required', ['email' => $email]);
            }

            header('Location: /admin/security/2fa_verify.php');
            exit;
        }

        // نجاح الدخول → تخزين المستخدم في السيشن
        $_SESSION['user'] = [
            'id'       => (int)$user['id'],
            'name'     => $user['username'] ?? $user['email'],
            'username' => $user['username'] ?? null,
            'role'     => $user['role'] ?? 'admin',
            'email'    => $user['email'],
            'status'   => $user['status'] ?? 'active',
        ];

        // session_version: يستخدم لإبطال الجلسات (Logout all devices)
        $sv = is_numeric($user['session_version'] ?? null) ? (int)$user['session_version'] : 0;
        $_SESSION['session_version'] = $sv;

        // تحديث آخر دخول و IP (إن كانت الأعمدة موجودة)
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id");
            $stmt->execute([':ip' => $ip, ':id' => (int)$user['id']]);
        } catch (\Throwable $e) {
            // ignore
        }


        // خيار __('t_06b29113eb', "تذكر البريد")
        if ($remember) {
            setcookie('admin_remember_email', $email, [
                'expires'  => time() + (30 * 24 * 60 * 60),
                'path'     => '/admin', // ✅ بدل /godyar/admin
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie('admin_remember_email', '', time() - 3600, '/admin');
        }

        // ملاحظة: بعض قواعد البيانات القديمة لا تحتوي عمود last_login.
        // نعتمد على last_login_at (إن وجد) والذي تم تحديثه أعلاه.

                // حماية بسيطة من إعادة إرسال النموذج
        unset($_SESSION['admin_csrf_token']);

        // ✅ Audit log
        if (function_exists('admin_audit_db')) { admin_audit_db('admin_login_success', ['email' => $email]); }

        if (function_exists('gody_audit_log')) {
            gody_audit_log('admin_login_success', [
                'email' => $email,
                'role'  => (string)($_SESSION['user']['role'] ?? ''),
            ]);
        }

// ✅ تحويل إلى لوحة التحكم بعد نجاح الدخول (مسار مطلق)
        if (in_array((string)($_SESSION['user']['role'] ?? ''), ['writer','author'], true)) {
            header('Location: /admin/news/index.php');
        } else {
            header('Location: /admin/index.php');
        }
        exit;

    } catch (\Throwable $e) {
        $error = $e->getMessage();
        @error_log('[Godyar Login] '.$e->getMessage());
    

        // ✅ Audit log (failed login)
        if (function_exists('admin_audit_db')) { admin_audit_db('admin_login_failed', ['email' => (string)($_POST['email'] ?? '')]); }

        if (function_exists('gody_audit_log')) {
            $failEmail = trim((string)($_POST['email'] ?? ''));
            gody_audit_log('admin_login_failed', [
                'email' => $failEmail,
            ]);
        }
}
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars((string)(function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')), ENT_QUOTES, 'UTF-8') ?>" dir="<?= ((function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')) === 'ar' ? 'rtl' : 'ltr') ?>">
<head>
  <meta charset="utf-8">
  <title><?= h(__("login")) ?> — <?= h(__("admin_panel")) ?> Godyar</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link
    href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css"
    rel="stylesheet"
  >
  <style>
    :root {
      --gdy-bg: #020617;
      --gdy-surface: rgba(15,23,42,.96);
      --gdy-accent: #22c55e;
      --gdy-accent-soft: #0ea5e9;
      --gdy-border-subtle: rgba(148,163,184,.35);
    }
    *{ box-sizing:border-box; }
    body{
      min-height:100vh;
      margin:0;
      display:flex;
      align-items:stretch;
      justify-content:center;
      background:radial-gradient(circle at top left,#22c55e 0,#020617 55%);
      color:#e5e7eb;
      font-family:'Tajawal','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif;
    }
    .auth-shell{
      width:100%;
      max-width:980px;
      margin:auto;
      padding:1.5rem;
    }
    .auth-layout{
      display:grid;
      grid-template-columns:minmax(0,3fr) minmax(0,2.4fr);
      gap:1.5rem;
    }
    @media (max-width:768px){
      .auth-layout{
        grid-template-columns:minmax(0,1fr);
      }
      .auth-side{
        display:none;
      }
    }
    .auth-card{
      background:var(--gdy-surface);
      border-radius:20px;
      border:1px solid var(--gdy-border-subtle);
      box-shadow:0 24px 60px rgba(0,0,0,.7);
      padding:1.5rem 1.75rem;
    }
    .gdy-brand-badge{
      width:52px;
      height:52px;
      border-radius:16px;
      display:grid;
      place-items:center;
      background:linear-gradient(135deg,var(--gdy-accent),var(--gdy-accent-soft));
      color:#0f172a;
      box-shadow:0 18px 40px rgba(34,197,94,.45);
      margin-inline:auto;
    }
    .auth-title{
      font-size:1.25rem;
      font-weight:600;
    }
    .auth-subtitle{
      font-size:.85rem;
    }
    .form-label{
      font-size:.8rem;
      margin-bottom:.35rem;
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
      border-color:var(--gdy-accent);
      box-shadow:0 0 0 1px rgba(34,197,94,.6);
      color:#e5e7eb;
    }
    .btn-auth-primary{
      border-radius:999px;
      background:linear-gradient(135deg,var(--gdy-accent),var(--gdy-accent-soft));
      border:0;
      font-weight:600;
      font-size:.95rem;
      padding:.6rem 1rem;
    }
    .btn-auth-primary:hover{
      filter:brightness(1.05);
    }
    .login-meta{
      font-size:.75rem;
      color:#9ca3af;
    }
    .auth-side{
      position:relative;
      border-radius:20px;
      border:1px solid rgba(148,163,184,.35);
      overflow:hidden;
      background:radial-gradient(circle at top,#0ea5e9 0,rgba(15,23,42,.98) 45%);
      padding:1.4rem 1.5rem;
    }
    .auth-chip{
      display:inline-flex;
      align-items:center;
      gap:.3rem;
      border-radius:999px;
      padding:.2rem .7rem;
      font-size:.75rem;
      background:rgba(15,23,42,.86);
      border:1px solid rgba(148,163,184,.5);
      color:#e5e7eb;
    }
    .auth-side-title{
      font-size:1.1rem;
      font-weight:600;
      margin-top:.9rem;
      margin-bottom:.4rem;
    }
    .auth-side-list{
      font-size:.8rem;
      color:#d1d5db;
      margin:0;
      padding-left:1.1rem;
    }
    .auth-side-list li{
      margin-bottom:.25rem;
    }
    .badge-env{
      font-size:.7rem;
      border-radius:999px;
      padding:.15rem .5rem;
      border:1px solid rgba(148,163,184,.5);
      color:#e5e7eb;
    }
    .password-toggle-btn{
      border-radius:999px;
      border:0;
      background:transparent;
      color:#9ca3af;
      padding:0 .35rem;
    }
    .password-toggle-btn:hover{
      color:#e5e7eb;
    }
  

        /* SVG icon sizing (fix huge icons) */
        .gdy-icon{ width:18px; height:18px; display:inline-block; vertical-align:middle; color: currentColor; }
        .gdy-icon use{ pointer-events:none; }
        .gdy-icon.spin{ animation:gdySpin 1s linear infinite; }
        @keyframes gdySpin{ from{ transform:rotate(0deg);} to{ transform:rotate(360deg);} }
        /* ensure buttons don't blow up */
        button .gdy-icon, a .gdy-icon { flex: 0 0 auto; }
    
</style>
</head>
<body>
  <div class="auth-shell">
    <div class="auth-layout">
      <section class="auth-card">
        <div class="text-center mb-3">
          <div class="gdy-brand-badge mb-2">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use id="togglePassIcon" href="/assets/icons/gdy-icons.svg#eye"></use></svg>
          </div>
          <h1 class="auth-title mb-1"><?= h(__("admin_panel")) ?> Godyar Pro</h1>
          <p class="auth-subtitle text-muted mb-0"><?= h(__("login_to_admin")) ?></p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small mb-3"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

          <div class="mb-3">
            <label for="email" class="form-label"><?= h(__("email")) ?></label>
            <input
              type="email"
              name="email"
              id="email"
              class="form-control auth-input"
              required
              autocomplete="username"
              value="<?= h($email) ?>"
            >
          </div>

          <div class="mb-2">
            <label for="password" class="form-label d-flex justify-content-between align-items-center">
              <span><?= h(__("password")) ?></span>
              <button type="button" class="password-toggle-btn" data-action="toggle-password">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#alert"></use></svg>
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
              <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember" <?= $rememberedEmail ? 'checked' : '' ?>>
              <label class="form-check-label small" for="remember">
                <?= h(__("remember_email")) ?>
              </label>
            </div>
            <span class="small text-muted">
              <?= h(__('t_efdc17402d', 'نسيت كلمة المرور؟')) ?>
            </span>
          </div>

          <button type="submit" class="btn btn-auth-primary w-100 mb-2">
            <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#login"></use></svg>
            <?= h(__("login_to_dashboard")) ?>
          </button>

          <div class="login-meta d-flex justify-content-between align-items-center mt-2">
            <span><?= h(__('t_f8e20d0ab5', 'بيئة:')) ?> <span class="badge-env"><?= h(getenv('APP_ENV') ?: 'production') ?></span></span>
            <span><?= h(__('t_27f6c4c05c', 'إصدار: Godyar Pro')) ?></span>
          </div>
        </form>
      </section>

      <aside class="auth-side d-none d-md-block">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="auth-chip">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#alert"></use></svg>
            <?= h(__("secure_login")) ?>
          </span>
          <span class="auth-chip">
            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#calendar"></use></svg>
            <?= date('Y-m-d'); ?>
          </span>
        </div>
        <h2 class="auth-side-title"><?= h(__("godyar_admin_area")) ?></h2>
        <p class="text-muted small mb-3">
          <?= h(__("admin_area_desc")) ?>
        </p>
        <ul class="auth-side-list">
          <li><?= h(__('t_0f7cff7bf9', 'نظام أذونات وصلاحيات يمكن توسعه لاحقاً.')) ?></li>
          <li><?= h(__('t_c40fd6b389', 'تسجيل دخول آمن مع دعم كلمات مرور مشفّرة.')) ?></li>
          <li><?= h(__('t_f5a0a12850', 'سجل نشاط إداري وتقارير استخدام (من صفحات النظام).')) ?></li>
          <li><?= h(__('t_7a122ecaec', 'تكامل مع هوية الواجهة الأمامية للموقع.')) ?></li>
        </ul>
        <p class="text-muted login-meta mt-4 mb-0">
          <?= h(__("logout_notice")) ?>
        </p>
      </aside>
    </div>
  </div>

  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    function togglePassword() {
      var input = document.getElementById('password');
      var icon  = document.getElementById('passwordToggleIcon');
      if (!input || !icon) return;
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }
  </script>
</body>
</html>
