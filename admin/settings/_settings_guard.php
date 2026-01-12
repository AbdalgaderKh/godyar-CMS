<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('settings_get')) {
    function settings_get(string $key, $default = '') {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return $default;
        }
        try {
            $stmt = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` = ? LIMIT 1");
            $stmt->execute([$key]);
            $v = $stmt->fetchColumn();
            return ($v === false) ? $default : $v;
        } catch (Throwable $e) {
            @error_log('[settings_get] ' . $e->getMessage());
            return $default;
        }
    }
}

if (!function_exists('settings_save')) {
    function settings_save(array $pairs): void {
        global $pdo;
        if (!($pdo instanceof PDO) || empty($pairs)) {
            return;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO `settings` (`key`, `value`)
                 VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = CURRENT_TIMESTAMP"
            );

            foreach ($pairs as $k => $v) {
                $stmt->execute([
                    ':k' => (string)$k,
                    ':v' => (string)$v,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @error_log('[settings_save] ' . $e->getMessage());
        }
    }
}
