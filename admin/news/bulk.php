<?php
declare(strict_types=1);

// IMPORTANT: this endpoint is called via fetch(). Any PHP warning/notice printed to output
// will break JSON parsing and the UI shows "Bulk failed".
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
@ini_set('log_errors', '1');

// Buffer output so we can strip accidental output (BOM, notices, etc.) before JSON.
if (!ob_get_level()) {
    ob_start();
}

$__gdy_bulk_warnings = [];
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$__gdy_bulk_warnings) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    // Collect (do not output). Keep it compact.
    $type = match ($errno) {
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_DEPRECATED => 'DEPRECATED',
        default => 'ERROR',
    };
    $__gdy_bulk_warnings[] = $type . ': ' . $errstr . ' @' . basename((string)$errfile) . ':' . (int)$errline;
    return true;
});

// Ensure fatal errors still return JSON.
register_shutdown_function(function () use (&$__gdy_bulk_warnings) {
    $err = error_get_last();
    if (!$err) return;
    $fatals = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array((int)$err['type'], $fatals, true)) return;
    if (ob_get_level()) {
        @ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'fatal',
        'detail' => [
            'type' => (int)$err['type'],
            'message' => (string)$err['message'],
            'file' => basename((string)$err['file']),
            'line' => (int)$err['line'],
        ],
        // kept for debugging; frontend ignores.
        'warnings' => $__gdy_bulk_warnings,
    ], JSON_UNESCAPED_UNICODE);
});

