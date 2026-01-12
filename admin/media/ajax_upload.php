<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

header('Content-Type: application/json; charset=utf-8');

function jexit(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::isLoggedIn()) {
    jexit(401, ['ok' => false, 'error' => 'unauthorized']);
}

// CSRF (supports both verify_csrf_token and verify_csrf)
try {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (function_exists('verify_csrf_token')) {
        if (!verify_csrf_token($csrf)) {
            jexit(419, ['ok' => false, 'error' => 'csrf', 'message' => 'رمز الحماية غير صحيح أو انتهت الجلسة.']);
        }
    } elseif (function_exists('verify_csrf')) {
        // may throw
        verify_csrf();
    }
} catch (Throwable $e) {
    jexit(419, ['ok' => false, 'error' => 'csrf', 'message' => 'رمز الحماية غير صحيح أو انتهت الجلسة.']);
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    jexit(400, ['ok' => false, 'error' => 'no_file', 'message' => 'لم يتم اختيار ملف.']);
}

$f = $_FILES['file'];
$err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err === UPLOAD_ERR_NO_FILE) {
    jexit(400, ['ok' => false, 'error' => 'no_file', 'message' => 'لم يتم اختيار ملف.']);
}
if ($err !== UPLOAD_ERR_OK) {
    jexit(400, ['ok' => false, 'error' => 'upload_error', 'message' => 'فشل رفع الملف.']);
}

$tmp = (string)($f['tmp_name'] ?? '');
$orig = (string)($f['name'] ?? 'image');
$size = (int)($f['size'] ?? 0);

$max = 5 * 1024 * 1024;
if ($size <= 0 || $size > $max) {
    jexit(400, ['ok' => false, 'error' => 'size', 'message' => 'حجم الصورة غير مسموح (الحد 5MB).']);
}

// Detect MIME
$mime = '';
if (function_exists('finfo_open')) {
    $fi = @finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
        $mime = (string)@finfo_file($fi, $tmp);
        @finfo_close($fi);
    }
}
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) {
    jexit(400, ['ok' => false, 'error' => 'mime', 'message' => 'نوع الصورة غير مسموح.']);
}

// Extra validation: ensure it's a real image
if (@getimagesize($tmp) === false) {
    jexit(400, ['ok' => false, 'error' => 'bad_image', 'message' => 'الملف ليس صورة صالحة.']);
}

$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
if ($ext === '' || !in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
    $ext = $allowed[$mime];
}
if ($ext === 'jpeg') $ext = 'jpg';

$root = defined('ROOT_PATH') ? rtrim((string)ROOT_PATH, '/\\') : rtrim(dirname(__DIR__, 2), '/\\');
$uploadDir = $root . '/assets/uploads/media/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$base = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$fileName = $base . '.' . $ext;
$dest = $uploadDir . $fileName;

if (!@move_uploaded_file($tmp, $dest)) {
    jexit(500, ['ok' => false, 'error' => 'move', 'message' => 'تعذر حفظ الصورة على السيرفر.']);
}
@chmod($dest, 0644);

