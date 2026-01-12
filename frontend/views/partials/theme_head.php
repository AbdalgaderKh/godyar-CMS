<?php
/**
 * Front Theme Head Loader
 * - يضمن تطبيق الثيم (بما فيه Default) على كل الصفحات حتى تلك التي لا تستخدم header.php الموحد.
 * - يعتمد على: BASE_URL/ROOT_URL و $siteSettings (من includes/site_settings.php).
 */
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '/godyar';
$rootUrl = defined('ROOT_URL') ? rtrim((string)ROOT_URL, '/') : $baseUrl;

// Resolve settings (supports multiple keys)
$rawSettings = (isset($siteSettings['raw']) && is_array($siteSettings['raw'])) ? $siteSettings['raw'] : [];
$themeFront = (string)(
    $siteSettings['frontend_theme']
    ?? $siteSettings['settings.frontend_theme']
    ?? ($rawSettings['frontend_theme'] ?? '')
    ?? $siteSettings['theme_front']
    ?? ($rawSettings['theme.front'] ?? '')
    ?? ($siteSettings['theme.front'] ?? 'default')
);
$themeFront = strtolower(trim($themeFront)) ?: 'default';
$themeFront = preg_replace('/^theme-/', '', $themeFront);
$themeFront = preg_replace('/[^a-z0-9_-]/', '', $themeFront) ?: 'default';

// Front preset (Default vs Custom)
$frontPreset = (string)($siteSettings['front_preset'] ?? $siteSettings['settings.front_preset'] ?? ($rawSettings['front_preset'] ?? '') ?? ($rawSettings['settings.front_preset'] ?? ''));
$frontPreset = strtolower(trim($frontPreset)) ?: 'default';

$themeCoreDisk = ROOT_PATH . '/assets/css/themes/theme-core.css';
$themeCoreHref = $baseUrl . '/assets/css/themes/theme-core.css';
$themeCoreV = is_file($themeCoreDisk) ? (string)@filemtime($themeCoreDisk) : (string)time();
echo '<link rel="stylesheet" href="' . h($themeCoreHref) . '?v=' . h($themeCoreV) . '">' . "\n";

// Optional theme file for non-default
$hasThemeCss = false;
if ($themeFront !== 'default') {
    $themeCssDisk = ROOT_PATH . '/assets/css/themes/theme-' . $themeFront . '.css';
    if (is_file($themeCssDisk)) {
        $hasThemeCss = true;
        $themeCssHref = $baseUrl . '/assets/css/themes/theme-' . $themeFront . '.css';
        $v = (string)@filemtime($themeCssDisk);
        echo '<link rel="stylesheet" href="' . h($themeCssHref) . ($v !== '' ? ('?v=' . h($v)) : '') . '">' . "\n";
    }
}

// If no explicit theme file: allow settings color to override core fallbacks
$primaryColor = (string)($siteSettings['theme_primary'] ?? $siteSettings['primary_color'] ?? $siteSettings['settings.theme_primary'] ?? '');
$primaryDark  = (string)($siteSettings['theme_primary_dark'] ?? $siteSettings['primary_dark'] ?? '');
$primaryRgb   = (string)($siteSettings['theme_primary_rgb'] ?? $siteSettings['primary_rgb'] ?? '');

// Normalize RGB if provided as hex
if ($primaryColor && !$primaryRgb && preg_match('/^#?[0-9a-f]{6}$/i', $primaryColor)) {
    $hex = ltrim($primaryColor, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $primaryRgb = $r . ',' . $g . ',' . $b;
}
if ($primaryColor && !$primaryDark && preg_match('/^#?[0-9a-f]{6}$/i', $primaryColor)) {
    // simple darken by 20%
    $hex = ltrim($primaryColor, '#');
    $r = max(0, (int)round(hexdec(substr($hex, 0, 2)) * 0.8));
    $g = max(0, (int)round(hexdec(substr($hex, 2, 2)) * 0.8));
    $b = max(0, (int)round(hexdec(substr($hex, 4, 2)) * 0.8));
    $primaryDark = sprintf('#%02X%02X%02X', $r, $g, $b);
}

// Inject only if there is NO theme css file (to not override theme-red/...).
if (!$hasThemeCss) {
    if ($frontPreset !== 'custom') {
        // Force Default palette (black/white) across all pages
        echo "<style>:root{--primary:#111111;--primary-rgb:17,17,17;--primary-dark:#000000;}</style>
";
    } elseif ($primaryColor) {
        // Custom palette chosen by admin
        echo "<style>:root{" .
             "--primary:" . h($primaryColor) . ";" .
             ($primaryRgb ? ("--primary-rgb:" . h($primaryRgb) . ";") : "") .
             ($primaryDark ? ("--primary-dark:" . h($primaryDark) . ";") : "") .
             "}</style>
";
    }
}
?>