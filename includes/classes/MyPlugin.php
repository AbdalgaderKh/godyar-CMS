<?php
// includes/classes/MyPlugin.php
class MyPlugin {
    public function init() {
        // تسجيل الإجراءات
        add_action('news_published', [$this, 'onNewsPublished']);
    }
    
    public function onNewsPublished($newsId) {
        // تنفيذ عند نشر خبر
    }
}

// تسجيل الإضافة
$plugin = new MyPlugin();
$plugin->init();
?>