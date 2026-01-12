<?php
declare(strict_types=1);

/**
 * manifest.php — Dynamic Web App Manifest (PWA)
 *
 * مهم:
 * - هذا الملف لا يعتمد على includes/bootstrap.php لتفادي session headers (Pragma/Expires) التي تمنع الكاش.
 * - يدعم ?lang=ar|en|fr وكذلك المسار /ar/manifest.webmanifest عبر .htaccess.
 */

// ---------- Language ----------
$lang = 'ar';

if (isset($_GET['lang'])) {
    $q = strtolower(trim((string)$_GET['lang']));
    if (in_array($q, ['ar', 'en', 'fr'], true)) {
        $lang = $q;
    }
}

// ---------- Base prefix (subfolder installs) ----------
$script = (string)($_SERVER['SCRIPT_NAME'] ?? '/manifest.php');
$basePrefix = rtrim(str_replace('\\', '/', dirname($script)), '/');
if ($basePrefix === '.' || $basePrefix === '/') {
    $basePrefix = '';
}

$prefix = ($basePrefix !== '' ? $basePrefix : '');
$startUrl = $prefix . '/' . $lang . '/?source=pwa';
$scope    = $prefix . '/' . $lang . '/';

$icon192 = $prefix . '/assets/images/icons/icon-192.png';
$icon512 = $prefix . '/assets/images/icons/icon-512.png';

$manifest = [
    'name' => 'Godyar News',
    'short_name' => 'Godyar',
    'start_url' => $startUrl,
    'scope' => $scope,
    'display' => 'standalone',
    'background_color' => '#0b1220',
    'theme_color' => '#0b1220',
    'icons' => [
        ['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => $icon512, 'sizes' => '512x512', 'type' => 'image/png'],
    ],
    'shortcuts' => [
        ['name' => 'Saved', 'short_name' => 'Saved', 'url' => $prefix . '/' . $lang . '/saved'],
        ['name' => 'Categories', 'short_name' => 'Categories', 'url' => $prefix . '/' . $lang . '/categories'],
    ],
];

// ---------- Headers ----------
header('Content-Type: application/manifest+json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// manifest يتغير نادرًا، لكن نريد كاش معقول + قابل للتحديث
header('Cache-Control: public, max-age=3600');

// إزالة أي headers قد تأتي من إعدادات السيرفر/السيشن (احتياطًا)
if (function_exists('header_remove')) {
    @header_remove('Pragma');
    @header_remove('Expires');
}

echo json_encode(
    $manifest,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
