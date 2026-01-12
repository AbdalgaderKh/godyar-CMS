<?php
// Godyar — Category Load More (AJAX)
// Returns HTML cards for the next page in category listing.

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/bootstrap.php';

function gdy_json($arr, int $code = 200): void {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

try {
    /** @var PDO $pdo */
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('DB not ready');
    }

    $categoryId = (int)($_GET['category_id'] ?? 0);
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $perPage    = (int)($_GET['per_page'] ?? 8);
    if ($perPage < 1) $perPage = 8;
    if ($perPage > 24) $perPage = 24;

    if ($categoryId <= 0) {
        gdy_json(['success' => false, 'message' => 'category_id مطلوب'], 400);
    }

    $sort   = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'latest';
    $period = isset($_GET['period']) ? trim((string)$_GET['period']) : 'all';

    if (!in_array($sort, ['latest', 'popular'], true)) $sort = 'latest';
    if (!in_array($period, ['all', 'today', 'week', 'month'], true)) $period = 'all';

    $periodSql = '';
    if ($period === 'today') {
        $periodSql = " AND COALESCE(n.published_at, n.created_at) >= CURDATE() ";
    } elseif ($period === 'week') {
        $periodSql = " AND COALESCE(n.published_at, n.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
    } elseif ($period === 'month') {
        $periodSql = " AND COALESCE(n.published_at, n.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";
    }

    $orderSql = " ORDER BY n.published_at DESC, n.id DESC ";
    if ($sort === 'popular') {
        $orderSql = " ORDER BY COALESCE(n.views, 0) DESC, n.published_at DESC, n.id DESC ";
    }

    $offset = ($page - 1) * $perPage;
    $limitPlus = $perPage + 1;

    $stmt = $pdo->prepare("
        SELECT n.id, n.title, n.slug, n.excerpt, n.image, n.published_at, n.created_at, n.views
        FROM news n
        WHERE n.status = 'published'
          AND n.category_id = :cid
          AND (n.published_at IS NULL OR n.published_at <= NOW())
          {$periodSql}
        {$orderSql}
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':cid', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limitPlus, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $hasMore = false;
    if (count($rows) > $perPage) {
        $hasMore = true;
        $rows = array_slice($rows, 0, $perPage);
    }

    // base url
    if (function_exists('base_url')) {
        $baseUrl = rtrim(base_url(), '/');
    } elseif (defined('BASE_URL')) {
        $baseUrl = rtrim((string)BASE_URL, '/');
    } else {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }

    $html = '';
    foreach ($rows as $row) {
        $title   = (string)($row['title'] ?? '');
        $slug    = (string)($row['slug'] ?? '');
        $excerpt = (string)($row['excerpt'] ?? '');
        $image   = (string)($row['image'] ?? '');
        $views   = (int)($row['views'] ?? 0);

        $date = $row['published_at'] ?: ($row['created_at'] ?? null);
        $dateStr = $date ? date('Y-m-d', strtotime((string)$date)) : '';
        $dateAttr = $date ? date('Y-m-d H:i:s', strtotime((string)$date)) : '';

        // URL
        if (function_exists('gdy_news_url')) {
            $newsUrl = (string)gdy_news_url($slug);
        } else {
            $newsUrl = $baseUrl . '/news/' . rawurlencode($slug);
        }

        // Image URL
        $imgUrl = '';
        if ($image !== '') {
            if (preg_match('~^https?://~i', $image)) {
                $imgUrl = $image;
            } else {
                $imgUrl = rtrim($baseUrl, '/') . '/' . ltrim($image, '/');
            }
        }

        $html .= '<article class="news-card" data-date="' . h($dateAttr) . '" data-views="' . (int)$views . '">';
        $html .=   '<a href="' . h($newsUrl) . '" class="text-decoration-none text-dark d-block h-100">';
        $html .=     '<div class="news-thumb">';
        if ($imgUrl !== '') {
            $html .=       '<img src="' . h($imgUrl) . '" alt="' . h($title) . '" loading="lazy" decoding="async" style="opacity:0" onerror="this.style.display=\'none\'; this.parentElement.classList.add(\'news-thumb-empty\');">';
        } else {
            $html .=       '<div class="news-thumb-placeholder gdy-skeleton" aria-hidden="true"></div>';
        }
        $html .=     '</div>';

        $html .=     '<div class="news-body">';
        $html .=       '<div class="news-meta">';
        $html .=         '<span>';
        if ($dateStr !== '') {
            $html .=           '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h($dateStr);
        }
        $html .=         '</span>';
        $html .=         '<span>';
        if ($views > 0) {
            $html .=           '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h(number_format($views));
        } else {
            $html .=           '<svg class="gdy-icon gdy-new-dot" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> جديد';
        }
        $html .=         '</span>';
        $html .=       '</div>';

        $html .=       '<h2 class="news-title h5 mb-0">' . h($title) . '</h2>';

        if ($excerpt !== '') {
            $html .=     '<p class="news-excerpt">' . h($excerpt) . '</p>';
        }

        $html .=       '<div class="news-footer">';
        $html .=         '<div class="news-author"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg><span>فريق التحرير</span></div>';
        $html .=         '<span class="more-link">قراءة المزيد <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></span>';
        $html .=       '</div>';

        $html .=     '</div>';
        $html .=   '</a>';
        $html .= '</article>';
    }

    gdy_json([
        'success' => true,
        'page' => $page,
        'per_page' => $perPage,
        'has_more' => $hasMore,
        'count' => count($rows),
        'html' => $html,
    ]);

} catch (Throwable $e) {
    error_log('[category_loadmore] ' . $e->getMessage());
    gdy_json(['success' => false, 'message' => 'Server error'], 500);
}
