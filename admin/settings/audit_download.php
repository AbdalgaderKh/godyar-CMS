<?php
declare(strict_types=1);
require_once __DIR__ . '/_settings_guard.php';
$logFile = ROOT_PATH . '/storage/logs/audit.log';
if (!is_file($logFile) || !is_readable($logFile)) {
  http_response_code(404);
  echo 'Not found';
  exit;
}
header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="audit.log"');
readfile($logFile);
exit;
