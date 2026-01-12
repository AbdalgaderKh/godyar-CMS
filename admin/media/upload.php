<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/media/upload.php - نسخة مصححة
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$currentPage = 'media';
$pageTitle   = __('t_ce4c722d7f', 'رفع ملف وسائط');

$pdo = gdy_pdo_safe();
$errors = [];
$success = null;
$uploadedFile = null;

// المسار الصحيح للرفع
// المسار الصحيح للرفع (داخل المشروع)
$uploadDir = rtrim((defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)), '/') . '/assets/uploads/media/';

// التحقق من وجود جدول media
$tableExists = false;
if ($pdo instanceof PDO) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'media'");
        $tableExists = $check && $check->fetchColumn();
    } catch (Exception $e) {
        error_log(__('t_e4409ca5ea', 'خطأ في التحقق من جدول media: ') . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (function_exists('verify_csrf_token') && !verify_csrf_token($csrf)) {
        $errors[] = __('t_0d5b2d99a5', 'انتهت الجلسة أو رمز الحماية غير صحيح. أعد المحاولة.');
    }

    if (!empty($errors)) {
        // لا تُكمل عملية الرفع عند فشل CSRF
    } elseif (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = __('t_b9f81100e5', 'يرجى اختيار ملف لرفعه.');
    } else {
        $file = $_FILES['file'];
        
        try {
            // التحقق من الأخطاء
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(getUploadError($file['error']));
            }
            
            // التحقق من حجم الملف (10MB كحد أقصى)
            $maxFileSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxFileSize) {
                throw new Exception(__('t_c6b9d0518b', 'حجم الملف يتجاوز 10MB.'));
            }
            
            // التحقق من نوع الملف (امتداد + MIME الحقيقي)
            $allowed = [
                'jpg'  => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png'  => ['image/png'],
                'gif'  => ['image/gif'],
                'webp' => ['image/webp'],
                'pdf'  => ['application/pdf'],
                'doc'  => ['application/msword', 'application/octet-stream'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
                'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
                'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
                'mp4'  => ['video/mp4', 'application/octet-stream'],
                'mp3'  => ['audio/mpeg', 'audio/mp3', 'application/octet-stream'],
            ];

            $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if ($extension === '') {
                throw new Exception(__('t_7c2eda6568', 'نوع الملف غير مسموح به.'));
            }

            if (!isset($allowed[$extension])) {
                throw new Exception(__('t_7c2eda6568', 'نوع الملف غير مسموح به. الأنواع المسموحة: ') . implode(', ', array_keys($allowed)));
            }

            $detectedMime = '';
            if (function_exists('finfo_open')) {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detectedMime = (string)@finfo_file($finfo, (string)($file['tmp_name'] ?? ''));
                    @finfo_close($finfo);
                }
            }

            if ($detectedMime !== '' && !in_array($detectedMime, $allowed[$extension], true)) {
                throw new Exception(__('t_7c2eda6568', 'نوع الملف غير مسموح به (MIME): ') . $detectedMime);
            }

            // فحوصات إضافية للصور و PDF
            if (in_array($extension, ['jpg','jpeg','png','gif','webp'], true)) {
                if (@getimagesize((string)($file['tmp_name'] ?? '')) === false) {
                    throw new Exception(__('t_7c2eda6568', 'الملف ليس صورة صالحة.'));
                }
            }

            if ($extension === 'pdf') {
                $fh = @fopen((string)($file['tmp_name'] ?? ''), 'rb');
                if ($fh) {
                    $sig = (string)@fread($fh, 4);
                    @fclose($fh);
                    if ($sig !== '%PDF') {
                        throw new Exception(__('t_7c2eda6568', 'الملف ليس PDF صالحاً.'));
                    }
                }
            }
            
            // التأكد من وجود مجلد الرفع
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception(__('t_9dab2d2410', 'لا يمكن إنشاء مجلد الرفع. يرجى التحقق من الصلاحيات.'));
                }
            }
            
            // إنشاء اسم فريد للملف
            $fileName = generateFileName($file['name']);
            $filePath = $uploadDir . $fileName;
            
            // نقل الملف
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                @chmod($filePath, 0644);

                // تحسين الصور (Resize + ضغط) لرفع أسرع وأداء أفضل
                try {
                    $extOpt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (in_array($extOpt, ['jpg','jpeg','png'], true)) {
                        $infoOpt = @getimagesize($filePath);
                        if ($infoOpt && isset($infoOpt[0], $infoOpt[1])) {
                            $w = (int)$infoOpt[0];
                            $h = (int)$infoOpt[1];
                            $maxDim = 1800;
                            $needResize = ($w > $maxDim || $h > $maxDim);
                            $src = null;

                            if (in_array($extOpt, ['jpg','jpeg'], true) && function_exists('imagecreatefromjpeg')) {
                                $src = @imagecreatefromjpeg($filePath);
                            } elseif ($extOpt === 'png' && function_exists('imagecreatefrompng')) {
                                $src = @imagecreatefrompng($filePath);
                            }

                            if ($src) {
                                $dst = $src;

                                if ($needResize) {
                                    $ratio = min($maxDim / max(1, $w), $maxDim / max(1, $h));
                                    $nw = (int)max(1, round($w * $ratio));
                                    $nh = (int)max(1, round($h * $ratio));

                                    if (function_exists('imagecreatetruecolor')) {
                                        $tmp = @imagecreatetruecolor($nw, $nh);
                                        if ($tmp) {
                                            if ($extOpt === 'png') {
                                                @imagealphablending($tmp, false);
                                                @imagesavealpha($tmp, true);
                                                $transparent = @imagecolorallocatealpha($tmp, 0, 0, 0, 127);
                                                if ($transparent !== false) {
                                                    @imagefilledrectangle($tmp, 0, 0, $nw, $nh, $transparent);
                                                }
                                            }
                                            @imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                                            $dst = $tmp;
                                        }
                                    }
                                }

                                // إعادة حفظ بنفس المسار (ضغط)
                                if (in_array($extOpt, ['jpg','jpeg'], true) && function_exists('imagejpeg')) {
                                    @imagejpeg($dst, $filePath, 85);
                                } elseif ($extOpt === 'png' && function_exists('imagepng')) {
                                    @imagepng($dst, $filePath, 7);
                                }

                                if ($dst !== $src && function_exists('imagedestroy')) { @imagedestroy($dst); }
                                if (function_exists('imagedestroy')) { @imagedestroy($src); }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // ignore
                }


                // إنشاء نسخة WebP للصور (اختياري - يحسن الأداء)
                $webpUrl = '';
                try {
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png'], true) && function_exists('imagewebp')) {
                        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                        $webpName = $baseName . '.webp';
                        $webpPath = $uploadDir . $webpName;

                        $img = null;
                        if ($ext === 'png' && function_exists('imagecreatefrompng')) {
                            $img = @imagecreatefrompng($filePath);
                        } elseif (in_array($ext, ['jpg','jpeg'], true) && function_exists('imagecreatefromjpeg')) {
                            $img = @imagecreatefromjpeg($filePath);
                        }

                        if ($img) {
                            if (function_exists('imagepalettetotruecolor')) { @imagepalettetotruecolor($img); }
                            if (function_exists('imagealphablending')) { @imagealphablending($img, true); }
                            if (function_exists('imagesavealpha')) { @imagesavealpha($img, true); }

                            // جودة 82 توازن ممتاز بين الحجم والجودة
                            if (@imagewebp($img, $webpPath, 82)) {
                                @chmod($webpPath, 0644);
                                $webpUrl = function_exists('base_url')
                                    ? rtrim((string)base_url(), '/') . '/assets/uploads/media/' . $webpName
                                    : '/assets/uploads/media/' . $webpName;
                            }
                            if (function_exists('imagedestroy')) { @imagedestroy($img); }
                        }
                    }
                } catch (Throwable $e) {
                    // ignore
                }

// رابط الويب للملف (يتوافق مع تثبيت داخل مجلد أو الدومين)
                $fileUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') . '/assets/uploads/media/' . $fileName : '/assets/uploads/media/' . $fileName;
                
                // حفظ في قاعدة البيانات إذا كان الجدول موجوداً
                $dbSuccess = false;
                if ($tableExists && $pdo instanceof PDO) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO media (file_name, file_path, file_type, file_size, created_at)
                            VALUES (:file_name, :file_path, :file_type, :file_size, NOW())
                        ");
                        
                        $stmt->execute([
                            ':file_name' => $fileName,
                            ':file_path' => $fileUrl,
                            ':file_type' => ($detectedMime !== '' ? $detectedMime : (string)($file['type'] ?? '')),
                            ':file_size' => $file['size']
                        ]);
                        
                        $dbSuccess = true;
                        
                    } catch (Exception $e) {
                        error_log(__('t_f0e46027ac', 'خطأ في حفظ الملف في قاعدة البيانات: ') . $e->getMessage());
                        // نستمر حتى لو فشل حفظ في DB لأن الملف تم رفعه بنجاح
                    }
                }
                
                $uploadedFile = [
                    'name' => $fileName,
                    'url' => $fileUrl,
                     'webp_url' => $webpUrl,
                    'type' => ($detectedMime !== '' ? $detectedMime : (string)($file['type'] ?? '')),
                    'size' => formatFileSize($file['size']),
                    'saved_in_db' => $dbSuccess,
                    'full_path' => $filePath
                ];
                
                $success = __('t_322ceea5c0', 'تم رفع الملف بنجاح!');
                
            } else {
                throw new Exception(__('t_dd673469b3', 'فشل في حفظ الملف على الخادم.'));
            }
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// دالات مساعدة
function getUploadError($errorCode) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => __('t_67e3f4ef00', 'حجم الملف يتجاوز الحد المسموح في السيرفر'),
        UPLOAD_ERR_FORM_SIZE => __('t_c2274600e9', 'حجم الملف يتجاوز الحد المسموح في النموذج'),
        UPLOAD_ERR_PARTIAL => __('t_664618cbca', 'تم رفع جزء من الملف فقط'),
        UPLOAD_ERR_NO_FILE => __('t_cc026c347c', 'لم يتم اختيار ملف'),
        UPLOAD_ERR_NO_TMP_DIR => __('t_ca4a73561c', 'المجلد المؤقت غير موجود'),
        UPLOAD_ERR_CANT_WRITE => __('t_83db2c4671', 'فشل في كتابة الملف على القرص'),
        UPLOAD_ERR_EXTENSION => __('t_451307fbec', 'امتداد PHP أوقف عملية الرفع')
    ];
    return $errors[$errorCode] ?? __('t_317afa6968', 'خطأ غير معروف في الرفع');
}

