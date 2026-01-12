<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$clientId = function_exists('env') ? (string)env('GITHUB_OAUTH_CLIENT_ID', '') : '';
$clientSecret = function_exists('env') ? (string)env('GITHUB_OAUTH_CLIENT_SECRET', '') : '';

if ($clientId === '' || $clientSecret === '') {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>OAuth</title>';
    echo '<div style="font-family:system-ui;padding:24px">';
    echo '<h2>GitHub OAuth غير مُعدّ</h2>';
    echo '<p>أضف القيم التالية في ملف <code>.env</code>:</p>';
    echo '<pre style="background:#f5f5f5;padding:12px;border-radius:12px">GITHUB_OAUTH_CLIENT_ID=...\nGITHUB_OAUTH_CLIENT_SECRET=...</pre>';
    echo '</div>';
    exit;
}

// Safe next
$next = (string)($_GET['next'] ?? '/');
if ($next === '' || str_contains($next, '\n') || str_contains($next, '\r')) {
    $next = '/';
}
if (str_starts_with($next, 'http://') || str_starts_with($next, 'https://')) {
    // Disallow full URLs to prevent open redirect
    $next = '/';
}
if (!str_starts_with($next, '/')) $next = '/';
$_SESSION['oauth_next'] = $next;

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_github_state'] = $state;

$base = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
$redirectUri = $base . '/oauth/github/callback';

$params = http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => 'read:user user:email',
    'state' => $state,
], '', '&', PHP_QUERY_RFC3986);

header('Location: https://github.com/login/oauth/authorize?' . $params, true, 302);
exit;
