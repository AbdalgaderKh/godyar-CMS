<?php
declare(strict_types=1);

ob_start();

// seo/sitemap.php — Dynamic sitemap (XML)
// Served via route: /sitemap.xml

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!function_exists('gdy_pdo_safe')) {
    require_once ROOT_PATH . '/includes/bootstrap.php';
}

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;

// Simple file cache (auto-invalidated on publish/edit/delete)
$cacheFile = ROOT_PATH . '/cache/sitemap.xml';
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

header('Content-Type: application/xml; charset=UTF-8');

// Collect URLs
$urls = [];

// Home + core pages
$urls[] = ['loc' => $baseUrl . '/', 'lastmod' => gmdate('c')];
$urls[] = ['loc' => $baseUrl . '/trending', 'lastmod' => gmdate('c')];
$urls[] = ['loc' => $baseUrl . '/archive', 'lastmod' => gmdate('c')];

// Categories
if ($pdo instanceof PDO) {
    try {
        $st = $pdo->query("SELECT slug, updated_at FROM categories WHERE status = 'active' OR status IS NULL ORDER BY id DESC LIMIT 500");
        $cats = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($cats as $c) {
            $slug = trim((string)($c['slug'] ?? ''));
            if ($slug === '') continue;
            $lm = '';
            $u = (string)($c['updated_at'] ?? '');
            if ($u !== '') {
                $ts = @strtotime($u);
                if ($ts) $lm = gmdate('c', $ts);
            }
            $urls[] = ['loc' => $baseUrl . '/category/' . rawurlencode($slug), 'lastmod' => $lm];
        }
    } catch (Throwable $e) {
        // ignore
    }
}

// Latest news (by id)
if ($pdo instanceof PDO) {
    try {
        $st = $pdo->query("SELECT id, updated_at, date, created_at FROM news WHERE status = 'published' OR status IS NULL ORDER BY id DESC LIMIT 1000");
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if ($id <= 0) continue;
            $lm = '';
            $cand = (string)($r['updated_at'] ?? ($r['date'] ?? ($r['created_at'] ?? '')));
            if ($cand !== '') {
                $ts = @strtotime($cand);
                if ($ts) $lm = gmdate('c', $ts);
            }
            $urls[] = ['loc' => $baseUrl . '/news/id/' . $id, 'lastmod' => $lm];
        }
    } catch (Throwable $e) {
        // ignore
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    $loc = htmlspecialchars((string)($u['loc'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($loc === '') continue;
    echo "  <url>\n";
    echo "    <loc>{$loc}</loc>\n";
    if (!empty($u['lastmod'])) {
        $lm = htmlspecialchars((string)$u['lastmod'], ENT_QUOTES, 'UTF-8');
        echo "    <lastmod>{$lm}</lastmod>\n";
    }
    echo "  </url>\n";
}
echo '</urlset>';
$xml = ob_get_clean();
if (is_string($xml) && $xml !== '') {
    @file_put_contents($cacheFile, $xml, LOCK_EX);
}
echo $xml;
exit;
