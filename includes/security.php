<?php
/**
 * security.php - طبقة أمان مبسطة (محسّنة)
 * - Sanitization للمدخلات
 * - Escaping للمخرجات
 * - Helpers آمنة لاستخدام GET/POST
 */

if (!defined('GODYAR_ROOT')) {
    define('GODYAR_ROOT', dirname(__DIR__));
}

if (!function_exists('in')) {
    /** sanitize input (string) */
    function in($v): string {
        $v = is_string($v) ? $v : (is_null($v) ? '' : (string)$v);
        return trim($v);
    }
}

if (!function_exists('out')) {
    /** escape output for HTML */
    function out($v): string {
        $v = is_string($v) ? $v : (is_null($v) ? '' : (string)$v);
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('get_str')) {
    function get_str(string $key, string $default=''): string {
        return isset($_GET[$key]) ? in($_GET[$key]) : $default;
    }
}

if (!function_exists('post_str')) {
    function post_str(string $key, string $default=''): string {
        return isset($_POST[$key]) ? in($_POST[$key]) : $default;
    }
}

// ملاحظة: دوال CSRF موجودة في includes/bootstrap.php (generate_csrf_token / validate_csrf_token)
// ويمكن تفعيل التحقق المركزي في admin/_admin_boot.php
