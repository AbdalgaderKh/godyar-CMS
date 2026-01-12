<?php
// /api/v1/home_loadmore.php
// Public endpoint used by Home "Load more" buttons (AJAX).
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    $pdo = gdy_pdo_safe();

    $type   = isset($_GET['type']) ? (string)$_GET['type'] : '';
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;

    if ($offset < 0) $offset = 0;
    if ($limit < 1) $limit = 8;
    if ($limit > 24) $limit = 24;

    $items = [];
    $hasMore = false;

    // Helper: safe date
    $fmtDate = static function($v): string {
        try {
            if (!$v) return '';
            return date('Y-m-d', strtotime((string)$v));
        } catch (\Throwable $e) {
            return '';
        }
    };

    // Base WHERE for published content
    $where = "n.status = 'published' AND n.deleted_at IS NULL AND (n.publish_at IS NULL OR n.publish_at <= NOW())";

    if ($type === 'latest') {
        $period = isset($_GET['period']) ? (string)$_GET['period'] : 'today';
        $period = in_array($period, ['today','week','month','all'], true) ? $period : 'today';

        // Date range filter
        $dateCond = '';
        $params = [];
        if ($period === 'today') {
            $dateCond = " AND DATE(COALESCE(n.publish_at, n.published_at, n.created_at)) = CURDATE()";
        } elseif ($period === 'week') {
            $dateCond = " AND COALESCE(n.publish_at, n.published_at, n.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $dateCond = " AND COALESCE(n.publish_at, n.published_at, n.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        // Exclude opinion posts if the schema supports it
        $excludeOpinion = '';
        try {
            $stmtCol = $pdo->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='news' AND COLUMN_NAME='opinion_author_id'");
            $stmtCol->execute();
            $rowCol = $stmtCol->fetch(PDO::FETCH_ASSOC);
            if ((int)($rowCol['c'] ?? 0) > 0) {
                $excludeOpinion = " AND (n.opinion_author_id IS NULL OR n.opinion_author_id = 0)";
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Fetch limit+1 to detect "hasMore"
        $sql = "
            SELECT
                n.id, n.title, n.slug, n.excerpt, n.image,
                COALESCE(n.publish_at, n.published_at, n.created_at) AS sort_date
            FROM news n
            WHERE {$where}{$excludeOpinion}{$dateCond}
            ORDER BY sort_date DESC
            LIMIT :limitPlus OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        $limitPlus = $limit + 1;
        $stmt->bindValue(':limitPlus', $limitPlus, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > $limit) {
            $hasMore = true;
            $rows = array_slice($rows, 0, $limit);
        }

        foreach ($rows as $r) {
            $items[] = [
                'id'      => (int)($r['id'] ?? 0),
                'title'   => (string)($r['title'] ?? ''),
                'excerpt' => (string)($r['excerpt'] ?? ''),
                'image'   => (string)($r['image'] ?? ''),
                'date'    => $fmtDate($r['sort_date'] ?? ''),
            ];
        }

        echo json_encode([
            'ok' => true,
            'type' => 'latest',
            'items' => $items,
            'has_more' => $hasMore,
            'next_offset' => $offset + count($items),
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($type === 'category') {
        $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : (isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0);
        if ($cid <= 0) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Missing category id'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        $sql = "
            SELECT
                n.id, n.title, n.slug, n.excerpt, n.image,
                COALESCE(n.publish_at, n.published_at, n.created_at) AS sort_date
            FROM news n
            WHERE {$where} AND n.category_id = :cid
            ORDER BY sort_date DESC
            LIMIT :limitPlus OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        $limitPlus = $limit + 1;
        $stmt->bindValue(':cid', $cid, PDO::PARAM_INT);
        $stmt->bindValue(':limitPlus', $limitPlus, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > $limit) {
            $hasMore = true;
            $rows = array_slice($rows, 0, $limit);
        }

        foreach ($rows as $r) {
            $items[] = [
                'id'      => (int)($r['id'] ?? 0),
                'title'   => (string)($r['title'] ?? ''),
                'excerpt' => (string)($r['excerpt'] ?? ''),
                'image'   => (string)($r['image'] ?? ''),
                'date'    => $fmtDate($r['sort_date'] ?? ''),
            ];
        }

        echo json_encode([
            'ok' => true,
            'type' => 'category',
            'category_id' => $cid,
            'items' => $items,
            'has_more' => $hasMore,
            'next_offset' => $offset + count($items),
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid type'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
