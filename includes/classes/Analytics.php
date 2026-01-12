<?php
namespace Godyar\Analytics;

class Analytics {
    private \PDO $db;

    public function __construct(\PDO $pdo) {
        $this->db = $pdo;
    }

    public function trackVisit(string $page, string $ip, ?string $ua, ?string $ref = null): void {
        $sql = "INSERT INTO visits (page, ip_address, user_agent, referrer, visit_time)
                VALUES (:page, :ip, :ua, :ref, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':page' => $page,
            ':ip'   => $ip,
            ':ua'   => $ua,
            ':ref'  => $ref
        ]);
    }

    public function getDailyVisits(string $period = 'month'): array {
        $dateCondition = $this->dateCondition($period);
        $sql = "SELECT DATE(visit_time) AS visit_date, COUNT(*) AS total_visits
                FROM visits
                WHERE {$dateCondition}
                GROUP BY DATE(visit_time)
                ORDER BY visit_date DESC";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTopPages(string $period = 'month', int $limit = 10): array {
        $dateCondition = $this->dateCondition($period);
        $sql = "SELECT page, COUNT(*) AS visits
                FROM visits
                WHERE {$dateCondition}
                GROUP BY page
                ORDER BY visits DESC
                LIMIT :lim";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function dateCondition(string $period): string {
        // PHP 7.4 compatibility: avoid "match" (PHP 8+)
        switch ($period) {
            case 'today':
                return "DATE(visit_time) = CURDATE()";
            case 'week':
                return "visit_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "visit_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            default:
                return "1=1";
        }
    }
}