function gdy_bulk_json(array $payload, int $code = 200): void {
    if (ob_get_level()) {
        @ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// GDY_BUILD: v9

// Tell the guard this is a JSON (AJAX) endpoint (avoid HTML redirects that break fetch JSON).
if (!defined('GDY_ADMIN_JSON')) {
    define('GDY_ADMIN_JSON', true);
}
require_once __DIR__ . '/../_admin_guard.php';
$BASE_DIR = dirname(__DIR__, 2);
// bootstrap loaded by _admin_guard.php; keep require_once to avoid redeclare fatals
require_once $BASE_DIR . '/includes/bootstrap.php';
require_once __DIR__ . '/_news_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// CSRF
$csrf = (string)($_POST['csrf_token'] ?? '');
if (function_exists('verify_csrf_token') && !verify_csrf_token($csrf)) {
    gdy_bulk_json(['ok' => false, 'msg' => 'CSRF failed'], 400);
}

$role = $_SESSION['user']['role'] ?? 'guest';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    gdy_bulk_json(['ok' => false, 'msg' => 'forbidden'], 403);
}

$pdo = $pdo ?? null;
if (!($pdo instanceof PDO)) {
    gdy_bulk_json(['ok' => false, 'msg' => 'no db'], 500);
}

$allowedStatus = ['published', 'draft', 'pending', 'approved', 'archived'];

$action = (string)($_POST['action'] ?? '');
$scope  = (string)($_POST['scope'] ?? 'ids'); // ids | all
$cursor = (int)($_POST['cursor'] ?? 0);
$batchSize = 200;

$extra = [
    'to' => (string)($_POST['to'] ?? ''),
    'category_id' => (int)($_POST['category_id'] ?? 0),
];

if ($action === '') {
    gdy_bulk_json(['ok' => false, 'msg' => 'missing action'], 400);
}

// normalize status:<value>
if (str_starts_with($action, 'status:')) {
    $to = substr($action, 7);
    $action = 'status';
    $extra['to'] = $to;
}

function gdy_decode_filters($raw): array {
    if (is_array($raw)) return $raw;
    if (!is_string($raw) || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function gdy_build_where_from_filters(array $f, array &$params): string {
    $where = '1=1';

    $trash = ((int)($f['trash'] ?? 0) === 1);
    $where .= $trash ? ' AND n.deleted_at IS NOT NULL' : ' AND (n.deleted_at IS NULL)';

    $q = trim((string)($f['q'] ?? ''));
    $inContent = ((int)($f['in_content'] ?? 0) === 1);
    if ($q !== '') {
        if ($inContent) {
            $where .= " AND (n.title LIKE :q OR n.slug LIKE :q OR n.excerpt LIKE :q OR n.content LIKE :q)";
        } else {
            $where .= " AND (n.title LIKE :q OR n.slug LIKE :q)";
        }
        $params[':q'] = '%' . $q . '%';
    }

    $status = trim((string)($f['status'] ?? ''));
    $allowed = ['', 'published', 'draft', 'pending', 'approved', 'archived'];
    if ($status !== '' && in_array($status, $allowed, true)) {
        $where .= " AND n.status = :status";
        $params[':status'] = $status;
    }

    $cid = (int)($f['category_id'] ?? 0);
    if ($cid > 0) {
        $where .= " AND n.category_id = :cid";
        $params[':cid'] = (string)$cid;
    }

    $dateRe = '/^\d{4}-\d{2}-\d{2}$/';
    $df = (string)($f['from'] ?? '');
    $dt = (string)($f['to'] ?? '');
    if ($df !== '' && preg_match($dateRe, $df)) {
        $where .= " AND DATE(n.created_at) >= :df";
        $params[':df'] = $df;
    }
    if ($dt !== '' && preg_match($dateRe, $dt)) {
        $where .= " AND DATE(n.created_at) <= :dt";
        $params[':dt'] = $dt;
    }

    if (((int)($f['no_image'] ?? 0) === 1)) {
        $where .= " AND (n.image IS NULL OR n.image = '')";
    }
    if (((int)($f['no_desc'] ?? 0) === 1)) {
        $where .= " AND (n.seo_description IS NULL OR n.seo_description = '')";
    }
    if (((int)($f['no_keywords'] ?? 0) === 1)) {
        $where .= " AND (n.seo_keywords IS NULL OR n.seo_keywords = '')";
    }

    return $where;
}

function gdy_exec_in(PDO $pdo, string $sqlBase, array $ids, array $prefixParams = []): bool {
    if (!$ids) return true;
    $chunks = array_chunk($ids, 200);
    foreach ($chunks as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $sql = str_replace('%%IN%%', $in, $sqlBase);
        $st = $pdo->prepare($sql);
        $ok = $st->execute(array_merge($prefixParams, $chunk));
        if (!$ok) return false;
    }
    return true;
}

function gdy_duplicate_one(PDO $pdo, int $id, int $userId): int {
    $cols = gdy_db_columns($pdo, 'news');
    if (!$cols) return 0;

    $st = $pdo->prepare("SELECT * FROM news WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return 0;

    $now = date('Y-m-d H:i:s');
    $title = (string)($row['title'] ?? '');
    $slug  = (string)($row['slug'] ?? '');

    $newTitle = trim($title . ' (نسخة)');
    $suffix = '-copy-' . date('ymdHis') . '-' . random_int(100, 999);
    $newSlug = $slug !== '' ? $slug . $suffix : ('news' . $suffix);

    // Trim slug if too long
    if (isset($cols['slug']) && strlen($newSlug) > 180) {
        $newSlug = substr($newSlug, 0, 180);
    }

    $insert = [];
    $place = [];
    $vals  = [];

    foreach ($cols as $c => $_) {
        if ($c === 'id') continue;

        // default: copy
        $val = $row[$c] ?? null;

        // overrides
        if ($c === 'title') $val = $newTitle;
        if ($c === 'slug') $val = $newSlug;

        if ($c === 'status') $val = 'draft';
        if ($c === 'deleted_at') $val = null;
        if ($c === 'published_at') $val = null;
        if ($c === 'views') $val = 0;
        if ($c === 'view_count') $val = 0;

        if ($c === 'author_id') $val = $userId;

        if ($c === 'created_at' || $c === 'updated_at') $val = $now;

        $insert[] = "`$c`";
        $place[] = "?";
        $vals[] = $val;
    }

    $sql = "INSERT INTO news (" . implode(',', $insert) . ") VALUES (" . implode(',', $place) . ")";
    $pdo->prepare($sql)->execute($vals);
    $newId = (int)$pdo->lastInsertId();

    // Copy tags if exists
    if ($newId > 0 && gdy_db_table_exists($pdo, 'news_tags')) {
        $t = $pdo->prepare("SELECT tag_id FROM news_tags WHERE news_id=?");
        $t->execute([$id]);
        $tags = $t->fetchAll(PDO::FETCH_COLUMN);
        if ($tags) {
            $ins = $pdo->prepare("INSERT INTO news_tags (news_id, tag_id) VALUES (?, ?)");
            foreach ($tags as $tagId) {
                $ins->execute([$newId, (int)$tagId]);
            }
        }
    }

    return $newId;
}

function gdy_apply_action(PDO $pdo, string $action, array $ids, array $extra): int {
    if (!$ids) return 0;

    $cols = gdy_db_columns($pdo, 'news');

    if ($action === 'status') {
        $to = (string)($extra['to'] ?? '');
        $allowedStatus = ['published', 'draft', 'pending', 'approved', 'archived'];
        if (!in_array($to, $allowedStatus, true)) return 0;

        if ($to === 'published' && isset($cols['published_at'])) {
            $sql = "UPDATE news SET status=?, published_at=IFNULL(published_at, NOW()) WHERE id IN (%%IN%%)";
            return gdy_exec_in($pdo, $sql, $ids, [$to]) ? count($ids) : 0;
        }
        $sql = "UPDATE news SET status=? WHERE id IN (%%IN%%)";
        return gdy_exec_in($pdo, $sql, $ids, [$to]) ? count($ids) : 0;
    }

    if ($action === 'delete') {
        if (!isset($cols['deleted_at'])) return 0;
        $sql = "UPDATE news SET deleted_at = NOW() WHERE id IN (%%IN%%)";
        return gdy_exec_in($pdo, $sql, $ids) ? count($ids) : 0;
    }

    if ($action === 'restore') {
        if (!isset($cols['deleted_at'])) return 0;
        $sql = "UPDATE news SET deleted_at = NULL WHERE id IN (%%IN%%)";
        return gdy_exec_in($pdo, $sql, $ids) ? count($ids) : 0;
    }

    if ($action === 'destroy') {
        // Remove related records if tables exist
        if (gdy_db_table_exists($pdo, 'news_tags')) {
            gdy_exec_in($pdo, "DELETE FROM news_tags WHERE news_id IN (%%IN%%)", $ids);
        }
        if (gdy_db_table_exists($pdo, 'news_attachments')) {
            gdy_exec_in($pdo, "DELETE FROM news_attachments WHERE news_id IN (%%IN%%)", $ids);
        }
        if (gdy_db_table_exists($pdo, 'news_notes')) {
            gdy_exec_in($pdo, "DELETE FROM news_notes WHERE news_id IN (%%IN%%)", $ids);
        }
        if (gdy_db_table_exists($pdo, 'news_revisions')) {
            gdy_exec_in($pdo, "DELETE FROM news_revisions WHERE news_id IN (%%IN%%)", $ids);
        }
        $sql = "DELETE FROM news WHERE id IN (%%IN%%)";
        return gdy_exec_in($pdo, $sql, $ids) ? count($ids) : 0;
    }

    if ($action === 'move_category') {
        $cid = (int)($extra['category_id'] ?? 0);
        if ($cid <= 0) return 0;
        $sql = "UPDATE news SET category_id=? WHERE id IN (%%IN%%)";
        return gdy_exec_in($pdo, $sql, $ids, [$cid]) ? count($ids) : 0;
    }

    if ($action === 'duplicate') {
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $count = 0;
        foreach ($ids as $id) {
            $newId = gdy_duplicate_one($pdo, (int)$id, $userId);
            if ($newId > 0) $count++;
        }
        return $count;
    }

    return 0;
}

try {
    $processed = 0;

    
if ($scope === 'all') {
        $filters = gdy_decode_filters($_POST['filters'] ?? '');
        $excluded = $_POST['excluded_ids'] ?? [];
        $excluded = array_values(array_filter(array_map('intval', (array)$excluded)));

        $params = [];
        $where = gdy_build_where_from_filters($filters, $params);

        // Exclude unchecked ids when selecting all results (named placeholders only)
        if ($excluded) {
            $exNames = [];
            foreach ($excluded as $i => $exId) {
                $n = ':ex' . $i;
                $exNames[] = $n;
                $params[$n] = (int)$exId;
            }
            $where .= " AND n.id NOT IN (" . implode(',', $exNames) . ")";
        }

        $sql = "SELECT n.id
                FROM news n
                WHERE $where AND n.id > :cursor
                ORDER BY n.id ASC
                LIMIT :lim";
        $stmt = $pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':cursor', $cursor, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $batchSize, PDO::PARAM_INT);
        $stmt->execute();

        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if (!$ids) {
            gdy_bulk_json(['ok' => true, 'processed' => 0, 'continue' => false, 'next_cursor' => $cursor]);
        }

        $processed = gdy_apply_action($pdo, $action, $ids, $extra);
        $nextCursor = (int)end($ids);

        gdy_bulk_json([
            'ok' => true,
            'processed' => $processed,
            'continue' => (count($ids) === $batchSize),
            'next_cursor' => $nextCursor,
        ]);
    }// scope: ids
    $ids = $_POST['ids'] ?? [];
    $ids = array_values(array_filter(array_map('intval', (array)$ids)));
    if (!$ids) {
        gdy_bulk_json(['ok' => false, 'msg' => 'no ids'], 400);
    }

    $processed = gdy_apply_action($pdo, $action, $ids, $extra);
    gdy_bulk_json(['ok' => true, 'processed' => $processed]);
} catch (Throwable $e) {
    @error_log('[Admin News bulk] ' . $e->getMessage());
    gdy_bulk_json(['ok' => false, 'msg' => 'server error'], 500);
}
