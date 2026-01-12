<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/opinion_authors/delete_author.php
// حذف كاتب رأي مع التحقق من عدم وجود مقالات مرتبطة به

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$pdo = gdy_pdo_safe();
$authorId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$pdo instanceof PDO || $authorId <= 0) {
    echo json_encode(['success' => false, 'message' => __('t_0cd2c37448', 'بيانات غير صالحة')]);
    exit;
}

try {
    // التحقق من وجود مقالات مرتبطة بهذا الكاتب في جدول الأخبار
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM news 
        WHERE author_id = :author_id
          AND (deleted_at IS NULL)
    ");
    $checkStmt->execute([':author_id' => $authorId]);
    $articleCount = (int)$checkStmt->fetchColumn();

    if ($articleCount > 0) {
        echo json_encode([
            'success' => false,
            'message' => __('t_277eef9c3c', 'لا يمكن حذف الكاتب لأنه مرتبط بمقالات موجودة في الموقع.')
        ]);
        exit;
    }

    // لا توجد مقالات مرتبطة، يمكن الحذف بأمان
    $stmt = $pdo->prepare("DELETE FROM opinion_authors WHERE id = :id");
    $stmt->execute([':id' => $authorId]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    @error_log('[Opinion Authors] delete_author error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => __('t_b36dd83f10', 'حدث خطأ غير متوقع أثناء الحذف.')]);
}
