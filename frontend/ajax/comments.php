<?php
declare(strict_types=1);

// AJAX API for news comments
// - list comments for a news item
// - add / reply / edit / delete
// - vote up/down

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!function_exists('json_out')) {
    function json_out(array $payload, int $code = 200): void {
        http_response_code($code);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$pdo = gdy_pdo_safe();
if (!($pdo instanceof PDO)) {
    json_out(['ok' => false, 'error' => 'DB error'], 500);
}

// Helpers
function current_member(): ?array {
    $u = $_SESSION['user'] ?? null;
    if (!is_array($u)) return null;
    if (empty($u['id'])) return null;
    if (!empty($u['role']) && $u['role'] !== 'guest') return $u;
    return $u; // allow legacy sessions
}

function require_csrf(): void {
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!function_exists('verify_csrf_token')) {
        // fallback: accept if session has csrf_token and matches
        $sess = (string)($_SESSION['csrf_token'] ?? '');
        if ($sess === '' || $token === '' || !hash_equals($sess, $token)) {
            json_out(['ok'=>false,'error'=>'CSRF'], 403);
        }
        return;
    }
    if (!verify_csrf_token($token)) {
        json_out(['ok'=>false,'error'=>'CSRF'], 403);
    }
}

function can_moderate(?array $u): bool {
    if (!$u) return false;
    $role = (string)($u['role'] ?? '');
    return $role === 'admin' || (int)($u['is_admin'] ?? 0) === 1;
}

function normalize_body(string $body): string {
    $body = trim($body);
    $body = preg_replace("~\r\n?~", "\n", $body);
    // Basic safety: allow plain text only
    $body = strip_tags($body);
    // limit
    if (mb_strlen($body, 'UTF-8') > 2000) {
        $body = mb_substr($body, 0, 2000, 'UTF-8');
    }
    return $body;
}

// ACTION
$action = (string)($_POST['action'] ?? $_GET['action'] ?? 'list');

// ---------------------------------------------------------------------
// LIST
// ---------------------------------------------------------------------
if ($action === 'list') {
    $newsId = (int)($_GET['news_id'] ?? 0);
    if ($newsId <= 0) {
        json_out(['ok'=>false,'error'=>'news_id'], 400);
    }

    // Determine if users table exists / columns
    $usersCols = [];
    try {
        $usersCols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Throwable $e) {
        $usersCols = [];
    }
    $hasUsers = is_array($usersCols) && !empty($usersCols);

    $nameExpr = "c.name";
    $avatarExpr = "NULL";
    if ($hasUsers) {
        $nameExpr = "COALESCE(u.display_name, u.username, c.name)";
        if (in_array('avatar', $usersCols, true)) {
            $avatarExpr = "u.avatar";
        }
    }

    $sql = "
        SELECT
          c.id, c.news_id, c.user_id, c.name, c.email, c.body, c.parent_id, c.status, c.score,
          c.created_at, c.updated_at,
          {$nameExpr} AS author_name,
          {$avatarExpr} AS author_avatar
        FROM news_comments c
        " . ($hasUsers ? "LEFT JOIN users u ON u.id = c.user_id" : "") . "
        WHERE c.news_id = :nid AND c.status = 'approved'
        ORDER BY c.created_at ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':nid' => $newsId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // build tree
    $byId = [];
    foreach ($rows as $r) {
        $r['id'] = (int)$r['id'];
        $r['news_id'] = (int)$r['news_id'];
        $r['user_id'] = $r['user_id'] !== null ? (int)$r['user_id'] : null;
        $r['parent_id'] = (int)$r['parent_id'];
        $r['score'] = (int)$r['score'];
        $r['children'] = [];
        $byId[$r['id']] = $r;
    }
    $tree = [];
    foreach ($byId as $id => $r) {
        if ($r['parent_id'] > 0 && isset($byId[$r['parent_id']])) {
            $byId[$r['parent_id']]['children'][] = $r;
        } else {
            $tree[] = $r;
        }
    }

    $me = current_member();
    json_out([
        'ok' => true,
        'news_id' => $newsId,
        'comments' => $tree,
        'me' => $me ? [
            'id' => (int)($me['id'] ?? 0),
            'username' => (string)($me['username'] ?? ''),
            'display_name' => (string)($me['display_name'] ?? ($me['username'] ?? '')),
            'role' => (string)($me['role'] ?? ''),
        ] : null,
    ]);
}

// All write actions require CSRF
if (!in_array($action, ['add','edit','delete','vote'], true)) {
    json_out(['ok'=>false,'error'=>'Unknown action'], 400);
}
require_csrf();

$me = current_member();

// ---------------------------------------------------------------------
// ADD / REPLY
// ---------------------------------------------------------------------
if ($action === 'add') {
    $newsId = (int)($_POST['news_id'] ?? 0);
    $parentId = (int)($_POST['parent_id'] ?? 0);
    $body = normalize_body((string)($_POST['body'] ?? ''));
    if ($newsId <= 0 || $body === '') {
        json_out(['ok'=>false,'error'=>'validation'], 422);
    }

    $userId = $me ? (int)($me['id'] ?? 0) : 0;
    $name = '';
    $email = '';

    if ($userId > 0) {
        $name = (string)($me['display_name'] ?? ($me['username'] ?? ''));
        $email = (string)($me['email'] ?? '');
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        if ($name === '' || $email === '') {
            json_out(['ok'=>false,'error'=>'login_required'], 401);
        }
    }

    // Auto-approve for logged-in members; guests -> pending
    $status = ($userId > 0) ? 'approved' : 'pending';

    $st = $pdo->prepare(
        "INSERT INTO news_comments (news_id, user_id, name, email, body, parent_id, status, ip, user_agent)\n"
      . "VALUES (:nid, :uid, :name, :email, :body, :pid, :status, :ip, :ua)"
    );
    $ok = $st->execute([
        ':nid' => $newsId,
        ':uid' => ($userId > 0 ? $userId : null),
        ':name' => ($name !== '' ? $name : null),
        ':email' => ($email !== '' ? $email : null),
        ':body' => $body,
        ':pid' => max(0, $parentId),
        ':status' => $status,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
    ]);

    if (!$ok) {
        json_out(['ok'=>false,'error'=>'db'], 500);
    }
    json_out(['ok'=>true, 'status'=>$status]);
}

// ---------------------------------------------------------------------
// EDIT
// ---------------------------------------------------------------------
if ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $body = normalize_body((string)($_POST['body'] ?? ''));
    if ($id <= 0 || $body === '') {
        json_out(['ok'=>false,'error'=>'validation'], 422);
    }

    $st = $pdo->prepare("SELECT id, user_id FROM news_comments WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(['ok'=>false,'error'=>'not_found'], 404);

    $ownerId = (int)($row['user_id'] ?? 0);
    if (!can_moderate($me) && (!$me || (int)($me['id'] ?? 0) !== $ownerId)) {
        json_out(['ok'=>false,'error'=>'forbidden'], 403);
    }

    $st = $pdo->prepare("UPDATE news_comments SET body=:b, updated_at=NOW() WHERE id=:id");
    $st->execute([':b'=>$body, ':id'=>$id]);
    json_out(['ok'=>true]);
}

// ---------------------------------------------------------------------
// DELETE
// ---------------------------------------------------------------------
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_out(['ok'=>false,'error'=>'validation'], 422);

    $st = $pdo->prepare("SELECT id, user_id FROM news_comments WHERE id = :id LIMIT 1");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(['ok'=>false,'error'=>'not_found'], 404);

    $ownerId = (int)($row['user_id'] ?? 0);
    if (!can_moderate($me) && (!$me || (int)($me['id'] ?? 0) !== $ownerId)) {
        json_out(['ok'=>false,'error'=>'forbidden'], 403);
    }

    // delete children too
    $pdo->prepare("DELETE FROM news_comments WHERE parent_id = :id")->execute([':id'=>$id]);
    $pdo->prepare("DELETE FROM news_comments WHERE id = :id")->execute([':id'=>$id]);
    json_out(['ok'=>true]);
}

