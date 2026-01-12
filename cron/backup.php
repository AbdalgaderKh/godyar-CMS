<?php
/**
 * مهمة النسخ الاحتياطي التلقائي
 * تشغيل: 0 3 * * 0 (كل أحد الساعة 3 صباحاً)
 */

define('CRON_MODE', true);
require_once '../includes/config.php';

class SystemBackup {
    private $backupDir;
    
    public function __construct() {
        $this->backupDir = '../backups/' . date('Y-m') . '/';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    public function createBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $results = [];
        
        // نسخ قاعدة البيانات
        $results['database'] = $this->backupDatabase($timestamp);
        
        // نسخ الملفات المهمة
        $results['files'] = $this->backupImportantFiles($timestamp);
        
        // تنظيف النسخ القديمة
        $results['cleanup'] = $this->cleanupOldBackups();
        
        return $results;
    }
    
    private function backupDatabase($timestamp) {
        $backupFile = $this->backupDir . "database_{$timestamp}.sql";
        
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($backupFile)
        );
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($backupFile)) {
            // ضغط ملف النسخ الاحتياطي
            $compressedFile = $backupFile . '.gz';
            exec("gzip $backupFile");
            
            return [
                'success' => true,
                'file' => $compressedFile,
                'size' => filesize($compressedFile)
            ];
        }
        
        return [
            'success' => false,
            'error' => 'فشل في نسخ قاعدة البيانات'
        ];
    }
    
    private function backupImportantFiles($timestamp) {
        $backupFile = $this->backupDir . "files_{$timestamp}.zip";
        $filesToBackup = [
            '../includes/',
            '../admin/',
            '../frontend/templates/',
            '../assets/css/',
            '../assets/js/'
        ];
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE) === TRUE) {
            foreach ($filesToBackup as $file) {
                if (is_dir($file)) {
                    $this->addFolderToZip($zip, $file);
                } elseif (is_file($file)) {
                    $zip->addFile($file, basename($file));
                }
            }
            
            $zip->close();
            
            return [
                'success' => true,
                'file' => $backupFile,
                'size' => filesize($backupFile)
            ];
        }
        
        return [
            'success' => false,
            'error' => 'فشل في إنشاء ملف النسخ الاحتياطي'
        ];
    }
    
    private function addFolderToZip($zip, $folder, $parentFolder = '') {
        $files = scandir($folder);
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            
            $filePath = $folder . '/' . $file;
            $localPath = $parentFolder . $file;
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($localPath);
                $this->addFolderToZip($zip, $filePath, $localPath . '/');
            } else {
                $zip->addFile($filePath, $localPath);
            }
        }
    }
    
    private function cleanupOldBackups() {
        $deleted = 0;
        $backupDirs = glob('../backups/*', GLOB_ONLYDIR);
        $now = time();
        
        foreach ($backupDirs as $dir) {
            // حذف المجلدات الأقدم من 30 يوم
            if ($now - filemtime($dir) >= 2592000) {
                $this->deleteDirectory($dir);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}

// تنفيذ النسخ الاحتياطي
$backup = new SystemBackup();
$results = $backup->createBackup();

// تسجيل النتائج
$logMessage = date('Y-m-d H:i:s') . " - النسخ الاحتياطي:\n";
foreach ($results as $type => $result) {
    if (is_array($result) && isset($result['success'])) {
        $status = $result['success'] ? 'نجح' : 'فشل';
        $logMessage .= " - $type: $status\n";
        
        if ($result['success'] && isset($result['file'])) {
            $logMessage .= "   الملف: " . basename($result['file']) . "\n";
            $logMessage .= "   الحجم: " . round($result['size'] / 1024 / 1024, 2) . " MB\n";
        }
    } else {
        $logMessage .= " - $type: $result\n";
    }
}

file_put_contents('../logs/backup.log', $logMessage . "\n", FILE_APPEND);

echo "تم النسخ الاحتياطي بنجاح:\n";
print_r($results);
?>