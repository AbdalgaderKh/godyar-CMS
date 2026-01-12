<?php
declare(strict_types=1);

/**
 * Upload - Safe uploader (production)
 * - Validates MIME using finfo
 * - Validates extension
 * - Enforces max size
 * - Generates random filename
 * - Upload path is relative to project ROOT_PATH (defined in includes/bootstrap.php)
 */
class Upload {
    /** @var array<string,string> */
    private array $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        // add more types intentionally if you need them
    ];

    private int $maxBytes;
    private string $destRelDir;

    public function __construct(string $destRelDir = '/uploads/', int $maxMB = 5) {
        $this->destRelDir = '/' . trim($destRelDir, '/') . '/';
        $this->maxBytes   = max(1, $maxMB) * 1024 * 1024;
    }

    /**
     * @return array{file_name:string,file_rel:string,mime:string,size:int}|null
     */
    public function uploadImage(array $file): ?array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        $tmp  = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || $size <= 0 || $size > $this->maxBytes) return null;

        if (!function_exists('finfo_open')) return null;
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) return null;
        $mime = (string)@finfo_file($finfo, $tmp);
        @finfo_close($finfo);

        if (!isset($this->allowedMimeToExt[$mime])) return null;
        $ext = $this->allowedMimeToExt[$mime];

        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 1);
		// Trim both forward and back slashes (must escape backslash in PHP string).
		$destAbs = rtrim($root, '/\\') . $this->destRelDir;
        if (!is_dir($destAbs)) @mkdir($destAbs, 0775, true);

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
		$abs  = rtrim($destAbs, '/\\') . DIRECTORY_SEPARATOR . $name;

        if (!@move_uploaded_file($tmp, $abs)) return null;

        $rel = rtrim($this->destRelDir, '/') . '/' . $name;
        return ['file_name'=>$name,'file_rel'=>$rel,'mime'=>$mime,'size'=>$size];
    }
}
