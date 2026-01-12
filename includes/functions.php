<?php
// includes/functions.php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    // تعريف ثابت المسار الجذر إذا لم يكن معرفًا مسبقًا
    define('ABSPATH', __DIR__ . '/../');
}

/**
 * وظائف مساعدة لنظام جويار
 */

/**
 * تضمين ملف بشكل آمن مع تحقق من الوجود
 */
function safe_include(string $file_path): void {
    if (file_exists($file_path) && is_file($file_path)) {
        include $file_path;
    } else {
        error_log("ملف غير موجود: " . $file_path);
        throw new RuntimeException("الملف المطلوب غير متوفر: " . basename($file_path));
    }
}

/**
 * تضمين ملف بشكل آمن مع إرجاع المحتوى
 */
function safe_include_return(string $file_path): string {
    if (file_exists($file_path) && is_file($file_path)) {
        ob_start();
        include $file_path;
        return ob_get_clean();
    }
    return '';
}

/**
 * تطبيق ألوان الثيم
 */
function apply_theme_colors(): void {
    static $applied = false;

    if ($applied) {
        return;
    }
    $applied = true;

    $style = "
        <style>
            :root {
                --turquoise-60: rgba(64, 224, 208, 0.6);
                --turquoise-2: rgba(64, 224, 208, 0.02);
                --turquoise-3_5: rgba(64, 224, 208, 0.035);
                --turquoise-10: rgba(64, 224, 208, 0.1);
                --turquoise-20: rgba(64, 224, 208, 0.2);
            }

            .godyar-header,
            .godyar-footer {
                background: linear-gradient(135deg, var(--turquoise-60), rgba(32, 178, 170, 0.6)) !important;
                backdrop-filter: blur(10px);
            }

            .godyar-body {
                background-color: var(--turquoise-2) !important;
                background-image:
                    radial-gradient(var(--turquoise-10) 1px, transparent 1px),
                    radial-gradient(var(--turquoise-10) 1px, transparent 1px);
                background-size: 50px 50px;
                background-position: 0 0, 25px 25px;
            }

            .godyar-card,
            .godyar-block,
            .godyar-panel,
            .godyar-widget {
                background: var(--turquoise-3_5) !important;
                backdrop-filter: blur(5px);
                border: 1px solid var(--turquoise-20);
                border-radius: 12px;
            }

            .godyar-btn-primary {
                background: linear-gradient(135deg, var(--turquoise-60), #20b2aa) !important;
                border: none;
                border-radius: 8px;
                transition: all 0.3s ease;
            }

            .godyar-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(64, 224, 208, 0.4);
            }
        </style>
    ";
    echo $style;
}

/**
 * تحميل تنسيقات CSS ديناميكياً
 */
function load_dynamic_css(array $styles = []): void {
    $default_styles = [
        'primary_color'   => '#40e0d0',
        'secondary_color' => '#20b2aa',
        'background_color'=> 'rgba(64, 224, 208, 0.02)',
        'card_background' => 'rgba(64, 224, 208, 0.035)'
    ];

    $styles = array_merge($default_styles, $styles);

    $css = "
        <style>
            .dynamic-primary { color: {$styles['primary_color']} !important; }
            .dynamic-bg { background: {$styles['background_color']} !important; }
            .dynamic-card { background: {$styles['card_background']} !important; }
            .btn-dynamic {
                background: linear-gradient(135deg, {$styles['primary_color']}, {$styles['secondary_color']}) !important;
            }
        </style>
    ";

    echo $css;
}

/**
 * --- حماية CSRF موحدة ---
 */
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_time']  = time();
        }

        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return generate_csrf_token();
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(string $token): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        if (!hash_equals((string)$_SESSION['csrf_token'], (string)$token)) {
            return false;
        }

        $token_time = (int)($_SESSION['csrf_time'] ?? 0);
        if ($token_time > 0 && (time() - $token_time) > 1800) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_time']);
            return false;
        }

        return true;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(string $name = 'csrf_token'): string {
        $token = csrf_token();
        $html  = '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') .
                 '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        echo $html;
        return $html;
    }
}

