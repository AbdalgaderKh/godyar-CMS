<?php
// frontend/news/search.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    echo 'مشكلة في الاتصال بقاعدة البيانات';
    exit;
}

$q          = trim((string)($_GET['q'] ?? ''));
$page       = max(1, (int)($_GET['page'] ?? 1));
$typeFilter = $_GET['type'] ?? 'all';
$catFilter  = $_GET['cat']  ?? 'all';
$dateFilter = $_GET['date'] ?? 'any';

// نستخدمه فقط لتمرير القيمة للواجهة (التحكم في ربط البحث بقوقل)
$engine     = $_GET['engine'] ?? 'local';

$perPage = 16;
$offset  = ($page - 1) * $perPage;

// تحميل قائمة التصنيفات للفلاتر
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
    $categories = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $categories = [];
}

// بناء شروط البحث
$where  = [];
$params = [];

if ($q !== '') {
    // حالياً باستخدام LIKE، يمكن لاحقاً التحويل إلى FULLTEXT
    $where[]        = "(n.title LIKE :q OR n.content LIKE :q OR n.slug LIKE :q)";
    $params[':q']   = '%' . $q . '%';
}

if ($typeFilter !== 'all') {
    $where[]          = "n.type = :type";
    $params[':type']  = $typeFilter;
}

if ($catFilter !== 'all') {
    $where[]             = "c.slug = :catSlug";
    $params[':catSlug']  = $catFilter;
}

// فلتر التاريخ
if ($dateFilter === '1d') {
    $where[] = "n.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
} elseif ($dateFilter === '7d') {
    $where[] = "n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($dateFilter === '30d') {
    $where[] = "n.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// إجمالي النتائج
$total = 0;
try {
    $sqlCount = "
        SELECT COUNT(*)
        FROM news n
        LEFT JOIN categories c ON c.id = n.category_id
        $whereSql
    ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();
} catch (Throwable $e) {
    $total = 0;
}

// جلب النتائج
$results = [];
try {
    $sql = "
        SELECT n.*, c.slug AS category_slug
        FROM news n
        LEFT JOIN categories c ON c.id = n.category_id
        $whereSql
        -- تحسين ترتيب النتائج: العاجلة، المميزة، الحصرية ثم الأحدث
        ORDER BY
            n.is_breaking DESC,
            n.is_featured DESC,
            n.is_exclusive DESC,
            n.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $results = [];
}

$pages = max(1, (int)ceil($total / $perPage));

// تمرير متغير engine للواجهة لو احتجناه هناك
// (الواجهة أيضاً تقرأه من $_GET، لكن لا ضرر في وجوده هنا)
require __DIR__ . '/../views/search.php';
