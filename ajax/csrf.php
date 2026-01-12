<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$token = function_exists('csrf_token') ? csrf_token() : (string)($_SESSION['csrf_token'] ?? '');
if ($token === '') {
    try {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_time']  = time();
    } catch (Throwable $e) {
        $token = '';
    }
}

echo json_encode(['ok' => true, 'csrf_token' => $token], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
