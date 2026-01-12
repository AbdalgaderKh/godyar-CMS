<?php
declare(strict_types=1);

// robots.php — output robots.txt dynamically + points to sitemap
require_once __DIR__ . '/includes/bootstrap.php';

$robots = 'index,follow';
try {
    $pdo = gdy_pdo_safe();
    if ($pdo instanceof PDO && class_exists('Godyar\\Services\\SettingsService')) {
        $svc = new Godyar\Services\SettingsService($pdo);
        $robots = (string)$svc->getValue('seo.robots', $robots);
    }
} catch (Throwable $e) {
    // ignore
}

$base = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
$sitemap = $base ? ($base . '/sitemap.xml') : '/sitemap.xml';

header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /storage/\n";
echo "Sitemap: " . $sitemap . "\n";
if ($robots !== '' && strtolower(trim($robots)) !== 'index,follow') {
    // إذا رغبت بمنع الأرشفة من لوحة التحكم
    if (stripos($robots, 'noindex') !== false) {
        echo "# NOTE: site robots meta suggests noindex\n";
    }
}
