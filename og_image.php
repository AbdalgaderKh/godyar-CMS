<?php
declare(strict_types=1);

/**
 * Dynamic OG image generator
 * URL: /og_image.php?type=category|tag|news&title=...&subtitle=...
 */

if (!function_exists('h')) {
    function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

$type = isset($_GET['type']) ? strtolower((string)$_GET['type']) : 'page';
$title = isset($_GET['title']) ? trim((string)$_GET['title']) : '';
$subtitle = isset($_GET['subtitle']) ? trim((string)$_GET['subtitle']) : '';

if ($title === '') {
    $title = ($type === 'category') ? 'تصنيف' : (($type === 'tag') ? 'وسم' : 'Godyar News');
}

$title = mb_substr($title, 0, 80, 'UTF-8');
$subtitle = mb_substr($subtitle, 0, 120, 'UTF-8');

$siteName = 'Godyar News';
$primary = '#0ea5e9';

// حاول قراءة إعدادات الموقع
try {
    $boot = __DIR__ . '/includes/bootstrap.php';
    if (is_file($boot)) require_once $boot;

    if (function_exists('get_site_settings')) {
        $s = get_site_settings();
        if (!empty($s['site_name'])) $siteName = (string)$s['site_name'];
        if (!empty($s['theme_primary'])) $primary = (string)$s['theme_primary'];
    }
} catch (Throwable $e) {
    // ignore
}

function hex2rgb(string $hex): array {
    $hex = trim($hex);
    if ($hex === '') return [14, 165, 233];
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6) return [14, 165, 233];
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}

$w = 1200;
$h = 630;

if (!extension_loaded('gd')) {
    header('Content-Type: image/svg+xml; charset=utf-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'"><rect width="100%" height="100%" fill="#0ea5e9"/><text x="60" y="170" font-size="64" fill="#fff" font-family="Arial">'.h($title).'</text><text x="60" y="250" font-size="34" fill="#e2e8f0" font-family="Arial">'.h($siteName).'</text></svg>';
    exit;
}

$im = imagecreatetruecolor($w, $h);
imagealphablending($im, true);
imagesavealpha($im, true);

[$r,$g,$b] = hex2rgb($primary);
$bg = imagecolorallocate($im, 245, 250, 255);
imagefill($im, 0, 0, $bg);

// gradient overlay
for ($y=0; $y<$h; $y++) {
    $t = $y / $h;
    $rr = (int)round(245*(1-$t) + $r*$t);
    $gg = (int)round(250*(1-$t) + $g*$t);
    $bb = (int)round(255*(1-$t) + $b*$t);
    $col = imagecolorallocate($im, $rr, $gg, $bb);
    imageline($im, 0, $y, $w, $y, $col);
}

// soft card
$cardCol = imagecolorallocatealpha($im, 255, 255, 255, 18);
imagefilledrectangle($im, 55, 70, $w-55, $h-70, $cardCol);

$white = imagecolorallocate($im, 255, 255, 255);
$dark  = imagecolorallocate($im, 15, 23, 42);
$muted = imagecolorallocate($im, 100, 116, 139);

// accent pill
$accent = imagecolorallocatealpha($im, $r, $g, $b, 8);
imagefilledrectangle($im, 80, 95, 320, 140, $accent);

$label = ($type === 'category') ? 'CATEGORY' : (($type === 'tag') ? 'TAG' : strtoupper($type));
$font = __DIR__ . '/assets/fonts/DejaVuSans-Bold.ttf';
$useTtf = is_file($font) && function_exists('imagettftext');

if ($useTtf) {
    imagettftext($im, 22, 0, 95, 128, $dark, $font, $label);
} else {
    imagestring($im, 5, 95, 110, $label, $dark);
}

// helper to wrap text
function wrap_text(string $text, int $maxChars): array {
    $words = preg_split('~\s+~u', trim($text)) ?: [];
    $lines = [];
    $line = '';
    foreach ($words as $w) {
        $try = $line === '' ? $w : ($line . ' ' . $w);
        if (mb_strlen($try, 'UTF-8') <= $maxChars) {
            $line = $try;
        } else {
            if ($line !== '') $lines[] = $line;
            $line = $w;
        }
        if (count($lines) >= 3) break;
    }
    if ($line !== '' && count($lines) < 3) $lines[] = $line;
    return $lines;
}

// Title
$lines = wrap_text($title, 26);
$y = 240;
$size = 58;
if (count($lines) >= 3) $size = 50;
if (count($lines) === 1 && mb_strlen($title,'UTF-8') <= 18) $size = 64;

if ($useTtf) {
    foreach ($lines as $ln) {
        imagettftext($im, $size, 0, 90, $y, $dark, $font, $ln);
        $y += (int)round($size * 1.15);
    }
} else {
    imagestring($im, 5, 90, $y-25, $title, $dark);
    $y += 60;
}

// Subtitle
if ($subtitle !== '') {
    $subLines = wrap_text($subtitle, 40);
    $yy = $y + 10;
    if ($useTtf) {
        foreach ($subLines as $ln) {
            imagettftext($im, 30, 0, 90, $yy, $muted, $font, $ln);
            $yy += 38;
        }
    } else {
        imagestring($im, 4, 90, $yy-18, $subtitle, $muted);
    }
}

// Footer / site name
$footerText = $siteName;
if ($useTtf) {
    imagettftext($im, 28, 0, 90, $h-110, $white, $font, $footerText);
} else {
    imagestring($im, 5, 90, $h-140, $footerText, $white);
}

// output
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('Pragma: public');

imagepng($im);
imagedestroy($im);
