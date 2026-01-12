<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
/**
 * admin/categories/api.php
 *
 * API لإدارة الأقسام:
 *  - جلب الإحصائيات (إجمالي الأقسام، أقسام فيها أخبار، أقسام بدون أخبار)
 *  - جلب قائمة الأقسام مع عدد الأخبار في كل قسم
 *
 * ملاحظة:
 *  - هذا السكربت متاح لمدير النظام فقط (role = admin).
 *  - يُرجع JSON للاستخدام مع Ajax.
 */

$rootPath = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';

// تحميل bootstrap
$bootstrapPath = $rootPath . '/includes/bootstrap.php';
if (!is_file($bootstrapPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => false,
        'error' => __('t_b3cb8f49d9', 'bootstrap.php غير موجود. المسار: ') . $bootstrapPath,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $bootstrapPath;

// بدء الجلسة
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// هيلبر للهروب
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

// التحقق من صلاحيات المدير
$user    = $_SESSION['user'] ?? null;
$isAdmin = is_array($user) && (($user['role'] ?? '') === 'admin');

header('Content-Type: application/json; charset=utf-8');

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode([
        'ok'    => false,
        'error' => __('t_03a633f671', 'غير مصرح لك بالوصول إلى هذا المورد (مدير النظام فقط).'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// الحصول على PDO
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => __('t_121fb45a70', 'الاتصال بقاعدة البيانات غير مهيأ (PDO غير متوفر).'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// نوع العملية (يمكن التطوير لاحقاً: create/update/delete)
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    if ($action === 'list') {
        // جلب الأقسام مع عدد الأخبار
        $sql = "
            SELECT 
                c.*,
                (
                    SELECT COUNT(*) 
                    FROM news n 
                    WHERE n.category_id = c.id
                ) AS news_count
            FROM categories c
            ORDER BY c.id DESC
        ";

        $stmt = $pdo->query($sql);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $total        = count($categories);
        $withNews     = 0;
        $withoutNews  = 0;

        foreach ($categories as $cat) {
            $cnt = (int)($cat['news_count'] ?? 0);
            if ($cnt > 0) {
                $withNews++;
            } else {
                $withoutNews++;
            }
        }

        $response = [
            'ok'   => true,
            'stats'=> [
                'total'        => $total,
                'with_news'    => $withNews,
                'without_news' => $withoutNews,
            ],
            'categories' => $categories,
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // إذا وصلنا هنا، فـ action غير معروف
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => __('t_91c373ec9b', 'action غير مدعوم في هذا الـ API.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    @error_log('[categories_api] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => __('t_b29ffb3972', 'حدث خطأ أثناء جلب الأقسام. راجع سجلات الأخطاء.'),
        'trace' => false, // يمكن تغييره لـ true في وضع التطوير
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
