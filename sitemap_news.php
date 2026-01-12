<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;
$base = function_exists('gdy_base_url') ? gdy_base_url() : '';
$base = rtrim($base, '/');

$siteName = $GLOBALS['site_settings']['site.name'] ?? ($GLOBALS['site_settings']['site_name'] ?? 'Godyar News');
$lang = $GLOBALS['site_settings']['site.locale'] ?? 'ar';

function xml($s){ return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

$items = [];
if ($pdo instanceof PDO) {
    try {
        $cols = $pdo->query("DESCRIBE news")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $hasDeleted = in_array('deleted_at', $cols, true);
        $hasSlug = in_array('slug', $cols, true);
        $dateExpr = "COALESCE(publish_at, published_at, created_at)";

        $where = "status='published'";
        if ($hasDeleted) $where .= " AND deleted_at IS NULL";

        // last 48h
        $sql = "SELECT id, " . ($hasSlug ? "slug," : "'' as slug,") . " title, {$dateExpr} as dt
                FROM news
                WHERE {$where}
                  AND {$dateExpr} >= (NOW() - INTERVAL 2 DAY)
                ORDER BY {$dateExpr} DESC
                LIMIT 1000";
        $stmt = $pdo->query($sql);
        $items = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        @error_log('[sitemap-news] ' . $e->getMessage());
    }
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
<?php foreach ($items as $it):
  $id = (int)($it['id'] ?? 0);
  $slug = trim((string)($it['slug'] ?? ''));
  $url = $slug !== '' ? ($base . '/news/' . rawurlencode($slug)) : ($base . '/news/id/' . $id);
  $title = (string)($it['title'] ?? '');
  $dt = (string)($it['dt'] ?? '');
  $pub = $dt ? gmdate('c', strtotime($dt)) : gmdate('c');
?>
  <url>
    <loc><?= xml($url) ?></loc>
    <news:news>
      <news:publication>
        <news:name><?= xml($siteName) ?></news:name>
        <news:language><?= xml($lang) ?></news:language>
      </news:publication>
      <news:publication_date><?= xml($pub) ?></news:publication_date>
      <news:title><?= xml($title) ?></news:title>
    </news:news>
  </url>
<?php endforeach; ?>
</urlset>
