<?php
declare(strict_types=1);

require_once __DIR__ . '/token.php';

// Allow cron without login if token is correct
if (!gdy_queue_has_valid_token()) {
    require_once __DIR__ . '/../../_admin_guard.php';

    // المدير فقط عندما لا يوجد توكن
    if (class_exists('Godyar\\Auth') && method_exists('Godyar\\Auth','requireRole')) {
        \Godyar\Auth::requireRole('admin');
    } else {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') { http_response_code(403); exit('403 Forbidden'); }
    }
}

require_once __DIR__ . '/../../plugins/loader.php';
require_once __DIR__ . '/../../queue.php';

// Load bootstrap for DB access (scheduler)
$bootstrapPaths = [
  __DIR__ . '/../../../includes/bootstrap.php',
  __DIR__ . '/../../../godyar/includes/bootstrap.php',
  __DIR__ . '/../../../bootstrap.php',
];
$pdo = null;
foreach ($bootstrapPaths as $p){
  if (is_file($p)) { require_once $p; break; }
}

// If DB is available, run scheduler every call (so Cron can just hit this URL)
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->exec("UPDATE news SET status='published', published_at=COALESCE(published_at, NOW())
                   WHERE status IN ('draft','pending') AND publish_at IS NOT NULL AND publish_at <= NOW() AND (deleted_at IS NULL)");
        $pdo->exec("UPDATE news SET status='draft'
                   WHERE status='published' AND unpublish_at IS NOT NULL AND unpublish_at <= NOW() AND (deleted_at IS NULL)");
    } catch (Throwable $e) {
        // ignore scheduler errors; still allow queue jobs
    }
}

// Allow queue jobs for advanced tasks / plugins
gdy_queue_register('news_scheduler', function(array $payload) use ($pdo){
    if (!isset($pdo) || !($pdo instanceof PDO)) return;
    $pdo->exec("UPDATE news SET status='published', published_at=COALESCE(published_at, NOW())
               WHERE status IN ('draft','pending') AND publish_at IS NOT NULL AND publish_at <= NOW() AND (deleted_at IS NULL)");
    $pdo->exec("UPDATE news SET status='draft'
               WHERE status='published' AND unpublish_at IS NOT NULL AND unpublish_at <= NOW() AND (deleted_at IS NULL)");
});

$ran = GdyQueue::run(20);

header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
echo "Ran: ".count($ran)."\n";
foreach ($ran as $j){
  $type = (string)($j['type'] ?? '');
  $status = (string)($j['status'] ?? '');
  $attempts = (int)($j['attempts'] ?? 0);
  echo "- {$type} [{$status}] attempts={$attempts}\n";
  if (!empty($j['last_error'])) echo "  error: {$j['last_error']}\n";
}
