<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$appId = function_exists('env') ? (string)env('FACEBOOK_OAUTH_APP_ID', '') : '';
$appSecret = function_exists('env') ? (string)env('FACEBOOK_OAUTH_APP_SECRET', '') : '';
$graphVer = function_exists('env') ? (string)env('FACEBOOK_GRAPH_VERSION', 'v20.0') : 'v20.0';

if ($appId === '' || $appSecret === '') {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>OAuth</title>';
    echo '<div style="font-family:system-ui;padding:24px">';
    echo '<h2>Facebook OAuth غير مُعدّ</h2>';
    echo '<p>أضف القيم التالية في ملف <code>.env</code>:</p>';
    echo '<pre style="background:#f5f5f5;padding:12px;border-radius:12px">FACEBOOK_OAUTH_APP_ID=...\nFACEBOOK_OAUTH_APP_SECRET=...\nFACEBOOK_GRAPH_VERSION=v20.0</pre>';
    echo '</div>';
    exit;
}

// Safe next
$next = (string)($_GET['next'] ?? '/');
if ($next === '' || str_contains($next, "\n") || str_contains($next, "\r")) {
    $next = '/';
}
if (str_starts_with($next, 'http://') || str_starts_with($next, 'https://')) {
    $next = '/';
}
if (!str_starts_with($next, '/')) $next = '/';
$_SESSION['oauth_next'] = $next;

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_facebook_state'] = $state;

$base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
$redirectUri = $base . '/oauth/facebook/callback';

$params = http_build_query([
    'client_id' => $appId,
    'redirect_uri' => $redirectUri,
    'state' => $state,
    'scope' => 'email,public_profile',
    'response_type' => 'code',
], '', '&', PHP_QUERY_RFC3986);

$graphVer = trim($graphVer);
if ($graphVer === '') $graphVer = 'v20.0';

header('Location: https://www.facebook.com/' . rawurlencode($graphVer) . '/dialog/oauth?' . $params, true, 302);
exit;