function generateFileName($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    
    // تنظيف الاسم
    $baseName = preg_replace('/[^\p{L}\p{N}]+/u', '-', $baseName);
    $baseName = trim($baseName, '-');
    
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    
    return $baseName . '_' . $timestamp . '_' . $random . '.' . $extension;
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// تحميل الهيدر والسايدبار
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<style>
html, body{
    overflow-x:hidden;
    background:#020617;
    color:#e5e7eb;
}
.admin-content{
    max-width: 1100px;
    margin: 0 auto;
}
</style>

<div class="admin-content container-fluid py-4">
    <div class="gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h1 class="h4 text-white mb-1"><?= h(__('t_ce4c722d7f', 'رفع ملف وسائط')) ?></h1>
            <p class="text-muted mb-0 small">
                <?= h(__('t_6d1e1e9e79', 'قم برفع الصور والملفات لاستخدامها داخل الأخبار والصفحات.')) ?>
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="create_folders.php" class="btn btn-warning btn-sm">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_27dac8de6d', 'إنشاء المجلدات')) ?>
            </a>
            <?php if (!$tableExists): ?>
                <a href="../create_media_table.php" class="btn btn-warning btn-sm">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_bd67355239', 'إنشاء جدول الوسائط')) ?>
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_06dd6988d0', 'مكتبة الوسائط')) ?>
            </a>
        </div>
    </div>

    <!-- معلومات المسار -->
    <div class="alert alert-info">
        <strong><?= h(__('t_e7b151de48', 'مسار الرفع:')) ?></strong> 
        <code><?= htmlspecialchars($uploadDir) ?></code><br>
        <strong><?= h(__('t_bfaaa1d24f', 'مسار الويب:')) ?></strong> 
        <code><?= h(function_exists('base_url') ? rtrim((string)base_url(), '/') . '/assets/uploads/media/' : '/assets/uploads/media/') ?></code>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <strong><?= h(__('t_b83c3996d9', 'تنبيه:')) ?></strong> <?= h(__('t_97e2efe901', 'جدول الوسائط غير موجود. الملفات سيتم حفظها على السيرفر ولكن لن يتم تسجيلها في قاعدة البيانات.')) ?>
            <a href="../create_media_table.php" class="alert-link"><?= h(__('t_98b74d89fa', 'انقر هنا لإنشاء الجدول')) ?></a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            
            <?php if ($uploadedFile): ?>
                <div class="mt-2 p-3 bg-dark rounded">
                    <strong><?= h(__('t_10046f2f99', 'تفاصيل الملف:')) ?></strong>
                    <ul class="mb-0 mt-1">
                        <li><strong><?= h(__('t_504c65fb07', 'الاسم:')) ?></strong> <?= htmlspecialchars($uploadedFile['name']) ?></li>
                        <li><strong><?= h(__('t_c3a348da73', 'الحجم:')) ?></strong> <?= htmlspecialchars($uploadedFile['size']) ?></li>
                        <li><strong><?= h(__('t_7c2d6e8e3b', 'النوع:')) ?></strong> <?= htmlspecialchars($uploadedFile['type']) ?></li>
                        <li><strong><?= h(__('t_f3f8258644', 'حفظ في قاعدة البيانات:')) ?></strong> 
                            <?= $uploadedFile['saved_in_db'] ? __('t_57aebfd1fb', '✅ نعم') : __('t_618036b23f', '❌ لا') ?>
                        </li>
                        <li><strong><?= h(__('t_bfa4d01454', 'الرابط:')) ?></strong> 
                            <a href="<?= htmlspecialchars($uploadedFile['url']) ?>" target="_blank">
                                <?= htmlspecialchars($uploadedFile['url']) ?>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <?php if (strpos($uploadedFile['type'], 'image/') === 0): ?>
                    <div class="mt-3">
                        <strong><?= h(__('t_450e6c05a3', 'معاينة الصورة:')) ?></strong>
                        <div class="mt-2">
                            <img src="<?= htmlspecialchars($uploadedFile['url']) ?>" 
                                 alt="<?= h(__('t_0075044f10', 'معاينة الصورة')) ?>" 
                                 style="max-width: 300px; max-height: 200px; border-radius: 8px;"
                                 data-img-error="hide">
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <strong><?= h(__('t_c83b645dc3', 'حدث خطأ:')) ?></strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-light">
                    <h5 class="card-title mb-0">
                        <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_f2a2721bcb', 'رفع ملف جديد')) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="fileInput" class="form-label"><?= h(__('t_92953a3c39', 'اختر الملف')) ?></label>
                            <input type="file" name="file" class="form-control" id="fileInput" required 
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.mp4,.mp3">
                            <div class="form-text">
                                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <?= h(__('t_8651df9d1e', 'الحد الأقصى لحجم الملف: 10MB. الأنواع المسموحة: 
                                JPG, JPEG, PNG, GIF, PDF, DOC, DOCX, MP4, MP3')) ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><?= h(__('t_88cb29796d', 'معاينة الملف المحدد')) ?></label>
                            <div id="filePreview" class="border rounded p-3 text-center" style="min-height: 100px; display: none;">
                                <div id="previewContent"></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_17c92b3f36', 'رفع الملف')) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-light">
                    <h5 class="card-title mb-0">
                        <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_7316dab3a1', 'معلومات الرفع')) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6><?= h(__('t_9274f5ce87', 'حالة قاعدة البيانات:')) ?></h6>
                        <span class="badge bg-<?= $tableExists ? 'success' : 'warning' ?>">
                            <?= $tableExists ? __('t_9bb9544c5e', '✅ الجدول موجود') : __('t_121aaeeb90', '⚠️ الجدول غير موجود') ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <h6><?= h(__('t_669c12da96', 'مسار الحفظ:')) ?></h6>
                        <code class="small"><?= htmlspecialchars($uploadDir) ?></code>
                    </div>
                    
                    <div class="mb-3">
                        <h6><?= h(__('t_d8204922bd', 'حالة المجلدات:')) ?></h6>
                        <span class="badge bg-<?= is_dir($uploadDir) ? 'success' : 'danger' ?>">
                            <?= is_dir($uploadDir) ? __('t_b6fd182e90', '✅ مجلد الرفع موجود') : __('t_4352a639d4', '❌ مجلد الرفع غير موجود') ?>
                        </span>
                    </div>
                    
                    <?php if (!is_dir($uploadDir)): ?>
                        <div class="alert alert-danger small mb-0">
                            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            <strong><?= h(__('t_5f1154f94b', 'خطأ:')) ?></strong> <?= h(__('t_b3c0dc4a30', 'مجلد الرفع غير موجود.')) ?> 
                            <a href="create_folders.php" class="alert-link"><?= h(__('t_74748e3ab3', 'انقر هنا لإنشاء المجلدات')) ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const previewContent = document.getElementById('previewContent');
    
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            filePreview.style.display = 'block';
            
            // عرض معلومات الملف
            previewContent.innerHTML = `
                <div class="text-start">
                    <strong>اسم الملف:</strong> ${file.name}<br>
                    <strong>الحجم:</strong> ${formatFileSize(file.size)}<br>
                    <strong>النوع:</strong> ${file.type}
                </div>
            `;
            
            // معاينة الصور
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContent.innerHTML += `
                        <div class="mt-3">
                            <img src="${e.target.result}" 
                                 alt="معاينة" 
                                 style="max-width: 200px; max-height: 150px; border-radius: 8px;">
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        } else {
            filePreview.style.display = 'none';
            previewContent.innerHTML = '';
        }
    });
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});
</script>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>