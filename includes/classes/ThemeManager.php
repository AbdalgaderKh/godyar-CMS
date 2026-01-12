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
<?php

class ThemeManager {
    private $db;
    private $table = 'themes';
    private $currentTheme;
    
    public function __construct() {
        global $database;
        $this->db = $database;
        $this->loadCurrentTheme();
    }
    
    // تحميل الثيم الحالي
    private function loadCurrentTheme() {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 LIMIT 1";
        $stmt = $this->db->query($sql);
        $this->currentTheme = $stmt->fetch();
    }
    
    // توليد ثيم جديد باستخدام الذكاء الاصطناعي
    public function generateNewTheme($themeData) {
        $required = ['name', 'base_color'];
        foreach ($required as $field) {
            if (empty($themeData[$field])) {
                throw new Exception("حقل {$field} مطلوب");
            }
        }
        
        // توليد الألوان باستخدام الخوارزمية
        $colors = $this->generateColorPalette($themeData['base_color']);
        
        // توليد CSS تلقائياً
        $css = $this->generateCSS($colors, $themeData);
        
        $sql = "INSERT INTO {$this->table} (name, description, styles, colors, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $params = [
            Security::cleanInput($themeData['name']),
            Security::cleanInput($themeData['description'] ?? ''),
            $css,
            json_encode($colors),
            $_SESSION['user_id'] ?? 1
        ];
        
        $result = $this->db->query($sql, $params);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    // تطبيق ثيم
    public function applyTheme($themeId) {
        // إلغاء تفعيل جميع الثيمات
        $this->db->query("UPDATE {$this->table} SET is_active = 0");
        
        // تفعيل الثيم المحدد
        $sql = "UPDATE {$this->table} SET is_active = 1 WHERE id = ?";
        $result = $this->db->query($sql, [$themeId]);
        
        if ($result) {
            $this->loadCurrentTheme();
            
            // إنشاء ملف CSS
            $this->generateThemeFile();
            
            return true;
        }
        
        return false;
    }
    
    // توليد palette الألوان
    private function generateColorPalette($baseColor) {
        // تحويل اللون الأساسي إلى RGB
        list($r, $g, $b) = sscanf($baseColor, "#%02x%02x%02x");
        
        return [
            'primary' => $baseColor,
            'secondary' => $this->adjustColor($r, $g, $b, 30),
            'accent' => $this->adjustColor($r, $g, $b, -30),
            'background' => $this->adjustColor($r, $g, $b, 90),
            'text' => $this->getContrastColor($r, $g, $b),
            'header_footer' => "rgba($r, $g, $b, 0.6)",
            'interface' => "rgba($r, $g, $b, 0.02)",
            'blocks' => "rgba($r, $g, $b, 0.035)"
        ];
    }
    
    // تعديل اللون
    private function adjustColor($r, $g, $b, $amount) {
        $r = max(0, min(255, $r + $amount));
        $g = max(0, min(255, $g + $amount));
        $b = max(0, min(255, $b + $amount));
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
    
    // الحصول على لون النص المناسب
    private function getContrastColor($r, $g, $b) {
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
        return $brightness > 128 ? '#333333' : '#ffffff';
    }
    
    // توليد CSS تلقائياً
    private function generateCSS($colors, $themeData) {
        return "
        :root {
            --primary-color: {$colors['primary']};
            --secondary-color: {$colors['secondary']};
            --accent-color: {$colors['accent']};
            --background-color: {$colors['background']};
            --text-color: {$colors['text']};
            --header-footer-bg: {$colors['header_footer']};
            --interface-bg: {$colors['interface']};
            --blocks-bg: {$colors['blocks']};
        }
        
        body {
            background-color: var(--interface-bg);
            color: var(--text-color);
        }
        
        header, footer {
            background: var(--header-footer-bg) !important;
            backdrop-filter: blur(10px);
        }
        
        .card, .block, .widget {
            background-color: var(--blocks-bg) !important;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
        }
        ";
    }
    
    // إنشاء ملف CSS للثيم
    private function generateThemeFile() {
        if ($this->currentTheme) {
            $cssContent = $this->currentTheme['styles'];
            $filePath = dirname(__DIR__) . '/../assets/css/themes/theme-' . $this->currentTheme['id'] . '.css';
            
            // التأكد من وجود المجلد
            if (!is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            file_put_contents($filePath, $cssContent);
        }
    }
    
    // الحصول على الثيمات
    public function getThemes() {
        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    // الحصول على الثيم الحالي
    public function getCurrentTheme() {
        return $this->currentTheme;
    }
}
?>