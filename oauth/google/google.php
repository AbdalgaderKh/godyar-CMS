<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$clientId = function_exists('env') ? (string)env('GOOGLE_OAUTH_CLIENT_ID', '') : '';
$clientSecret = function_exists('env') ? (string)env('GOOGLE_OAUTH_CLIENT_SECRET', '') : '';

if ($clientId === '' || $clientSecret === '') {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>OAuth</title>';
    echo '<div style="font-family:system-ui;padding:24px">';
    echo '<h2>Google OAuth غير مُعدّ</h2>';
    echo '<p>أضف القيم التالية في ملف <code>.env</code>:</p>';
    echo '<pre style="background:#f5f5f5;padding:12px;border-radius:12px">GOOGLE_OAUTH_CLIENT_ID=...\nGOOGLE_OAUTH_CLIENT_SECRET=...</pre>';
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
$_SESSION['oauth_google_state'] = $state;

$base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
$redirectUri = $base . '/oauth/google/callback';

$params = http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'include_granted_scopes' => 'true',
    'state' => $state,
    // UX: helps when multiple accounts exist
    'prompt' => 'select_account',
], '', '&', PHP_QUERY_RFC3986);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params, true, 302);
exit;
