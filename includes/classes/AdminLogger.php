<?php

/**
 * AdminLogger
 * تسجيل الأحداث الإدارية في جدول admin_logs
 */

use Godyar\DB;
use PDO;

class AdminLogger
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * log
     *
     * @param string      $action      نوع الحدث (مثال: login_success, news_create)
     * @param string|null $entityType  نوع الكيان (news, user, settings)
     * @param int|null    $entityId    رقم الكيان
     * @param array|null  $extra       بيانات إضافية (مثلاً ['title' => '...', 'status' => 'published'])
     */
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $extra = null, ?PDO $pdo = null): void
    {
        // Support DI (preferred): pass $pdo from caller.
        // Backward compatibility: if not passed, we use the unified DB layer.
        if (!$pdo instanceof PDO) {
            try {
                $pdo = DB::pdo();
            } catch (\Throwable $e) {
                @error_log('[AdminLogger] PDO not available: ' . $e->getMessage());
                return;
            }
        }

        // نحاول نجيب المستخدم الحالي من الجلسة
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        $userId = null;
        if (!empty($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        } elseif (!empty($_SESSION['user']['id'])) {
            $userId = (int)$_SESSION['user']['id'];
        }

        $ip        = $_SERVER['REMOTE_ADDR']     ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // نحول extra إلى JSON لو موجود
        $details = null;
        if (!empty($extra)) {
            $details = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            $sql = "INSERT INTO admin_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details)
                    VALUES (:user_id, :action, :entity_type, :entity_id, :ip, :ua, :details)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'    => $userId,
                ':action'     => $action,
                ':entity_type'=> $entityType,
                ':entity_id'  => $entityId,
                ':ip'         => $ip,
                ':ua'         => $userAgent,
                ':details'    => $details,
            ]);

        } catch (\Throwable $e) {
            @error_log('[AdminLogger] log error: ' . $e->getMessage());
        }
    }
}
