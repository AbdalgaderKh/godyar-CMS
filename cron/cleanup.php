<?php
/**
 * مهمة تنظيف النظام التلقائية
 * تشغيل: 0 2 * * * (كل يوم الساعة 2 صباحاً)
 */

define('CRON_MODE', true);
require_once '../includes/bootstrap.php';

class SystemCleanup {
    private $db;
    
    public function __construct() {
        global $database;
        $this->db = $database;
    }
    
    public function runCleanup() {
        $results = [];
        
        // تنظيف الجلسات المنتهية
        $results['sessions'] = $this->cleanupSessions();
        
        // تنظيف ملفات الكاش القديمة
        $results['cache'] = $this->cleanupCache();
        
        // تنظيف الملفات المؤقتة
        $results['temp_files'] = $this->cleanupTempFiles();
        
        // تنظيف سجلات النظام القديمة
        $results['logs'] = $this->cleanupOldLogs();
        
        // تحسين جداول قاعدة البيانات
        $results['database'] = $this->optimizeDatabase();
        
        return $results;
    }
    
    private function cleanupSessions() {
        $sql = "DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->db->query($sql);
        return $stmt->rowCount();
    }
    
    private function cleanupCache() {
        $cacheDir = '../cache/';
        $deleted = 0;
        
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*.cache');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    // حذف الملفات الأقدم من 24 ساعة
                    if ($now - filemtime($file) >= 86400) {
                        unlink($file);
                        $deleted++;
                    }
                }
            }
        }
        
        return $deleted;
    }
    
    private function cleanupTempFiles() {
        $tempDir = '../assets/uploads/temp/';
        $deleted = 0;
        
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '*');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    // حذف الملفات المؤقتة الأقدم من ساعة
                    if ($now - filemtime($file) >= 3600) {
                        unlink($file);
                        $deleted++;
                    }
                }
            }
        }
        
        return $deleted;
    }
    
    private function cleanupOldLogs() {
        $logDir = '../logs/';
        $deleted = 0;
        
        if (is_dir($logDir)) {
            $files = glob($logDir . '*.log.*'); // الملفات المؤرشفة
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    // حذف سجلات أقدم من 30 يوم
                    if ($now - filemtime($file) >= 2592000) {
                        unlink($file);
                        $deleted++;
                    }
                }
            }
        }
        
        return $deleted;
    }
    
    private function optimizeDatabase() {
        $tables = ['news', 'users', 'categories', 'comments', 'sessions'];
        $optimized = 0;
        
        foreach ($tables as $table) {
            $sql = "OPTIMIZE TABLE $table";
            $this->db->query($sql);
            $optimized++;
        }
        
        return $optimized;
    }
}

// تنفيذ التنظيف
$cleanup = new SystemCleanup();
$results = $cleanup->runCleanup();

// تسجيل النتائج
$logMessage = date('Y-m-d H:i:s') . " - تنظيف النظام:\n";
foreach ($results as $type => $count) {
    $logMessage .= " - $type: $count\n";
}

file_put_contents('../logs/cleanup.log', $logMessage . "\n", FILE_APPEND);

echo "تم تنظيف النظام بنجاح:\n";
print_r($results);
?>