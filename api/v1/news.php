<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../includes/bootstrap.php';
require_once '../../includes/security.php';
require_once '../../includes/classes/News.php';

$method = $_SERVER['REQUEST_METHOD'];
$newsManager = new News();

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($newsManager);
            break;
            
        case 'POST':
            handlePostRequest($newsManager);
            break;
            
        case 'PUT':
            handlePutRequest($newsManager);
            break;
            
        case 'DELETE':
            handleDeleteRequest($newsManager);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'الطريقة غير مسموحة']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetRequest($newsManager) {
    $filters = [];
    
    if (isset($_GET['category_id'])) {
        $filters['category_id'] = intval($_GET['category_id']);
    }
    
    if (isset($_GET['limit'])) {
        $filters['limit'] = intval($_GET['limit']);
    }
    
    if (isset($_GET['offset'])) {
        $filters['offset'] = intval($_GET['offset']);
    }
    
    $filters['status'] = 'published';
    
    $news = $newsManager->getNews($filters);
    
    echo json_encode([
        'success' => true,
        'data' => $news,
        'total' => count($news)
    ]);
}

function handlePostRequest($newsManager) {
    // التحقق من الصلاحيات
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'غير مصرح']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'بيانات غير صالحة']);
        return;
    }
    
    $newsId = $newsManager->addNews($input);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إضافة الخبر بنجاح',
        'news_id' => $newsId
    ]);
}

function handlePutRequest($newsManager) {
    // تنفيذ تحديث الخبر
}

function handleDeleteRequest($newsManager) {
    // تنفيذ حذف الخبر
}
?>