<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$baseUrl = function_exists('base_url') ? rtrim(base_url(), '/') : '/';

// إزالة بيانات العضو (توحيد)
if (function_exists('auth_clear_user_session')) {
    auth_clear_user_session();
} else {
    unset($_SESSION['user'], $_SESSION['is_member_logged'], $_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_name']);
}
// ممكن لو أردت إنهاء الجلسة بالكامل:
session_regenerate_id(true);

header('Location: ' . $baseUrl . '/');
exit;
