<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Throwable;

/**
 * NewsService - schema-tolerant helpers used by controllers.
 *
 * This file includes all methods referenced by NewsController in the refactor branch:
 * - findBySlugOrId
 * - relatedByCategory
 * - latest
 * - mostRead
 * - incrementViews
 */
final class NewsService
{
    public function __construct(private PDO $pdo) {}

    private static array $colCache = [];

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . ':' . $column;
        if (array_key_exists($key, self::$colCache)) return (bool)self::$colCache[$key];

        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :col");
            $stmt->execute([':col' => $column]);
            $exists = (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            $exists = false;
        }

        self::$colCache[$key] = $exists;
        return $exists;
    }

    private static array $tableCache = [];

    private function hasTable(string $table): bool
    {
        $table = trim($table);
        if ($table === '') return false;

        if (array_key_exists($table, self::$tableCache)) return (bool)self::$tableCache[$table];

        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE :t");
            $stmt->execute([':t' => $table]);
            $exists = (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            $exists = false;
        }
        self::$tableCache[$table] = $exists;
        return $exists;
    }


    private function slugColumn(): ?string
    {
        // Prefer canonical name
        if ($this->hasColumn('news', 'slug')) return 'slug';

        // Common alternatives across forks
        foreach (['news_slug', 'slug_title', 'slug_name', 'title_slug', 'permalink', 'url_slug'] as $alt) {
            if ($this->hasColumn('news', $alt)) return $alt;
        }

        return null;
    }


private function looksUrlEncoded(string $s): bool
{
    return (bool)preg_match('/%[0-9A-Fa-f]{2}/', $s);
}

private function decodeIfEncoded(string $s): string
{
    $s = trim($s);
    if ($s === '') return '';
    return $this->looksUrlEncoded($s) ? rawurldecode($s) : $s;
}


private static ?string $statusMode = null;

private function statusPublishedPredicate(string $prefix): ?string
{
    if (!$this->hasColumn('news', 'status')) return null;

    if (self::$statusMode === null) {
        // Try to infer common conventions from existing rows
        try {
            $stmt = $this->pdo->query("SELECT DISTINCT status FROM news WHERE status IS NOT NULL LIMIT 30");
            $vals = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        } catch (Throwable) {
            $vals = [];
        }

        $norm = array_map(static function ($v) {
            $v = is_string($v) ? trim(strtolower($v)) : (string)$v;
            return $v;
        }, $vals);

        $hasPublished = in_array('published', $norm, true) || in_array('publish', $norm, true);
        $hasActive = in_array('active', $norm, true);
        $hasNumeric1 = in_array('1', $norm, true) || in_array('0', $norm, true);

        if ($hasPublished) self::$statusMode = 'text_published';
        elseif ($hasActive) self::$statusMode = 'text_active';
        elseif ($hasNumeric1) self::$statusMode = 'numeric_one';
        else self::$statusMode = 'unknown';
    }

    $col = "{$prefix}status";
    return match (self::$statusMode) {
        'text_published' => "({$col} = 'published' OR {$col} = 'publish')",
        'text_active'    => "({$col} = 'active')",
        'numeric_one'    => "({$col} = 1 OR {$col} = '1')",
        default          => null,
    };
}


/**
 * Build a schema-tolerant "published" predicate for the news table.
 * Supports:
 * - status column (varchar or int) with common values
 * - is_published / published / is_active / active flags (tinyint/bool)
 */
