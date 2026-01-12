<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/controllers/DashboardController.php
// Controller مبسط يعيد توجيه أي استدعاء للوحة التحكم إلى index.php الحديث

require_once __DIR__ . '/../../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$user    = $_SESSION['user'] ?? null;
$isAdmin = is_array($user) && (($user['role'] ?? '') === 'admin');

if (!$isAdmin) {
    header('Location: ../login.php');
    exit;
}

// إعادة التوجيه للوحة التحكم الرئيسية
header('Location: ../index.php');
exit;
