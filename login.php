<?php
declare(strict_types=1);

/**
 * صفحة دخول الأعضاء — /login.php (أو /login)
 * مستقلة عن /admin/login.php
 *
 * تحسينات:
 * - CSRF Token
 * - Rate limiting بسيط (جلسة) لمنع التخمين السريع
 * - Redirect آمن بعد الدخول عبر ?next=/path
 * - تحسين واجهة: أيقونات داخل الحقول، إظهار/إخفاء كلمة المرور، تنبيه CapsLock، تلميحات وصول
 * - ترقية hash قديم إلى password_hash عند نجاح الدخول (إن أمكن)
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/rate_limit.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// هيلبر للهروب الآمن
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// رابط الأساس
$baseUrl = function_exists('base_url') ? rtrim(base_url(), '/') : '';

/**
 * مسار Redirect آمن:
 * - يسمح فقط بمسارات داخلية تبدأ بـ /
 * - يمنع // أو http(s):// أو javascript:
 */
function safe_next_path(?string $next): string {
    $next = trim((string)$next);
    if ($next === '') return '';
    if (preg_match('~^(https?:)?//~i', $next)) return '';
    if (preg_match('~^[a-z]+:~i', $next)) return '';
    if ($next[0] !== '/') return '';
    if (strpos($next, '//') === 0) return '';
    return $next;
}

$next = safe_next_path($_GET['next'] ?? $_POST['next'] ?? '');
$redirectAfterLogin = $next !== '' ? ($baseUrl . $next) : ($baseUrl . '/');

// لو المستخدم مسجّل مسبقاً → Redirect
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    header('Location: ' . $redirectAfterLogin);
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

$errorMessage = '';
$oldLogin     = '';

/**
 * Rate limiting بسيط بالجلسة:
 * 5 محاولات فاشلة خلال 10 دقائق → قفل 5 دقائق
 */
function throttle_state(): array {
    $s = $_SESSION['login_throttle'] ?? null;
    if (!is_array($s)) {
        $s = ['count' => 0, 'first' => time(), 'lock_until' => 0];
    }
    if (time() - (int)$s['first'] > 600) {
        $s = ['count' => 0, 'first' => time(), 'lock_until' => 0];
    }
    return $s;
}
function throttle_save(array $s): void {
    $_SESSION['login_throttle'] = $s;
}
function throttle_blocked_seconds(): int {
    $s = throttle_state();
    $lockUntil = (int)($s['lock_until'] ?? 0);
    return ($lockUntil > time()) ? ($lockUntil - time()) : 0;
}
function throttle_on_fail(): void {
    $s = throttle_state();
    $s['count'] = (int)($s['count'] ?? 0) + 1;

    if ($s['count'] >= 5) {
        $s['lock_until'] = time() + 300;
        $s['count'] = 0;
        $s['first'] = time();
    }
    throttle_save($s);
}
function throttle_on_success(): void {
    throttle_save(['count' => 0, 'first' => time(), 'lock_until' => 0]);
}

