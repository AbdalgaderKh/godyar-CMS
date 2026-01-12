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

class News {
    private $db;
    private $table = 'news';
    
    public function __construct() {
        global $database;
        $this->db = $database;
    }
    
    // إضافة خبر جديد
    public function addNews($newsData) {
        $required = ['title', 'content', 'category_id', 'author_id'];
        foreach ($required as $field) {
            if (empty($newsData[$field])) {
                throw new Exception("حقل {$field} مطلوب");
            }
        }
        
        $sql = "INSERT INTO {$this->table} (title, content, excerpt, category_id, author_id, featured_image, tags, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            Security::cleanInput($newsData['title']),
            Security::cleanInput($newsData['content']),
            $this->generateExcerpt(Security::cleanInput($newsData['content'])),
            intval($newsData['category_id']),
            intval($newsData['author_id']),
            Security::cleanInput($newsData['featured_image'] ?? ''),
            Security::cleanInput($newsData['tags'] ?? ''),
            $newsData['status'] ?? 'draft'
        ];
        
        $result = $this->db->query($sql, $params);
        
        if ($result) {
            $newsId = $this->db->lastInsertId();
            
            // معالجة الوسوم إذا وجدت
            if (!empty($newsData['tags'])) {
                $this->processTags($newsId, $newsData['tags']);
            }
            
            return $newsId;
        }
        
        return false;
    }
    
    // تحديث الخبر
    public function updateNews($newsId, $newsData) {
        $allowedFields = ['title', 'content', 'category_id', 'featured_image', 'tags', 'status'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($newsData[$field])) {
                $updates[] = "{$field} = ?";
                if ($field === 'content') {
                    $params[] = Security::cleanInput($newsData[$field]);
                    // تحديث الملخص تلقائياً
                    $updates[] = "excerpt = ?";
                    $params[] = $this->generateExcerpt(Security::cleanInput($newsData[$field]));
                } else {
                    $params[] = Security::cleanInput($newsData[$field]);
                }
            }
        }
        
        $updates[] = "updated_at = NOW()";
        
        if (empty($updates)) {
            throw new Exception('لا توجد بيانات لتحديثها');
        }
        
        $params[] = $newsId;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $result = $this->db->query($sql, $params);
        
        if ($result && isset($newsData['tags'])) {
            $this->processTags($newsId, $newsData['tags']);
        }
        
        return $result;
    }
    
    // الحصول على الأخبار
    public function getNews($filters = []) {
        $where = [];
        $params = [];
        
        // الفلاتر الأساسية
        if (isset($filters['category_id'])) {
            $where[] = "category_id = ?";
            $params[] = intval($filters['category_id']);
        }
        
        if (isset($filters['status'])) {
            $where[] = "status = ?";
            $params[] = Security::cleanInput($filters['status']);
        }
        
        if (isset($filters['author_id'])) {
            $where[] = "author_id = ?";
            $params[] = intval($filters['author_id']);
        }
        
        $whereClause = $where ? "WHERE " . implode(' AND ', $where) : "";
        
        $sql = "SELECT n.*, c.name as category_name, u.username as author_name 
                FROM {$this->table} n 
                LEFT JOIN categories c ON n.category_id = c.id 
                LEFT JOIN users u ON n.author_id = u.id 
                {$whereClause} 
                ORDER BY n.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = intval($filters['limit'] ?? 10);
        $params[] = intval($filters['offset'] ?? 0);
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // الحصول على خبر بمفرده
    public function getNewsById($newsId) {
        $sql = "SELECT n.*, c.name as category_name, u.username as author_name 
                FROM {$this->table} n 
                LEFT JOIN categories c ON n.category_id = c.id 
                LEFT JOIN users u ON n.author_id = u.id 
                WHERE n.id = ?";
        
        $stmt = $this->db->query($sql, [$newsId]);
        return $stmt->fetch();
    }
    
    // حذف الخبر
    public function deleteNews($newsId) {
        // حذف الوسوم المرتبطة أولاً
        $this->db->query("DELETE FROM news_tags WHERE news_id = ?", [$newsId]);
        
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->query($sql, [$newsId]);
    }
    
    // توليد ملخص تلقائي
    private function generateExcerpt($content, $length = 150) {
        $content = strip_tags($content);
        if (mb_strlen($content) > $length) {
            $content = mb_substr($content, 0, $length) . '...';
        }
        return $content;
    }
    
    // معالجة الوسوم
    private function processTags($newsId, $tags) {
        // حذف الوسوم القديمة
        $this->db->query("DELETE FROM news_tags WHERE news_id = ?", [$newsId]);
        
        $tagsArray = explode(',', $tags);
        foreach ($tagsArray as $tag) {
            $tag = trim($tag);
            if (!empty($tag)) {
                // إضافة الوسم إذا لم يكن موجوداً
                $tagId = $this->getOrCreateTag($tag);
                
                // ربط الوسم بالخبر
                $this->db->query(
                    "INSERT INTO news_tags (news_id, tag_id) VALUES (?, ?)",
                    [$newsId, $tagId]
                );
            }
        }
    }
    
    // الحصول على وسم أو إنشائه
    private function getOrCreateTag($tagName) {
        $stmt = $this->db->query("SELECT id FROM tags WHERE name = ?", [$tagName]);
        $tag = $stmt->fetch();
        
        if ($tag) {
            return $tag['id'];
        }
        
        $this->db->query("INSERT INTO tags (name, slug) VALUES (?, ?)", [
            $tagName, 
            $this->generateSlug($tagName)
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // توليد slug
    private function generateSlug($text) {
        $text = preg_replace('~[^\p{L}\p{N}]+~u', '-', $text);
        $text = trim($text, '-');
        $text = mb_strtolower($text);
        return $text;
    }
}
?>