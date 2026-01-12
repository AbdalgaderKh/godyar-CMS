<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

function gdy_oauth_fail_facebook(string $msg, int $code = 400): void {
    http_response_code($code);
    echo '<!doctype html><meta charset="utf-8"><title>OAuth</title>';
    echo '<div style="font-family:system-ui;padding:24px">';
    echo '<h2>تعذر تسجيل الدخول عبر Facebook</h2>';
    echo '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/login">تسجيل الدخول</a></p>';
    echo '</div>';
    exit;
}

$appId = function_exists('env') ? (string)env('FACEBOOK_OAUTH_APP_ID', '') : '';
$appSecret = function_exists('env') ? (string)env('FACEBOOK_OAUTH_APP_SECRET', '') : '';
$graphVer = function_exists('env') ? (string)env('FACEBOOK_GRAPH_VERSION', 'v20.0') : 'v20.0';

if ($appId === '' || $appSecret === '') {
    gdy_oauth_fail_facebook('Facebook OAuth غير مُعدّ.', 500);
}

$code = (string)($_GET['code'] ?? '');
$state = (string)($_GET['state'] ?? '');
$expected = (string)($_SESSION['oauth_facebook_state'] ?? '');
if ($code === '' || $state === '' || $expected === '' || !hash_equals($expected, $state)) {
    gdy_oauth_fail_facebook('رمز التحقق غير صحيح.');
}

if (!function_exists('curl_init')) {
    gdy_oauth_fail_facebook('cURL غير متوفر على السيرفر.');
}

$base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
$redirectUri = $base . '/oauth/facebook/callback';

$graphVer = trim($graphVer);
if ($graphVer === '') $graphVer = 'v20.0';

// Exchange code for token (GET)
$tokenUrl = 'https://graph.facebook.com/' . rawurlencode($graphVer) . '/oauth/access_token?' . http_build_query([
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'client_secret' => $appSecret,
    'code' => $code,
], '', '&', PHP_QUERY_RFC3986);

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_TIMEOUT => 20,
]);
$res = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false || $http < 200 || $http >= 300) {
    gdy_oauth_fail_facebook('فشل الحصول على رمز الدخول من Facebook.');
}

$tok = json_decode((string)$res, true);
if (!is_array($tok)) {
    gdy_oauth_fail_facebook('استجابة Facebook غير صالحة.');
}

$accessToken = (string)($tok['access_token'] ?? '');
if ($accessToken === '') {
    gdy_oauth_fail_facebook('لم يتم استلام access_token.');
}

// Fetch user
$meUrl = 'https://graph.facebook.com/' . rawurlencode($graphVer) . '/me?' . http_build_query([
    'fields' => 'id,name,email,picture.type(large)',
    'access_token' => $accessToken,
], '', '&', PHP_QUERY_RFC3986);

$ch = curl_init($meUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_TIMEOUT => 20,
]);
$uRes = curl_exec($ch);
$uHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($uRes === false || $uHttp < 200 || $uHttp >= 300) {
    gdy_oauth_fail_facebook('تعذر قراءة بيانات المستخدم من Facebook.');
}

$u = json_decode((string)$uRes, true);
if (!is_array($u)) {
    gdy_oauth_fail_facebook('بيانات Facebook غير صالحة.');
}

$facebookId = (string)($u['id'] ?? '');
$email = (string)($u['email'] ?? '');
$displayName = trim((string)($u['name'] ?? ''));
$avatarUrl = '';
if (isset($u['picture']['data']['url'])) {
    $avatarUrl = trim((string)$u['picture']['data']['url']);
}

if ($displayName === '') {
    $displayName = 'Facebook';
}

if ($facebookId === '') {
    gdy_oauth_fail_facebook('بيانات Facebook غير مكتملة (id).');
}
if ($email === '') {
    // Facebook قد لا يعيد البريد إن لم يكن متاحاً/غير مصرح.
    // نطلب email scope، لكن قد يظل فارغاً.
    gdy_oauth_fail_facebook('لم يتم تزويد البريد الإلكتروني من Facebook. تأكد من تفعيل صلاحية email في إعدادات التطبيق، أو استخدم طريقة دخول أخرى.');
}

$username = '';
if (strpos($email, '@') !== false) {
    $username = strtolower(substr($email, 0, strpos($email, '@')));
    $username = preg_replace('~[^a-z0-9_\.\-]+~', '_', $username);
    $username = trim($username, '._-');
}
if ($username === '') {
    $username = 'fb_' . substr(preg_replace('~\D+~', '', $facebookId), 0, 10);
}

$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    gdy_oauth_fail_facebook('تعذر الاتصال بقاعدة البيانات.', 500);
}

try {
    $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!is_array($cols) || empty($cols)) {
        throw new RuntimeException('No users columns');
    }
} catch (Throwable $e) {
    gdy_oauth_fail_facebook('جدول users غير متوفر.', 500);
}

$col_exists = static fn(array $c, string $n): bool => in_array($n, $c, true);

