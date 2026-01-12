<?php
declare(strict_types=1);

namespace Godyar\Services;

use PDO;
use Throwable;

/**
 * AdService (Schema-compatible)
 * - يدعم اختلاف أسماء الأعمدة في جدول ads
 * - يعيد HTML جاهز لعرض الإعلان في الواجهة
 */
final class AdService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Render ad by location.
     * @param string $location example: home_under_featured_video
     * @param string $baseUrl  site base URL (for click redirect fallback)
     */
    public function render(string $location, string $baseUrl = ''): string
    {
        $location = trim($location);
        if ($location === '') return '';

        $baseUrl = rtrim($baseUrl, '/');

        try {
            if (!$this->tableExists('ads')) {
                return '';
            }

            $cols = $this->getColumns('ads');

            $colId       = $this->pick($cols, ['id','ad_id']) ?? 'id';
            $colTitle    = $this->pick($cols, ['title','name','ad_title']) ?? null;
            $colLocation = $this->pick($cols, ['location','loc','placement','position','slot']) ?? null;

            // image / url
            $colImage    = $this->pick($cols, ['image','image_url','img','banner','banner_url','picture','photo','file','path']) ?? null;
            $colUrl      = $this->pick($cols, ['url','target_url','link','href','redirect_url','click_url']) ?? null;

            // html ads (optional)
            $colType     = $this->pick($cols, ['ad_type','type','content_type']) ?? null;
            $colContent  = $this->pick($cols, ['content','html','html_code','code','body']) ?? null;

            // is_active (optional)
            $colActive   = $this->pick($cols, ['is_active','active','status','enabled']) ?? null;

            // date range (optional)
            $colStart    = $this->pick($cols, ['starts_at','start_at','start_date','date_start','from_date','campaign_start']) ?? null;
            $colEnd      = $this->pick($cols, ['ends_at','end_at','end_date','date_end','to_date','campaign_end']) ?? null;

            // Build WHERE
            $where = [];
            $params = [];

            if ($colLocation) {
                $where[] = "`$colLocation` = :loc";
                $params[':loc'] = $location;
            }

            if ($colActive) {
                // common: is_active=1 OR status='active'
                if ($colActive === 'status') {
                    $where[] = "(`$colActive` = 'active' OR `$colActive` = 1)";
                } else {
                    $where[] = "(`$colActive` = 1)";
                }
            }

            if ($colStart) {
                $where[] = "(`$colStart` IS NULL OR `$colStart` = '' OR `$colStart` <= NOW())";
            }
            if ($colEnd) {
                $where[] = "(`$colEnd` IS NULL OR `$colEnd` = '' OR `$colEnd` >= NOW())";
            }

            $sql = "SELECT * FROM ads";
            if ($where) $sql .= " WHERE " . implode(' AND ', $where);
            $sql .= " ORDER BY `$colId` DESC LIMIT 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $ad = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ad) return '';

            $title = $colTitle && isset($ad[$colTitle]) ? trim((string)$ad[$colTitle]) : '';
            $image = $colImage && isset($ad[$colImage]) ? trim((string)$ad[$colImage]) : '';
            $url   = $colUrl && isset($ad[$colUrl]) ? trim((string)$ad[$colUrl]) : '';
            $type  = $colType && isset($ad[$colType]) ? strtolower(trim((string)$ad[$colType])) : '';
            $html  = $colContent && isset($ad[$colContent]) ? (string)$ad[$colContent] : '';

            // Normalize image URL (allow relative)
            if ($image !== '' && !preg_match('~^https?://~i', $image) && $baseUrl !== '') {
                $image = $baseUrl . '/' . ltrim($image, '/');
            }

            $safeTitle = $this->h($title !== '' ? $title : 'Advertisement');

            $inner = '';
            if ($type === 'html' || $type === 'code') {
                $inner = '<div class="gdy-ad-html">'.$html.'</div>';
            } else {
                if ($image === '') return '';
                $imgTag = '<img src="'.$this->h($image).'" alt="'.$safeTitle.'" loading="lazy" decoding="async">';
                if ($url !== '') {
                    $inner = '<a class="gdy-ad-link" href="'.$this->h($url).'" target="_blank" rel="sponsored noopener noreferrer">'.$imgTag.'</a>';
                } else {
                    $inner = '<div class="gdy-ad-link">'.$imgTag.'</div>';
                }
            }

            return '<div class="gdy-ad-slot gdy-ad-slot--'.$this->h($location).'">'.$inner.'</div>';
        } catch (Throwable $e) {
            @error_log('[AdService] render error: ' . $e->getMessage());
            return '';
        }
    }

    private function pick(array $cols, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return null;
    }

    private function h(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE :t");
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return string[] */
    private function getColumns(string $table): array
    {
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `$table`");
            $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
            return is_array($cols) ? $cols : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
