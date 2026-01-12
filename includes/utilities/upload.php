<?php
namespace Godyar\Util;
final class Upload {
  public static function image(string $field, string $destRelDir, int $maxMB=5): ?string {
    if (empty($_FILES[$field]['name'])) return null;
    $tmp  = $_FILES[$field]['tmp_name'] ?? '';
    $size = (int)($_FILES[$field]['size'] ?? 0);
    $err  = (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) return null;
    if ($size > $maxMB*1024*1024) return null;
    if (!function_exists('finfo_open')) return null;
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $tmp) : 'application/octet-stream';
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if (!isset($allowed[$mime])) return null;
    $ext = $allowed[$mime];
    $destAbs = rtrim(ROOT_PATH, '/').$destRelDir;
    if (!is_dir($destAbs)) @mkdir($destAbs, 0775, true);
    $name = uniqid('img_', true).'.'.$ext;
    $abs  = rtrim($destAbs,'/').'/'.$name;
    if (!move_uploaded_file($tmp, $abs)) return null;
    return rtrim($destRelDir,'/').'/'.$name;
  }
}
