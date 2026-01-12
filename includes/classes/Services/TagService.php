<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Throwable;

final class TagService
{
    /** @var PDO */
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{id:int,name:string,slug:string,description:string}|null
     */
    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        try {
            if (!$this->tableExists('tags')) {
                return null;
            }

            $sql = 'SELECT id, name, slug, description FROM tags WHERE slug = :slug LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$row) {
                return null;
            }

            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'slug' => (string)($row['slug'] ?? ''),
                'description' => (string)($row['description'] ?? ''),
            ];
        } catch (Throwable $e) {
            @error_log('[TagService] findBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array{items:array<int,array<string,mixed>>, total:int, total_pages:int}
     */
    public function listNews(int $tagId, int $page = 1, int $perPage = 12): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(60, $perPage));

        if ($tagId <= 0) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1];
        }

        try {
            if (!$this->tableExists('news') || !$this->tableExists('news_tags')) {
                return ['items' => [], 'total' => 0, 'total_pages' => 1];
            }

            $newsHasStatus = $this->hasColumn('news', 'status');

            $dateCol = $this->hasColumn('news', 'publish_at') ? 'publish_at'
                : ($this->hasColumn('news', 'published_at') ? 'published_at'
                    : ($this->hasColumn('news', 'created_at') ? 'created_at' : 'id'));

            $imgCol = $this->hasColumn('news', 'featured_image') ? 'featured_image'
                : ($this->hasColumn('news', 'image') ? 'image' : null);

            $excerptCol = $this->hasColumn('news', 'excerpt') ? 'excerpt'
                : ($this->hasColumn('news', 'summary') ? 'summary' : null);

            $where = 'nt.tag_id = :tid';
            if ($newsHasStatus) {
                $where .= " AND (n.status IN ('published','publish','active','approved') OR n.status = 1)";
            }

            $cnt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM news n INNER JOIN news_tags nt ON nt.news_id = n.id WHERE {$where}"
            );
            $cnt->execute([':tid' => $tagId]);
            $total = (int)$cnt->fetchColumn();

            $totalPages = max(1, (int)ceil($total / $perPage));
            $offset = ($page - 1) * $perPage;

            $select = "n.id, n.slug, n.title, {$dateCol} AS publish_at";
            if ($imgCol) {
                $select .= ", n.{$imgCol} AS featured_image";
            }
            if ($excerptCol) {
                $select .= ", n.{$excerptCol} AS excerpt";
            }

            $sql = "SELECT {$select} FROM news n INNER JOIN news_tags nt ON nt.news_id = n.id "
                . "WHERE {$where} ORDER BY {$dateCol} DESC, n.id DESC LIMIT :lim OFFSET :off";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':tid', $tagId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return ['items' => $items, 'total' => $total, 'total_pages' => $totalPages];
        } catch (Throwable $e) {
            @error_log('[TagService] listNews error: ' . $e->getMessage());
            return ['items' => [], 'total' => 0, 'total_pages' => 1];
        }
    }

    /**
     * @return array<int,array{id:int,name:string,slug:string}>
     */
    public function forNews(int $newsId): array
    {
        if ($newsId <= 0) {
            return [];
        }

        try {
            if (!$this->tableExists('tags') || !$this->tableExists('news_tags')) {
                return [];
            }

            $stmt = $this->pdo->prepare(
                "SELECT t.id, t.name, t.slug\n"
                . "FROM tags t\n"
                . "INNER JOIN news_tags nt ON nt.tag_id = t.id\n"
                . "WHERE nt.news_id = :nid\n"
                . "ORDER BY t.name ASC"
            );
            $stmt->execute([':nid' => $newsId]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id' => (int)($r['id'] ?? 0),
                    'name' => (string)($r['name'] ?? ''),
                    'slug' => (string)($r['slug'] ?? ''),
                ];
            }
            return $out;
        } catch (Throwable $e) {
            @error_log('[TagService] forNews error: ' . $e->getMessage());
            return [];
        }
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return (bool)$cache[$table];
        }

        try {
            $stmt = $this->pdo->prepare('SHOW TABLES LIKE :t');
            $stmt->execute([':t' => $table]);
            $exists = (bool)$stmt->fetchColumn();
            $cache[$table] = $exists;
            return $exists;
        } catch (Throwable $e) {
            $cache[$table] = false;
            return false;
        }
    }

    /**
     * @return array<int,string>
     */
    private function columns(string $table): array
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $this->pdo->query('SHOW COLUMNS FROM ' . preg_replace('~[^a-zA-Z0-9_]+~', '', $table));
            $cols = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: []) : [];
            $cache[$table] = $cols;
            return $cols;
        } catch (Throwable $e) {
            $cache[$table] = [];
            return [];
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return (bool)$cache[$key];
        }

        $cols = $this->columns($table);
        $exists = in_array($column, $cols, true);
        $cache[$key] = $exists;
        return $exists;
    }
}
