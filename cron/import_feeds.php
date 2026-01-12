<?php
declare(strict_types=1);

// cron/import_feeds.php
// Run via cron: */15 * * * * php /path/to/godyar/cron/import_feeds.php >> /path/to/godyar/storage/logs/feeds.log 2>&1

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/bootstrap.php';

use Godyar\Services\FeedImportService;

/** @var PDO|null $pdo */
$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : ($GLOBALS['pdo'] ?? null);
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "DB not available\n");
    exit(1);
}

$svc = new FeedImportService($pdo);
$result = $svc->run();

echo date('c') . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
