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

$next = $_GET['next'] ?? '/';
$next = is_string($next) ? $next : '/';
if (!preg_match('#^/[a-z0-9/_\-?&=%\.]*$#i', $next)) $next = '/';
$_SESSION['oauth_next'] = $next;

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state_github'] = $state;

$base = rtrim((defined('APP_URL') && APP_URL) ? APP_URL : (defined('APP_URL_AUTO') ? APP_URL_AUTO : ''), '/');
$redirectUri = $base . '/oauth/github/callback';

$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'state' => $state,
    'scope' => 'read:user user:email',
];

header('Location: https://github.com/login/oauth/authorize?' . http_build_query($params));
exit;
