<?php
declare(strict_types=1);
/**
 * admin/_role_guard.php
 * حارس صلاحيات الكاتب/المؤلف:
 * - يسمح للكاتب/المؤلف بالدخول فقط إلى إدارة الأخبار (إنشاء/تعديل/عرض مقالاته).
 * - يمنع الوصول لباقي أقسام لوحة التحكم حتى لو تم إدخال الرابط مباشرة.
 *
 * ملاحظة: هذا الملف لا يفترض وجود bootstrap/auth. يعتمد فقط على $_SESSION.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$role = (string)($_SESSION['user']['role'] ?? 'guest');
if (!in_array($role, ['writer', 'author'], true)) {
    return; // غير كاتب: لا تقييد هنا
}

$uriPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($uriPath === '') {
    return;
}

// السماح فقط بالأخبار + الخروج + الدخول
$allowedPrefixes = [
    '/admin/news/',
    '/admin/news',
];

$allowedExact = [
    '/admin/logout.php',
    '/admin/login',
];

foreach ($allowedExact as $ok) {
    if ($uriPath === $ok) {
        return;
    }
}

foreach ($allowedPrefixes as $prefix) {
    if (strpos($uriPath, $prefix) === 0) {
        return;
    }
}

// أي شيء آخر -> إعادة توجيه لمقالات الكاتب
header('Location: /admin/news/index.php');
exit;