private function publishedWhere(string $alias = 'n'): string
{
    // IMPORTANT:
    // بعض قواعد البيانات تحتوي على أكثر من عمود يدل على النشر (مثلاً status + is_published).
    // في هذه الحالة لا يجوز ربطها بـ AND لأن أحد الأعمدة قد يبقى 0 رغم أن status = published.
    // لذلك نُعاملها كـ OR: يكفي أن يدل أي عمود على النشر.

    $clauses = [];

    $prefix = '';
    if ($alias !== '') {
        $prefix = rtrim($alias, '.') . '.';
    }

    // status column (text or numeric) — accept common conventions
    if ($this->hasColumn('news', 'status')) {
        $col = "{$prefix}status";
        $clauses[] = "({$col} = 'published' OR {$col} = 'publish' OR {$col} = 'active' OR {$col} = 1 OR {$col} = '1')";
    }

    // boolean-like flags
    foreach (['is_published', 'published', 'is_active', 'active'] as $flag) {
        if ($this->hasColumn('news', $flag)) {
            $col = "{$prefix}`{$flag}`";
            $clauses[] = "({$col} = 1 OR {$col} = '1' OR {$col} = 'yes' OR {$col} = 'true')";
        }
    }

    // If no publish columns exist, do not block results.
    $where = $clauses ? ('(' . implode(' OR ', $clauses) . ')') : '1=1';

    // Scheduling + soft delete (AND constraints)
    if ($this->hasColumn('news', 'publish_at')) {
        $col = "{$prefix}publish_at";
        $where .= " AND ({$col} IS NULL OR {$col} <= NOW())";
    }
    if ($this->hasColumn('news', 'unpublish_at')) {
        $col = "{$prefix}unpublish_at";
        $where .= " AND ({$col} IS NULL OR {$col} > NOW())";
    }
    if ($this->hasColumn('news', 'deleted_at')) {
        $col = "{$prefix}deleted_at";
        $where .= " AND ({$col} IS NULL)";
    }

    return $where;
}



    public function slugById(int $id): ?string
    {
        $col = $this->slugColumn();
        if ($col === null) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT `' . $col . '` FROM news WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $slug = trim((string)($stmt->fetchColumn() ?: ''));
            return $slug !== '' ? $slug : null;
        } catch (Throwable $e) {
            @error_log('[NewsService] slugById error: ' . $e->getMessage());
            return null;
        }
    }

    public function idBySlug(string $slug): ?int
    {
        $slug = trim($slug);
        if ($slug === '') return null;

        // Accept numeric slugs as ids
        $slugDec = $this->decodeIfEncoded($slug);
        if (ctype_digit($slugDec)) {
            return (int)$slugDec;
        }

        $slugEnc = rawurlencode($slugDec);

        // Prefer a dedicated mapping table (keeps old slugs working even if news.slug changes)
        if ($this->hasTable('news_slug_map')) {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT news_id FROM news_slug_map WHERE slug = :s OR slug = :se LIMIT 1"
                );
                $stmt->execute([':s' => $slugDec, ':se' => $slugEnc]);
                $id = (int)($stmt->fetchColumn() ?: 0);
                if ($id > 0) return $id;
            } catch (Throwable) {
                // ignore and fallback
            }
        }

        // Fallback: look up directly from news table if a slug column exists
        $col = $this->slugColumn();
        if ($col === null) return null;

        try {
            $stmt = $this->pdo->prepare('SELECT id FROM news WHERE `' . $col . '` = :s OR `' . $col . '` = :se LIMIT 1');
            $stmt->execute([':s' => $slugDec, ':se' => $slugEnc]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            return $id > 0 ? $id : null;
        } catch (Throwable) {
            return null;
        }
    }


    /** @return array<string,mixed>|null */
    public function findBySlugOrId(string $param, bool $preview = false): ?array
    {
        $param = trim($param);
        if ($param === '') return null;

        $isNumeric = ctype_digit($param);
        $id = $isNumeric ? (int)$param : 0;
        $slug = $isNumeric ? '' : $param;

        $newsHasStatus = $this->hasColumn('news', 'status');

        $slugCol = $this->slugColumn();

        // category name expression (schema-tolerant)
        $catNameExpr = 'c.name';
        if (!$this->hasColumn('categories', 'name')) {
            if ($this->hasColumn('categories', 'category_name')) $catNameExpr = 'c.category_name';
            elseif ($this->hasColumn('categories', 'cat_name')) $catNameExpr = 'c.cat_name';
            elseif ($this->hasColumn('categories', 'title')) $catNameExpr = 'c.title';
        }


// category slug expression (schema-tolerant)
$catSlugExpr = "c.slug";
if (!$this->hasColumn('categories', 'slug')) {
    if ($this->hasColumn('categories', 'category_slug')) $catSlugExpr = 'c.category_slug';
    elseif ($this->hasColumn('categories', 'slug_name')) $catSlugExpr = 'c.slug_name';
    elseif ($this->hasColumn('categories', 'permalink')) $catSlugExpr = 'c.permalink';
    else $catSlugExpr = "''";
}


        // IMPORTANT: when not numeric, do NOT reference :id (fix HY093)
        if ($slugCol === null) {
            // No slug column in this schema: only allow lookup by numeric id
            if (!$isNumeric) {
                return null;
            }
            $where = 'n.id = :id';
        } else {
            $where = $isNumeric ? '(n.id = :id OR n.`' . $slugCol . '` = :slug)' : '(n.`' . $slugCol . '` = :slug OR n.`' . $slugCol . '` = :slug_enc)';
        }
        if (!$preview) $where .= ' AND ' . $this->publishedWhere('n');

        $sql = "SELECT n.*, {$catNameExpr} AS category_name, {$catSlugExpr} AS category_slug
                FROM news n
                LEFT JOIN categories c ON c.id = n.category_id
                WHERE {$where}
                LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($sql);

            // For numeric param, also allow lookup by slug = id (legacy)
            if ($slugCol === null) {
                $params = [':id' => $id];
            } else {
                // For numeric param, also allow lookup by slug = id (legacy)
                $params = [':slug' => $isNumeric ? (string)$id : $slug];
                if (!$isNumeric) $params[':slug_enc'] = rawurlencode($slug);
                if ($isNumeric) $params[':id'] = $id;
            }

            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            @error_log('[NewsService] findBySlugOrId error: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<int,array<string,mixed>> */
    
/** @return array<int,array<string,mixed>> */
public function relatedByCategory(int $categoryId, int $excludeNewsId, int $limit = 6, bool $preview = false): array
{
    $limit = max(1, min(30, $limit));

    $dateCol = $this->hasColumn('news', 'published_at') ? 'published_at'
        : ($this->hasColumn('news', 'created_at') ? 'created_at'
        : ($this->hasColumn('news', 'date') ? 'date' : 'id'));

    $contentCol = $this->hasColumn('news', 'content') ? 'content'
        : ($this->hasColumn('news', 'body') ? 'body'
        : ($this->hasColumn('news', 'details') ? 'details' : null));

    $excerptCol = $this->hasColumn('news', 'excerpt') ? 'excerpt'
        : ($this->hasColumn('news', 'summary') ? 'summary'
        : ($this->hasColumn('news', 'short_description') ? 'short_description' : null));

    $picked = [];
    $out = [];

    try {
        // ===== 1) حسب الوسوم (إن وجدت الجداول) =====
        if ($excludeNewsId > 0 && $this->hasTable('news_tags') && $this->hasTable('tags')) {
            $tagIds = [];
            $st = $this->pdo->prepare("SELECT tag_id FROM news_tags WHERE news_id = :nid");
            $st->execute([':nid' => $excludeNewsId]);
            $tagIds = array_values(array_unique(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: [])));

            if (!empty($tagIds)) {
                $in = implode(',', array_fill(0, count($tagIds), '?'));

                $where = "n.id != ? AND nt.tag_id IN ($in)";
                if (!$preview) $where .= " AND " . $this->publishedWhere('n');

                $sql = "
                    SELECT n.id, n.title, n.slug, n.featured_image, n.{$dateCol} AS published_at, " . ($excerptCol ? "n.{$excerptCol} AS excerpt," : "'' AS excerpt,") . "
                           COUNT(*) AS match_score
                    FROM news n
                    INNER JOIN news_tags nt ON nt.news_id = n.id
                    WHERE {$where}
                    GROUP BY n.id
                    ORDER BY match_score DESC, n.{$dateCol} DESC
                    LIMIT {$limit}
                ";

                $params = array_merge([$excludeNewsId], $tagIds);
                $stRel = $this->pdo->prepare($sql);
                $stRel->execute($params);
                $rows = $stRel->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $rid = (int)($r['id'] ?? 0);
                    if ($rid <= 0 || $rid === $excludeNewsId) continue;
                    if (isset($picked[$rid])) continue;
                    $picked[$rid] = true;
                    $out[] = $r;
                    if (count($out) >= $limit) return $out;
                }
            }
        }

        // ===== 2) حسب القسم =====
        if ($categoryId > 0 && count($out) < $limit) {
            $need = $limit - count($out);

            $where = "category_id = :cid AND id != :nid";
            if (!$preview) $where .= " AND " . $this->publishedWhere('');

            $sql = "SELECT id, title, slug, featured_image, {$dateCol} AS published_at, " . ($excerptCol ? "{$excerptCol} AS excerpt" : "'' AS excerpt") . "
                    FROM news
                    WHERE {$where}
                    ORDER BY {$dateCol} DESC
                    LIMIT :lim";
            $st = $this->pdo->prepare($sql);
            $st->bindValue(':cid', $categoryId, PDO::PARAM_INT);
            $st->bindValue(':nid', $excludeNewsId, PDO::PARAM_INT);
            $st->bindValue(':lim', $need, PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $rid = (int)($r['id'] ?? 0);
                if ($rid <= 0 || $rid === $excludeNewsId) continue;
                if (isset($picked[$rid])) continue;
                $picked[$rid] = true;
                $out[] = $r;
                if (count($out) >= $limit) return $out;
            }
        }

        // ===== 3) تشابه المحتوى (TF‑IDF مبسّط) لملء النقص =====
        if ($excludeNewsId > 0 && $contentCol !== null && count($out) < $limit) {
            $need = $limit - count($out);

            // اجلب نص الخبر الحالي
            $whereCur = "id = :nid";
            if (!$preview) $whereCur .= " AND " . $this->publishedWhere('');
            $sqlCur = "SELECT id, title, " . ($excerptCol ? "{$excerptCol} AS excerpt," : "'' AS excerpt,") . " {$contentCol} AS content
                       FROM news WHERE {$whereCur} LIMIT 1";
            $stCur = $this->pdo->prepare($sqlCur);
            $stCur->bindValue(':nid', $excludeNewsId, PDO::PARAM_INT);
            $stCur->execute();
            $cur = $stCur->fetch(PDO::FETCH_ASSOC) ?: null;
            $baseText = '';
            if ($cur) {
                $baseText = (string)($cur['title'] ?? '') . ' ' . (string)($cur['excerpt'] ?? '') . ' ' . (string)($cur['content'] ?? '');
            }

            // candidates (آخر أخبار + أولوية لنفس القسم)
            $where = "id != :nid";
            if (!$preview) $where .= " AND " . $this->publishedWhere('');
            $order = ($categoryId > 0) ? " (category_id = :cid) DESC, {$dateCol} DESC" : "{$dateCol} DESC";

            $sqlCand = "SELECT id, title, slug, featured_image, {$dateCol} AS published_at, " .
                       ($excerptCol ? "{$excerptCol} AS excerpt," : "'' AS excerpt,") .
                       " {$contentCol} AS content, category_id
                       FROM news
                       WHERE {$where}
                       ORDER BY {$order}
                       LIMIT 60";
            $stC = $this->pdo->prepare($sqlCand);
            $stC->bindValue(':nid', $excludeNewsId, PDO::PARAM_INT);
            if ($categoryId > 0) $stC->bindValue(':cid', $categoryId, PDO::PARAM_INT);
            $stC->execute();
            $cands = $stC->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // tokenization helpers
            $stop = array_fill_keys([
                // AR
                'و','في','على','الى','إلى','من','عن','أن','إن','كان','كانت','كما','هذا','هذه','ذلك','تلك','هناك','هنا','ثم','أو',
                'مع','كل','قد','لم','لن','له','لها','ب','بـ','بين','بعد','قبل','حتى','عند','أي','أيضا','أيضاً','ما','ماذا',
                // EN
                'the','and','or','to','in','of','a','an','is','are','was','were','for','on','with','as','by','at','it','this','that'
            ], true);

            $tokenize = function (string $text) use ($stop): array {
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = strip_tags($text);
                $text = mb_strtolower($text, 'UTF-8');
                $text = preg_replace('~[^\p{L}\p{N}]+~u', ' ', $text);
                $parts = preg_split('~\s+~u', trim($text)) ?: [];
                $out = [];
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p === '') continue;
                    if (mb_strlen($p, 'UTF-8') < 2) continue;
                    if (isset($stop[$p])) continue;
                    $out[] = $p;
                }
                return $out;
            };

            $tf = function (array $tokens): array {
                $c = [];
                foreach ($tokens as $t) {
                    $c[$t] = ($c[$t] ?? 0) + 1;
                }
                $n = max(1, count($tokens));
                foreach ($c as $k => $v) {
                    $c[$k] = $v / $n;
                }
                return $c;
            };

            $baseTokens = $tokenize($baseText);
            $baseTf = $tf($baseTokens);

            // build DF
            $docsTf = [];
            $df = [];
            $N = 0;

            foreach ($cands as $row) {
                $rid = (int)($row['id'] ?? 0);
                if ($rid <= 0 || $rid === $excludeNewsId) continue;
                if (isset($picked[$rid])) continue;

                $txt = (string)($row['title'] ?? '') . ' ' . (string)($row['excerpt'] ?? '') . ' ' . (string)($row['content'] ?? '');
                $tokens = $tokenize($txt);
                if (empty($tokens)) continue;

                $N++;
                $tfi = $tf($tokens);
                $docsTf[$rid] = ['tf' => $tfi, 'row' => $row];

                foreach (array_keys($tfi) as $term) {
                    $df[$term] = ($df[$term] ?? 0) + 1;
                }
            }

            if ($N > 0 && !empty($baseTf)) {
                // precompute base tfidf
                $idf = [];
                foreach ($df as $term => $d) {
                    $idf[$term] = log(($N + 1) / ($d + 1)) + 1.0;
                }

                $baseVec = [];
                $baseNorm = 0.0;
                foreach ($baseTf as $term => $val) {
                    $w = $val * ($idf[$term] ?? 1.0);
                    $baseVec[$term] = $w;
                    $baseNorm += $w * $w;
                }
                $baseNorm = sqrt(max(1e-9, $baseNorm));

                $scores = [];
                foreach ($docsTf as $rid => $data) {
                    $vec = $data['tf'];
                    $dot = 0.0;
                    $norm = 0.0;
                    foreach ($vec as $term => $val) {
                        $w = $val * ($idf[$term] ?? 1.0);
                        $norm += $w * $w;
                        if (isset($baseVec[$term])) {
                            $dot += $w * $baseVec[$term];
                        }
                    }
                    $norm = sqrt(max(1e-9, $norm));
                    $sim = ($dot > 0.0) ? ($dot / ($baseNorm * $norm)) : 0.0;
                    if ($sim > 0.0) {
                        $scores[$rid] = $sim;
                    }
                }

                if (!empty($scores)) {
                    arsort($scores); // high -> low
                    foreach ($scores as $rid => $sim) {
                        if (count($out) >= $limit) break;
                        if (!isset($docsTf[$rid])) continue;
                        $picked[$rid] = true;
                        $row = $docsTf[$rid]['row'];
                        unset($row['content']); // لا حاجة لإرساله للواجهة
                        $out[] = $row;
                        if (count($out) >= $limit) break;
                        $need--;
                        if ($need <= 0) break;
                    }
                }
            }
        }

        return $out;

    } catch (Throwable $e) {
        @error_log('[NewsService] relatedByCategory error: ' . $e->getMessage());
        return $out;
    }
}



    public function latest(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $newsHasStatus = $this->hasColumn('news', 'status');

        $slugCol = $this->slugColumn();

        $dateCol = $this->hasColumn('news', 'published_at') ? 'published_at'
            : ($this->hasColumn('news', 'created_at') ? 'created_at'
            : ($this->hasColumn('news', 'date') ? 'date' : 'id'));

        $where = "WHERE " . $this->publishedWhere('');
        $sql = "SELECT * FROM news {$where} ORDER BY {$dateCol} DESC LIMIT :lim";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[NewsService] latest error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Most-read articles (views-based) used by NewsController sidebar.
     * If the schema doesn't have a views column, falls back to latest().
     *
     * @return array<int,array<string,mixed>>
     */
    public function mostRead(int $limit = 8, string $period = 'month'): array
    {
        $limit = max(1, min(50, $limit));

        $viewsCol = $this->hasColumn('news', 'views') ? 'views'
            : ($this->hasColumn('news', 'view_count') ? 'view_count' : null);

        if ($viewsCol === null) {
            return $this->latest($limit);
        }

        $newsHasStatus = $this->hasColumn('news', 'status');

        $slugCol = $this->slugColumn();

        $dateCol = $this->hasColumn('news', 'published_at') ? 'published_at'
            : ($this->hasColumn('news', 'created_at') ? 'created_at'
            : ($this->hasColumn('news', 'date') ? 'date' : null));

        $where = [];
         $where[] = $this->publishedWhere('');

        if ($dateCol !== null) {
            $days = match ($period) {
                'today' => 1,
                'week'  => 7,
                'month' => 30,
                'year'  => 365,
                default => 0,
            };
            if ($days > 0) {
                $where[] = "{$dateCol} >= (NOW() - INTERVAL {$days} DAY)";
            }
        }

        $sql = "SELECT * FROM news";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$viewsCol} DESC";
        if ($dateCol !== null) $sql .= ", {$dateCol} DESC";
        $sql .= " LIMIT :lim";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[NewsService] mostRead error: ' . $e->getMessage());
            return $this->latest($limit);
        }
    }

    /**
     * Increment views counter for a news item.
     * - If no views column exists, it becomes a no-op.
     * - If preview is true, caller should skip calling this (but we keep it safe anyway).
     */
    public function incrementViews(int $newsId): void
    {
        if ($newsId <= 0) return;

        $viewsCol = $this->hasColumn('news', 'views') ? 'views'
            : ($this->hasColumn('news', 'view_count') ? 'view_count' : null);

        if ($viewsCol === null) return;

        try {
            $sql = "UPDATE news SET {$viewsCol} = COALESCE({$viewsCol}, 0) + 1 WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $newsId]);
        } catch (Throwable $e) {
            @error_log('[NewsService] incrementViews error: ' . $e->getMessage());
        }
    }

    /**
     * Diagnostic: helps confirm you deployed the correct file.
     */
    public function __version(): string
    {
        return 'NewsService v5 (mostRead+incrementViews) 2025-12-23';
    }

