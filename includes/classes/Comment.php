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

class Comment {
    private $db;
    private $table = 'comments';
    
    public function __construct() {
        global $database;
        $this->db = $database;
    }
    
    // إضافة تعليق
    public function addComment($commentData) {
        $required = ['news_id', 'user_id', 'content'];
        foreach ($required as $field) {
            if (empty($commentData[$field])) {
                throw new Exception("حقل {$field} مطلوب");
            }
        }
        
        $sql = "INSERT INTO {$this->table} (news_id, user_id, content, parent_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $params = [
            intval($commentData['news_id']),
            intval($commentData['user_id']),
            Security::cleanInput($commentData['content']),
            intval($commentData['parent_id'] ?? 0),
            $commentData['status'] ?? 'pending'
        ];
        
        $result = $this->db->query($sql, $params);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    // الحصول على تعليقات الخبر
    public function getNewsComments($newsId, $filters = []) {
        $where = ["c.news_id = ?", "c.status = 'approved'"];
        $params = [$newsId];
        
        if (isset($filters['parent_id'])) {
            $where[] = "c.parent_id = ?";
            $params[] = intval($filters['parent_id']);
        } else {
            $where[] = "c.parent_id = 0";
        }
        
        $whereClause = "WHERE " . implode(' AND ', $where);
        
        $sql = "SELECT c.*, u.username, u.avatar 
                FROM {$this->table} c 
                LEFT JOIN users u ON c.user_id = u.id 
                {$whereClause} 
                ORDER BY c.created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        $comments = $stmt->fetchAll();
        
        // إضافة الردود لكل تعليق
        foreach ($comments as &$comment) {
            $comment['replies'] = $this->getCommentReplies($comment['id']);
        }
        
        return $comments;
    }
    
    // الحصول على ردود التعليق
    private function getCommentReplies($commentId) {
        $sql = "SELECT c.*, u.username, u.avatar 
                FROM {$this->table} c 
                LEFT JOIN users u ON c.user_id = u.id 
                WHERE c.parent_id = ? AND c.status = 'approved' 
                ORDER BY c.created_at ASC";
        
        $stmt = $this->db->query($sql, [$commentId]);
        return $stmt->fetchAll();
    }
    
    // الموافقة على تعليق
    public function approveComment($commentId) {
        $sql = "UPDATE {$this->table} SET status = 'approved' WHERE id = ?";
        return $this->db->query($sql, [$commentId]);
    }
    
    // رفض تعليق
    public function rejectComment($commentId) {
        $sql = "UPDATE {$this->table} SET status = 'rejected' WHERE id = ?";
        return $this->db->query($sql, [$commentId]);
    }
    
    // حذف تعليق
    public function deleteComment($commentId) {
        // حذف الردود أولاً
        $this->db->query("DELETE FROM {$this->table} WHERE parent_id = ?", [$commentId]);
        
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->query($sql, [$commentId]);
    }
    
    // إحصائيات التعليقات
    public function getCommentStats() {
        $sql = "SELECT 
                COUNT(*) as total_comments,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_comments,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_comments
                FROM {$this->table}";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }
}
?>