// CSRF
$csrfToken = '';
if (function_exists('generate_csrf_token')) {
    $csrfToken = generate_csrf_token();
} else {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = (string)$_SESSION['csrf_token'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // IP-based rate limiting (10 attempts / 10 minutes)
    if (!gody_rate_limit('login', 10, 600)) {
        $wait = gody_rate_limit_retry_after('login');
        $errorMessage = 'محاولات كثيرة. حاول بعد ' . $wait . ' ثانية.';
    }

    $login    = trim($_POST['login'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']) ? '1' : '0';

    $oldLogin = $login;

    // CSRF validate
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (function_exists('validate_csrf_token')) {
        if (!validate_csrf_token($postedToken)) {
            $errorMessage = 'انتهت صلاحية الجلسة أو حدث خطأ في التحقق. حدّث الصفحة وحاول مرة أخرى.';
        }
    } else {
        if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $postedToken)) {
            $errorMessage = 'انتهت صلاحية الجلسة أو حدث خطأ في التحقق. حدّث الصفحة وحاول مرة أخرى.';
        }
    }

    // Throttle
    if ($errorMessage === '') {
        $blockedFor = throttle_blocked_seconds();
        if ($blockedFor > 0) {
            $errorMessage = 'محاولات كثيرة. الرجاء الانتظار ' . (int)$blockedFor . ' ثانية ثم المحاولة مجدداً.';
        }
    }

    if ($errorMessage === '') {
        if ($login === '' || $password === '') {
            $errorMessage = 'يرجى إدخال البريد الإلكتروني / اسم المستخدم وكلمة المرور.';
        } elseif (!$pdo instanceof PDO) {
            $errorMessage = 'لا يمكن الاتصال بقاعدة البيانات حالياً.';
        } else {
            try {
                $sql = "
                    SELECT *
                    FROM users
                    WHERE (email = :email OR username = :username)
                    LIMIT 1
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':email'    => $login,
                    ':username' => $login,
                ]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    throttle_on_fail();
                    $errorMessage = 'بيانات الدخول غير صحيحة.';
                } else {
                    $status = $user['status'] ?? null;
                    $role   = $user['role']   ?? 'user';

                    if (!empty($status) && in_array($status, ['blocked','banned'], true)) {
                        throttle_on_fail();
                        $errorMessage = 'حسابك موقوف، يرجى مراجعة إدارة الموقع.';
                    } else {
                        $hash = (string)($user['password_hash'] ?? $user['password'] ?? '');
                        $ok   = false;
                        $verifiedWithPasswordHash = false;
                        $legacyMatched = false;

                        if ($hash !== '') {
                            if (password_verify($password, $hash)) {
                                $ok = true;
                                $verifiedWithPasswordHash = true;
                            } elseif (md5($password) === $hash) {
                                $ok = true;
                                $legacyMatched = true;
                            } elseif (sha1($password) === $hash) {
                                $ok = true;
                                $legacyMatched = true;
                            }
}

                        if (!$ok) {
                            throttle_on_fail();
                            $errorMessage = 'بيانات الدخول غير صحيحة.';
                        } else {
                            throttle_on_success();

                            // (اختياري) تذكرني: تمديد عمر كوكي السيشن 30 يوم
                            if ($remember === '1') {
                                $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                                $params = session_get_cookie_params();
                                @setcookie(
                                    session_name(),
                                    session_id(),
                                    [
                                        'expires'  => time() + 60 * 60 * 24 * 30,
                                        'path'     => $params['path'] ?? '/',
                                        'domain'   => $params['domain'] ?? '',
                                        'secure'   => $isSecure,
                                        'httponly' => true,
                                        'samesite' => 'Lax',
                                    ]
                                );
                            }

                            session_regenerate_id(true);

                            // تحميل صورة المستخدم إن كانت موجودة (users.avatar أو user_profiles.avatar)
                            $avatar = null;
                            try {
                                $uid = (int)($user['id'] ?? 0);
                                if ($uid > 0 && $pdo instanceof PDO) {
                                    if (function_exists('db_column_exists') && db_column_exists($pdo, 'users', 'avatar')) {
                                        $avatar = $user['avatar'] ?? null;
                                    } elseif (function_exists('db_table_exists') && db_table_exists($pdo, 'user_profiles')) {
                                        $stAv = $pdo->prepare('SELECT avatar FROM user_profiles WHERE user_id = :id LIMIT 1');
                                        $stAv->execute([':id' => $uid]);
                                        $avatar = $stAv->fetchColumn() ?: null;
                                    }
                                }
                            } catch (Throwable $e) {
                                $avatar = null;
                            }

                            // ✅ توحيد تخزين جلسة المستخدم عبر كامل المشروع
                            if (function_exists('auth_set_user_session')) {
                                auth_set_user_session([
                                    'id'       => (int)($user['id'] ?? 0),
                                    'username' => $user['username'] ?? null,
                                    'display_name' => $user['display_name'] ?? ($user['username'] ?? null),
                                    'email'    => $user['email'] ?? null,
                                    'role'     => $role,
                                    'status'   => $status,
                                    'avatar'   => $avatar,
                                ]);
                            } else {
                                $_SESSION['user'] = [
                                    'id'        => (int)($user['id'] ?? 0),
                                    'username'  => $user['username'] ?? null,
                                    'email'     => $user['email'] ?? null,
                                    'role'      => $role,
                                    'status'    => $status,
                                    'avatar'    => $avatar,
                                    'login_at'  => date('Y-m-d H:i:s'),
                                ];
                                $_SESSION['is_member_logged'] = true;
                            }

                            // تحديث آخر تسجيل دخول لو العمود موجود
                            try {
                                $colsStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login_at'");
                                $hasLastLogin = $colsStmt && $colsStmt->fetch(PDO::FETCH_ASSOC);
                                if ($hasLastLogin && !empty($user['id'])) {
                                    $upd = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
                                    $upd->execute([':id' => (int)$user['id']]);
                                }
                            } catch (Throwable $e) { /* ignore */ }

                            // ترقية الهاش القديم إلى password_hash عند نجاح الدخول (إن أمكن)
                            try {
                                $colsStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
                                $hasPasswordHashCol = $colsStmt && $colsStmt->fetch(PDO::FETCH_ASSOC);

                                if ($hasPasswordHashCol && !empty($user['id'])) {
                                    if (($verifiedWithPasswordHash && password_needs_rehash($hash, PASSWORD_DEFAULT)) || $legacyMatched) {
                                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                                        $upd = $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id");
                                        $upd->execute([':h' => $newHash, ':id' => (int)$user['id']]);
                                    }
                                }
                            } catch (Throwable $e) { /* ignore */ }

                            header('Location: ' . $redirectAfterLogin);
                            exit;
                        }
                    }
                }
            } catch (Throwable $e) {
                @error_log('[login] ' . $e->getMessage());
                $errorMessage = 'حدث خطأ أثناء التحقق من بيانات الدخول. الرجاء المحاولة لاحقاً.';
            }
        }
    }
}