/**
 * البحث عن الأخبار (يُستخدم في /search).
 *
 * @param string $q
 * @param int $page
 * @param int $perPage
 * @param array<string, mixed> $filters
 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
 */
public function search(string $q, int $page = 1, int $perPage = 12, array $opts = []): array
{
    $q = trim((string)$q);
    $page = max(1, (int)$page);
    $perPage = max(1, min(100, (int)$perPage));
    $offset = ($page - 1) * $perPage;

    $empty = static function(int $page, int $perPage): array {
        return [
            'items' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 0,
            'counts' => ['news'=>0,'pages'=>0,'authors'=>0],
        ];
    };

    if ($q === '') return $empty($page, $perPage);

    $qNorm = preg_replace('~\s+~u', ' ', $q);
    $tokens = preg_split('~\s+~u', (string)$qNorm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$tokens) return $empty($page, $perPage);

    $type = isset($opts['type']) ? trim((string)$opts['type']) : 'all'; // all|news|opinion|page|author
    $match = isset($opts['match']) ? trim((string)$opts['match']) : 'all'; // all|any
    $categoryId = isset($opts['category_id']) ? (int)$opts['category_id'] : 0;
    $dateFrom = isset($opts['date_from']) ? trim((string)$opts['date_from']) : '';
    $dateTo   = isset($opts['date_to']) ? trim((string)$opts['date_to']) : '';
    $debug = !empty($_GET['debug_search'] ?? null);

    $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $likeValue = static function (string $tok): string {
        $tok = addcslashes($tok, "%_\\");
        return '%' . $tok . '%';
    };

    $buildTokenWhere = static function (string $alias, array $cols, array $tokens, string $match, array &$binds) use ($likeValue): string {
        $glue = ($match === 'any') ? ' OR ' : ' AND ';
        $parts = [];
        foreach ($tokens as $tok) {
            $or = [];
            foreach ($cols as $col) {
                $or[] = "$alias.$col LIKE ? ESCAPE '\\\\'";
                $binds[] = $likeValue((string)$tok);
            }
            $parts[] = '(' . implode(' OR ', $or) . ')';
        }
        return '(' . implode($glue, $parts) . ')';
    };

	    // Ranking: compute a lightweight score in SQL (no extra placeholders to avoid bind mismatches)
	    $scoreExpr = function (string $alias, array $fieldWeights) use ($tokens): string {
	        $parts = [];
	        foreach ($tokens as $tok) {
	            $tok = (string)$tok;
	            $tok = addcslashes($tok, "%_\\\\");
	            $like = '%' . $tok . '%';
	            $qLike = $this->pdo->quote($like);
	            foreach ($fieldWeights as $field => $w) {
	                $w = (int)$w;
	                $parts[] = "(CASE WHEN $alias.$field LIKE $qLike ESCAPE '\\\\' THEN $w ELSE 0 END)";
	            }
	        }
	        if (!$parts) return '0';
	        return '(' . implode(' + ', $parts) . ')';
	    };

    $unionSqlParts = [];
    $unionBindsParts = [];

    // NEWS
    if ($type === 'all' || $type === 'news' || $type === 'opinion') {
        $binds = [];
        $where = [];
        $where[] = "n.status = 'published'";
        $where[] = "n.deleted_at IS NULL";
        $where[] = "(n.publish_at IS NULL OR n.publish_at <= ?)";
        $binds[] = $now;
        $where[] = "(n.unpublish_at IS NULL OR n.unpublish_at > ?)";
        $binds[] = $now;

        if ($type === 'opinion') $where[] = "n.opinion_author_id IS NOT NULL";
        if ($type === 'news') $where[] = "(n.opinion_author_id IS NULL OR n.opinion_author_id = 0)";

        if ($categoryId > 0) { $where[] = "n.category_id = ?"; $binds[] = $categoryId; }

        if ($dateFrom !== '') { $where[] = "COALESCE(n.publish_at, n.created_at) >= ?"; $binds[] = $dateFrom . ' 00:00:00'; }
        if ($dateTo   !== '') { $where[] = "COALESCE(n.publish_at, n.created_at) <= ?"; $binds[] = $dateTo   . ' 23:59:59'; }

        $where[] = $buildTokenWhere('n', ['title','content','excerpt','slug','tags'], $tokens, $match, $binds);
	        $scoreNews = $scoreExpr('n', ['title'=>60,'slug'=>35,'excerpt'=>18,'content'=>6,'tags'=>10]);

        $unionSqlParts[] = trim("
            SELECT
                'news' AS kind,
                n.id AS id,
                n.title AS title,
                n.slug AS slug,
                COALESCE(n.excerpt, '') AS excerpt,
                COALESCE(n.publish_at, n.created_at) AS created_at,
                COALESCE(NULLIF(n.featured_image,''), NULLIF(n.image_path,''), NULLIF(n.image,'')) AS image,
                c.slug AS category_slug,
                n.is_breaking AS is_breaking,
                n.is_featured AS is_featured,
                0 AS is_exclusive,
	                CONCAT('/news/id/', n.id) AS url,
	                $scoreNews AS score
            FROM news n
            LEFT JOIN categories c ON c.id = n.category_id
            WHERE " . implode(' AND ', $where) . "
        ");
        $unionBindsParts[] = $binds;
    }

    // PAGES
    if ($type === 'all' || $type === 'page') {
        $binds = [];
        $where = [];
        $where[] = "p.status = 'published'";
        $where[] = $buildTokenWhere('p', ['title','content','slug'], $tokens, $match, $binds);
	        $scorePage = $scoreExpr('p', ['title'=>60,'slug'=>30,'content'=>10]);

        $unionSqlParts[] = trim("
            SELECT
                'page' AS kind,
                p.id AS id,
                p.title AS title,
                p.slug AS slug,
                '' AS excerpt,
                p.updated_at AS created_at,
                NULL AS image,
                NULL AS category_slug,
                0 AS is_breaking,
                0 AS is_featured,
                0 AS is_exclusive,
	                CONCAT('/page/', p.slug) AS url,
	                $scorePage AS score
            FROM pages p
            WHERE " . implode(' AND ', $where) . "
        ");
        $unionBindsParts[] = $binds;
    }

    // AUTHORS
    if ($type === 'all' || $type === 'author') {
        $binds = [];
        $where = [];
        $where[] = "a.is_active = 1";
        $where[] = $buildTokenWhere('a', ['name','slug','bio','specialization'], $tokens, $match, $binds);
	        $scoreAuthor = $scoreExpr('a', ['name'=>60,'slug'=>30,'bio'=>10,'specialization'=>10]);

        $unionSqlParts[] = trim("
            SELECT
                'author' AS kind,
                a.id AS id,
                a.name AS title,
                a.slug AS slug,
                '' AS excerpt,
                a.updated_at AS created_at,
                a.avatar AS image,
                NULL AS category_slug,
                0 AS is_breaking,
                0 AS is_featured,
                0 AS is_exclusive,
	                CONCAT('/opinion_author.php?slug=', a.slug) AS url,
	                $scoreAuthor AS score
            FROM opinion_authors a
            WHERE " . implode(' AND ', $where) . "
        ");
        $unionBindsParts[] = $binds;
    }

    if (!$unionSqlParts) return $empty($page, $perPage);

    $unionSql = implode("\nUNION ALL\n", $unionSqlParts);
    $bindsAll = [];
    foreach ($unionBindsParts as $b) { foreach ($b as $v) { $bindsAll[] = $v; } }

    try {
        $counts = ['news'=>0,'pages'=>0,'authors'=>0];

        $groupSql = "SELECT kind, COUNT(*) AS cnt FROM ($unionSql) u GROUP BY kind";
        $stG = $this->pdo->prepare($groupSql);
        $stG->execute($bindsAll);
        while ($row = $stG->fetch(\PDO::FETCH_ASSOC)) {
            $k = (string)($row['kind'] ?? '');
            $cnt = (int)($row['cnt'] ?? 0);
            if ($k === 'news') $counts['news'] = $cnt;
            elseif ($k === 'page') $counts['pages'] = $cnt;
            elseif ($k === 'author') $counts['authors'] = $cnt;
        }

        $countSql = "SELECT COUNT(*) FROM ($unionSql) x";
        $stC = $this->pdo->prepare($countSql);
        $stC->execute($bindsAll);
        $total = (int)$stC->fetchColumn();

	        $dataSql = "SELECT * FROM ($unionSql) x ORDER BY score DESC, created_at DESC LIMIT ? OFFSET ?";
        $bindsData = $bindsAll;
        $bindsData[] = $perPage;
        $bindsData[] = $offset;

        $st = $this->pdo->prepare($dataSql);
        $st->execute($bindsData);
        $items = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if ($debug) {
            $db = '';
            try { $db = (string)($this->pdo->query('SELECT DATABASE()')->fetchColumn() ?: ''); } catch (\Throwable $e) {}
            error_log('[NewsService::search][diag] db=' . $db
                . ' total=' . $total
                . ' counts=' . json_encode($counts, JSON_UNESCAPED_UNICODE)
                . ' bind_base=' . count($bindsAll)
                . ' bind_data=' . count($bindsData)
            );
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
            'counts' => $counts,
        ];
    } catch (\Throwable $e) {
        error_log('[NewsService::search] ERROR: ' . $e->getMessage());
        return $empty($page, $perPage);
    }
}



    /**
     * Archive listing (used by /archive).
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function archive(int $page = 1, int $perPage = 12, ?int $year = null, ?int $month = null): array
    {
        $page = max(1, (int)$page);
        $perPage = max(1, min(100, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        // choose a date column if available
        $dateCol = $this->hasColumn('news', 'published_at') ? 'published_at'
            : ($this->hasColumn('news', 'created_at') ? 'created_at'
            : ($this->hasColumn('news', 'date') ? 'date' : null));

        $where = [];
        $where[] = $this->publishedWhere('n');

        $bind = [];
        if ($dateCol !== null) {
            if ($year !== null) {
                $where[] = "YEAR(n.`{$dateCol}`) = :y";
                $bind[':y'] = (int)$year;
            }
            if ($month !== null) {
                $where[] = "MONTH(n.`{$dateCol}`) = :m";
                $bind[':m'] = (int)$month;
            }
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Count
        $total = 0;
        try {
            $st = $this->pdo->prepare("SELECT COUNT(*) FROM news n {$whereSql}");
            foreach ($bind as $k => $v) {
                $st->bindValue($k, $v, PDO::PARAM_INT);
            }
            $st->execute();
            $total = (int)($st->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            @error_log('[NewsService] archive count error: ' . $e->getMessage());
            $total = 0;
        }

        $orderCol = $dateCol !== null ? "n.`{$dateCol}`" : "n.id";
        $dataSql = "SELECT n.* FROM news n {$whereSql} ORDER BY {$orderCol} DESC, n.id DESC LIMIT :lim OFFSET :off";

        try {
            $st = $this->pdo->prepare($dataSql);
            foreach ($bind as $k => $v) {
                $st->bindValue($k, $v, PDO::PARAM_INT);
            }
            $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
            $st->bindValue(':off', $offset, PDO::PARAM_INT);
            $st->execute();
            $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[NewsService] archive list error: ' . $e->getMessage());
            $items = [];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage)),
        ];
    }

}