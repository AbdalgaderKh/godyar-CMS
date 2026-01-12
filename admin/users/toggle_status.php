<?php
declare(strict_types=1);

// admin/users/toggle_status.php — تبديل حالة مستخدم (AJAX)

define('GDY_ADMIN_JSON', true);
require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

$authFile = __DIR__ . '/../../includes/auth.php';
if (is_file($authFile)) {
    require_once $authFile;
}

use Godyar\Auth;

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    // CSRF (يتم إرساله من JS)
    verify_csrf('csrf_token');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
    exit;
}

$pdo = gdy_pdo_safe();
$currentUser = (class_exists(Auth::class) && method_exists(Auth::class, 'user'))
    ? (Auth::user() ?? ($_SESSION['user'] ?? []))
    : ($_SESSION['user'] ?? []);

$userId = (int)($_POST['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرّف المستخدم غير صحيح']);
    exit;
}

if ((int)($currentUser['id'] ?? 0) === $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'لا يمكن تغيير حالة حسابك']);
    exit;
}

if (!($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, role, status FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود']);
        exit;
    }

    $currentRole = (string)($currentUser['role'] ?? '');
    if (($target['role'] ?? '') === 'superadmin' && $currentRole !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتعديل هذا المستخدم']);
        exit;
    }

    $currentStatus = (string)($target['status'] ?? 'active');
    $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

    $upd = $pdo->prepare("UPDATE users SET status = :s WHERE id = :id LIMIT 1");
    $upd->execute([':s' => $newStatus, ':id' => $userId]);

    echo json_encode(['success' => true, 'status' => $newStatus]);
    exit;

} catch (Throwable $e) {
    @error_log('[Admin Users Toggle] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء تحديث الحالة']);
    exit;
}