// ---- Optional compression + watermark (GD) ----
function gdy_image_open(string $path, string $mime) {
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            return @imagecreatefromjpeg($path);
        case 'image/png':
            return @imagecreatefrompng($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
        default:
            return null;
    }
}
function gdy_image_save($im, string $path, string $mime, int $quality): bool {
    if ($mime === 'image/png') {
        // PNG: 0 (no compression) .. 9 (max)
        $level = 6;
        imagesavealpha($im, true);
        return @imagepng($im, $path, $level);
    }
    if ($mime === 'image/webp' && function_exists('imagewebp')) {
        return @imagewebp($im, $path, max(0, min(100, $quality)));
    }
    // default jpeg
    return @imagejpeg($im, $path, max(40, min(95, $quality)));
}
function gdy_apply_watermark($im, int $w, int $h, int $opacity): void {
    try {
        $enabled = (int)settings_get('media.watermark.enabled', 0);
        if (!$enabled) { return; }

        $logoUrl = (string)settings_get('site.logo', '');
        if ($logoUrl === '') { return; }

        $p = @parse_url($logoUrl);
        $rel = (string)($p['path'] ?? '');
        if ($rel === '') { return; }

        // Resolve file path under public root
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 3);
        $logoPath = rtrim($root, '/') . $rel;
        if (!is_file($logoPath)) { return; }

        $info = @getimagesize($logoPath);
        if (!$info) { return; }
        $mime = (string)($info['mime'] ?? '');
        $wm = gdy_image_open($logoPath, $mime);
        if (!$wm) { return; }

        $wmW = imagesx($wm);
        $wmH = imagesy($wm);

        // Resize watermark to ~18% of image width (max 220px)
        $targetW = (int)min(220, max(80, (int)round($w * 0.18)));
        $ratio = $wmW > 0 ? ($targetW / $wmW) : 1.0;
        $targetH = (int)max(1, (int)round($wmH * $ratio));

        $wm2 = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($wm2, false);
        imagesavealpha($wm2, true);
        $trans = imagecolorallocatealpha($wm2, 0, 0, 0, 127);
        imagefilledrectangle($wm2, 0, 0, $targetW, $targetH, $trans);
        imagecopyresampled($wm2, $wm, 0, 0, 0, 0, $targetW, $targetH, $wmW, $wmH);
        imagedestroy($wm);

        // Position bottom-left for RTL sites (with padding)
        $pad = 12;
        $x = $pad;
        $y = $h - $targetH - $pad;

        // Merge with opacity
        imagealphablending($im, true);
        // For PNG alpha watermark, imagecopymerge ignores alpha; blend onto a temp first
        $tmp = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $trans2 = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefilledrectangle($tmp, 0, 0, $targetW, $targetH, $trans2);
        imagecopy($tmp, $im, 0, 0, $x, $y, $targetW, $targetH);
        imagecopy($tmp, $wm2, 0, 0, 0, 0, $targetW, $targetH);
        imagecopymerge($im, $tmp, $x, $y, 0, 0, $targetW, $targetH, max(10, min(100, $opacity)));
        imagedestroy($tmp);
        imagedestroy($wm2);
    } catch (Throwable $e) {
        @error_log('[ajax_upload watermark] ' . $e->getMessage());
    }
}
function gdy_compress_and_watermark(string $path, string $mime): void {
    if (!function_exists('imagecreatetruecolor')) { return; }
    $enabled = (int)settings_get('media.compress.enabled', 1);
    if (!$enabled) { 
        // still allow watermark-only
        $enabledWm = (int)settings_get('media.watermark.enabled', 0);
        if (!$enabledWm) { return; }
    }

    $info = @getimagesize($path);
    if (!$info) { return; }
    $w = (int)$info[0];
    $h = (int)$info[1];

    $im = gdy_image_open($path, $mime);
    if (!$im) { return; }

    $maxW = (int)settings_get('media.compress.max_width', 1920);
    $quality = (int)settings_get('media.compress.quality', 82);
    $wmOpacity = (int)settings_get('media.watermark.opacity', 35);

    // Resize if needed
    if ($enabled && $maxW > 0 && $w > $maxW) {
        $newW = $maxW;
        $newH = (int)max(1, (int)round($h * ($newW / $w)));
        $dst = imagecreatetruecolor($newW, $newH);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $trans);
        }
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($im);
        $im = $dst;
        $w = $newW; $h = $newH;
    }

    // Watermark (logo)
    gdy_apply_watermark($im, $w, $h, $wmOpacity);

    // Save (compress)
    if ($enabled) {
        // For PNG, saving as PNG may not reduce much. Keep type, but apply compression level.
        gdy_image_save($im, $path, $mime, $quality);
    } else {
        // watermark-only, preserve
        gdy_image_save($im, $path, $mime, 90);
    }

    imagedestroy($im);
}

// Process image after upload
try {
    gdy_compress_and_watermark($dest, $mime);
} catch (Throwable $e) {
    @error_log('[ajax_upload process] ' . $e->getMessage());
}


$url = rtrim((string)base_url(), '/') . '/assets/uploads/media/' . $fileName;
$size = (int)(@filesize($dest) ?: $size);

// Insert into media table if exists
$pdo = gdy_pdo_safe();
if ($pdo instanceof PDO) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'media'");
        $exists = (bool)($check && $check->fetchColumn());
        if ($exists) {
            $st = $pdo->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, created_at) VALUES (:n,:p,:t,:s,NOW())");
            $st->execute([
                ':n' => $orig,
                ':p' => $url,
                ':t' => $mime,
                ':s' => $size,
            ]);
        }
    } catch (Throwable $e) {
        // ignore db failure; upload still OK
        @error_log('[ajax_upload] ' . $e->getMessage());
    }
}

jexit(200, ['ok' => true, 'url' => $url, 'name' => $orig, 'size' => $size, 'mime' => $mime]);
