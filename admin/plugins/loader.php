<?php
declare(strict_types=1);

// منع الوصول المباشر لهذا الملف (يسمح بالـ include فقط)
try {
    $isDirect = (PHP_SAPI !== 'cli') && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);
} catch (Throwable $e) {
    $isDirect = false;
}
if ($isDirect) {
    require_once __DIR__ . '/../_admin_guard.php';
    if (class_exists('Godyar\\Auth') && method_exists('Godyar\\Auth','requireRole')) {
        \Godyar\Auth::requireRole('admin');
    } else {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') { http_response_code(403); exit('403 Forbidden'); }
    }
    header('Location: /admin/plugins/index.php');
    exit;
}

require_once __DIR__ . '/hooks.php';

// مسار مجلد الإضافات
function gdy_admin_plugins_dir(): string {
    return __DIR__;
}

// ملف تخزين السلقز المفعّلة
function gdy_admin_enabled_plugins_path(): string {
    return __DIR__ . '/enabled_plugins.json';
}

function gdy_admin_get_enabled_plugins(): array {
    $path = gdy_admin_enabled_plugins_path();
    if (!is_file($path)) return [];
    $json = file_get_contents($path);
    $arr  = json_decode($json ?: '[]', true);
    if (!is_array($arr)) return [];
    // نظافة بسيطة
    $arr = array_values(array_unique(array_filter($arr, fn($v) => is_string($v) && $v !== '')));
    return $arr;
}

function gdy_admin_set_enabled_plugins(array $slugs): void {
    $slugs = array_values(array_unique(array_filter($slugs, fn($v) => is_string($v) && $v !== '')));
    file_put_contents(
        gdy_admin_enabled_plugins_path(),
        json_encode($slugs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

// قراءة plugin.json لكل إضافة
function gdy_admin_list_plugins(): array {
    $dir = gdy_admin_plugins_dir();
    $items = [];
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $pdir = $dir . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($pdir)) continue;
        $jsonPath = $pdir . DIRECTORY_SEPARATOR . 'plugin.json';
        if (!is_file($jsonPath)) continue;

        $meta = json_decode((string)file_get_contents($jsonPath), true);
        if (!is_array($meta)) continue;

        $slug = $meta['slug'] ?? $entry;
        if (!is_string($slug) || $slug === '') $slug = $entry;

        $meta['name']        = is_string($meta['name'] ?? null) ? $meta['name'] : $slug;
        $meta['version']     = is_string($meta['version'] ?? null) ? $meta['version'] : '';
        $meta['description'] = is_string($meta['description'] ?? null) ? $meta['description'] : '';
        $meta['author']      = is_string($meta['author'] ?? null) ? $meta['author'] : '';
        $meta['entry']       = is_string($meta['entry'] ?? null) ? $meta['entry'] : 'plugin.php';
        $meta['_dir']        = $pdir;

        $items[$slug] = $meta;
    }
    ksort($items);
    return $items;
}

// تحميل الإضافات المفعلة
function gdy_admin_load_plugins(): void {
    $enabled = array_flip(gdy_admin_get_enabled_plugins());
    foreach (gdy_admin_list_plugins() as $slug => $meta) {
        if (!isset($enabled[$slug])) continue;

        $entryPath = rtrim((string)$meta['_dir'], '/\\') . DIRECTORY_SEPARATOR . ($meta['entry'] ?? 'plugin.php');
        if (!is_file($entryPath)) continue;

        try {
            require_once $entryPath;
        } catch (\Throwable $e) {
            // تجاهل أخطاء الإضافات حتى لا تُسقط لوحة التحكم
        }
    }
}

// تحميل الإضافات فورًا عند الاستدعاء
gdy_admin_load_plugins();
