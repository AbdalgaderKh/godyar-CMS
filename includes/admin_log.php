<?php
declare(strict_types=1);

/**
 * دالة بسيطة لتسجيل عمليات المدير في جدول admin_logs (إن وُجد).
 */

if (!function_exists('admin_log')) {
    function admin_log(string $action, ?string $details = null): void
    {
        try {
            $pdo = gdy_pdo_safe();
            if (!$pdo instanceof \PDO) {
                return;
            }

            static $checked = false;
            static $hasTable = false;

            if (!$checked) {
                $stmt = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
                $hasTable = (bool) ($stmt && $stmt->fetchColumn());
                $checked  = true;
            }

            if (!$hasTable) {
                return;
            }

            $user = $_SESSION['user'] ?? [];

            $stmt = $pdo->prepare("
                INSERT INTO admin_logs 
                    (user_id, action, details, ip_address, user_agent, created_at)
                VALUES 
                    (:user_id, :action, :details, :ip, :ua, NOW())
            ");

            $stmt->execute([
                ':user_id' => isset($user['id']) ? (int)$user['id'] : null,
                ':action'  => $action,
                ':details' => $details,
                ':ip'      => $_SERVER['REMOTE_ADDR']     ?? null,
                ':ua'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Throwable $e) {
            @error_log('[admin_log] ' . $e->getMessage());
        }
    }
}
