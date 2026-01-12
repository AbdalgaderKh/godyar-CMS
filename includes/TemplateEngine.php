<?php
// /godyar/includes/TemplateEngine.php
declare(strict_types=1);

class TemplateEngine {
    private $data = [];
    private $headerFile;
    private $footerFile;
    
    public function __construct() {
        // نستخدم نفس الهيدر/الفوتر الجزئيين في الواجهة الأمامية
        $this->headerFile = __DIR__ . '/../frontend/views/partials/header.php';
        $this->footerFile = __DIR__ . '/../frontend/views/partials/footer.php';
    }
    
    public function set($key, $value) {
        $this->data[$key] = $value;
    }
    
    public function render(string $contentFile, array $data = []): void {
        if (!defined("GDY_TPL_WRAPPED")) {
            define("GDY_TPL_WRAPPED", true);
        }

        // دمج البيانات مع إعطاء الأولوية للبيانات الجديدة
        $this->data = array_merge($this->data, $data);
        
        // ============= الإصلاح: تمرير المتغيرات بشكل صحيح =============
        // استخراج المتغيرات للقالب مع إتاحتها كمتغيرات عامة
        extract($this->data, EXTR_SKIP | EXTR_REFS);
        
        // التأكد أن baseUrl موجود
        if (!isset($baseUrl) || $baseUrl === '') {
            if (function_exists('base_url')) {
                $baseUrl = rtrim(base_url(), '/');
            } else {
                $baseUrl = '/godyar';
            }
        }
        
        // تنظيف أي /frontend/controllers من النهاية
        $baseUrl = preg_replace('#/frontend/controllers$#', '', $baseUrl);
        
        // ============= تحقق من البيانات قبل العرض =============
        // Debug output is noisy and should be opt-in in production
        $tplDebug = (int)($_ENV['TEMPLATE_DEBUG'] ?? getenv('TEMPLATE_DEBUG') ?? 0) === 1;
        if ($tplDebug) {
            $this->debugTemplateData($contentFile);
        }
        
        // ============= الإصلاح: تمرير البيانات للقالب =============
        // تحميل الهيدر مع تمرير البيانات
        if (file_exists($this->headerFile)) {
            // استخراج المتغيرات للهيدر
            extract($this->data, EXTR_SKIP | EXTR_REFS);
            require $this->headerFile;
        } else {
            echo "<!-- Header file not found: " . htmlspecialchars($this->headerFile) . " -->";
        }
        
        // ============= الإصلاح: تمرير البيانات للمحتوى =============
        // تحميل المحتوى مع تمرير البيانات
        if (file_exists($contentFile)) {
            // استخراج المتغيرات للمحتوى
            extract($this->data, EXTR_SKIP | EXTR_REFS);
            require $contentFile;
        } else {
            echo "View not found: " . htmlspecialchars($contentFile, ENT_QUOTES, 'UTF-8');
        }
        
        // ============= الإصلاح: تمرير البيانات للفوتر =============
        // تحميل الفوتر مع تمرير البيانات
        if (file_exists($this->footerFile)) {
            // استخراج المتغيرات للفوتر
            extract($this->data, EXTR_SKIP | EXTR_REFS);
            require $this->footerFile;
        } else {
            echo "<!-- Footer file not found: " . htmlspecialchars($this->footerFile) . " -->";
        }
    }
    
    // دالة لتصحيح البيانات
    private function debugTemplateData(string $contentFile): void {
        error_log("=== TEMPLATE ENGINE DEBUG ===");
        error_log("Content file: " . $contentFile);
        error_log("Total variables: " . count($this->data));
        
        // تحقق من متغيرات الإعلانات المهمة
        $important_vars = ['headerAd', 'sidebarTopAd', 'sidebarBottomAd', 'latestNews', 'siteName'];
        foreach ($important_vars as $var) {
            $exists = isset($this->data[$var]);
            $value = $exists ? $this->data[$var] : 'NOT_SET';
            $type = gettype($value);
            $length = is_string($value) ? strlen($value) : 'N/A';
            
            error_log("Variable '$var': exists=$exists, type=$type, length=$length");
            
            if ($exists && is_string($value) && strlen($value) > 0) {
                error_log("  Content preview: " . substr($value, 0, 100));
            }
        }
        
        // إضافة تعليقات تصحيح في HTML
        echo "<!-- TEMPLATE ENGINE DEBUG -->";
        echo "<!-- Total variables: " . count($this->data) . " -->";
        echo "<!-- headerAd exists: " . (isset($this->data['headerAd']) ? 'YES' : 'NO') . " -->";
        echo "<!-- sidebarTopAd exists: " . (isset($this->data['sidebarTopAd']) ? 'YES' : 'NO') . " -->";
        echo "<!-- latestNews count: " . (isset($this->data['latestNews']) ? count($this->data['latestNews']) : '0') . " -->";
        
        // عرض الإعلانات مباشرة كحل بديل
        if (isset($this->data['headerAd']) && !empty($this->data['headerAd'])) {
            echo "<!-- DIRECT AD INJECTION HEADER -->";
            echo $this->data['headerAd'];
        }
    }
}