$blockedForNow = throttle_blocked_seconds();
$btnDisabled = $blockedForNow > 0;
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
    <title><?= h(__('تسجيل دخول الأعضاء')) ?> - Godyar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#020617">

    <!-- Bootstrap RTL -->
    <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <style>
        :root{
            --gdy-bg: #020617;
            --gdy-card: rgba(15,23,42,.92);
            --gdy-border: rgba(148,163,184,.38);
            --gdy-border-soft: rgba(148,163,184,.24);
            --gdy-text: #e5e7eb;
            --gdy-muted: #9ca3af;
            --gdy-primary: #0ea5e9;
            --gdy-primary2:#2563eb;
            --gdy-shadow: 0 22px 55px rgba(15,23,42,.92);
        }

        html, body { height:100%; }
        body{
            min-height: 100vh;
            background:
                radial-gradient(circle at 12% 8%, rgba(14,165,233,.18), transparent 40%),
                radial-gradient(circle at 85% 80%, rgba(37,99,235,.18), transparent 45%),
                radial-gradient(circle at top, #0f172a, var(--gdy-bg) 58%, var(--gdy-bg) 100%);
            color: var(--gdy-text);
            display:flex;
            align-items:center;
            justify-content:center;
            padding: 1.25rem;
            overflow-x:hidden;
        }

        .gdy-shell{
            width:100%;
            max-width: 980px;
            display:grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 1rem;
            align-items: stretch;
        }
        @media (max-width: 992px){
            .gdy-shell{ grid-template-columns: 1fr; max-width: 520px; }
        }

        .gdy-side{
            background: linear-gradient(135deg, rgba(15,23,42,.78), rgba(2,6,23,.92));
            border:1px solid var(--gdy-border-soft);
            border-radius: 1.35rem;
            box-shadow: var(--gdy-shadow);
            position:relative;
            overflow:hidden;
            padding: 1.25rem 1.25rem;
        }
        .gdy-side::before{
            content:'';
            position:absolute;
            inset:-35%;
            background:
                radial-gradient(circle at top right, rgba(56,189,248,.18), transparent 60%),
                radial-gradient(circle at bottom left, rgba(96,165,250,.16), transparent 58%);
            pointer-events:none;
            opacity:.95;
        }
        .gdy-side-inner{ position:relative; z-index:1; }

        .gdy-brand{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:.75rem;
            margin-bottom: .85rem;
        }
        .gdy-brand .logo{
            display:flex;
            align-items:center;
            gap:.65rem;
            min-width:0;
        }
        .gdy-brand .logo-badge{
            width: 44px; height:44px;
            border-radius: 14px;
            display:flex; align-items:center; justify-content:center;
            background: radial-gradient(circle at top, rgba(14,165,233,.35), rgba(2,6,23,0));
            border: 1px solid rgba(56,189,248,.32);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.06);
        }
        .gdy-brand .logo-badge i{ color:#7dd3fc; font-size: 1.35rem; }
        .gdy-brand .title{
            font-weight:800;
            color:#f9fafb;
            font-size: 1.15rem;
            line-height:1.2;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .gdy-brand .subtitle{
            color: var(--gdy-muted);
            font-size: .86rem;
            margin-top:.15rem;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }

        .gdy-features{
            margin-top: .9rem;
            display:grid;
            gap:.65rem;
        }
        .gdy-feature{
            display:flex;
            gap:.65rem;
            align-items:flex-start;
            background: rgba(2,6,23,.55);
            border: 1px solid rgba(148,163,184,.2);
            border-radius: 1rem;
            padding: .75rem .8rem;
        }
        .gdy-feature i{
            color:#60a5fa;
            margin-top: .1rem;
        }
        .gdy-feature b{
            color:#f9fafb;
            font-size: .9rem;
        }
        .gdy-feature p{
            margin: .15rem 0 0;
            color: var(--gdy-muted);
            font-size: .82rem;
        }

        .gdy-card{
            background: var(--gdy-card);
            border-radius: 1.35rem;
            border: 1px solid var(--gdy-border);
            box-shadow: var(--gdy-shadow);
            padding: 1.35rem 1.2rem 1.15rem;
            position: relative;
            overflow: hidden;
        }
        .gdy-card::before{
            content:'';
            position:absolute;
            inset:-40%;
            background:
                radial-gradient(circle at top right, rgba(56,189,248,.14), transparent 60%),
                radial-gradient(circle at bottom left, rgba(96,165,250,.14), transparent 60%);
            pointer-events:none;
            opacity:.9;
        }
        .gdy-card-inner{ position:relative; z-index:1; }

        .gdy-title{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:.5rem;
            margin-bottom: .25rem;
        }
        .gdy-title h1{
            font-size: 1.25rem;
            font-weight: 800;
            margin:0;
            color:#f9fafb;
        }
        .gdy-sub{
            text-align:center;
            color: var(--gdy-muted);
            font-size: .88rem;
            margin-bottom: 1rem;
        }

        .form-label{
            font-size: .84rem;
            color: #cbd5f5;
            margin-bottom:.35rem;
        }

        .input-group .input-group-text{
            background: rgba(2,6,23,.85);
            border-color: rgba(148,163,184,.35);
            color:#93c5fd;
            border-radius: .9rem;
        }
        .form-control{
            background: rgba(2,6,23,.9);
            border-color: rgba(148,163,184,.35);
            color: var(--gdy-text);
            border-radius: .9rem;
        }
        .form-control:focus{
            background: rgba(2,6,23,.96);
            border-color: var(--gdy-primary);
            box-shadow: 0 0 0 .14rem rgba(14,165,233,.28);
            color: var(--gdy-text);
        }

        .gdy-btn{
            background: linear-gradient(135deg, var(--gdy-primary), var(--gdy-primary2));
            border: none;
            color: #f9fafb;
            font-weight: 700;
            border-radius: 1rem;
            padding: .6rem 1rem;
            transition: transform .15s ease, filter .2s ease, box-shadow .2s ease;
        }
        .gdy-btn:hover{
            filter: brightness(1.05);
            box-shadow: 0 10px 28px rgba(37,99,235,.45);
            transform: translateY(-1px);
        }
        .gdy-btn:disabled{
            opacity: .7;
            cursor: not-allowed;
            transform:none;
        }

        .gdy-meta{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:.75rem;
            flex-wrap:wrap;
            margin-top: .75rem;
            font-size: .82rem;
            color: var(--gdy-muted);
        }
        .gdy-meta a{
            color:#7dd3fc;
            text-decoration:none;
        }
        .gdy-meta a:hover{ text-decoration: underline; }

        .gdy-note{
            font-size: .78rem;
            color: #94a3b8;
            margin-top:.6rem;
            line-height:1.55;
        }

        .gdy-alert{
            border-radius: 1rem;
            border: 1px solid rgba(239,68,68,.35);
            background: rgba(239,68,68,.12);
            color:#fecaca;
            padding: .65rem .75rem;
            font-size: .88rem;
        }

        .caps-hint{
            display:none;
            margin-top:.35rem;
            font-size:.78rem;
            color:#fbbf24;
        }
        .caps-hint i{ margin-inline-start:.35rem; }

        .toggle-pass{
            border-radius: .9rem;
            border: 1px solid rgba(148,163,184,.35);
            background: rgba(2,6,23,.85);
            color:#cbd5f5;
        }
        .toggle-pass:hover{
            border-color: rgba(125,211,252,.55);
        }
    
.spin{animation:spin 1s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}


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

<div class="gdy-shell">

    <!-- لوحة مزايا (تظهر في الشاشات الكبيرة فقط) -->
    <aside class="gdy-side d-none d-lg-block" aria-label="<?= h(__('مزايا تسجيل الدخول')) ?>">
        <div class="gdy-side-inner">
            <div class="gdy-brand">
                <div class="logo">
                    <div class="logo-badge" aria-hidden="true">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use id="togglePassIcon" href="/assets/icons/gdy-icons.svg#eye"></use></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="title">Godyar News</div>
                        <div class="subtitle"><?= h(__('دخول آمن وسريع للأعضاء')) ?></div>
                    </div>
                </div>
                <a class="btn btn-sm btn-outline-light" href="<?= h($baseUrl) ?>/" title="<?= h(__('العودة للموقع')) ?>">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg>
                </a>
            </div>

            <div class="gdy-welcome" style="margin-top:18px;padding:18px 16px;border-radius:18px;background:rgba(2,6,23,.55);border:1px solid rgba(148,163,184,.18);">
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px;">
                    <div style="width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:rgba(14,165,233,.14);border:1px solid rgba(125,211,252,.35);">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#alert"></use></svg>
                    </div>
                    <div style="min-width:0">
                        <div style="font-weight:900;font-size:1.05rem;letter-spacing:.2px;color:#e2e8f0;">اهلا بكم في Godyar</div>
                        <div style="font-size:.86rem;color:rgba(203,213,225,.85);">سجّل دخولك للمتابعة وإدارة حسابك.</div>
                    </div>
                </div>
                <div style="font-size:.82rem;color:rgba(203,213,225,.75);line-height:1.55;">
                    يمكنك الدخول بالبريد أو اسم المستخدم.
                </div>
            </div>
    

</div>
    </aside>

    <!-- كرت الدخول -->
    <main class="gdy-card" aria-label="<?= h(__('تسجيل دخول الأعضاء')) ?>">
        <div class="gdy-card-inner">

            <div class="text-center mb-3">
                <div class="mb-2" aria-hidden="true">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                </div>
                <div class="gdy-title">
                    <h1><?= h(__('تسجيل دخول الأعضاء')) ?></h1>
                </div>
                <div class="gdy-sub">
                    <?= h(__('ادخل إلى حسابك لمتابعة الأخبار والمحتوى المخصص لك.')) ?>
                </div>
            </div>

            <?php if ($errorMessage): ?>
                <div class="gdy-alert mb-3" role="alert">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#alert"></use></svg>
                    <span class="ms-1"><?= $errorMessage ?></span>
                </div>
            <?php endif; ?>

            <div class="gdy-note mb-3" style="display:flex;flex-direction:column;gap:10px;">
                <div style="color:rgba(226,232,240,.9);font-weight:800;">
                    <?= h(__('أو سجّل الدخول عبر')) ?>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="btn btn-outline-light" href="<?= h($baseUrl) ?>/oauth/google?next=<?= urlencode($next !== '' ? $next : '/') ?>" style="border-radius:14px;">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#google"></use></svg> Google
                    </a>
                    <a class="btn btn-outline-light" href="<?= h($baseUrl) ?>/oauth/facebook?next=<?= urlencode($next !== '' ? $next : '/') ?>" style="border-radius:14px;">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg> Facebook
                    </a>
                    <a class="btn btn-outline-light" href="<?= h($baseUrl) ?>/oauth/github?next=<?= urlencode($next !== '' ? $next : '/') ?>" style="border-radius:14px;">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#github"></use></svg> GitHub
                    </a>
                </div>
            </div>

            <form method="post" novalidate autocomplete="on" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="next" value="<?= h($next) ?>">

                <div class="mb-3">
                    <label for="login" class="form-label"><?= h(__('البريد الإلكتروني أو اسم المستخدم')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#mail"></use></svg>
                        </span>
                        <input
                            type="text"
                            class="form-control"
                            id="login"
                            name="login"
                            required
                            inputmode="email"
                            autocomplete="username"
                            placeholder="name@example.com أو username"
                            value="<?= h($oldLogin) ?>"
                            autofocus
                        >
                    </div>
                </div>

                <div class="mb-2">
                    <label for="password" class="form-label"><?= h(__('كلمة المرور')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#lock"></use></svg>
                        </span>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        >
                        <button class="btn toggle-pass" type="button" id="togglePass" aria-label="<?= h(__('إظهار/إخفاء كلمة المرور')) ?>">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use id="togglePassIcon" href="/assets/icons/gdy-icons.svg#eye"></use></svg>
                        </button>
                    </div>
                    <div class="caps-hint" id="capsHint">
                        تنبيه: Caps Lock مفعل <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#alert"></use></svg>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                        <label class="form-check-label small" for="remember">
                            <?= h(__('تذكرني (30 يوم)')) ?>
                        </label>
                    </div>
                    <a class="small" href="<?= h($baseUrl) ?>/forgot-password.php">
                        <?= h(__('نسيت كلمة المرور؟')) ?>
                    </a>
                </div>

                <button type="submit" class="btn w-100 gdy-btn" id="submitBtn" <?= $btnDisabled ? 'disabled' : '' ?>>
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#login"></use></svg>
                    <span class="ms-1">دخول</span>
                </button>

                <?php if ($btnDisabled): ?>
                    <div class="gdy-note mt-2">
                        تم تعطيل الدخول مؤقتاً بسبب محاولات كثيرة. انتظر <b><?= (int)$blockedForNow ?></b> ثانية ثم جرّب.
                    </div>
                <?php endif; ?>
            </form>

            <div class="gdy-meta mt-3">
                <span>
                    ليس لديك حساب؟
                    <a href="<?= h($baseUrl) ?>/register"><?= h(__('إنشاء حساب جديد')) ?></a>
                </span>

                <span>
                    <a href="<?= h($baseUrl) ?>/">
                        <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg> <?= h(__('العودة للرئيسية')) ?>
                    </a>
                </span>
            </div>

            <div class="gdy-note mt-3">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#alert"></use></svg>
                <?= h(__('إذا فعّلت “تذكرني”، قد تحتاج الاستضافة لضبط إعدادات الجلسة (session.gc_maxlifetime) حتى تعمل بالكامل.')) ?>
            </div>

        </div>
    </main>

</div>

<script>
(function(){
    const pass = document.getElementById('password');
    const toggle = document.getElementById('togglePass');
    const iconUse = document.getElementById('togglePassIcon');
const capsHint = document.getElementById('capsHint');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('loginForm');

    if (toggle && pass) {
        toggle.addEventListener('click', function(){
            const isPass = pass.getAttribute('type') === 'password';
            pass.setAttribute('type', isPass ? 'text' : 'password');
            if (iconUse) {
                iconUse.setAttribute('href', isPass ? '/assets/icons/gdy-icons.svg#eye-off' : '/assets/icons/gdy-icons.svg#eye');
            }
            pass.focus();
        });
    }

    function updateCaps(e){
        if (!capsHint) return;
        const caps = e.getModifierState && e.getModifierState('CapsLock');
        capsHint.style.display = caps ? 'block' : 'none';
    }
    if (pass) {
        pass.addEventListener('keyup', updateCaps);
        pass.addEventListener('keydown', updateCaps);
        pass.addEventListener('focus', function(e){ updateCaps(e); });
        pass.addEventListener('blur', function(){ if (capsHint) capsHint.style.display='none'; });
    }

    if (form && submitBtn) {
        form.addEventListener('submit', function(){
            if (submitBtn.disabled) return;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="gdy-icon spin" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#spinner"></use></svg><span class="ms-2">جارٍ التحقق...</span>';
        });
    }
})();
</script>

</body>
</html>
