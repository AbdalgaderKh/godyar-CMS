<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Editors/Admins only
Auth::requirePermission('posts.edit');

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'db_unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'bad_request'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $st = $pdo->prepare('SELECT id, title, excerpt, content, created_at, slug FROM news WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $title = (string)($row['title'] ?? '');
    $excerpt = (string)($row['excerpt'] ?? '');
    $content = (string)($row['content'] ?? '');
    $slug = (string)($row['slug'] ?? '');
    $created = (string)($row['created_at'] ?? '');

    // Build preview HTML (admin-only; content may include HTML from editor)
    $html = '';
    $html .= '<div class="mb-2 text-muted small" style="direction:ltr;">';
    $html .= '<span class="me-2"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h($created ?: '—') . '</span>';
    if ($slug !== '') {
        $html .= '<span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h($slug) . '</span>';
    }
    $html .= '</div>';

    if ($excerpt !== '') {
        $html .= '<div class="p-3 rounded" style="background:rgba(15,23,42,.65); border:1px solid rgba(148,163,184,.25);">';
        $html .= '<div class="fw-bold mb-2">' . h(__('t_17220bb323', 'ملخص')) . '</div>';
        $html .= '<div>' . nl2br(h($excerpt)) . '</div>';
        $html .= '</div>';
        $html .= '<hr style="border-color:rgba(148,163,184,.2);" />';
    }

    if ($content !== '') {
        $html .= '<div class="p-3 rounded" style="background:rgba(15,23,42,.65); border:1px solid rgba(148,163,184,.25);">';
        $html .= '<div class="fw-bold mb-2">' . h(__('t_b649baf3ad', 'المحتوى')) . '</div>';
        $html .= '<div class="gdy-preview-content" style="line-height:1.9;">' . $content . '</div>';
        $html .= '</div>';
    } else {
        $html .= '<div class="text-muted">—</div>';
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'id' => $id,
        'title' => $title,
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    @error_log('review_preview error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'server_error'], JSON_UNESCAPED_UNICODE);
}
