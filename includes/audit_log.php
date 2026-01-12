<?php
declare(strict_types=1);

/**
 * Audit Log (file-based)
 * ----------------------
 * Writes security-sensitive actions to: ROOT_PATH/storage/logs/audit.log
 *
 * Example:
 *   gody_audit_log('admin_login_success', ['user_id' => 12]);
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!function_exists('gody_audit_log')) {
    function gody_audit_log(string $action, array $meta = []): void
    {
        $dir = rtrim(ROOT_PATH, '/\\') . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $uid = (int)($_SESSION['user_id'] ?? 0);

        $row = [
            'ts'     => gmdate('c'),
            'action' => $action,
            'user_id'=> $uid,
            'ip'     => $ip,
            'ua'     => mb_substr($ua, 0, 250, 'UTF-8'),
            'meta'   => $meta,
        ];

        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$line) return;

        @file_put_contents($dir . '/audit.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
