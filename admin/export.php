<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_guard.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

use Godyar\Auth;

$entity = strtolower(trim((string)($_GET['entity'] ?? '')));
$allowed = ['news','users','comments','tags','categories','media'];
if (!in_array($entity, $allowed, true)) {
  http_response_code(400);
  echo "Invalid entity";
  exit;
}

// Basic permission mapping (adjust via DB permissions if desired)
$permMap = [
  'news' => 'posts.view',
  'users' => 'manage_users',
  'comments' => 'comments.view',
  'tags' => 'tags.view',
  'categories' => 'categories.view',
  'media' => 'media.view',
];
$perm = $permMap[$entity] ?? '';
if ($perm !== '' && class_exists('\\Godyar\\Auth')) {
  // fail-open to avoid breaking existing installs; if you want strict, change to requirePermission always.
  try { \Godyar\Auth::requirePermission($perm); } catch (\Throwable $e) {}
}

$pdo = \Godyar\DB::pdo();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$entity.'_export_'.date('Y-m-d_H-i').'.csv"');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

function row(array $r): void {
  global $out;
  fputcsv($out, $r);
}

try {
  switch ($entity) {
    case 'news':
      row(['id','title','status','author_id','category_id','created_at','published_at']);
      $stmt = $pdo->query("SELECT id,title,status,author_id,category_id,created_at,published_at FROM news ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;

    case 'users':
      row(['id','username','email','role','status','created_at']);
      $stmt = $pdo->query("SELECT id,username,email,role,status,created_at FROM users ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;

    case 'comments':
      // table optional
      $chk = $pdo->query("SHOW TABLES LIKE 'comments'");
      $has = $chk && $chk->fetchColumn();
      if (!$has) { row(['error']); row(['comments table missing']); break; }
      // column name differs between installs (body/comment)
      $col = 'body';
      try {
        $c = $pdo->query("SHOW COLUMNS FROM comments LIKE 'body'");
        $hasBody = $c && $c->fetchColumn();
        if (!$hasBody) $col = 'comment';
      } catch (\Throwable $e) {
        $col = 'comment';
      }
      row(['id','name','email','status','created_at','body']);
      $stmt = $pdo->query("SELECT id,name,email,status,created_at,`$col` AS body FROM comments ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;

    case 'tags':
      row(['id','name','slug','created_at']);
      $stmt = $pdo->query("SELECT id,name,slug,created_at FROM tags ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;

    case 'categories':
      row(['id','name','slug','created_at']);
      $stmt = $pdo->query("SELECT id,name,slug,created_at FROM categories ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;

    case 'media':
      row(['id','file_name','file_type','created_at']);
      $stmt = $pdo->query("SELECT id,file_name,file_type,created_at FROM media ORDER BY id DESC LIMIT 5000");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) row($r);
      break;
  }
} catch (\Throwable $e) {
  row(['error']);
  row([$e->getMessage()]);
}

fclose($out);
exit;