// ---------------------------------------------------------------------
// VOTE
// ---------------------------------------------------------------------
if ($action === 'vote') {
    $id = (int)($_POST['id'] ?? 0);
    $value = (int)($_POST['value'] ?? 0);
    if ($id <= 0 || !in_array($value, [-1, 1], true)) {
        json_out(['ok'=>false,'error'=>'validation'], 422);
    }

    $uid = $me ? (int)($me['id'] ?? 0) : 0;
    $ip  = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($uid <= 0 && $ip === '') {
        json_out(['ok'=>false,'error'=>'login_required'], 401);
    }

    // upsert vote
    try {
        if ($uid > 0) {
            $pdo->prepare("INSERT INTO news_comment_votes (comment_id,user_id,ip,value) VALUES (:cid,:uid,:ip,:v)")
                ->execute([':cid'=>$id, ':uid'=>$uid, ':ip'=>null, ':v'=>$value]);
        } else {
            $pdo->prepare("INSERT INTO news_comment_votes (comment_id,user_id,ip,value) VALUES (:cid,NULL,:ip,:v)")
                ->execute([':cid'=>$id, ':ip'=>$ip, ':v'=>$value]);
        }
    } catch (Throwable $e) {
        // duplicate vote -> update
        if ($uid > 0) {
            $pdo->prepare("UPDATE news_comment_votes SET value=:v WHERE comment_id=:cid AND user_id=:uid")
                ->execute([':v'=>$value, ':cid'=>$id, ':uid'=>$uid]);
        } else {
            $pdo->prepare("UPDATE news_comment_votes SET value=:v WHERE comment_id=:cid AND ip=:ip")
                ->execute([':v'=>$value, ':cid'=>$id, ':ip'=>$ip]);
        }
    }

    // recompute score
    $st = $pdo->prepare("SELECT COALESCE(SUM(value),0) FROM news_comment_votes WHERE comment_id=:cid");
    $st->execute([':cid'=>$id]);
    $score = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE news_comments SET score=:s WHERE id=:cid")->execute([':s'=>$score, ':cid'=>$id]);

    json_out(['ok'=>true,'score'=>$score]);
}

json_out(['ok'=>false,'error'=>'Unhandled'], 400);
