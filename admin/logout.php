<?php
declare(strict_types=1);


require_once __DIR__ . '/_role_guard.php';
require_once __DIR__ . '/../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}


// ✅ Audit log
if (function_exists('gody_audit_log')) {
    gody_audit_log('admin_logout');
}
// تنظيف بيانات الجلسة
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// إنهاء الجلسة
session_destroy();

// إعادة التوجيه إلى صفحة تسجيل دخول الأدمن
header('Location: login.php');
exit;
