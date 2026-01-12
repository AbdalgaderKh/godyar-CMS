<?php
declare(strict_types=1);

/**
 * صفحة إنشاء حساب — /register
 * محسّنة: CSRF + Rate limit + تحقق قوي + تصميم حديث + إظهار/إخفاء كلمة المرور
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// هيلبر للهروب الآمن
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// base url
$baseUrl = function_exists('base_url') ? rtrim(base_url(), '/') : '';

// لو المستخدم مسجّل مسبقاً → رجوع للرئيسية
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    header('Location: ' . $baseUrl . '/');
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

// ---------------- CSRF ----------------
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        $_SESSION['csrf_token'] = bin2hex((string)microtime(true));
    }
}
function csrf_token(): string {
    return (string)($_SESSION['csrf_token'] ?? '');
}
function verify_csrf_or_fail(): bool {
    $token = (string)($_POST['csrf_token'] ?? '');
    return $token !== '' && hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token);
}

// -------- Helpers: DB schema safe --------
function table_exists(PDO $pdo, string $table): bool {
    return function_exists('db_table_exists') ? db_table_exists($pdo, $table) : false;
}
function get_table_columns(PDO $pdo, string $table): array {
    return function_exists('db_table_columns') ? db_table_columns($pdo, $table) : [];
}
function col_exists(array $cols, string $col): bool {
    return in_array($col, $cols, true);
}

// -------- Rate limit (بسيط: جلسة + IP) --------
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return (string)$ip;
}
function rate_limit_hit(string $key, int $maxAttempts, int $windowSeconds): bool {
    if (!isset($_SESSION['_rl'])) $_SESSION['_rl'] = [];
    $now = time();

    $bucket = $_SESSION['_rl'][$key] ?? ['count' => 0, 'reset' => $now + $windowSeconds];
    if (($bucket['reset'] ?? 0) < $now) {
        $bucket = ['count' => 0, 'reset' => $now + $windowSeconds];
    }
    $bucket['count'] = (int)($bucket['count'] ?? 0) + 1;
    $_SESSION['_rl'][$key] = $bucket;

    return $bucket['count'] > $maxAttempts;
}

// -------------- Form state --------------
$errorMessage = '';
$old = [
    'username' => '',
    'email' => '',
    'agree' => '0',
];

$redirect = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
if ($redirect !== '' && !preg_match('~^/[A-Za-z0-9/_\-.]*$~', $redirect)) {
    $redirect = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // RL: 10 محاولات في 10 دقائق لكل IP
    $ip = get_client_ip();
    if (rate_limit_hit('register:' . $ip, 10, 600)) {
        $errorMessage = 'محاولات كثيرة خلال وقت قصير. الرجاء المحاولة لاحقاً.';
    } elseif (!$pdo instanceof PDO) {
        $errorMessage = 'لا يمكن الاتصال بقاعدة البيانات حالياً.';
    } elseif (!verify_csrf_or_fail()) {
        $errorMessage = 'انتهت صلاحية الجلسة. حدّث الصفحة وحاول مرة أخرى.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass     = (string)($_POST['password'] ?? '');
        $pass2    = (string)($_POST['password2'] ?? '');
        $agree    = !empty($_POST['agree']) ? '1' : '0';

        $old['username'] = $username;
        $old['email']    = $email;
        $old['agree']    = $agree;

        // تحقق أساسي
        if ($email === '' || $pass === '' || $pass2 === '') {
            $errorMessage = 'يرجى تعبئة البريد الإلكتروني وكلمة المرور وتأكيدها.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'البريد الإلكتروني غير صحيح.';
        } elseif ($pass !== $pass2) {
            $errorMessage = 'كلمتا المرور غير متطابقتين.';
        } elseif (mb_strlen($pass) < 8) {
            $errorMessage = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
        } elseif (!preg_match('~[A-Za-z]~', $pass) || !preg_match('~\d~', $pass)) {
            $errorMessage = 'كلمة المرور يجب أن تحتوي على حرف واحد ورقم واحد على الأقل.';
        } elseif ($agree !== '1') {
            $errorMessage = 'يرجى الموافقة على الشروط والأحكام.';
        } else {
            try {
                if (!table_exists($pdo, 'users')) {
                    throw new RuntimeException('Missing users table');
                }

                $cols = get_table_columns($pdo, 'users');

                // username: إن كان موجوداً في الجدول نُطبّق تحقّق عليه
                if (col_exists($cols, 'username')) {
                    if ($username === '') {
                        $errorMessage = 'يرجى إدخال اسم المستخدم.';
                    } else {
                        // ✅ اسم المستخدم: 3-30 (يدعم العربية) ويمكن أن يحتوي على أرقام و . _ -
                        // * نقبل مسافة داخلية بين الكلمات (بدون مسافة في البداية/النهاية)
                        // * نقبل العلامات (التشكيل) عبر \p{M}
                        $username = preg_replace('~\s+~u', ' ', $username);
                        $old['username'] = $username;

                        $ulen = mb_strlen($username, 'UTF-8');
                        if ($ulen < 3 || $ulen > 30) {
                            $errorMessage = 'اسم المستخدم يجب أن يكون 3-30 حرف (يدعم العربية)، ويمكن أن يحتوي على أرقام و . _ -';
                        } elseif (!preg_match('~^[\p{L}\p{M}\p{N}._-]+(?: [\p{L}\p{M}\p{N}._-]+)*$~u', $username)) {
                            $errorMessage = 'اسم المستخدم يجب أن يكون 3-30 حرف (يدعم العربية)، ويمكن أن يحتوي على أرقام و . _ -';
                        }
                    }
                } else {
                    $username = ''; // تجاهل لو العمود غير موجود
                }

                if ($errorMessage === '') {
                    // هل البريد/اسم المستخدم موجود مسبقاً؟
                    $where = ["email = :email"];
                    $params = [':email' => $email];

                    if ($username !== '' && col_exists($cols, 'username')) {
                        $where[] = "username = :username";
                        $params[':username'] = $username;
                    }

                    $sqlCheck = "SELECT id FROM users WHERE (" . implode(" OR ", $where) . ") LIMIT 1";
                    $st = $pdo->prepare($sqlCheck);
                    $st->execute($params);
                    $exists = $st->fetch(PDO::FETCH_ASSOC);

                    if ($exists) {
                        $errorMessage = 'هذا البريد أو اسم المستخدم مستخدم بالفعل.';
                    } else {
                        // اختيار عمود كلمة المرور (password_hash أو password)
                        $passCol = col_exists($cols, 'password_hash') ? 'password_hash' : (col_exists($cols, 'password') ? 'password' : '');
                        if ($passCol === '') {
                            throw new RuntimeException('No password column found');
                        }

                        $hash = password_hash($pass, PASSWORD_DEFAULT);


                        // display_name (اسم الظهور) افتراضيًا = username أو الجزء قبل @ من البريد
                        $displayName = $username !== '' ? $username : (string)preg_replace('/@.*/', '', $email);
                        // بناء INSERT ديناميكياً حسب الأعمدة المتاحة
                        $insertCols = [];
                        $insertVals = [];
                        $bind = [];

                        // email
                        $insertCols[] = 'email';
                        $insertVals[] = ':email';
                        $bind[':email'] = $email;

                        // username (اختياري)
                        if ($username !== '' && col_exists($cols, 'username')) {
                            $insertCols[] = 'username';
                            $insertVals[] = ':username';
                            $bind[':username'] = $username;
                        }


                        // display_name (اختياري)
                        if (col_exists($cols, 'display_name')) {
                            $insertCols[] = 'display_name';
                            $insertVals[] = ':display_name';
                            $bind[':display_name'] = $displayName;
                        }
                        // password
                        $insertCols[] = $passCol;
                        $insertVals[] = ':pass';
                        $bind[':pass'] = $hash;

                        // role/status إن توفرت
                        if (col_exists($cols, 'role')) {
                            $insertCols[] = 'role';
                            $insertVals[] = ':role';
                            $bind[':role'] = 'user';
                        }
                        if (col_exists($cols, 'status')) {
                            $insertCols[] = 'status';
                            $insertVals[] = ':status';
                            $bind[':status'] = 'active';
                        }

                        // created_at/updated_at إن توفرت
                        if (col_exists($cols, 'created_at')) {
                            $insertCols[] = 'created_at';
                            $insertVals[] = 'NOW()';
                        }
                        if (col_exists($cols, 'updated_at')) {
                            $insertCols[] = 'updated_at';
                            $insertVals[] = 'NOW()';
                        }

                        $sqlIns = "INSERT INTO users (" . implode(',', array_map(fn($c)=>"`{$c}`",$insertCols)) . ") VALUES (" . implode(',', $insertVals) . ")";
                        $ins = $pdo->prepare($sqlIns);
                        $ins->execute($bind);

                        $newId = (int)$pdo->lastInsertId();

                        // ✅ تسجيل دخول تلقائي بعد التسجيل (توحيد الجلسة)
                        session_regenerate_id(true);
                        if (function_exists('auth_set_user_session')) {
                            auth_set_user_session([
                                'id'       => $newId,
                                'username' => ($username !== '' ? $username : null),
                                'display_name' => $displayName,
                                'email'    => $email,
                                'role'     => 'user',
                                'status'   => 'active',
                                'avatar'   => null,
                            ]);
                        } else {
                            $_SESSION['user'] = [
                                'id'        => $newId,
                                'username'  => ($username !== '' ? $username : null),
                                'email'     => $email,
                                'role'      => 'user',
                                'status'    => 'active',
                                'login_at'  => date('Y-m-d H:i:s'),
                            ];
                            $_SESSION['is_member_logged'] = true;
                        }

                        $go = $baseUrl . ($redirect !== '' ? $redirect : '/');
                        header('Location: ' . $go);
                        exit;
                    }
                }
            } catch (Throwable $e) {
                $trace = 'GDY-REG-' . substr(bin2hex(random_bytes(10)), 0, 12);
                @error_log("[$trace] [register] " . $e->getMessage());
                $errorMessage = 'حدث خطأ أثناء إنشاء الحساب. الرجاء المحاولة لاحقاً. رقم التتبع: ' . $trace;
            }
        }
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8">
    <title>إنشاء حساب جديد - Godyar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap RTL -->
    <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <style>
        :root{
            /* Theme bridge: use global theme variables */
            --gdy-accent: var(--primary);
            --gdy-accent-rgb: var(--primary-rgb);

            --gdy-bg1:#0f172a;
            --gdy-bg2:#020617;
            --gdy-card: rgba(15,23,42,0.96);
            --gdy-border: rgba(148,163,184,0.40);
            --gdy-muted:#9ca3af;
            --gdy-text:#e5e7eb;
            --gdy-title:#f9fafb;
            --gdy-primary:var(--gdy-accent);
        }
        body{
            min-height:100vh;
            background: radial-gradient(circle at top, var(--gdy-bg1), var(--gdy-bg2) 55%, var(--gdy-bg2) 100%);
            color:var(--gdy-text);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:1rem;
        }
        .gdy-auth-shell{
            width:100%;
            max-width: 460px;
        }
        .gdy-card{
            width:100%;
            background: var(--gdy-card);
            border-radius: 1.35rem;
            border: 1px solid var(--gdy-border);
            box-shadow: 0 18px 40px rgba(15,23,42,0.9);
            padding: 1.7rem 1.6rem 1.35rem;
            position: relative;
            overflow: hidden;
        }
        .gdy-card::before{
            content:'';
            position:absolute;
            inset:-40%;
            background:
                radial-gradient(circle at top right, rgba(var(--gdy-accent-rgb),0.14), transparent 60%),
                radial-gradient(circle at bottom left, rgba(var(--gdy-accent-rgb),0.16), transparent 60%);
            opacity:.95;
            pointer-events:none;
        }
        .gdy-inner{ position:relative; z-index:1; }
        .gdy-badge{
            display:inline-flex;
            align-items:center;
            gap:.45rem;
            font-size:.78rem;
            padding:.35rem .7rem;
            border-radius:999px;
            border:1px solid rgba(148,163,184,0.45);
            background: rgba(2,6,23,0.55);
            color: var(--gdy-muted);
        }
        .gdy-title{
            font-size:1.25rem;
            font-weight:800;
            color: var(--gdy-title);
            margin:.6rem 0 .35rem;
        }
        .gdy-sub{
            font-size:.92rem;
            color: var(--gdy-muted);
            margin-bottom: 1.1rem;
        }
        .form-control, .form-select{
            background:#020617;
            border:1px solid rgba(55,65,81,0.95);
            color: var(--gdy-text);
            border-radius: .95rem;
            padding:.6rem .9rem;
        }
        .form-control:focus, .form-select:focus{
            background:#020617;
            border-color: var(--gdy-primary);
            box-shadow: 0 0 0 .14rem rgba(14,165,233,0.25);
            color: var(--gdy-text);
        }
        .form-label{
            font-size:.86rem;
            color:#cbd5f5;
            margin-bottom:.35rem;
        }
        .gdy-btn{
            border:none;
            width:100%;
            border-radius: .95rem;
            padding: .62rem 1rem;
            font-weight: 700;
            color:#f9fafb;
            background: linear-gradient(135deg, var(--gdy-primary), #2563eb);
            transition: transform .15s ease, filter .2s ease, box-shadow .2s ease;
        }
        .gdy-btn:hover{
            filter: brightness(1.05);
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(37,99,235,0.35);
        }
        .gdy-foot{
            margin-top: 1rem;
            font-size: .82rem;
            color: var(--gdy-muted);
            display:flex;
            justify-content: space-between;
            gap:.75rem;
            flex-wrap: wrap;
        }
        .gdy-foot a{
            color:#38bdf8;
            text-decoration:none;
        }
        .gdy-foot a:hover{ text-decoration:underline; }
        .gdy-pass-meter{
            height: 6px;
            background: rgba(31,41,55,0.95);
            border-radius: 999px;
            overflow:hidden;
        }
        .gdy-pass-meter > span{
            display:block;
            height:100%;
            width:0%;
            background: linear-gradient(90deg, #ef4444, #f59e0b, var(--gdy-accent));
            transition: width .25s ease;
        }
        .gdy-pass-hint{
            font-size:.78rem;
            color: var(--gdy-muted);
        }
        .gdy-icon-btn{
            border:1px solid rgba(148,163,184,0.35);
            background: rgba(2,6,23,0.55);
            color:#e5e7eb;
            border-radius: .85rem;
        }
        .gdy-icon-btn:hover{
            border-color: rgba(148,163,184,0.65);
            background: rgba(2,6,23,0.75);
            color:#fff;
        }
        .alert{ border-radius: 1rem; }
    
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

<div class="gdy-auth-shell">
    <div class="gdy-card">
        <div class="gdy-inner">

            <div class="text-center">
                <span class="gdy-badge">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#eye"></use></svg>
                    تسجيل آمن
                </span>
                <div class="mt-2">
                    <svg class="gdy-icon text-info" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                </div>
                <h1 class="gdy-title">إنشاء حساب جديد</h1>
                <p class="gdy-sub mb-0">أنشئ حسابك للوصول للمفضلة والمزايا المستقبلية.</p>
            </div>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger py-2 mt-3">
                    <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                    <?= h($errorMessage) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="mt-3" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <?php if ($redirect !== ''): ?>
                    <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label" for="username">اسم المستخدم</label>
                    <input
                        type="text"
                        class="form-control"
                        id="username"
                        name="username"
                        value="<?= h($old['username'] ?? '') ?>"
                        autocomplete="username"
                        placeholder="مثال: godyar_user"
                    >
                    <div class="form-text gdy-pass-hint">
                        حروف/أرقام/نقطة/شرطة سفلية (3-30). لو النظام عندك لا يستخدم اسم مستخدم سيتم تجاهله تلقائياً.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="email">البريد الإلكتروني</label>
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        required
                        value="<?= h($old['email'] ?? '') ?>"
                        autocomplete="email"
                        placeholder="name@example.com"
                    >
                </div>

                <div class="mb-2">
                    <label class="form-label" for="password">كلمة المرور</label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="8 أحرف على الأقل"
                        >
                        <button class="btn gdy-icon-btn" type="button" id="togglePass" aria-label="إظهار/إخفاء كلمة المرور">
                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use id="togglePassIcon" href="/assets/icons/gdy-icons.svg#eye"></use></svg>
                        </button>
                    </div>
                    <div class="mt-2 gdy-pass-meter"><span id="passBar"></span></div>
                    <div class="mt-1 gdy-pass-hint" id="passHint">نصيحة: استخدم حروف كبيرة/صغيرة + أرقام + رموز.</div>
                    <div class="mt-1 small text-warning d-none" id="capsWarn">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#alert"></use></svg> يبدو أن Caps Lock مفعّل.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password2">تأكيد كلمة المرور</label>
                    <input
                        type="password"
                        class="form-control"
                        id="password2"
                        name="password2"
                        required
                        autocomplete="new-password"
                        placeholder="أعد كتابة كلمة المرور"
                    >
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="agree" name="agree" <?= ($old['agree'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="agree">
                        أوافق على الشروط والأحكام وسياسة الخصوصية
                    </label>
                </div>

                <button type="submit" class="gdy-btn">
                    <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                    إنشاء الحساب
                </button>
            </form>

            <div class="gdy-foot">
                <span>
                    لديك حساب؟
                    <a href="<?= h($baseUrl) ?>/login<?= $redirect ? ('?redirect=' . urlencode($redirect)) : '' ?>">تسجيل الدخول</a>
                </span>
                <span>
                    <a href="<?= h($baseUrl) ?>/">
                        <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg> العودة للرئيسية
                    </a>
                </span>
            </div>

        </div>
    </div>
</div>

<script>
(function(){
    const pass = document.getElementById('password');
    const pass2 = document.getElementById('password2');
    const bar = document.getElementById('passBar');
    const hint = document.getElementById('passHint');
    const caps = document.getElementById('capsWarn');
    const toggle = document.getElementById('togglePass');

    function scorePassword(p){
        let s = 0;
        if(!p) return 0;
        if(p.length >= 8) s += 25;
        if(p.length >= 12) s += 15;
        if(/[A-Z]/.test(p)) s += 15;
        if(/[a-z]/.test(p)) s += 10;
        if(/[0-9]/.test(p)) s += 15;
        if(/[^A-Za-z0-9]/.test(p)) s += 20;
        return Math.min(s, 100);
    }

    function updateMeter(){
        const p = pass.value || '';
        const s = scorePassword(p);
        bar.style.width = s + '%';
        if(s < 40) hint.textContent = 'ضعيفة: زِد الطول وأضف أرقام/رموز.';
        else if(s < 70) hint.textContent = 'متوسطة: أضف رموزاً وحروفاً كبيرة.';
        else hint.textContent = 'قوية 👍';
    }

    if(pass){
        pass.addEventListener('input', updateMeter);
        pass.addEventListener('keyup', function(e){
            if(caps) caps.classList.toggle('d-none', !e.getModifierState || !e.getModifierState('CapsLock'));
        });
    }

    if(toggle && pass){
        toggle.addEventListener('click', function(){
            const isText = pass.getAttribute('type') === 'text';
            pass.setAttribute('type', isText ? 'password' : 'text');
            const icon = toggle.querySelector('i');
            if(icon){
                icon.className = isText ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
            }
        });
    }

    // تلميح بسيط عند عدم التطابق
    if(pass2 && pass){
        pass2.addEventListener('input', function(){
            if(!pass2.value) return;
            if(pass2.value !== pass.value){
                pass2.style.borderColor = 'rgba(239,68,68,.9)';
            } else {
                pass2.style.borderColor = 'rgba(34,197,94,.9)';
            }
        });
    }

    updateMeter();
})();
</script>

</body>
</html>
