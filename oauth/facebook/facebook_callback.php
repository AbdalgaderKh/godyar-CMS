<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$appId = env('FACEBOOK_OAUTH_APP_ID', '');
$appSecret = env('FACEBOOK_OAUTH_APP_SECRET', '');
$ver = env('FACEBOOK_GRAPH_VERSION', 'v20.0');

if (!$appId || !$appSecret) {
    http_response_code(500);
    exit("Facebook OAuth غير مُعدّ");
}

$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$expected = $_SESSION['oauth_state_facebook'] ?? '';

if (!$code || !$state || !$expected || !hash_equals($expected, $state)) {
    http_response_code(400);
    exit("تعذر تسجيل الدخول عبر Facebook: رمز التحقق غير صحيح.");
}

$base = rtrim((defined('APP_URL') && APP_URL) ? APP_URL : (defined('APP_URL_AUTO') ? APP_URL_AUTO : ''), '/');
$redirectUri = $base . '/oauth/facebook/callback';

// Exchange code for access token
$tokenUrl = "https://graph.facebook.com/{$ver}/oauth/access_token?" . http_build_query([
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'client_secret' => $appSecret,
    'code' => $code,
]);

$tokenJson = @file_get_contents($tokenUrl);
$data = json_decode((string)$tokenJson, true) ?: [];

if (empty($data['access_token'])) {
    @error_log('[FacebookOAuth] token exchange failed: ' . (string)$tokenJson);
    http_response_code(400);
    exit("تعذر تسجيل الدخول عبر Facebook.");
}

$accessToken = (string)$data['access_token'];

// Fetch user profile
$meUrl = "https://graph.facebook.com/{$ver}/me?" . http_build_query([
    'fields' => 'id,name,email,picture.type(large)',
    'access_token' => $accessToken,
]);

$meJson = @file_get_contents($meUrl);
$me = json_decode((string)$meJson, true) ?: [];

$email = strtolower(trim((string)($me['email'] ?? '')));
$name  = trim((string)($me['name'] ?? ''));
$fid   = (string)($me['id'] ?? '');
$pic   = (string)($me['picture']['data']['url'] ?? '');

if ($email === '') {
    // بعض حسابات فيسبوك ما تعطي email إذا الصلاحية غير مفعلة/غير متاحة
    http_response_code(400);
    exit("تعذر تسجيل الدخول عبر Facebook: البريد الإلكتروني غير متاح من الحساب.");
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('لا يمكن الاتصال بقاعدة البيانات حالياً.');
}

// ابحث/أنشئ مستخدم بنفس منطق Google (بالإيميل)
$st = $pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
$st->execute([':e' => $email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $username = preg_replace('/[^a-z0-9_]+/i', '_', strtok($email, '@') ?: 'user');
    $username = strtolower(trim($username, '_'));
    if ($username === '') $username = 'user';

    $pdo->prepare("INSERT INTO users (username,email,password_hash,role,status,created_at) VALUES (:u,:e,:p,'user','active',NOW())")
        ->execute([
            ':u' => substr($username, 0, 30),
            ':e' => $email,
            ':p' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        ]);

    $st->execute([':e' => $email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_email'] = $email;
$_SESSION['user_role'] = $user['role'] ?? 'user';
$_SESSION['is_member_logged'] = true;


// Ensure legacy session keys exist (used by some templates/widgets)
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $_SESSION['user_id']    = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
    $_SESSION['user_email'] = (string)($_SESSION['user_email'] ?? $_SESSION['user']['email'] ?? '');
    $_SESSION['user_name']  = (string)($_SESSION['user_name'] ?? $_SESSION['user']['display_name'] ?? $_SESSION['user']['username'] ?? '');
    $_SESSION['user_role']  = (string)($_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? 'user');
    $_SESSION['is_member_logged'] = true;
}

$next = $_SESSION['oauth_next'] ?? '/';
unset($_SESSION['oauth_next'], $_SESSION['oauth_state_facebook']);

if (!is_string($next) || $next === '' || $next[0] !== '/') $next = '/';
header('Location: ' . $next);
exit;
