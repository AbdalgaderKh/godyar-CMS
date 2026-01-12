<?php
declare(strict_types=1);

/**
 * هيلبر مركزي لتحميل إعدادات الموقع وتحضير هوية الواجهة الأمامية.
 * يمكن استدعاؤه من أي Controller في الواجهة الأمامية.
 */

if (!function_exists('gdy_setting')) {
    function gdy_setting(array $settings, string $key, $default = ''): string {
        return isset($settings[$key]) && $settings[$key] !== ''
            ? (string)$settings[$key]
            : (string)$default;
    }
}

if (!function_exists('gdy_setting_int')) {
    function gdy_setting_int(array $settings, string $key, int $default, int $min, int $max): int {
        if (!isset($settings[$key]) || $settings[$key] === '') {
            return $default;
        }
        $v = (int)$settings[$key];
        if ($v < $min) {
            $v = $min;
        }
        if ($v > $max) {
            $v = $max;
        }
        return $v;
    }
}

if (!function_exists('gdy_load_settings')) {
    /**
     * تحميل جدول settings إلى مصفوفة بسيطة key => value.
     */
    function gdy_load_settings(?\PDO $pdo): array {
        $settings = [];
        if (!$pdo instanceof \PDO) {
            return $settings;
        }

        try {
            $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $settings[$row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {
            @error_log('[Settings] load error: ' . $e->getMessage());
        }

        return $settings;
    }
}

if (!function_exists('gdy_prepare_frontend_options')) {
    /**
     * تجهيز متغيرات الهوية والثيم والنصوص الخاصة بالواجهة الأمامية
     * اعتمادًا على الإعدادات المخزنة.
     */
    function gdy_prepare_frontend_options(array $settings): array {
        // الهوية الأساسية
        $siteName     = gdy_setting($settings, 'site_name', 'Godyar News');
        $siteTagline  = gdy_setting($settings, 'site_tagline', 'منصة إخبارية متكاملة');
        $siteLogo     = gdy_setting($settings, 'site_logo', '');
        // الألوان: نعتمد مفاتيح الثيم الحديثة أولاً (theme.primary) ثم legacy (primary_color)
        $primaryColor = gdy_setting($settings, 'theme.primary', gdy_setting($settings, 'primary_color', '#111111'));

        // نصوص الواجهة القابلة للتعديل
        $searchPlaceholder      = gdy_setting($settings, 'search_placeholder',        'ابحث عن خبر أو موضوع...');
        $homeLatestTitle        = gdy_setting($settings, 'home_latest_title',         'أحدث الأخبار');
        $homeFeaturedTitle      = gdy_setting($settings, 'home_featured_title',       'أهم الأخبار');
        $homeTabsTitle          = gdy_setting($settings, 'home_tabs_title',           'أقسام الموقع');
        $homeMostReadTitle      = gdy_setting($settings, 'home_most_read_title',      'الأكثر قراءة');
        $homeMostCommentedTitle = gdy_setting($settings, 'home_most_commented_title', 'الأكثر تعليقًا');
        $homeRecommendedTitle   = gdy_setting($settings, 'home_recommended_title',    'مقترحة لك');
        $carbonBadgeText        = gdy_setting(
            $settings,
            'carbon_badge_text',
            'نلتزم بالمساعدة على تقليل انبعاث الكربون في بنيتنا التقنية.'
        );

        // إعدادات عدد العناصر
        $homeLatestLimit   = gdy_setting_int($settings, 'home_latest_limit',    9, 3, 30);
        $homeFeaturedLimit = gdy_setting_int($settings, 'home_featured_limit',  4, 1, 10);
        $homeTabsLimit     = gdy_setting_int($settings, 'home_tabs_limit',      6, 3, 20);
        $mostReadLimit     = gdy_setting_int($settings, 'most_read_limit',      5, 3, 20);

        // إعدادات ميزات الواجهة
        $enableMostRead      = gdy_setting($settings, 'enable_most_read',      '1') === '1';
        $enableMostCommented = gdy_setting($settings, 'enable_most_commented', '1') === '1';
        $enableRelatedNews   = gdy_setting($settings, 'enable_related_news',   '1') === '1';
        $showCarbonBadge     = gdy_setting($settings, 'show_carbon_badge',     '1') === '1';

        // حساب لون داكن من اللون الأساسي للاستخدام في التدرجات
        $primaryHex = ltrim($primaryColor, '#');
        if (strlen($primaryHex) === 6) {
            $r = max(0, hexdec(substr($primaryHex, 0, 2)) - 30);
            $g = max(0, hexdec(substr($primaryHex, 2, 2)) - 30);
            $b = max(0, hexdec(substr($primaryHex, 4, 2)) - 30);
            $primaryDark = sprintf('#%02x%02x%02x', $r, $g, $b);
        } else {
            $primaryDark = '#0369a1';
        }

        // إذا تم تعريف primary_dark صراحة في الإعدادات نستخدمه (أولوية أعلى)
        $explicitDark = gdy_setting($settings, 'theme.primary_dark', '');
        if ($explicitDark !== '') {
            $primaryDark = $explicitDark;
        }
        // ثيم الواجهة
        // ثيم الواجهة (المفتاح المطلوب: frontend_theme) مع fallback لمفتاح legacy
        $frontendTheme = gdy_setting($settings, 'frontend_theme', gdy_setting($settings, 'settings.frontend_theme', gdy_setting($settings, 'theme.front', 'default')));
        $frontendTheme = strtolower(trim((string)$frontendTheme));

        // Normalize to CSS class: theme-{name}
        // Supported: default, light, beige, red, blue, green, brown (plus legacy theme-ocean/theme-sunset)
        $themeClass = 'theme-default';
        if ($frontendTheme === '' || $frontendTheme === 'default') {
            $themeClass = 'theme-default';
        } elseif (in_array($frontendTheme, ['light','beige','red','blue','green','brown'], true)) {
            $themeClass = 'theme-' . $frontendTheme;
        } elseif ($frontendTheme === 'theme-ocean') {
            $themeClass = 'theme-ocean';
        } elseif ($frontendTheme === 'theme-sunset') {
            $themeClass = 'theme-sunset';
        } else {
            // If value already looks like theme-xxx
            if (strpos($frontendTheme, 'theme-') === 0) {
                $themeClass = $frontendTheme;
            }
        }

        return [
            'siteName'              => $siteName,
            'siteTagline'           => $siteTagline,
            'siteLogo'              => $siteLogo,
            'primaryColor'          => $primaryColor,
            'searchPlaceholder'     => $searchPlaceholder,
            'homeLatestTitle'       => $homeLatestTitle,
            'homeFeaturedTitle'     => $homeFeaturedTitle,
            'homeTabsTitle'         => $homeTabsTitle,
            'homeMostReadTitle'     => $homeMostReadTitle,
            'homeMostCommentedTitle'=> $homeMostCommentedTitle,
            'homeRecommendedTitle'  => $homeRecommendedTitle,
            'carbonBadgeText'       => $carbonBadgeText,
            'homeLatestLimit'       => $homeLatestLimit,
            'homeFeaturedLimit'     => $homeFeaturedLimit,
            'homeTabsLimit'         => $homeTabsLimit,
            'mostReadLimit'         => $mostReadLimit,
            'enableMostRead'        => $enableMostRead,
            'enableMostCommented'   => $enableMostCommented,
            'enableRelatedNews'     => $enableRelatedNews,
            'showCarbonBadge'       => $showCarbonBadge,
            'primaryDark'           => $primaryDark,
            'frontendTheme'         => $frontendTheme,
            'frontend_theme'        => $frontendTheme,
            'themeClass'            => $themeClass,
        ];
    }
}
