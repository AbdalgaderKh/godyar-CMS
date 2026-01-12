<?php
require_once __DIR__ . '/../includes/bootstrap.php';
?>
 header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode(['ok'=>true,'suggestions'=>[]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
