<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

function gdy_oauth_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo '<!doctype html><meta charset="utf-8"><title>OAuth</title>';
    echo '<div style="font-family:system-ui;padding:24px">';
    echo '<h2>تعذر تسجيل الدخول عبر GitHub</h2>';
    echo '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/login">تسجيل الدخول</a></p>';
    echo '</div>';
    exit;
}

$clientId = function_exists('env') ? (string)env('GITHUB_OAUTH_CLIENT_ID', '') : '';
$clientSecret = function_exists('env') ? (string)env('GITHUB_OAUTH_CLIENT_SECRET', '') : '';

if ($clientId === '' || $clientSecret === '') {
    gdy_oauth_fail('GitHub OAuth غير مُعدّ.', 500);
}

$code = (string)($_GET['code'] ?? '');
$state = (string)($_GET['state'] ?? '');
$expected = (string)($_SESSION['oauth_github_state'] ?? '');
if ($code === '' || $state === '' || $expected === '' || !hash_equals($expected, $state)) {
    gdy_oauth_fail('رمز التحقق غير صحيح.');
}

$base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
$redirectUri = $base . '/oauth/github/callback';

// Exchange code for access token
$token = null;
if (function_exists('curl_init')) {
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
            'state' => $state,
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $http < 200 || $http >= 300) {
        gdy_oauth_fail('فشل الحصول على رمز الدخول من GitHub.');
    }
    $data = json_decode((string)$res, true);
    $token = is_array($data) ? (string)($data['access_token'] ?? '') : '';
} else {
    gdy_oauth_fail('cURL غير متوفر على السيرفر.');
}

if (!$token) {
    gdy_oauth_fail('لم يتم استلام access_token.');
}

// Fetch user
function gdy_github_get(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $token,
            'User-Agent: Godyar',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $http < 200 || $http >= 300) {
        return [];
    }
    $d = json_decode((string)$res, true);
    return is_array($d) ? $d : [];
}

$u = gdy_github_get('https://api.github.com/user', $token);
if (empty($u)) {
    gdy_oauth_fail('تعذر قراءة بيانات المستخدم من GitHub.');
}

$emails = gdy_github_get('https://api.github.com/user/emails', $token);
$email = '';
if (is_array($emails)) {
    foreach ($emails as $e) {
        if (!is_array($e)) continue;
        if (!empty($e['primary']) && !empty($e['verified']) && !empty($e['email'])) {
            $email = (string)$e['email'];
            break;
        }
    }
    if ($email === '') {
        foreach ($emails as $e) {
            if (!is_array($e)) continue;
            if (!empty($e['verified']) && !empty($e['email'])) {
                $email = (string)$e['email'];
                break;
            }
        }
    }
}
if ($email === '' && !empty($u['email'])) {
    $email = (string)$u['email'];
}

$githubId = (string)($u['id'] ?? '');
$username = trim((string)($u['login'] ?? ''));
$displayName = trim((string)($u['name'] ?? ''));
$avatarUrl = trim((string)($u['avatar_url'] ?? ''));
if ($displayName === '') $displayName = ($username !== '' ? $username : 'GitHub');

if ($email === '' || $username === '' || $githubId === '') {
    gdy_oauth_fail('بيانات GitHub غير مكتملة (email/login/id).');
}

$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    gdy_oauth_fail('تعذر الاتصال بقاعدة البيانات.', 500);
}

// Find columns dynamically (same style as register.php)
try {
    $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!is_array($cols) || empty($cols)) {
        throw new RuntimeException('No users columns');
    }
} catch (Throwable $e) {
    gdy_oauth_fail('جدول users غير متوفر.', 500);
}

function col_exists(array $cols, string $name): bool {
    return in_array($name, $cols, true);
}

// Locate user (github_id first, then email)
$userRow = null;
if (col_exists($cols, 'github_id')) {
    $st = $pdo->prepare('SELECT * FROM users WHERE github_id = :gid LIMIT 1');
    $st->execute([':gid' => $githubId]);
    $userRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$userRow) {
    $st = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $st->execute([':email' => $email]);
    $userRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$userRow) {
    // Create new user
    $insertCols = ['email'];
    $insertVals = [':email'];
    $bind = [':email' => $email];

    if (col_exists($cols, 'username')) {
        $insertCols[] = 'username';
        $insertVals[] = ':username';
        $bind[':username'] = $username;
    }
    if (col_exists($cols, 'display_name')) {
        $insertCols[] = 'display_name';
        $insertVals[] = ':display_name';
        $bind[':display_name'] = $displayName;
    }
    if (col_exists($cols, 'github_id')) {
        $insertCols[] = 'github_id';
        $insertVals[] = ':github_id';
        $bind[':github_id'] = $githubId;
    }
    if ($avatarUrl !== '' && col_exists($cols, 'avatar')) {
        $insertCols[] = 'avatar';
        $insertVals[] = ':avatar';
        $bind[':avatar'] = $avatarUrl;
    }
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
    // Update github_id/avatar if missing
    $updates = [];
    $bind = [':id' => (int)($userRow['id'] ?? 0)];
    if (col_exists($cols, 'github_id') && empty($userRow['github_id'])) {
        $updates[] = 'github_id = :github_id';
        $bind[':github_id'] = $githubId;
    }
    if ($avatarUrl !== '' && col_exists($cols, 'avatar')) {
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
unset($_SESSION['oauth_next'], $_SESSION['oauth_github_state']);
if ($next === '' || str_contains($next, '\n') || str_contains($next, '\r')) $next = '/';
if (str_starts_with($next, 'http://') || str_starts_with($next, 'https://')) $next = '/';
if (!str_starts_with($next, '/')) $next = '/';

header('Location: ' . $base . $next, true, 302);
exit;
