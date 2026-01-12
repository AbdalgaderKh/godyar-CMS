<?php
declare(strict_types=1);

/**
 * og_news.php — Dynamic OG image for a news item by ID
 * Pretty URL (Apache): /og/news/{id}.png  ->  og_news.php?id={id}
 */

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

require_once ROOT_PATH . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/site_settings.php';

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0 || !($pdo instanceof PDO)) {
    http_response_code(404);
    exit;
}

try {
    // احضر الخبر + اسم القسم (مع احترام soft delete / status)
    $hasStatus = function_exists('gdy_column_exists') ? gdy_column_exists($pdo, 'news', 'status') : true;
    $hasDeleted = function_exists('gdy_column_exists') ? gdy_column_exists($pdo, 'news', 'deleted_at') : true;

    $where = "n.id = :id";
    if ($hasDeleted) $where .= " AND (n.deleted_at IS NULL)";
    if ($hasStatus) $where .= " AND (n.status = 'published' OR n.status = 1)";

    $sql = "SELECT n.id, n.title, c.name AS category_name
            FROM news n
            LEFT JOIN categories c ON c.id = n.category_id
            WHERE {$where}
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row) {
        http_response_code(404);
        exit;
    }

    $title = trim((string)($row['title'] ?? ''));
    $subtitle = trim((string)($row['category_name'] ?? ''));

    $settings = function_exists('gdy_load_settings') ? gdy_load_settings($pdo) : [];
    $siteName = (string)($settings['site_name'] ?? 'Godyar News');

    $w = 1200; $h = 630;
    if (!function_exists('imagecreatetruecolor')) {
        http_response_code(500);
        exit;
    }

    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true);
    $bg = imagecolorallocate($im, 245, 250, 255);
    imagefill($im, 0, 0, $bg);

    // gradient
    $r=2; $g=132; $b=199; // subtle blue
    for ($y=0; $y<$h; $y++) {
        $t = $y / $h;
        $rr = (int)round(245*(1-$t) + $r*$t);
        $gg = (int)round(250*(1-$t) + $g*$t);
        $bb = (int)round(255*(1-$t) + $b*$t);
        $col = imagecolorallocate($im, $rr, $gg, $bb);
        imageline($im, 0, $y, $w, $y, $col);
    }

    // card
    $card = imagecolorallocatealpha($im, 255, 255, 255, 18);
    imagefilledrectangle($im, 70, 70, $w-70, $h-70, $card);

    $dark = imagecolorallocate($im, 3, 7, 18);
    $muted = imagecolorallocate($im, 71, 85, 105);

    $fontBold = __DIR__ . '/assets/fonts/DejaVuSans-Bold.ttf';
    $fontReg  = __DIR__ . '/assets/fonts/DejaVuSans.ttf';
    $useTtf = is_file($fontBold) && function_exists('imagettftext');

    // helper to wrap text for TTF
    $wrap = function (string $text, int $maxChars): array {
        $text = trim(preg_replace('~\s+~u', ' ', $text));
        if ($text === '') return [''];
        $words = preg_split('~\s+~u', $text) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $wrd) {
            $cand = ($line === '') ? $wrd : ($line . ' ' . $wrd);
            if (mb_strlen($cand, 'UTF-8') <= $maxChars) {
                $line = $cand;
            } else {
                if ($line !== '') $lines[] = $line;
                $line = $wrd;
            }
            if (count($lines) >= 3) break;
        }
        if ($line !== '' && count($lines) < 3) $lines[] = $line;
        return $lines;
    };

    $x = 110;
    $y = 170;

    // subtitle
    if ($subtitle !== '') {
        if ($useTtf && is_file($fontReg)) {
            imagettftext($im, 28, 0, $x, $y-55, $muted, $fontReg, $subtitle);
        } else {
            imagestring($im, 5, $x, $y-80, $subtitle, $muted);
        }
    }

    // title (wrap)
    if ($title === '') $title = $siteName;
    $lines = $wrap($title, 32);
    if ($useTtf) {
        $fs = 52;
        foreach ($lines as $ln) {
            imagettftext($im, $fs, 0, $x, $y, $dark, $fontBold, $ln);
            $y += 70;
        }
    } else {
        imagestring($im, 5, $x, $y-20, mb_substr($title, 0, 70, 'UTF-8'), $dark);
    }

    // footer bar
    $bar = imagecolorallocatealpha($im, 3, 7, 18, 30);
    imagefilledrectangle($im, 70, $h-140, $w-70, $h-70, $bar);
    $white = imagecolorallocate($im, 241, 245, 249);

    if ($useTtf) {
        imagettftext($im, 30, 0, $x, $h-95, $white, $fontBold, $siteName);
    } else {
        imagestring($im, 5, $x, $h-115, $siteName, $white);
    }

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    header('Pragma: public');
    imagepng($im);
    imagedestroy($im);
    exit;

} catch (Throwable $e) {
    @error_log('[og_news] ' . $e->getMessage());
    http_response_code(500);
    exit;
}
