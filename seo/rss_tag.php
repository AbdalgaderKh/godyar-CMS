<?php
declare(strict_types=1);

ob_start();

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!function_exists('gdy_pdo_safe')) {
    require_once ROOT_PATH . '/includes/bootstrap.php';
}
$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : ($scheme . '://' . $host);

header('Content-Type: application/rss+xml; charset=UTF-8');

$siteTitle = (string)($GLOBALS['site_settings']['site_name'] ?? 'Godyar News');
$siteDesc  = (string)($GLOBALS['site_settings']['site_description'] ?? 'آخر الأخبار والتقارير');

$slug = trim((string)($_GET['slug'] ?? ''));
$items = [];
$feedTitleSuffix = '';
$feedLink = $baseUrl . '/';

if ($pdo instanceof PDO && $slug !== '') {
    try {
        // Resolve tag
        $tagStmt = $pdo->prepare("SELECT id, name, slug FROM tags WHERE slug = :s LIMIT 1");
        $tagStmt->execute([':s' => $slug]);
        $tag = $tagStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($tag) {
            $tid = (int)($tag['id'] ?? 0);
            $tname = (string)($tag['name'] ?? $slug);
            $feedTitleSuffix = ' — ' . $tname;
            $feedLink = $baseUrl . '/tag/' . rawurlencode($slug);

            $sql = "SELECT n.id, n.title, n.excerpt, n.summary, n.date, n.created_at, n.updated_at
                    FROM news n
                    INNER JOIN news_tags nt ON nt.news_id = n.id
                    WHERE (n.status = 'published' OR n.status IS NULL)
                      AND nt.tag_id = :tid
                    ORDER BY n.id DESC
                    LIMIT 50";
            $st = $pdo->prepare($sql);
            $st->execute([':tid' => $tid]);
            $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $items = [];
    }
}

$titleFeed = $siteTitle . $feedTitleSuffix;
$descFeed = $siteDesc;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0">' . "\n";
echo "<channel>\n";
echo '  <title>' . htmlspecialchars($titleFeed, ENT_QUOTES, 'UTF-8') . "</title>\n";
echo '  <link>' . htmlspecialchars($feedLink, ENT_QUOTES, 'UTF-8') . "</link>\n";
echo '  <description>' . htmlspecialchars($descFeed, ENT_QUOTES, 'UTF-8') . "</description>\n";

foreach ($items as $it) {
    $id = (int)($it['id'] ?? 0);
    if ($id <= 0) continue;

    $title = (string)($it['title'] ?? '');
    $desc  = (string)($it['excerpt'] ?? ($it['summary'] ?? ''));
    $desc  = trim($desc);
    if ($desc === '') $desc = $title;

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
