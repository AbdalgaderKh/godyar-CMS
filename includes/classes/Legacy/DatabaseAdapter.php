<?php
declare(strict_types=1);

namespace Godyar\Legacy;

use PDO;
use PDOStatement;

/**
 * DatabaseAdapter (Legacy compatibility)
 *
 * كثير من أجزاء المشروع القديمة كانت تعتمد على كائن $database مع دوال:
 *   - query($sql, $params)
 *   - lastInsertId()
 *
 * هذا الـ Adapter يبني نفس الواجهة فوق PDO، مع FETCH_ASSOC افتراضيًا.
 */
final class DatabaseAdapter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @param string $sql
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId(): string
    {
        return (string)$this->pdo->lastInsertId();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
