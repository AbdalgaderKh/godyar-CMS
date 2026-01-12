<?php
declare(strict_types=1);
/**
 * admin/includes/admin_debug.php
 * أدوات تشخيص أخطاء لوحة التحكم (للمدير فقط).
 *
 * - يتم حفظ آخر خطأ في Session (admin_last_error)
 * - ويتم تسجيله كذلك في admin/storage/admin_debug.log (إن أمكن)
 *
 * ملاحظة: لا تعرض التفاصيل إلا للمدير.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!function_exists('gdy_is_admin_user')) {
    function gdy_is_admin_user(): bool
    {
        $role = (string)($_SESSION['user']['role'] ?? '');
        return in_array($role, ['admin', 'superadmin'], true);
    }
}

if (!function_exists('gdy_admin_capture_error')) {
    function gdy_admin_capture_error(Throwable $e, string $context = ''): void
    {
        $payload = [
            'time'    => date('Y-m-d H:i:s'),
            'context' => $context,
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => substr((string)$e->getTraceAsString(), 0, 8000),
        ];

        // Session (للعرض داخل اللوحة)
        $_SESSION['admin_last_error'] = $payload;

        // ملف لوج داخلي
        $dir = __DIR__ . '/../storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $logFile = $dir . '/admin_debug.log';
        @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

        // سجل PHP العام (دائمًا)
        @error_log('[ADMIN_DEBUG] ' . $context . ' :: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
}

if (!function_exists('gdy_admin_get_last_error')) {
    function gdy_admin_get_last_error(bool $clear = false): ?array
    {
        $err = $_SESSION['admin_last_error'] ?? null;
        if ($clear) {
            unset($_SESSION['admin_last_error']);
        }
        return is_array($err) ? $err : null;
    }
}
