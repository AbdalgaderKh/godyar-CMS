<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// تأكد أن القيم موجودة
$clientId = env('GOOGLE_OAUTH_CLIENT_ID', '');
$clientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET', '');
if (!$clientId || !$clientSecret) {
    http_response_code(500);
    exit("Google OAuth غير مُعدّ: تأكد من GOOGLE_OAUTH_CLIENT_ID و GOOGLE_OAUTH_CLIENT_SECRET في .env");
}

// next: مسار داخلي فقط
$next = $_GET['next'] ?? '/';
$next = is_string($next) ? $next : '/';

// امنع تحويل خارجي
if (!preg_match('#^/[a-z0-9/_\-?&=%\.]*$#i', $next)) {
    $next = '/';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['oauth_next'] = $next;

// state للحماية
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state_google'] = $state;

// redirect_uri لازم يطابق Google Console حرفياً
$redirectUri = rtrim((defined('APP_URL') && APP_URL) ? APP_URL : (defined('APP_URL_AUTO') ? APP_URL_AUTO : ''), '/')
    . '/oauth/google/callback';

// scopes
$scope = 'openid email profile';

// بناء رابط جوجل
$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => $scope,
    'state' => $state,
    'prompt' => 'select_account',
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;