// Locate user
$userRow = null;
if ($col_exists($cols, 'facebook_id')) {
    $st = $pdo->prepare('SELECT * FROM users WHERE facebook_id = :fid LIMIT 1');
    $st->execute([':fid' => $facebookId]);
    $userRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$userRow) {
    $st = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $st->execute([':email' => $email]);
    $userRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$userRow) {
    $insertCols = ['email'];
    $insertVals = [':email'];
    $bind = [':email' => $email];

    if ($col_exists($cols, 'username')) {
        $insertCols[] = 'username';
        $insertVals[] = ':username';
        $bind[':username'] = $username;
    }
    if ($col_exists($cols, 'display_name')) {
        $insertCols[] = 'display_name';
        $insertVals[] = ':display_name';
        $bind[':display_name'] = $displayName;
    }
    if ($col_exists($cols, 'facebook_id')) {
        $insertCols[] = 'facebook_id';
        $insertVals[] = ':facebook_id';
        $bind[':facebook_id'] = $facebookId;
    }
    if ($avatarUrl !== '' && $col_exists($cols, 'avatar')) {
        $insertCols[] = 'avatar';
        $insertVals[] = ':avatar';
        $bind[':avatar'] = $avatarUrl;
    }
    if ($col_exists($cols, 'role')) {
        $insertCols[] = 'role';
        $insertVals[] = ':role';
        $bind[':role'] = 'user';
    }
    if ($col_exists($cols, 'status')) {
        $insertCols[] = 'status';
        $insertVals[] = ':status';
        $bind[':status'] = 'active';
    }
    if ($col_exists($cols, 'created_at')) {
        $insertCols[] = 'created_at';
        $insertVals[] = 'NOW()';
    }
    if ($col_exists($cols, 'updated_at')) {
        $insertCols[] = 'updated_at';
        $insertVals[] = 'NOW()';
    }

    $sqlIns = "INSERT INTO users (" . implode(',', array_map(fn($c)=>"`{$c}`", $insertCols)) . ") VALUES (" . implode(',', $insertVals) . ")";
    $ins = $pdo->prepare($sqlIns);
    $ins->execute($bind);
    $newId = (int)$pdo->lastInsertId();

    $userRow = [
        'id' => $newId,
        'email' => $email,
        'username' => $username,
        'display_name' => $displayName,
        'role' => 'user',
        'status' => 'active',
        'avatar' => $avatarUrl,
    ];
} else {
    $updates = [];
    $bind = [':id' => (int)($userRow['id'] ?? 0)];
    if ($col_exists($cols, 'facebook_id') && empty($userRow['facebook_id'])) {
        $updates[] = 'facebook_id = :facebook_id';
        $bind[':facebook_id'] = $facebookId;
    }
    if ($avatarUrl !== '' && $col_exists($cols, 'avatar')) {
        $updates[] = 'avatar = :avatar';
        $bind[':avatar'] = $avatarUrl;
    }
    if (!empty($updates)) {
        $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id')->execute($bind);
    }
}

// Log in
session_regenerate_id(true);
if (function_exists('auth_set_user_session')) {
    auth_set_user_session([
        'id' => (int)($userRow['id'] ?? 0),
        'username' => (string)($userRow['username'] ?? $username),
        'display_name' => (string)($userRow['display_name'] ?? $displayName),
        'email' => (string)($userRow['email'] ?? $email),
        'role' => (string)($userRow['role'] ?? 'user'),
        'status' => (string)($userRow['status'] ?? 'active'),
        'avatar' => (string)($userRow['avatar'] ?? $avatarUrl),
    ]);
} else {
    $_SESSION['user'] = [
        'id' => (int)($userRow['id'] ?? 0),
        'username' => (string)($userRow['username'] ?? $username),
        'display_name' => (string)($userRow['display_name'] ?? $displayName),
        'email' => (string)($userRow['email'] ?? $email),
        'role' => (string)($userRow['role'] ?? 'user'),
        'status' => (string)($userRow['status'] ?? 'active'),
        'avatar' => (string)($userRow['avatar'] ?? $avatarUrl),
        'login_at' => date('Y-m-d H:i:s'),
    ];
    $_SESSION['is_member_logged'] = true;


// Ensure legacy session keys exist (used by some templates/widgets)
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $_SESSION['user_id']    = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
    $_SESSION['user_email'] = (string)($_SESSION['user_email'] ?? $_SESSION['user']['email'] ?? '');
    $_SESSION['user_name']  = (string)($_SESSION['user_name'] ?? $_SESSION['user']['display_name'] ?? $_SESSION['user']['username'] ?? '');
    $_SESSION['user_role']  = (string)($_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? 'user');
    $_SESSION['is_member_logged'] = true;
}
}

$next = (string)($_SESSION['oauth_next'] ?? '/');
unset($_SESSION['oauth_next'], $_SESSION['oauth_facebook_state']);
if ($next === '' || str_contains($next, "\n") || str_contains($next, "\r")) $next = '/';
if (str_starts_with($next, 'http://') || str_starts_with($next, 'https://')) $next = '/';
if (!str_starts_with($next, '/')) $next = '/';

header('Location: ' . $base . $next, true, 302);
exit;
