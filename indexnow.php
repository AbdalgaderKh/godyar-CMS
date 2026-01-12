<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/seo/fast_index.php';

header('Content-Type: application/json; charset=utf-8');

$base = function_exists('gdy_base_url') ? gdy_base_url() : '';
$base = rtrim($base, '/');

$url = trim((string)($_GET['url'] ?? ''));
if ($url === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing url'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!preg_match('#^https?://#i', $url)) {
  $url = $base . '/' . ltrim($url, '/');
}

$ok = gdy_indexnow_submit([$url]);

echo json_encode(['ok'=>$ok, 'url'=>$url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
