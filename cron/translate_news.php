<?php
declare(strict_types=1);

/**
 * cron/translate_news.php
 * Translate news drafts/published into EN/FR and store in news_translations.
 * Requires: OPENAI_API_KEY and TRANSLATION_ENABLE=1
 *
 * Example:
 *   php cron/translate_news.php --limit=20
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = gdy_pdo_safe();

if (!gdy_translation_enabled()) {
    echo "Translation disabled (TRANSLATION_ENABLE=0)" . PHP_EOL;
    exit(0);
}

// Parse args
$limit = 10;
foreach ($argv as $arg) {
    if (preg_match('~^--limit=(\d+)$~', $arg, $m)) {
        $limit = max(1, (int)$m[1]);
    }
}

// Ensure table exists
try {
    gdy_ensure_news_translations_table($pdo);
} catch (Throwable $e) {
    echo "Failed ensuring table: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Find news missing any of EN/FR
$sql = "
SELECT n.id
FROM news n
LEFT JOIN news_translations t_en ON t_en.news_id = n.id AND t_en.lang = 'en'
LEFT JOIN news_translations t_fr ON t_fr.news_id = n.id AND t_fr.lang = 'fr'
WHERE n.status IN ('draft','published')
  AND (t_en.id IS NULL OR t_fr.id IS NULL)
ORDER BY n.created_at DESC
LIMIT :lim
";

$st = $pdo->prepare($sql);
$st->bindValue(':lim', $limit, PDO::PARAM_INT);
$st->execute();
$ids = $st->fetchAll(PDO::FETCH_COLUMN);

if (!$ids) {
    echo "No items to translate." . PHP_EOL;
    exit(0);
}

$done = 0;
foreach ($ids as $id) {
    $id = (int)$id;
    if ($id <= 0) continue;

    foreach (['en','fr'] as $lang) {
        // Skip if exists
        $tr = gdy_get_news_translation($pdo, $id, $lang);
        if (is_array($tr)) continue;

        $ok = gdy_translate_and_store_news($pdo, $id, $lang);
        echo "news #{$id} -> {$lang}: " . ($ok ? 'OK' : 'FAIL') . PHP_EOL;
    }
    $done++;
}

echo "Completed: {$done} items" . PHP_EOL;
