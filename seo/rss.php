<?php
declare(strict_types=1);

ob_start();

// seo/rss.php — RSS 2.0 feed
// Served via route: /rss.xml

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!function_exists('gdy_pdo_safe')) {
    require_once ROOT_PATH . '/includes/bootstrap.php';
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;

// Simple file cache (auto-invalidated on publish/edit/delete)
$cacheFile = ROOT_PATH . '/cache/rss.xml';
$cacheTtl  = 300; // seconds
$nocache = isset($_GET['nocache']) && $_GET['nocache'] === '1';
if (!$nocache && is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    header('Content-Type: application/xml; charset=UTF-8');
    echo file_get_contents($cacheFile);
    exit;
}


$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : ($scheme . '://' . $host);

header('Content-Type: application/rss+xml; charset=UTF-8');

$siteTitle = (string)($GLOBALS['site_settings']['site_name'] ?? 'Godyar News');
$siteDesc  = (string)($GLOBALS['site_settings']['site_description'] ?? 'آخر الأخبار والتقارير');
$siteLink  = $baseUrl . '/';

$items = [];
if ($pdo instanceof PDO) {
    try {
        $sql = "SELECT id, title, excerpt, summary, date, created_at, updated_at
                FROM news
                WHERE status = 'published' OR status IS NULL
                ORDER BY id DESC
                LIMIT 50";
        $st = $pdo->query($sql);
        $items = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $items = [];
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0">' . "\n";
echo "<channel>\n";
echo '  <title>' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') . "</title>\n";
echo '  <link>' . htmlspecialchars($siteLink, ENT_QUOTES, 'UTF-8') . "</link>\n";
echo '  <description>' . htmlspecialchars($siteDesc, ENT_QUOTES, 'UTF-8') . "</description>\n";
echo '  <language>ar</language>' . "\n";
echo '  <lastBuildDate>' . gmdate('r') . "</lastBuildDate>\n";

foreach ($items as $it) {
    $id = (int)($it['id'] ?? 0);
    if ($id <= 0) continue;

    $title = (string)($it['title'] ?? '');
    $desc  = (string)($it['excerpt'] ?? ($it['summary'] ?? ''));
    $desc  = trim($desc);

    // fallback description
    if ($desc === '') {
        $desc = $title;
    }

    $link = $baseUrl . '/news/id/' . $id;

    $date = (string)($it['updated_at'] ?? ($it['date'] ?? ($it['created_at'] ?? '')));
    $ts = $date !== '' ? @strtotime($date) : false;
    $pub = $ts ? gmdate('r', $ts) : gmdate('r');

    echo "  <item>\n";
    echo '    <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>\n";
    echo '    <link>' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "</link>\n";
    echo '    <guid isPermaLink="true">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "</guid>\n";
    echo '    <pubDate>' . $pub . "</pubDate>\n";
    echo '    <description><![CDATA[' . $desc . ']]></description>' . "\n";
    echo "  </item>\n";
}

echo "</channel>\n</rss>";
$xml = ob_get_clean();
if (is_string($xml) && $xml !== '') {
    @file_put_contents($cacheFile, $xml, LOCK_EX);
}
echo $xml;
exit;
