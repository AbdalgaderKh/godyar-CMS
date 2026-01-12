<?php
namespace Godyar\Services;

use PDO;
use Godyar\DB;

/**
 * SettingsService
 *
 * Step 15:
 * - دعم Constructor Injection (المفضل): new SettingsService(PDO $pdo)
 * - الإبقاء على static methods للتوافق الخلفي.
 */
final class SettingsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getValue(string $key, $default = null)
    {
        try {
            $st = $this->pdo->prepare("SELECT `value` FROM settings WHERE `key`=:k");
            $st->execute([':k' => $key]);
            $val = $st->fetchColumn();
            if ($val === false) {
                return $default;
            }
            $decoded = json_decode((string)$val, true);
            return ($decoded === null && json_last_error() !== JSON_ERROR_NONE) ? $val : $decoded;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function setValue(string $key, $value): void
    {
        $val = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string)$value;

        $st = $this->pdo->prepare(
            "INSERT INTO settings(`key`,`value`) VALUES(:k,:v) ON DUPLICATE KEY UPDATE `value`=:v2"
        );
        $st->execute([':k' => $key, ':v' => $val, ':v2' => $val]);
    }

    /** @param array<string, mixed> $pairs */
    public function setMany(array $pairs): void
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare(
                "INSERT INTO settings(`key`,`value`) VALUES(:k,:v) ON DUPLICATE KEY UPDATE `value`=:v2"
            );
            foreach ($pairs as $k => $v) {
                $val = is_array($v)
                    ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : (string)$v;
                $st->execute([':k' => $k, ':v' => $val, ':v2' => $val]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ------------------------
    // Backward-compatible static API
    // ------------------------
    public static function get(string $key, $default = null)
    {
        return (new self(DB::pdo()))->getValue($key, $default);
    }

    public static function set(string $key, $value): void
    {
        (new self(DB::pdo()))->setValue($key, $value);
    }

    /** @param array<string, mixed> $pairs */
    public static function many(array $pairs): void
    {
        (new self(DB::pdo()))->setMany($pairs);
    }
}
