<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/classes/Upload.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => __('t_ceb90cbe05', 'غير مصرح')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['image']['name'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => __('t_d51fab540f', 'لا يوجد ملف مرفوع')], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $uploader = new Upload();
    $result   = $uploader->uploadFile($_FILES['image'], 'editor/');

    if (!($result['success'] ?? false)) {
        throw new Exception(__('t_9e55297238', 'فشل رفع الملف'));
    }

    echo json_encode([
        'success' => true,
        'url'     => $result['file_url'] ?? '',
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
