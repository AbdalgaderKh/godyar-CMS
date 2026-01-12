<?php
// permissive bootstrap: allow direct access by bootstrapping app if needed
if (!defined('APP_BOOT')) {
    define('APP_BOOT', true);
    $__d = __DIR__;
    $__root = $__d;
    for ($__i=0; $__i<6; $__i++) {
        if (file_exists($__root . '/vendor/autoload.php') || file_exists($__root . '/index.php')) break;
        $__root = dirname($__root);
    }
    if (file_exists($__root . '/vendor/autoload.php')) {
        require $__root . '/vendor/autoload.php';
        if (class_exists('App\\Core\\App')) { App\Core\App::boot($__root); }
    }
    if (!defined('BASE_PATH')) define('BASE_PATH', $__root);
}
?>
defined('ABSPATH') or 

class Category {
    private $db;
    private $table = 'categories';
    
    public function __construct() {
        global $database;
        $this->db = $database;
    }
    
    // إنشاء تصنيف جديد
    public function createCategory($categoryData) {
        $required = ['name', 'slug'];
        foreach ($required as $field) {
            if (empty($categoryData[$field])) {
                throw new Exception("حقل {$field} مطلوب");
            }
        }
        
        // التحقق من أن slug غير مكرر
        if ($this->slugExists($categoryData['slug'])) {
            throw new Exception('الرابط مستخدم مسبقاً');
        }
        
        $sql = "INSERT INTO {$this->table} (name, slug, description, parent_id, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $params = [
            Security::cleanInput($categoryData['name']),
            Security::cleanInput($categoryData['slug']),
            Security::cleanInput($categoryData['description'] ?? ''),
            intval($categoryData['parent_id'] ?? 0)
        ];
        
        $result = $this->db->query($sql, $params);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    // تحديث التصنيف
    public function updateCategory($categoryId, $categoryData) {
        $allowedFields = ['name', 'slug', 'description', 'parent_id'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($categoryData[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = Security::cleanInput($categoryData[$field]);
            }
        }
        
        if (empty($updates)) {
            throw new Exception('لا توجد بيانات لتحديثها');
        }
        
        $params[] = $categoryId;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
        
        return $this->db->query($sql, $params);
    }
    
    // الحصول على التصنيفات
    public function getCategories($filters = []) {
        $where = [];
        $params = [];
        
        if (isset($filters['parent_id'])) {
            $where[] = "parent_id = ?";
            $params[] = intval($filters['parent_id']);
        }
        
        $whereClause = $where ? "WHERE " . implode(' AND ', $where) : "";
        
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY name ASC";
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = intval($filters['limit']);
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // الحصول على التصنيف مع عدد الأخبار
    public function getCategoryWithCount($categoryId) {
        $sql = "SELECT c.*, COUNT(n.id) as news_count 
                FROM {$this->table} c 
                LEFT JOIN news n ON c.id = n.category_id AND n.status = 'published'
                WHERE c.id = ? 
                GROUP BY c.id";
        
        $stmt = $this->db->query($sql, [$categoryId]);
        return $stmt->fetch();
    }
    
    // الحصول على التصنيفات الرئيسية
    public function getMainCategories() {
        $sql = "SELECT * FROM {$this->table} WHERE parent_id = 0 ORDER BY name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    // الحصول على التصنيفات الفرعية
    public function getSubCategories($parentId) {
        $sql = "SELECT * FROM {$this->table} WHERE parent_id = ? ORDER BY name ASC";
        $stmt = $this->db->query($sql, [$parentId]);
        return $stmt->fetchAll();
    }
    
    // حذف التصنيف
    public function deleteCategory($categoryId) {
        // التحقق من عدم وجود أخبار مرتبطة
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM news WHERE category_id = ?", [$categoryId]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            throw new Exception('لا يمكن حذف التصنيف لأنه يحتوي على أخبار');
        }
        
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->query($sql, [$categoryId]);
    }
    
    // التحقق من وجود slug
    private function slugExists($slug, $excludeId = null) {
        $sql = "SELECT id FROM {$this->table} WHERE slug = ?";
        $params = [Security::cleanInput($slug)];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch() !== false;
    }
    
    // توليد slug تلقائي من الاسم
    public function generateSlug($name) {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $name);
        $slug = trim($slug, '-');
        $slug = mb_strtolower($slug);
        
        // التأكد من أن slug فريد
        $baseSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}
?>