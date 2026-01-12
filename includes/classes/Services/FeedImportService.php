<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Godyar\Feeds\RssReader;

/**
 * FeedImportService
 * - Imports RSS/Atom items into the news table as (draft/published) posts.
 * - Stores unique item hashes in news_imports to prevent duplicates.
 * - Generates Arabic professional summaries (optional AI).
 */
final class FeedImportService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function run(): array
    {
        $this->ensureImportsTable();

        $feeds = $this->dueFeeds();
        $defaultAuthorId = $this->defaultAuthorId();

        $status = $this->env('FEEDS_PUBLISH_STATUS', 'draft'); // draft | pending | published
        $allowedStatuses = ['draft','pending','published'];
        if (!in_array($status, $allowedStatuses, true)) { $status = 'draft'; }
        $useAI  = $this->envBool('FEEDS_USE_AI', false);

        $imported = 0;
        $skipped  = 0;

        foreach ($feeds as $feed) {
            $items = RssReader::fetch((string)$feed['url'], 12);
            if (!$items) {
                $this->touchFeed((int)$feed['id']);
                continue;
            }

            foreach ($items as $it) {
                $linkRaw = (string)($it['link'] ?? '');
                $link    = self::normalizeLink($linkRaw);

                $title = (string)($it['title'] ?? '');
                $date  = (string)($it['date'] ?? '');

                // Hash is based on normalized link; if missing, fall back to a stable fingerprint
                $hashBase = ($link !== '') ? $link : ($title . '|' . $date . '|' . (string)($feed['id'] ?? '0'));
                $hash = sha1($hashBase);

                // 1) Already imported by hash?
                if ($this->hasImported($hash)) { $skipped++; continue; }

                // 2) Already exists in DB (by link/title)? Mark as imported to prevent future duplicates.
                $existingId = $this->findExistingNewsId($link, $title, $hash);
                if ($existingId > 0) {
                    try { $this->markImported($existingId, (int)$feed['id'], $hash, $link); } catch (\Throwable $e) {}
                    $skipped++;
                    continue;
                }

                $summary = self::cleanSummary((string)($it['summary'] ?? ''));

                // AI can rewrite/translate ONLY the SUMMARY, not full articles.
                $ai = null;
                if ($useAI) {
                    $ai = $this->aiSummarize($title, $summary, (string)$feed['name']);
                }

                $excerpt = $ai['excerpt'] ?? $this->fallbackExcerpt($summary);
                $content = $ai['content_html'] ?? $this->buildContentHtml($excerpt, $link, (string)$feed['name']);

                $newsId = $this->insertNews([
                    'title'       => $title,
                    'slug'        => $this->slugify($title),
                    'excerpt'     => $excerpt,
                    'content'     => $content,
                    'category_id' => (int)($feed['category_id'] ?? 0),
                    'author_id'   => $defaultAuthorId,
                    'image'       => (string)$it['image'],
                    'status'      => $status,
                    'source_link' => $link,
                ]);

                if ($newsId > 0) {
                    $this->markImported($newsId, (int)$feed['id'], $hash, $link);
                    $imported++;
                } else {
                    $skipped++;
                }
            }

            $this->touchFeed((int)$feed['id']);
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'feeds' => count($feeds)];
    }

    /* ------------------------------- DB ------------------------------- */

    private function ensureImportsTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS news_imports (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              news_id INT UNSIGNED NOT NULL,
              feed_id INT UNSIGNED NOT NULL,
              item_hash CHAR(40) NOT NULL,
              item_link VARCHAR(1000) NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_item_hash (item_hash),
              KEY idx_feed (feed_id),
              KEY idx_news (news_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    private function dueFeeds(): array
    {
        // fetch due feeds (interval minutes)
        $rows = $this->pdo->query("SELECT * FROM feeds WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return [];

        $due = [];
        $now = time();

        foreach ($rows as $f) {
            $interval = (int)($f['fetch_interval_minutes'] ?? 60);
            if ($interval < 5) $interval = 5;

            $last = $f['last_fetched_at'] ? strtotime((string)$f['last_fetched_at']) : 0;
            if ($last <= 0 || ($now - $last) >= ($interval * 60)) {
                $due[] = $f;
            }
        }
        return $due;
    }

    private function touchFeed(int $feedId): void
    {
        $st = $this->pdo->prepare("UPDATE feeds SET last_fetched_at = NOW() WHERE id = :id");
        $st->execute([':id' => $feedId]);
    }

    private function hasImported(string $hash): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM news_imports WHERE item_hash = :h LIMIT 1");
        $st->execute([':h' => $hash]);
        return (bool)$st->fetchColumn();
    }

    private function markImported(int $newsId, int $feedId, string $hash, string $link): void
    {
        $st = $this->pdo->prepare("INSERT INTO news_imports (news_id, feed_id, item_hash, item_link) VALUES (:n,:f,:h,:l)");
        $st->execute([':n'=>$newsId, ':f'=>$feedId, ':h'=>$hash, ':l'=>$link]);
    }

    private function defaultAuthorId(): int
    {
        $env = (int)$this->env('FEEDS_DEFAULT_AUTHOR_ID', '0');
        if ($env > 0) return $env;

        // Try first user
        try {
            $id = (int)($this->pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn());
            return $id > 0 ? $id : 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * Insert news with schema-flexible columns (supports different versions).
     */
    private function insertNews(array $d): int
    {
        $cols = $this->newsColumns();

        // Required minimal mapping
        $map = [];

        // title
        if (isset($cols['title'])) $map['title'] = ':title';
        // slug
        if (isset($cols['slug'])) $map['slug'] = ':slug';

        // excerpt field names
        if (isset($cols['excerpt'])) $map['excerpt'] = ':excerpt';

        // content field name might be content/body
        if (isset($cols['content'])) $map['content'] = ':content';
        elseif (isset($cols['body'])) $map['body'] = ':content';

        if (isset($cols['category_id'])) $map['category_id'] = ':category_id';
        if (isset($cols['author_id'])) $map['author_id'] = ':author_id';

        // image columns
        if (isset($cols['featured_image'])) $map['featured_image'] = ':image';
        elseif (isset($cols['image'])) $map['image'] = ':image';

        if (isset($cols['status'])) $map['status'] = ':status';

        // publish timestamp columns
        if (isset($cols['publish_at'])) $map['publish_at'] = 'NOW()';
        elseif (isset($cols['published_at'])) $map['published_at'] = 'NOW()';

        // created/updated
        if (isset($cols['created_at'])) $map['created_at'] = 'NOW()';
        if (isset($cols['updated_at'])) $map['updated_at'] = 'NOW()';

        if (!$map) return 0;

        $columns = implode(', ', array_keys($map));
        $values  = implode(', ', array_values($map));
        $sql = "INSERT INTO news ($columns) VALUES ($values)";

        // Ensure slug is unique to avoid cron failure on duplicates
        if (isset($cols['slug'])) {
            $d['slug'] = $this->uniqueSlug((string)($d['slug'] ?? ''), (string)($d['source_link'] ?? ''));
        }

        $st = $this->pdo->prepare($sql);
        foreach (['title','slug','excerpt','content','category_id','author_id','image','status'] as $k) {
            if (strpos($sql, ":$k") === false) continue;
            $val = $d[$k] ?? null;
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            if ($val === null) $type = PDO::PARAM_NULL;
            $st->bindValue(":$k", $val, $type);
        }

        $ok = $st->execute();
        return $ok ? (int)$this->pdo->lastInsertId() : 0;
    }

    private function newsColumns(): array
    {
        static $cache = null;
        if (is_array($cache)) return $cache;

        $out = [];
        $st = $this->pdo->query("SHOW COLUMNS FROM news");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(string)$r['Field']] = true;
        }
        $cache = $out;
        return $out;
    }

    /* ---------------------------- Content ---------------------------- */

    private function fallbackExcerpt(string $summary): string
    {
        $s = trim($summary);
        if ($s === '') return 'ملخص الخبر غير متوفر في موجز RSS.';
        if (mb_strlen($s,'UTF-8') > 450) $s = mb_substr($s,0,450,'UTF-8').'…';
        return $s;
    }


    /**
     * Normalize article URL by removing tracking parameters (utm_*, fbclid, gclid, ...).
     * This helps prevent duplicates across the same story.
     */
    private static function normalizeLink(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';

        $parts = @parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            // best-effort: remove whitespace only
            return preg_replace('/\s+/', '', $url);
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host'] ?? '';
        $path   = $parts['path'] ?? '';
        $query  = $parts['query'] ?? '';

        $keep = [];
        if ($query !== '') {
            parse_str($query, $q);
            if (is_array($q)) {
                $blocked = [
                    'fbclid','gclid','yclid','mc_cid','mc_eid','mkt_tok','ref','ref_src','spm','icid',
                    '_ga','_gl','utm_source','utm_medium','utm_campaign','utm_term','utm_content',
                    'ns_mchannel','ns_source','ns_campaign','ns_linkname','ns_fee','ns_mcontent',
                    'CMP','cmpid','cmp','ocid'
                ];
                foreach ($q as $k => $v) {
                    $k2 = strtolower((string)$k);
                    $isUtm = (strpos($k2, 'utm_') === 0);
                    if ($isUtm || in_array($k2, $blocked, true)) continue;
                    $keep[$k] = $v;
                }
            }
        }

        $out = $scheme . '://' . $host . $path;
        if (!empty($keep)) {
            $out .= '?' . http_build_query($keep);
        }
        return $out;
    }

    /**
     * Clean & normalize summary text (already mostly cleaned by RssReader, but we
     * still remove common "read more" tails and keep it tidy).
     */
    private static function cleanSummary(string $summary): string
    {
        $s = trim(html_entity_decode($summary, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $s = preg_replace('/\s+/u', ' ', $s);

        // Remove common tails
        $s = preg_replace('~(اقرأ المزيد.*)$~ui', '', $s);
        $s = preg_replace('~(Read more.*)$~ui', '', $s);

        // Remove trailing source mention patterns
        $s = preg_replace('~\s*[-–—]\s*(Al Jazeera|Al Arabiya|BBC|CNN|الجزيرة|العربية)\s*$~ui', '', $s);

        return trim($s);
    }

    /**
     * Try to find an existing news record for the same imported item.
     * - First checks news_imports (by hash/link)
     * - Then checks news table (by exact title)
     */
    private function findExistingNewsId(string $link, string $title, string $hash): int
    {
        try {
            $st = $this->pdo->prepare("SELECT news_id FROM news_imports WHERE item_hash = :h OR (item_link IS NOT NULL AND item_link = :l) ORDER BY id DESC LIMIT 1");
            $st->execute([':h' => $hash, ':l' => $link]);
            $id = (int)($st->fetchColumn() ?: 0);
            if ($id > 0) return $id;
        } catch (\Throwable $e) {
            // ignore
        }

        $title = trim($title);
        if ($title === '') return 0;

        try {
            // best-effort; do not rely on created_at existing
            $st = $this->pdo->prepare("SELECT id FROM news WHERE title = :t ORDER BY id DESC LIMIT 1");
            $st->execute([':t' => $title]);
            return (int)($st->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function slugExists(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') return false;
        try {
            $st = $this->pdo->prepare("SELECT 1 FROM news WHERE slug = :s LIMIT 1");
            $st->execute([':s' => $slug]);
            return (bool)$st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function uniqueSlug(string $slug, string $seed = ''): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            $slug = 'news-' . date('Ymd-His');
        }

        // If slug column is not unique in DB, this is still harmless.
        if (!$this->slugExists($slug)) return $slug;

        $base = $slug;
        $suffix = substr(sha1($seed !== '' ? $seed : ($base . microtime(true))), 0, 6);
        $try = $base . '-' . $suffix;
        if (!$this->slugExists($try)) return $try;

        // last resort
        return $base . '-' . date('His');
    }

    private function buildContentHtml(string $excerpt, string $sourceLink, string $sourceName): string
    {
        $excerptSafe = htmlspecialchars($excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sourceLinkSafe = htmlspecialchars($sourceLink, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sourceNameSafe = htmlspecialchars($sourceName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<div class="gdy-imported-news">'
            . '<p>' . nl2br($excerptSafe) . '</p>'
            . '<hr>'
            . '<p><strong>المصدر:</strong> ' . $sourceNameSafe . ' — '
            . '<a href="' . $sourceLinkSafe . '" target="_blank" rel="noopener">قراءة المزيد</a></p>'
            . '</div>';
    }

    private function slugify(string $title): string
    {
        $t = mb_strtolower(trim($title), 'UTF-8');
        $t = preg_replace('~[^\p{L}\p{N}]+~u', '-', $t) ?? $t;
        $t = trim($t, '-');
        if (mb_strlen($t,'UTF-8') > 180) $t = mb_substr($t, 0, 180, 'UTF-8');
        return $t !== '' ? $t : ('news-' . time());
    }

    /* ------------------------------- AI ------------------------------ */

    /**
     * Uses OpenAI (optional) to produce a short Arabic summary from feed-provided title+summary ONLY.
     * Returns ['excerpt'=>string,'content_html'=>string] or null.
     */
    private function aiSummarize(string $title, string $feedSummary, string $sourceName): ?array
    {
        $apiKey = (string)$this->env('OPENAI_API_KEY', '');
        if ($apiKey === '') return null;

        $title = trim($title);
        $feedSummary = trim($feedSummary);

        // Do NOT send full article bodies. We only send feed summary (usually short).
        $prompt = "أنت محرر أخبار عربي. لديك عنوان خبر وملخص قصير مأخوذ من موجز RSS.\n"
                . "مهمتك: صياغة ملخص عربي احترافي أصلي (80-120 كلمة) بدون نقل حرفي، مع 3 نقاط رئيسية.\n"
                . "لا تضف معلومات غير موجودة.\n"
                . "في النهاية أضف سطر: \"المصدر: {$sourceName}\".\n\n"
                . "العنوان: {$title}\n"
                . "الملخص: {$feedSummary}\n";

        // Use Chat Completions for compatibility with existing codebase.
        $payload = json_encode([
            'model' => (string)$this->env('OPENAI_MODEL', 'gpt-4.1-mini'),
            'temperature' => 0.3,
            'messages' => [
                ['role' => 'system', 'content' => 'أنت مساعد تحرير أخبار محترف.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 25,
        ]);
        $resp = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$resp || $status >= 400) {
            @error_log('[FeedImportService] OpenAI error status ' . $status . ' resp: ' . (string)$resp);
            return null;
        }
        $data = json_decode((string)$resp, true);
        $text = trim((string)($data['choices'][0]['message']['content'] ?? ''));
        if ($text === '') return null;

        // Build excerpt as first paragraph (up to 500 chars)
        $plain = trim((string)preg_replace('/\s+/u', ' ', strip_tags($text)));
        $excerpt = $plain;
        if (mb_strlen($excerpt,'UTF-8') > 480) $excerpt = mb_substr($excerpt,0,480,'UTF-8').'…';

        $html = '<div class="gdy-imported-news-ai"><p>' . nl2br(htmlspecialchars($text, ENT_QUOTES|ENT_HTML5,'UTF-8')) . '</p></div>';

        return ['excerpt' => $excerpt, 'content_html' => $html];
    }

    /* ------------------------------ Env ------------------------------ */

    private function env(string $key, string $default = ''): string
    {
        if (function_exists('env')) {
            $v = env($key, $default);
            return is_string($v) ? $v : (string)$default;
        }
        $g = getenv($key);
        return $g !== false ? (string)$g : $default;
    }

    private function envBool(string $key, bool $default = false): bool
    {
        $v = strtolower($this->env($key, $default ? '1' : '0'));
        return in_array($v, ['1','true','yes','on'], true);
    }
}
