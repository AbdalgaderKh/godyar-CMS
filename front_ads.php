<?php
// front_ads.php - عرض الإعلانات في الواجهة الأمامية

function display_ad($location = 'header_top') {
    global $pdo;
    
    if (!$pdo) return '';
    
    try {
        $sql = "SELECT * FROM ads 
                WHERE location = :location 
                AND is_active = 1 
                AND (starts_at IS NULL OR starts_at <= NOW()) 
                AND (ends_at IS NULL OR ends_at >= NOW())
                AND (max_clicks = 0 OR click_count < max_clicks)
                AND (max_views = 0 OR view_count < max_views)
                ORDER BY is_featured DESC, created_at DESC 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':location' => $location]);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ad) return '';
        
        // تحديث عدد المشاهدات
        $update_sql = "UPDATE ads SET view_count = view_count + 1 WHERE id = :id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([':id' => $ad['id']]);
        
        // بناء HTML للإعلان
        $html = '<div class="godyar-ad" data-ad-id="' . $ad['id'] . '">';
        
        if (!empty($ad['target_url'])) {
            $html .= '<a href="track_click.php?ad_id=' . $ad['id'] . '&redirect=' . urlencode($ad['target_url']) . '" class="ad-link" target="_blank">';
        }
        
        if (!empty($ad['image_url'])) {
            $html .= '<img src="' . htmlspecialchars($ad['image_url']) . '" alt="' . htmlspecialchars($ad['title']) . '" class="ad-image">';
        } else {
            $html .= '<div class="ad-text">' . htmlspecialchars($ad['title']) . '</div>';
        }
        
        if (!empty($ad['target_url'])) {
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
        
    } catch (Exception $e) {
        error_log('Ad display error: ' . $e->getMessage());
        return '';
    }
}

// دالة لتتبع النقرات
function track_ad_click($ad_id) {
    global $pdo;
    
    if (!$pdo) return false;
    
    try {
        $sql = "UPDATE ads SET click_count = click_count + 1 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':id' => $ad_id]);
    } catch (Exception $e) {
        error_log('Ad click tracking error: ' . $e->getMessage());
        return false;
    }
}
?>