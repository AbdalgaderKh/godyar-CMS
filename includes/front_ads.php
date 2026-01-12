<?php
declare(strict_types=1);

/**
 * godyar/includes/front_ads.php
 * وظائف مساعدة لعرض الإعلانات في الواجهات الأمامية.
 */

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * جلب وإظهار الإعلانات النشطة حسب position
 *
 * @param string $position  مثل: header, sidebar_top, sidebar_bottom, homepage_between_blocks, article_top ...
 * @param int    $limit     عدد الإعلانات (افتراضياً 1)
 */
function godyar_render_ads(string $position, int $limit = 1): void
{
    $pdo = gdy_pdo_safe();
    if (!($pdo instanceof PDO)) {
        return;
    }

    $now = date('Y-m-d H:i:s');

    // توافق مع نسخ قاعدة بيانات قديمة: position أو location
    $posCol = 'position';
    try {
        if (function_exists('db_column_exists')) {
            if (!db_column_exists($pdo, 'ads', 'position') && db_column_exists($pdo, 'ads', 'location')) {
                $posCol = 'location';
            }
        }
    } catch (Throwable $e) { $posCol = 'position'; }

    try {
        $sql = "
            SELECT *
            FROM ads
            WHERE {$posCol} = :pos
              AND is_active = 1
              AND (start_at IS NULL OR start_at <= :now)
              AND (end_at   IS NULL OR end_at   >= :now)
              AND (max_impressions IS NULL OR impressions < max_impressions)
            ORDER BY id DESC
            LIMIT :limit
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':pos',  $position, PDO::PARAM_STR);
        $stmt->bindValue(':now',  $now,      PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit,   PDO::PARAM_INT);
        $stmt->execute();

        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        @error_log('[Front Ads] fetch: ' . $e->getMessage());
        return;
    }

    if (empty($ads)) {
        return;
    }

    foreach ($ads as $ad) {
        // زيادة عدد المشاهدات (بسيطة)
        try {
            $up = $pdo->prepare("UPDATE ads SET impressions = impressions + 1 WHERE id = :id");
            $up->execute([':id' => (int)$ad['id']]);
        } catch (Throwable $e) {
            @error_log('[Front Ads] inc impressions: ' . $e->getMessage());
        }

        echo '<div class="godyar-ad-block my-2 text-center">';
        if (!empty($ad['html_code'])) {
            // كود HTML خارجي (Adsense..). لا نعمل له escape
            echo $ad['html_code'];
        } elseif (!empty($ad['image_path'])) {
            $img  = h($ad['image_path']);
            $alt  = h($ad['title'] ?? '');
            $href = !empty($ad['target_url']) ? h($ad['target_url']) : '';
            if ($href) {
                echo '<a href="'.$href.'" target="_blank" rel="noopener">';
            }
            echo '<img src="'.$img.'" alt="'.$alt.'" class="img-fluid">';
            if ($href) {
                echo '</a>';
            }
        }
        echo '</div>';
    }
}
