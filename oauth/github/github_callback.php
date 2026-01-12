<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$clientId = env('GITHUB_OAUTH_CLIENT_ID', '');
$clientSecret = env('GITHUB_OAUTH_CLIENT_SECRET', '');
if (!$clientId || !$clientSecret) {
    http_response_code(500);
    exit("GitHub OAuth غير مُعدّ");
}

$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$expected = $_SESSION['oauth_state_github'] ?? '';

if (!$code || !$state || !$expected || !hash_equals($expected, $state)) {
    http_response_code(400);
    exit("تعذر تسجيل الدخول عبر GitHub: رمز التحقق غير صحيح.");
}

$base = rtrim((defined('APP_URL') && APP_URL) ? APP_URL : (defined('APP_URL_AUTO') ? APP_URL_AUTO : ''), '/');
$redirectUri = $base . '/oauth/github/callback';

// Exchange code for token
$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $redirectUri,
    ]),
]);
$res = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode((string)$res, true) ?: [];
if ($http >= 400 || empty($data['access_token'])) {
    @error_log('[GitHubOAuth] token exchange failed: HTTP=' . $http . ' RES=' . (string)$res);
    http_response_code(400);
    exit("تعذر تسجيل الدخول عبر GitHub.");
}

$token = (string)$data['access_token'];

// Fetch user
$ch = curl_init('https://api.github.com/user');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'User-Agent: Godyar',
        'Accept: application/vnd.github+json',
    ],
]);
$uRes = curl_exec($ch);
$uHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$u = json_decode((string)$uRes, true) ?: [];
$login = (string)($u['login'] ?? '');
$gid   = (string)($u['id'] ?? '');
$name  = trim((string)($u['name'] ?? $login));
$avatar= trim((string)($u['avatar_url'] ?? ''));

// Fetch primary email
$ch = curl_init('https://api.github.com/user/emails');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'User-Agent: Godyar',
        'Accept: application/vnd.github+json',
    ],
]);
$eRes = curl_exec($ch);
$eHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$email = '';
$emails = json_decode((string)$eRes, true) ?: [];
if ($eHttp < 400 && is_array($emails)) {
    foreach ($emails as $row) {
        if (!empty($row['primary']) && !empty($row['verified']) && !empty($row['email'])) {
            $email = strtolower(trim((string)$row['email']));
            break;
        }
    }
    if ($email === '') {
        foreach ($emails as $row) {
            if (!empty($row['verified']) && !empty($row['email'])) {
                $email = strtolower(trim((string)$row['email']));
                break;
            }
        }
    }
}

if ($email === '') {
    http_response_code(400);
    exit("تعذر تسجيل الدخول عبر GitHub: لم نستطع الحصول على البريد الإلكتروني (تأكد من أن بريدك غير مخفي).");
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('لا يمكن الاتصال بقاعدة البيانات حالياً.');
}

$st = $pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
$st->execute([':e' => $email]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $username = $login !== '' ? $login : preg_replace('/[^a-z0-9_]+/i', '_', strtok($email, '@') ?: 'user');
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
unset($_SESSION['oauth_next'], $_SESSION['oauth_state_github']);

if (!is_string($next) || $next === '' || $next[0] !== '/') $next = '/';
header('Location: ' . $next);
exit;
