<?php
use Godyar\Feeds\FeedParser;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/bootstrap.php'; // expected to set $pdo

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$feeds = $pdo->query("SELECT * FROM feeds WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
if (!$feeds) exit(0);

foreach ($feeds as $feed) {
    $items = FeedParser::fetch($feed['url']);
    if (!$items) continue;

    $insert = $pdo->prepare("INSERT IGNORE INTO news (title, slug, content, featured_image, category_id, status, publish_at, created_at, updated_at)
                             VALUES (:title, :slug, :content, :img, :cat, 'draft', NOW(), NOW(), NOW())");
    foreach ($items as $it) {
        $slug = substr(preg_replace('~[^\p{L}\p{N}]+~u', '-', mb_strtolower($it['title'])), 0, 180);
        $insert->execute([
            ':title' => $it['title'],
            ':slug'  => $slug,
            ':content' => $it['content'],
            ':img' => $it['image'],
            ':cat' => $feed['category_id'],
        ]);
    }
    $upd = $pdo->prepare("UPDATE feeds SET last_fetched_at = NOW() WHERE id = :id");
    $upd->execute([':id' => $feed['id']]);
}
