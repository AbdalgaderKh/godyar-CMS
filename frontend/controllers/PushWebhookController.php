<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }
$payload = json_decode(file_get_contents('php://input'), true);
$title = trim($payload['title'] ?? '');
$body  = trim($payload['body'] ?? '');
$url   = trim($payload['url'] ?? '/godyar/');
$icon  = trim($payload['icon'] ?? '/godyar/assets/images/logos/logo.png');
if ($title === '' || $body === '') { http_response_code(422); echo json_encode(['ok'=>false,'msg'=>'missing']); exit; }
try {
  $st=$pdo->prepare("INSERT INTO notifications (title,body,url,icon,status,created_at) VALUES (:t,:b,:u,:i,'pending',NOW())");
  $st->execute([':t'=>$title,':b'=>$body,':u'=>$url,':i'=>$icon]);
  echo json_encode(['ok'=>true]);
} catch (Throwable $e){ error_log('PUSH_WEBHOOK: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false]); }
