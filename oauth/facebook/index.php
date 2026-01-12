<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$appId = env('FACEBOOK_OAUTH_APP_ID', '');
$appSecret = env('FACEBOOK_OAUTH_APP_SECRET', '');
if (!$appId || !$appSecret) {
    http_response_code(500);
    exit("Facebook OAuth غير مُعدّ");
}

$next = $_GET['next'] ?? '/';
$next = is_string($next) ? $next : '/';
if (!preg_match('#^/[a-z0-9/_\-?&=%\.]*$#i', $next)) $next = '/';
$_SESSION['oauth_next'] = $next;

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state_facebook'] = $state;

$base = rtrim((defined('APP_URL') && APP_URL) ? APP_URL : (defined('APP_URL_AUTO') ? APP_URL_AUTO : ''), '/');
$redirectUri = $base . '/oauth/facebook/callback';

$params = [
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'state' => $state,
    'response_type' => 'code',
    'scope' => 'email,public_profile',
];

header('Location: https://www.facebook.com/' . (env('FACEBOOK_GRAPH_VERSION','v20.0')) . '/dialog/oauth?' . http_build_query($params));
exit;