if (!function_exists('csrf_verify_or_die')) {
    function csrf_verify_or_die(string $fieldName = 'csrf_token'): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $sent = $_POST[$fieldName] ?? '';
        if (!verify_csrf_token((string)$sent)) {
            http_response_code(400);
            die('CSRF validation failed');
        }
    }
}

/**
 * إعادة توجيه آمن
 */
function safe_redirect(string $url, int $status_code = 302): void {
    if (!headers_sent()) {
        header("Location: " . $url, true, $status_code);
        exit;
    }

    echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>';
    exit;
}

/**
 * تحميل عرض (view) مع تمرير بيانات
 */
function load_view(string $view_path, array $data = []): string {
    extract($data, EXTR_SKIP);

    if (!file_exists($view_path)) {
        throw new RuntimeException("ملف العرض غير موجود: " . $view_path);
    }

    ob_start();
    include $view_path;
    return ob_get_clean();
}

/**
 * تسجيل خطأ في السجل
 */
function log_error(string $message, array $context = []): void {
    $log_dir = ABSPATH . '/storage/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $timestamp   = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $log_message = "[{$timestamp}] {$message} {$context_str}" . PHP_EOL;

    file_put_contents($log_dir . '/errors.log', $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * تسجيل حدث في السجل
 */
function log_event(string $event, string $type = 'info', array $data = []): void {
    $log_dir = ABSPATH . '/storage/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_id   = $_SESSION['user_id'] ?? 'guest';
    $data_str  = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : '';

    $log_message = "[{$timestamp}] [{$type}] [{$ip}] [user:{$user_id}] {$event} {$data_str}" . PHP_EOL;

    file_put_contents($log_dir . '/events.log', $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * تصفية بيانات الإدخال
 */
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if (is_array($data)) {
            return array_map('sanitize_input', $data);
        }

        if ($data === null) {
            return '';
        }

        $data = trim((string)$data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $data;
    }
}

/**
 * التحقق من صحة البريد الإلكتروني
 */
function is_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * التحقق من صحة الرقم
 */
function is_valid_number($number, int $min = null, int $max = null): bool {
    if (!is_numeric($number)) {
        return false;
    }

    $number = (int)$number;

    if ($min !== null && $number < $min) {
        return false;
    }

    if ($max !== null && $number > $max) {
        return false;
    }

    return true;
}

/**
 * تقصير النص مع إضافة نقاط
 */
function truncate_text(string $text, int $length = 100, string $suffix = '...'): string {
    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * تنسيق التاريخ بالعربية
 */
function format_arabic_date(string $date, bool $include_time = false): string {
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    $months = [
        'January'   => 'يناير',
        'February'  => 'فبراير',
        'March'     => 'مارس',
        'April'     => 'أبريل',
        'May'       => 'مايو',
        'June'      => 'يونيو',
        'July'      => 'يوليو',
        'August'    => 'أغسطس',
        'September' => 'سبتمبر',
        'October'   => 'أكتوبر',
        'November'  => 'نوفمبر',
        'December'  => 'ديسمبر'
    ];

    $english_month = date('F', $timestamp);
    $arabic_month  = $months[$english_month] ?? $english_month;

    $formatted = date('d', $timestamp) . ' ' . $arabic_month . ' ' . date('Y', $timestamp);

    if ($include_time) {
        $formatted .= ' - ' . date('H:i', $timestamp);
    }

    return $formatted;
}

/**
 * إنشاء slug من النص
 */
function generate_slug(string $text): string {
    $text = trim($text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/[^\p{L}\p{N}\-]/u', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim((string)$text, '-');
    return (string)$text;
}

/**
 * التحقق من صلاحيات المستخدم
 */
function has_permission(string $permission): bool {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }

    $user_role = $_SESSION['user_role'];

    $permissions = [
        'admin'  => ['manage_users', 'manage_content', 'manage_settings', 'manage_security', 'manage_plugins'],
        'editor' => ['manage_content', 'manage_media'],
        'author' => ['manage_own_content'],
        'user'   => ['view_content']
    ];

    return in_array($permission, $permissions[$user_role] ?? [], true);
}

/**
 * إضافة رسالة تنبيه
 */
function add_flash_message(string $message, string $type = 'info'): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }

    $_SESSION['flash_messages'][] = [
        'message' => $message,
        'type'    => $type,
        'time'    => time()
    ];
}

/**
 * عرض رسائل التنبيه
 */
function display_flash_messages(): void {
    if (empty($_SESSION['flash_messages'])) {
        return;
    }

    foreach ($_SESSION['flash_messages'] as $message) {
        // PHP 7.4 compatibility: avoid "match" (PHP 8+)
        switch ((string)($message['type'] ?? 'info')) {
            case 'success':
                $alert_class = 'alert-success';
                break;
            case 'error':
                $alert_class = 'alert-danger';
                break;
            case 'warning':
                $alert_class = 'alert-warning';
                break;
            default:
                $alert_class = 'alert-info';
                break;
        }

        echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8');
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }

    $_SESSION['flash_messages'] = [];
}

/**
 * تحميل إعدادات الموقع
 */
function get_site_settings(): array {
    static $settings = null;

    if ($settings === null) {
        $settings_file = ABSPATH . '/storage/settings/site.json';

        if (file_exists($settings_file)) {
            $settings = json_decode((string)file_get_contents($settings_file), true) ?? [];
        } else {
            $settings = [
                'site_name'        => 'Godyar',
                'site_description' => 'نظام إدارة المحتوى',
                'site_url'         => base_url(),
                'timezone'         => 'Asia/Riyadh',
                'language'         => 'ar'
            ];
        }
    }

    return $settings;
}

/**
 * الحصول على إعداد من إعدادات الموقع
 */
function get_setting(string $key, $default = null) {
    $settings = get_site_settings();
    return $settings[$key] ?? $default;
}

/**
 * تحديث إعدادات الموقع
 */
function update_site_settings(array $new_settings): bool {
    $current_settings = get_site_settings();
    $updated_settings = array_merge($current_settings, $new_settings);

    $settings_dir = ABSPATH . '/storage/settings';
    if (!is_dir($settings_dir)) {
        mkdir($settings_dir, 0755, true);
    }

    $result = file_put_contents(
        $settings_dir . '/site.json',
        json_encode($updated_settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    if ($result !== false) {
        return true;
    }

    return false;
}

/**
 * هيلبرز بسيطة لنظام العضويات/الاشتراكات
 */
if (!function_exists('current_user_subscription')) {
    function current_user_subscription(): ?array {
        if (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
            return null;
        }

        $userId = !empty($_SESSION['user']['id'])
            ? (int)$_SESSION['user']['id']
            : (int)($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        $pdo = gdy_pdo_safe();
        if (!$pdo instanceof \PDO) {
            return null;
        }

        $sql = "SELECT s.*, p.slug AS plan_slug, p.name AS plan_name
                FROM user_subscriptions s
                JOIN membership_plans p ON p.id = s.plan_id
                WHERE s.user_id = :uid
                  AND s.status = 'active'
                  AND s.starts_at <= NOW()
                  AND s.ends_at >= NOW()
                LIMIT 1";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            @error_log('[Godyar Membership] ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('user_has_plan')) {
    function user_has_plan(string $planSlug): bool {
        $sub = current_user_subscription();
        if (!$sub) {
            return false;
        }

        return ((string)$sub['plan_slug'] === $planSlug);
    }
}

if (!function_exists('require_plan')) {
    function require_plan(string $planSlug): void {
        if (empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
            header('Location: /godyar/login.php');
            exit;
        }

        if (!user_has_plan($planSlug)) {
            header('Location: /godyar/upgrade.php');
            exit;
        }
    }
}

// تحميل الدوال المساعدة إذا كانت موجودة
$helpers_file = __DIR__ . '/helpers.php';
if (file_exists($helpers_file)) {
    include $helpers_file;
}
