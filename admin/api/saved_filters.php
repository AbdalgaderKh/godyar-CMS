<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

use Godyar\Auth;

header('Content-Type: application/json; charset=UTF-8');

function j(bool $ok, $data=null, ?string $error=null): void {
  echo json_encode(['ok'=>$ok, 'data'=>$data, 'error'=>$error], JSON_UNESCAPED_UNICODE);
  exit;
}

$user = class_exists('Godyar\\Auth') && method_exists('Godyar\\Auth','user') ? Auth::user() : ($_SESSION['user'] ?? []);
$uid = (int)($user['id'] ?? 0);
if ($uid <= 0) j(false, null, 'not_logged_in');

try {
  $pdo = \Godyar\DB::pdo();
} catch (\Throwable $e) {
  j(false, null, 'db_error');
}

// ensure table exists
try {
  $chk = $pdo->query("SHOW TABLES LIKE 'admin_saved_filters'");
  $has = $chk && $chk->fetchColumn();
  if (!$has) j(false, null, 'missing_table:/admin/db/migrations/2026_01_04_admin_saved_filters.sql');
} catch (\Throwable $e) {
  j(false, null, 'db_error');
}

// feature detection (avoid information_schema permissions)
$hasDefaultCol = false;
try {
  $c = $pdo->query("SHOW COLUMNS FROM admin_saved_filters LIKE 'is_default'");
  $hasDefaultCol = (bool)($c && $c->fetchColumn());
} catch (\Throwable $e) {
  $hasDefaultCol = false;
}

$action  = strtolower((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));
$pageKey = trim((string)($_GET['page_key'] ?? $_POST['page_key'] ?? ''));

if ($action !== 'list' && function_exists('verify_csrf')) {
  if (!verify_csrf()) j(false, null, 'csrf');
}

if ($action === 'list') {
  if ($pageKey === '') j(true, ['filters'=>[], 'supports_default'=>$hasDefaultCol, 'default_id'=>null], null);

  try {
    if ($hasDefaultCol) {
      $stmt = $pdo->prepare("SELECT id, name, querystring, is_default, created_at FROM admin_saved_filters WHERE user_id=:uid AND page_key=:k ORDER BY is_default DESC, id DESC");
    } else {
      $stmt = $pdo->prepare("SELECT id, name, querystring, created_at FROM admin_saved_filters WHERE user_id=:uid AND page_key=:k ORDER BY id DESC");
    }
    $stmt->execute(['uid'=>$uid,'k'=>$pageKey]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $defaultId = null;
    if ($hasDefaultCol) {
      foreach ($rows as $r) {
        if ((int)($r['is_default'] ?? 0) === 1) { $defaultId = (int)$r['id']; break; }
      }
    }

    j(true, ['filters'=>$rows, 'supports_default'=>$hasDefaultCol, 'default_id'=>$defaultId], null);
  } catch (\Throwable $e) {
    j(false, null, 'db_error');
  }
}

if ($action === 'create') {
  $name = trim((string)($_POST['name'] ?? ''));
  $qs   = trim((string)($_POST['querystring'] ?? ''));
  $makeDefault = ((string)($_POST['make_default'] ?? '0') === '1');

  if ($pageKey === '' || $name === '' || $qs === '') j(false, null, 'missing_fields');

  try {
    $stmt = $pdo->prepare("INSERT INTO admin_saved_filters (user_id, page_key, name, querystring, created_at) VALUES (:uid,:k,:n,:qs,NOW())");
    $stmt->execute(['uid'=>$uid,'k'=>$pageKey,'n'=>$name,'qs'=>$qs]);
    $newId = (int)$pdo->lastInsertId();

    if ($hasDefaultCol && $makeDefault) {
      $pdo->prepare("UPDATE admin_saved_filters SET is_default=0 WHERE user_id=:uid AND page_key=:k")->execute(['uid'=>$uid,'k'=>$pageKey]);
      $pdo->prepare("UPDATE admin_saved_filters SET is_default=1 WHERE id=:id AND user_id=:uid")->execute(['id'=>$newId,'uid'=>$uid]);
    }

    j(true, ['id'=>$newId], null);
  } catch (\Throwable $e) {
    j(false, null, 'db_error');
  }
}

if ($action === 'delete') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) j(false, null, 'bad_id');

  try {
    $stmt = $pdo->prepare("DELETE FROM admin_saved_filters WHERE id=:id AND user_id=:uid");
    $stmt->execute(['id'=>$id,'uid'=>$uid]);
    j(true, true, null);
  } catch (\Throwable $e) {
    j(false, null, 'db_error');
  }
}

if ($action === 'set_default') {
  if (!$hasDefaultCol) j(false, null, 'missing_column:is_default');

  $id = (int)($_POST['id'] ?? 0);
  if ($pageKey === '' || $id <= 0) j(false, null, 'bad_id');

  try {
    $pdo->prepare("UPDATE admin_saved_filters SET is_default=0 WHERE user_id=:uid AND page_key=:k")->execute(['uid'=>$uid,'k'=>$pageKey]);
    $pdo->prepare("UPDATE admin_saved_filters SET is_default=1 WHERE id=:id AND user_id=:uid AND page_key=:k")->execute(['id'=>$id,'uid'=>$uid,'k'=>$pageKey]);
    j(true, true, null);
  } catch (\Throwable $e) {
    j(false, null, 'db_error');
  }
}

j(false, null, 'unknown_action');
