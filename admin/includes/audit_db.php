<?php
declare(strict_types=1);

/**
 * DB-based admin audit logger.
 * Fail-open: never blocks admin flow.
 */
function admin_audit_db(string $action, array $meta = []): void
{
    try {
        if (!class_exists('Godyar\\DB') || !method_exists('Godyar\\DB', 'pdo')) return;
        $pdo = \Godyar\DB::pdo();

        $userId = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log (user_id, action, ip, meta, created_at)
            VALUES (:user_id, :action, :ip, :meta, NOW())
        ");
        $stmt->execute([
            ':user_id' => is_numeric($userId) ? (int)$userId : null,
            ':action'  => $action,
            ':ip'      => $ip,
            ':meta'    => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (\Throwable $e) {
        // ignore
    }
}
