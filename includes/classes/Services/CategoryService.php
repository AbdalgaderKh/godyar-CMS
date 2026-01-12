<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Throwable;

/**
 * CategoryService (schema-tolerant)
 * Provides the methods used by CategoryController + NewsController:
 * - findBySlug
 * - headerCategories
 * - listPublishedNews
 * - subcategories
 * - siblingCategories (accepts NULL parent_id)
 */
final class CategoryService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private static array $colCache = [];

    

    private static array $tableCache = [];

    private function hasTable(string $table): bool
    {
        $table = trim($table);
        if ($table === '') return false;

        if (array_key_exists($table, self::$tableCache)) {
            return (bool) self::$tableCache[$table];
        }

        $exists = false;
        try {
            // Works on MySQL/MariaDB
            $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
            $stmt->execute([':t' => $table]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            // Fallback in restricted environments
            try {
                $stmt2 = $this->pdo->prepare("SHOW TABLES LIKE :t");
                $stmt2->execute([':t' => $table]);
                $exists = (bool) $stmt2->fetchColumn();
            } catch (Throwable) {
                $exists = false;
            }
        }

        self::$tableCache[$table] = $exists;
        return $exists;
    }
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

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') return null;

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            @error_log('[CategoryService] findBySlug error: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id <= 0) return null;
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            @error_log('[CategoryService] findById error: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function headerCategories(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));

        $orderBy = $this->hasColumn('categories', 'sort_order')
            ? 'sort_order ASC, id ASC'
            : ($this->hasColumn('categories', 'name') ? 'name ASC, id ASC' : 'id ASC');

        $where = [];
        if ($this->hasColumn('categories', 'parent_id')) {
            $where[] = "(parent_id IS NULL OR parent_id = 0)";
        }
        if ($this->hasColumn('categories', 'is_active')) {
            $where[] = "(is_active = 1 OR is_active IS NULL)";
        }

        $sql = "SELECT * FROM categories";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$orderBy} LIMIT {$limit}";

        try {
            $stmt = $this->pdo->query($sql);
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            @error_log('[CategoryService] headerCategories error: ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function subcategories(int $categoryId, int $limit = 10): array
    {
        if (!$this->hasColumn('categories', 'parent_id')) return [];
        $limit = max(1, min(50, $limit));

        $orderBy = $this->hasColumn('categories', 'sort_order')
            ? 'sort_order ASC, id ASC'
            : ($this->hasColumn('categories', 'name') ? 'name ASC, id ASC' : 'id ASC');

        $sql = "SELECT * FROM categories WHERE parent_id = :pid";
        if ($this->hasColumn('categories', 'is_active')) {
            $sql .= " AND (is_active = 1 OR is_active IS NULL)";
        }
        $sql .= " ORDER BY {$orderBy} LIMIT :lim";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':pid', $categoryId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[CategoryService] subcategories error: ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function siblingCategories(?int $parentId, int $currentId, int $limit = 8): array
    {
        if (!$this->hasColumn('categories', 'parent_id')) return [];
        $limit = max(1, min(50, $limit));

        // normalize NULL/0 => top-level
        if ($parentId === 0) $parentId = null;

        $orderBy = $this->hasColumn('categories', 'sort_order')
            ? 'sort_order ASC, id ASC'
            : ($this->hasColumn('categories', 'name') ? 'name ASC, id ASC' : 'id ASC');

        $sql = "SELECT * FROM categories WHERE id != :cid";
        if ($parentId === null) {
            $sql .= " AND (parent_id IS NULL OR parent_id = 0)";
        } else {
            $sql .= " AND parent_id = :pid";
        }
        if ($this->hasColumn('categories', 'is_active')) {
            $sql .= " AND (is_active = 1 OR is_active IS NULL)";
        }
        $sql .= " ORDER BY {$orderBy} LIMIT :lim";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':cid', $currentId, PDO::PARAM_INT);
            if ($parentId !== null) $stmt->bindValue(':pid', $parentId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            @error_log('[CategoryService] siblingCategories error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * List news in a category with pagination and optional sort/period.
     * This is intentionally tolerant of schema differences (status column optional).
     *
     * @return array{items: array<int,array<string,mixed>>, total: int}
     */

/**
 * إرجاع جميع الأقسام (لاستخدامها في قوائم الفلاتر مثل صفحة البحث).
 * يرجّح الأقسام النشطة فقط إذا كان هناك عمود is_active / status.
 *
 * @return array<int, array<string, mixed>>
 */
public function listAll(): array
{
    $table = 'categories';

    // إن لم يوجد جدول الأقسام (حالة نادرة) أرجع مصفوفة فارغة
    if (!$this->hasTable($table)) {
        return [];
    }

    $cols = ['id', 'name', 'slug'];
    if ($this->hasColumn($table, 'parent_id')) {
        $cols[] = 'parent_id';
    }
    if ($this->hasColumn($table, 'sort_order')) {
        $cols[] = 'sort_order';
    }

    $select = implode(', ', array_map(static fn($c) => "`{$c}`", array_unique($cols)));

    $where = [];
    if ($this->hasColumn($table, 'is_active')) {
        $where[] = "(`is_active` = 1 OR `is_active` = '1' OR `is_active` IS NULL)";
    }
    if ($this->hasColumn($table, 'status')) {
        // بعض النسخ تستخدم status = active
        $where[] = "`status` = 'active'";
    }

    $sql = "SELECT {$select} FROM `{$table}`";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    if ($this->hasColumn($table, 'sort_order')) {
        $sql .= " ORDER BY `sort_order` ASC, `id` ASC";
    } else {
        $sql .= " ORDER BY `id` ASC";
    }

    try {
        $stmt = $this->pdo->query($sql);
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        return [];
    }
}

    public function listPublishedNews(int $categoryId, int $page = 1, int $perPage = 12, string $sort = 'latest', string $period = 'all'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $newsHasStatus = $this->hasColumn('news', 'status');
        $newsHasViews  = $this->hasColumn('news', 'views') || $this->hasColumn('news', 'view_count');

        $dateCol = $this->hasColumn('news', 'published_at') ? 'published_at'
            : ($this->hasColumn('news', 'created_at') ? 'created_at'
            : ($this->hasColumn('news', 'date') ? 'date' : 'id'));

        $viewsCol = $this->hasColumn('news', 'views') ? 'views'
            : ($this->hasColumn('news', 'view_count') ? 'view_count' : $dateCol);

        $orderBy = ($sort === 'popular' && $newsHasViews) ? "{$viewsCol} DESC, {$dateCol} DESC" : "{$dateCol} DESC";

        $where = "category_id = :cid";
        $params = [':cid' => $categoryId];

        if ($newsHasStatus) {
            $where .= " AND status = 'published'";
        }

        // period filter (best-effort)
        if ($period !== 'all' && $dateCol !== 'id') {
            $days = 0;
switch ($period) {
    case 'today':
        $days = 1;
        break;
    case 'week':
        $days = 7;
        break;
    case 'month':
        $days = 30;
        break;
    default:
        $days = 0;
        break;
}
            if ($days > 0) {
                $where .= " AND {$dateCol} >= (NOW() - INTERVAL {$days} DAY)";
            }
        }

        try {
            $countSql = "SELECT COUNT(*) FROM news WHERE {$where}";
            $stmt = $this->pdo->prepare($countSql);
            $stmt->execute($params);
            $total = (int)($stmt->fetchColumn() ?: 0);

            $sql = "SELECT * FROM news WHERE {$where} ORDER BY {$orderBy} LIMIT :lim OFFSET :off";
            $stmt2 = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) $stmt2->bindValue($k, $v, PDO::PARAM_INT);
            $stmt2->bindValue(':lim', $perPage, PDO::PARAM_INT);
            $stmt2->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt2->execute();
            $items = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return ['items' => $items, 'total' => $total];
        } catch (Throwable $e) {
            @error_log('[CategoryService] listPublishedNews error: ' . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }
}
