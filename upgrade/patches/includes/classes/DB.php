<?php
namespace Godyar;

require_once dirname(__DIR__) . '/env.php';

class DB {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $host = DB_HOST;
            $dbname = DB_NAME;
            $username = DB_USER;
            $password = DB_PASS;

            // Support DB_PORT and DB_DSN (already provided by includes/env.php)
            $port = defined('DB_PORT') && DB_PORT !== '' ? (string)DB_PORT : '3306';

            if (defined('DB_DSN') && is_string(DB_DSN) && trim(DB_DSN) !== '') {
                $dsn = trim(DB_DSN);
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            }

            $this->connection = new \PDO(
                $dsn,
                $username,
                $password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (\PDOException $e) {
            // Keep details in error_log only (do NOT expose to users)
            error_log("DB connect failed: " . $e->getMessage());
            throw new \Exception("فشل الاتصال بقاعدة البيانات");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Return a PDO connection (compat helper for older code that expects DB::pdo()).
     */
    public static function pdo(): \PDO
    {
        return self::getInstance()->getConnection();
    }
}
