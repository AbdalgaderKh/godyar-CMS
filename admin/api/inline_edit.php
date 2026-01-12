<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

use Godyar\Auth;

header('Content-Type: application/json; charset=UTF-8');

function j($ok, $data=null, $error=null) {
  echo json_encode(['ok'=>$ok,'data'=>$data,'error'=>$error], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!verify_csrf()) j(false, null, 'csrf');

$user = Auth::user();
$uid = (int)($user['id'] ?? 0);
if ($uid <= 0) j(false, null, 'not_logged_in');

$entity = strtolower(trim((string)($_POST['entity'] ?? '')));
$id     = (int)($_POST['id'] ?? 0);
$field  = strtolower(trim((string)($_POST['field'] ?? '')));
$value  = trim((string)($_POST['value'] ?? ''));

if ($id <= 0 || $entity === '' || $field === '') j(false, null, 'missing_fields');

$allow = [
  'tags' => ['name','slug'],
  'categories' => ['name','slug'],
];

if (!isset($allow[$entity]) || !in_array($field, $allow[$entity], true)) {
  j(false, null, 'not_allowed');
}

try {
  $pdo = \Godyar\DB::pdo();
  $sql = "UPDATE {$entity} SET {$field} = :v WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['v'=>$value,'id'=>$id]);
  j(true, true, null);
} catch (\Throwable $e) {
  j(false, null, 'db_error');
}
