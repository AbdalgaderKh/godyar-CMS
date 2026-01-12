<?php
// admin/_admin_boot.php
declare(strict_types=1);


require_once __DIR__ . '/_admin_guard.php';
// Bootstrap مشترك لكل صفحات لوحة التحكم
require_once __DIR__ . '/../includes/bootstrap.php';
// ---------------------------
// CSRF enforced (Admin)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!function_exists('validate_csrf_token') || !validate_csrf_token($token)) {
        http_response_code(403);
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'error'=>'CSRF blocked'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        exit('CSRF blocked');
    }
}

require_once __DIR__ . '/../includes/auth.php';

use Godyar\Auth;

// اسم الصفحة يمكن ضبطه من الملف المستدعي قبل include
$currentPage = $currentPage ?? 'dashboard';
$pageTitle   = $pageTitle   ?? __('t_a06ee671f4', 'لوحة التحكم');

// حماية: التحقق من تسجيل الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ' . GODYAR_BASE_URL . '/admin/login');
            exit;
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ' . GODYAR_BASE_URL . '/admin/login');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Godyar Admin Boot] Auth check error: ' . $e->getMessage());
    if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
        header('Location: ' . GODYAR_BASE_URL . '/admin/login');
        exit;
    }
}

// PDO مشترك
$pdo = gdy_pdo_safe();

// دالة هيلبر للهروب
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// اسم المستخدم الحالي
$currentUserName = __('t_ead53a737a', 'مشرف النظام');
try {
    if (class_exists(Auth::class)) {
        if (method_exists(Auth::class, 'userName')) {
            $currentUserName = (string)Auth::userName();
        } elseif (method_exists(Auth::class, 'user')) {
            $u = Auth::user();
            if (is_array($u)) {
                $currentUserName = $u['name'] ?? ($u['email'] ?? $currentUserName);
            }
        }
    } elseif (!empty($_SESSION['user']['name'])) {
        $currentUserName = (string)$_SESSION['user']['name'];
    }
} catch (Throwable $e) {
    @error_log('[Godyar Admin Boot] currentUserName: ' . $e->getMessage());
}

// تضمين رأس اللوحة والقائمة الجانبية
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/layout/sidebar.php';
