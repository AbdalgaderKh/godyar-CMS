<?php
declare(strict_types=1);

/**
 * Settings UI meta (titles, subtitles, breadcrumbs) + helpers.
 * Used by settings pages to keep UI consistent and avoid duplication.
 */

$GLOBALS['__SETTINGS_PAGES'] = [
    'index.php' => [
        'title' => __('t_eec142389c', 'لوحة الإعدادات'),
        'subtitle' => __('t_2f6fa685c5', 'إدارة إعدادات الموقع بشكل منظم'),
        'icon' => '📌',
    ],
    'general.php' => [
        'title' => __('t_46ce4c91ac', 'الإعدادات العامة'),
        'subtitle' => __('t_1a9b184d99', 'الهوية العامة وبيانات التواصل'),
        'icon' => '⚙️',
    ],
    'theme.php' => [
        'title' => __('t_98b5984f5b', 'المظهر'),
        'subtitle' => __('t_f35ba814e9', 'الألوان والقالب وعناصر الواجهة'),
        'icon' => '🎨',
    ],
    'seo.php' => [
        'title' => 'SEO',
        'subtitle' => __('t_56c902213d', 'الميتا والروبوتس والروابط الأساسية'),
        'icon' => '🔍',
    ],
    'social_media.php' => [
        'title' => __('t_59be08cf61', 'السوشيال'),
        'subtitle' => __('t_9d24d30068', 'روابط السوشيال والتكاملات'),
        'icon' => '📱',
    ],
    'header_footer.php' => [
        'title' => __('t_aabf4db196', 'الهيدر والفوتر'),
        'subtitle' => __('t_bf431caa86', 'أكواد إضافية داخل head و قبل body'),
        'icon' => '📄',
    ],
    'frontend_sidebar.php' => [
        'title' => __('t_9ad11b9dac', 'سايدبار الواجهة'),
        'subtitle' => __('t_f444a2f298', 'إظهار/إخفاء سايدبار الزوار'),
        'icon' => '📚',
    ],
    'cache.php' => [
        'title' => __('t_a10e27b470', 'الكاش'),
        'subtitle' => __('t_36b8b5a74b', 'التحكم بالتخزين المؤقت'),
        'icon' => '⚡',
    ],
    'tools.php' => [
        'title' => __('t_4c1c5a5d43', 'أدوات الإعدادات'),
        'subtitle' => __('t_a4d7f42f0f', 'تصدير/استيراد + تنظيف الكاش'),
        'icon' => '🧰',
    ],
    'time.php' => [
        'title' => __('t_8e39afdb3e', 'الوقت واللغة'),
        'subtitle' => __('t_031281a5ac', 'اللغة والمنطقة الزمنية'),
        'icon' => '🕒',
    ],
    'pwa.php' => [
        'title' => __('t_pwa_push_title', 'PWA & Push'),
        'subtitle' => __('t_pwa_push_sub', 'إعدادات التثبيت والإشعارات'),
        'icon' => '📲',
    ],
];

if (!function_exists('settings_current_file')) {
    function settings_current_file(): string {
        $s = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
        $file = basename((string)$s);
        return $file ?: 'index.php';
    }
}

if (!function_exists('settings_page_meta')) {
    function settings_page_meta(?string $file = null): array {
        $file = $file ?: settings_current_file();
        $pages = $GLOBALS['__SETTINGS_PAGES'] ?? [];
        if (isset($pages[$file]) && is_array($pages[$file])) {
            return $pages[$file] + ['file' => $file];
        }
        return ['title' => __('t_1f60020959', 'الإعدادات'), 'subtitle' => '', 'icon' => '⚙️', 'file' => $file];
    }
}

if (!function_exists('settings_apply_context')) {
    function settings_apply_context(?string $file = null): void {
        $meta = settings_page_meta($file);
        $title = (string)($meta['title'] ?? __('t_1f60020959', 'الإعدادات'));

        // Highlight "settings" in the main admin sidebar (if supported)
        $GLOBALS['currentPage'] = 'settings';

        // Page header (rendered by layout/app_start.php)
        $GLOBALS['pageTitle'] = $title;
        $GLOBALS['pageSubtitle'] = (string)($meta['subtitle'] ?? '');

        // Breadcrumbs (keys are labels; values are URLs or null for current)
        $GLOBALS['breadcrumbs'] = [
            __('t_a06ee671f4', 'لوحة التحكم') => '../index.php',
            __('t_1f60020959', 'الإعدادات')   => 'index.php',
            $title        => null,
        ];

        // Optional action button (top-right)
        $GLOBALS['pageActionsHtml'] = __('t_c0dee6ffe8', '<a href="index.php" class="btn btn-sm btn-outline-secondary">لوحة الإعدادات</a>');
    }
}
