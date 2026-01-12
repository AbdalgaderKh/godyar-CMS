<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
$data = json_decode(file_get_contents('php://input'), true);
$endpoint = $data['endpoint'] ?? '';
$p256dh   = $data['keys']['p256dh'] ?? '';
$auth     = $data['keys']['auth'] ?? '';
if (!$endpoint || !$p256dh || !$auth) { http_response_code(422); echo json_encode(['ok'=>false]); exit; }
try {
  $st = $pdo->prepare("INSERT INTO push_subscribers (endpoint,p256dh,auth,created_at) VALUES (:e,:p,:a,NOW()) ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth=VALUES(auth)");
  $st->execute([':e'=>$endpoint, ':p'=>$p256dh, ':a'=>$auth]);
  echo json_encode(['ok'=>true]);
} catch (Throwable $e){ error_log('PUSH_SUBSCRIBE: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false]); }
