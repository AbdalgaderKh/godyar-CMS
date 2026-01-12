<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
// admin/media/index.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'media';
$pageTitle   = __('t_06dd6988d0', 'مكتبة الوسائط');

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// دالة مساعدة لتنسيق حجم الملف
function formatFileSize($bytes): string {
    $bytes = (float)($bytes ?? 0);
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = (int)floor(log($bytes, 1024));
    $pow = max(0, min($pow, count($units) - 1));
    $bytes /= pow(1024, $pow);
    return rtrim(rtrim(number_format($bytes, 2, '.', ''), '0'), '.') . ' ' . $units[$pow];
}

// دالة للحصول على أيقونة حسب نوع الملف
function getFileIcon(string $fileType): string {
    $t = strtolower($fileType);
    if (strpos($t, 'image') !== false) return 'fa-image';
    if (strpos($t, 'video') !== false) return 'fa-video';
    if (strpos($t, 'audio') !== false) return 'fa-music';
    if (strpos($t, 'pdf') !== false) return 'fa-file-pdf';
    if (strpos($t, 'word') !== false || strpos($t, 'document') !== false) return 'fa-file-word';
    if (strpos($t, 'excel') !== false || strpos($t, 'spreadsheet') !== false) return 'fa-file-excel';
    if (strpos($t, 'powerpoint') !== false || strpos($t, 'presentation') !== false) return 'fa-file-powerpoint';
    if (strpos($t, 'zip') !== false || strpos($t, 'rar') !== false || strpos($t, '7z') !== false) return 'fa-file-zipper';
    if (strpos($t, 'text') !== false) return 'fa-file-lines';
    return 'fa-file';
}

$items  = [];
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$type   = isset($_GET['type']) ? trim((string)$_GET['type']) : '';

// ✅ pagination / load more
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$isAjax  = (isset($_GET['ajax']) && (string)$_GET['ajax'] === '1');

$totalCount = 0;
$totalPages = 1;
$imageCount = 0;
$videoCount = 0;
$otherCount = 0;

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'media'");
        if ($stmt && $stmt->fetchColumn()) {

            // -----------------------
            // WHERE (بحث + نوع)
            // -----------------------
            $where = "WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $where .= " AND file_name LIKE :q";
                $params[':q'] = '%' . $search . '%';
            }

            $typeWhere = '';
            if ($type === 'images') {
                $typeWhere = " AND file_type LIKE 'image%'";
            } elseif ($type === 'videos') {
                $typeWhere = " AND file_type LIKE 'video%'";
            } elseif ($type === 'documents') {
                $typeWhere = " AND (file_type LIKE 'application%' OR file_type LIKE 'text%')";
            }

            // -----------------------
            // COUNTS (متوافقة مع الفلتر الحالي)
            // -----------------------
            $countSql = "SELECT COUNT(*) FROM media {$where}{$typeWhere}";
            $countStmt = $pdo->prepare($countSql);
            foreach ($params as $k => $v) $countStmt->bindValue($k, $v, PDO::PARAM_STR);
            $countStmt->execute();
            $totalCount = (int)$countStmt->fetchColumn();

            $totalPages = max(1, (int)ceil($totalCount / $perPage));

            if ($type === 'images') {
                $imageCount = $totalCount;
                $videoCount = 0;
                $otherCount = 0;
            } elseif ($type === 'videos') {
                $imageCount = 0;
                $videoCount = $totalCount;
                $otherCount = 0;
            } elseif ($type === 'documents') {
                $imageCount = 0;
                $videoCount = 0;
                $otherCount = $totalCount;
            } else {
                // breakdown داخل نفس نطاق البحث (بدون تقييد نوع)
                $imgSql = "SELECT COUNT(*) FROM media {$where} AND file_type LIKE 'image%'";
                $vidSql = "SELECT COUNT(*) FROM media {$where} AND file_type LIKE 'video%'";
                $imgStmt = $pdo->prepare($imgSql);
                $vidStmt = $pdo->prepare($vidSql);
                foreach ($params as $k => $v) {
                    $imgStmt->bindValue($k, $v, PDO::PARAM_STR);
                    $vidStmt->bindValue($k, $v, PDO::PARAM_STR);
                }
                $imgStmt->execute();
                $vidStmt->execute();
                $imageCount = (int)$imgStmt->fetchColumn();
                $videoCount = (int)$vidStmt->fetchColumn();
                $otherCount = max(0, $totalCount - $imageCount - $videoCount);
            }

            // -----------------------
            // LIST (25 لكل صفحة)
            // -----------------------
            $sql = "SELECT id, file_name, file_path, file_type, file_size, created_at
                    FROM media
                    {$where}{$typeWhere}
                    ORDER BY created_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt2 = $pdo->prepare($sql);
            foreach ($params as $k => $v) $stmt2->bindValue($k, $v, PDO::PARAM_STR);
            $stmt2->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stmt2->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt2->execute();
            $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        @error_log('[Godyar Media Index] ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// ✅ Ajax: إرجاع JSON لزر __('t_407bdba777', "المزيد")
// ---------------------------------------------------------------------
if ($isAjax) {
    $payloadItems = [];
    foreach ($items as $it) {
        $ft = (string)($it['file_type'] ?? '');
        $isImage = (strpos($ft, 'image') !== false);
        $isVideo = (strpos($ft, 'video') !== false);

        $payloadItems[] = [
            'id' => (int)($it['id'] ?? 0),
            'file_name' => (string)($it['file_name'] ?? ''),
            'file_path' => (string)($it['file_path'] ?? ''),
            'file_type' => $ft,
            'type_label' => $isImage ? __('t_22d882505c', 'صورة') : ($isVideo ? __('t_f58f599d0d', 'فيديو') : __('t_1e679e3005', 'ملف')),
            'file_size_formatted' => formatFileSize($it['file_size'] ?? 0),
            'created_formatted' => !empty($it['created_at']) ? date('Y-m-d H:i', strtotime((string)$it['created_at'])) : '',
            'icon' => getFileIcon($ft),
            'is_image' => $isImage,
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'page' => $page,
        'per_page' => $perPage,
        'total' => (int)$totalCount,
        'total_pages' => (int)$totalPages,
        'items' => $payloadItems,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Professional unified admin shell
$pageSubtitle = __('t_05ce6719e5', 'رفع وإدارة الصور والفيديوهات والملفات');
$breadcrumbs = [
    __('t_3aa8578699', 'الرئيسية') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/index.php',
    __('t_06dd6988d0', 'مكتبة الوسائط') => null,
];
$pageActionsHtml = __('t_01e4eb823f', '<a href="upload.php" class="btn btn-gdy btn-gdy-primary"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> رفع ملف جديد</a>');
require_once __DIR__ . '/../layout/app_start.php';
?>
<style>
:root{
    --gdy-shell-max: 1200px;
}

/* NOTE: global shell styles handled by admin-ui.css */

/* ====== Shell ====== */
.gdy-filter-bar {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
.gdy-stats {
    display: flex;
    gap: .75rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}
.gdy-stat {
    background: rgba(30, 41, 59, 0.6);
    padding: .65rem .9rem;
    border-radius: .75rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
}
.gdy-stat-value {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0ea5e9;
    display: block;
}
.gdy-stat-label {
    font-size: .75rem;
    color: #94a3b8;
    display: block;
}

/* ====== Gallery ====== */
.gdy-media-gallery {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    padding: 1.5rem;
}

/* ✅ 5 ملفات في الصف (على الشاشات الواسعة) */
.gdy-media-grid{
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
@media (min-width: 768px){
    .gdy-media-grid{ grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (min-width: 992px){
    .gdy-media-grid{ grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (min-width: 1200px){
    .gdy-media-grid{ grid-template-columns: repeat(5, minmax(0, 1fr)); } /* ✅ 5 */
}

.gdy-media-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1rem;
    overflow: hidden;
    transition: all 0.25s ease;
    position: relative;
}

.gdy-media-card:hover {
    transform: translateY(-6px);
    border-color: #0ea5e9;
    box-shadow: 0 18px 38px rgba(0, 0, 0, 0.35);
}

.gdy-media-preview {
    position: relative;
    overflow: hidden;
    background: #0f172a;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gdy-media-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .25s ease, opacity .25s ease;
    opacity: 0;
}

.gdy-media-card:hover .gdy-media-image {
    transform: scale(1.04);
}

.gdy-media-icon {
    font-size: 2.8rem;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gdy-media-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 45%, rgba(15, 23, 42, 0.96) 100%);
    opacity: 0;
    transition: opacity .25s ease;
    display: flex;
    align-items: flex-end;
    padding: .9rem;
}

.gdy-media-card:hover .gdy-media-overlay { opacity: 1; }

.gdy-media-actions {
    display: flex;
    gap: .5rem;
    width: 100%;
    justify-content: center;
}

.gdy-media-btn {
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 0.55rem;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e293b;
    text-decoration: none;
    transition: all .2s ease;
    backdrop-filter: blur(10px);
}

.gdy-media-btn:hover {
    background: #0ea5e9;
    color: white;
    transform: scale(1.08);
}

.gdy-media-info {
    padding: .9rem;
}

.gdy-media-name {
    font-weight: 700;
    color: #e5e7eb;
    margin-bottom: .45rem;
    font-size: .88rem;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.gdy-media-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .75rem;
    color: #94a3b8;
}

.gdy-media-type {
    background: rgba(14, 165, 233, 0.18);
    color: #38bdf8;
    padding: .2rem .5rem;
    border-radius: .35rem;
    font-weight: 700;
}

.gdy-media-size { font-weight: 700; color: #cbd5e1; }

.gdy-media-date{
    margin-top: .45rem;
    font-size: .72rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: .35rem;
}

.gdy-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #94a3b8;
}

.gdy-empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: .55;
}

/* زر المزيد */
#loadMoreBtn.is-loading { opacity: .75; pointer-events: none; }

/* مودال المعاينة */
.gdy-preview-modal .modal-content {
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(148, 163, 184, 0.3);
    border-radius: 1.5rem;
    color: #e5e7eb;
}
.gdy-preview-modal .modal-header {
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    background: transparent;
}
.gdy-preview-modal .modal-body {
    padding: 2rem;
    text-align: center;
}
.gdy-preview-image {
    max-width: 100%;
    max-height: 60vh;
    border-radius: 1rem;
    margin-bottom: 1.5rem;
}
.gdy-preview-video {
    max-width: 100%;
    border-radius: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .gdy-media-gallery { padding: 1rem; }
    .gdy-filter-bar { padding: 1rem; }
    .gdy-stats { gap: .5rem; }
    .gdy-stat { padding: .5rem .7rem; }
}

/* Ensures no horizontal overflow */
.gdy-media-gallery{max-width:100%; overflow:hidden;}
.gdy-media-grid{width:100%; max-width:100%;}
.gdy-media-card{min-width:0;}
.gdy-media-name{word-break:break-word; overflow-wrap:anywhere;}
</style>

    <!-- شريط التصفية والإحصائيات -->
    <div class="gdy-filter-bar">
        <form method="get" class="row g-3 align-items-end">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <div class="col-12 col-md-5">
                <label class="form-label small mb-1" style="color: #e5e7eb;"><?= h(__('t_402c90a28c', 'بحث بالاسم')) ?></label>
                <input type="text" name="q" value="<?= h($search) ?>"
                       class="form-control bg-dark text-light border-secondary"
                       placeholder="<?= h(__('t_dda805e1c7', 'ابحث باسم الملف...')) ?>">
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label small mb-1" style="color: #e5e7eb;"><?= h(__('t_ac419af75c', 'نوع الملف')) ?></label>
                <select name="type" class="form-select bg-dark text-light border-secondary">
                    <option value="" <?= $type === '' ? 'selected' : '' ?>><?= h(__('t_cd654353e8', 'كل الملفات')) ?></option>
                    <option value="images" <?= $type === 'images' ? 'selected' : '' ?>><?= h(__('t_c52c6607e1', 'صور فقط')) ?></option>
                    <option value="videos" <?= $type === 'videos' ? 'selected' : '' ?>><?= h(__('t_d589afa239', 'فيديو فقط')) ?></option>
                    <option value="documents" <?= $type === 'documents' ? 'selected' : '' ?>><?= h(__('t_741b399829', 'مستندات')) ?></option>
                </select>
            </div>

            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_abe3151c63', 'تطبيق')) ?>
                </button>
                <a href="index.php" class="btn btn-outline-light flex-fill">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_2cb0b85c56', 'إعادة تعيين')) ?>
                </a>
            </div>
        </form>

        <!-- الإحصائيات -->
        <div class="gdy-stats">
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= (int)$totalCount ?></span>
                <span class="gdy-stat-label"><?= h(__('t_82d70c2243', 'إجمالي الملفات')) ?></span>
            </div>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= (int)$imageCount ?></span>
                <span class="gdy-stat-label"><?= h(__('t_f4f25f539b', 'صور')) ?></span>
            </div>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= (int)$videoCount ?></span>
                <span class="gdy-stat-label"><?= h(__('t_f58f599d0d', 'فيديو')) ?></span>
            </div>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= (int)$otherCount ?></span>
                <span class="gdy-stat-label"><?= h(__('t_288654d1a0', 'ملفات أخرى')) ?></span>
            </div>
        </div>
    </div>

    <!-- معرض الوسائط -->
    <div class="gdy-media-gallery">
        <?php if (empty($items)): ?>
            <div class="gdy-empty-state">
                <div class="gdy-empty-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
                <h4 class="text-muted mb-3"><?= h(__('t_bec90f91b5', 'لا توجد ملفات في المكتبة')) ?></h4>
                <p class="text-muted mb-4"><?= h(__('t_4665f3462c', 'ابدأ برفع أول ملف إلى مكتبة الوسائط')) ?></p>
                <a href="upload.php" class="btn btn-primary btn-lg">
                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_82db4f0e17', 'رفع أول ملف')) ?>
                </a>
            </div>
        <?php else: ?>

            <div class="gdy-media-grid">
                <?php foreach ($items as $index => $item):
                    $filePath = (string)($item['file_path'] ?? '');
                    $fileType = (string)($item['file_type'] ?? '');
                    $fileName = (string)($item['file_name'] ?? '');
                    $isImage  = (strpos($fileType, 'image') !== false);
                    $isVideo  = (strpos($fileType, 'video') !== false);
                    $typeLabel = $isImage ? __('t_22d882505c', 'صورة') : ($isVideo ? __('t_f58f599d0d', 'فيديو') : __('t_1e679e3005', 'ملف'));
                    $created = !empty($item['created_at']) ? date('Y-m-d H:i', strtotime((string)$item['created_at'])) : '';
                ?>
                    <div class="gdy-media-card" style="animation-delay: <?= ($index * 0.04) ?>s">
                        <div class="gdy-media-preview">
                            <?php if ($isImage): ?>
                                <img src="<?= h($filePath) ?>"
                                     alt="<?= h($fileName) ?>"
                                     class="gdy-media-image"
                                     loading="lazy"
                                     onload="this.style.opacity='1';"
                                     data-img-error="hide-show-next-flex">
                                <div class="gdy-media-icon" style="display:none;">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                </div>
                            <?php else: ?>
                                <div class="gdy-media-icon">
                                    <svg class="gdy-icon <?= h(getFileIcon($fileType)) ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                </div>
                            <?php endif; ?>

                            <div class="gdy-media-overlay">
                                <div class="gdy-media-actions">
                                    <a href="<?= h($filePath) ?>" target="_blank" class="gdy-media-btn" title="<?= h(__('t_b66b00ea74', 'فتح/معاينة')) ?>">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    </a>
                                    <button type="button" class="gdy-media-btn copy-link" data-url="<?= h($filePath) ?>" title="<?= h(__('t_0d8af0ab07', 'نسخ الرابط')) ?>">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    </button>
                                    <button type="button"
                                            class="gdy-media-btn preview-btn"
                                            data-file="<?= h($filePath) ?>"
                                            data-type="<?= h($fileType) ?>"
                                            data-name="<?= h($fileName) ?>"
                                            title="<?= h(__('t_c9214ed951', 'معاينة مفصلة')) ?>">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="gdy-media-info">
                            <div class="gdy-media-name" title="<?= h($fileName) ?>"><?= h($fileName) ?></div>
                            <div class="gdy-media-meta">
                                <span class="gdy-media-type"><?= h($typeLabel) ?></span>
                                <span class="gdy-media-size"><?= h(formatFileSize($item['file_size'] ?? 0)) ?></span>
                            </div>
                            <?php if ($created): ?>
                                <div class="gdy-media-date">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($created) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($page < $totalPages): ?>
                <div class="d-flex justify-content-center mt-4">
                    <button type="button"
                            id="loadMoreBtn"
                            class="btn btn-outline-light"
                            data-next-page="<?= (int)($page + 1) ?>"
                            data-total-pages="<?= (int)$totalPages ?>">
                        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#chevron-down"></use></svg> <?= h(__('t_407bdba777', 'المزيد')) ?>
                    </button>
                </div>

                <div class="text-center small text-muted mt-2">
                    <?= h(__('t_bab35cd1eb', 'تم عرض')) ?> <span id="shownCount"><?= (int)count($items) ?></span> من <?= (int)$totalCount ?>
                </div>
            <?php else: ?>
                <div class="text-center small text-muted mt-2">
                    <?= h(__('t_bab35cd1eb', 'تم عرض')) ?> <span id="shownCount"><?= (int)count($items) ?></span> من <?= (int)$totalCount ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
 

<!-- مودال المعاينة -->
<div class="modal fade gdy-preview-modal" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewFileName"><?= h(__('t_2010f031f4', 'معاينة الملف')) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent"></div>

                <div class="mt-3">
                    <button class="btn btn-outline-light copy-full-link me-2" type="button">
                        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_0d8af0ab07', 'نسخ الرابط')) ?>
                    </button>
                    <button class="btn btn-outline-info copy-embed me-2" type="button">
                        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_5268d50cb2', 'نسخ كود التضمين')) ?>
                    </button>
                    <a href="#" id="previewDownload" class="btn btn-primary" download>
                        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_969879d297', 'تحميل')) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ===== Helpers =====
    function flashBtn(btn, ok = true) {
        if (!btn) return;
        const original = btn.innerHTML;
        btn.innerHTML = ok ? '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>' : '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>';
        const oldBg = btn.style.background;
        btn.style.background = ok ? '#10b981' : '#ef4444';
        setTimeout(() => {
            btn.innerHTML = original;
            btn.style.background = oldBg;
        }, 1500);
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, s => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[s]));
    }

    // ===== Modal Preview =====
    const previewModalEl = document.getElementById('previewModal');
    const previewModal = previewModalEl ? new bootstrap.Modal(previewModalEl) : null;
    const previewContent = document.getElementById('previewContent');
    const previewFileName = document.getElementById('previewFileName');
    const previewDownload = document.getElementById('previewDownload');
    const copyFullLink = document.querySelector('.copy-full-link');
    const copyEmbed = document.querySelector('.copy-embed');

    let currentFileUrl = '';
    let currentFileType = '';
    let currentFileName = '';

    function openPreview(fileUrl, fileType, fileName) {
        currentFileUrl = fileUrl || '';
        currentFileType = fileType || '';
        currentFileName = fileName || '';
        if (previewFileName) previewFileName.textContent = currentFileName || 'معاينة الملف';
        if (previewDownload) {
            previewDownload.href = currentFileUrl || '#';
            previewDownload.setAttribute('download', currentFileName || '');
        }

        let html = '';
        if ((currentFileType || '').startsWith('image/')) {
            html = `<img src="${escapeHtml(currentFileUrl)}" alt="${escapeHtml(currentFileName)}" class="gdy-preview-image">`;
        } else if ((currentFileType || '').startsWith('video/')) {
            html = `
                <video controls class="gdy-preview-video" style="max-width:100%;">
                    <source src="${escapeHtml(currentFileUrl)}" type="${escapeHtml(currentFileType)}">
                    متصفحك لا يدعم تشغيل الفيديو.
                </video>
            `;
        } else {
            html = `
                <div class="text-center py-4">
                    <svg class="gdy-icon text-muted mb-3" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <p class="text-muted">لا يمكن معاينة هذا النوع من الملفات</p>
                    <a href="${escapeHtml(currentFileUrl)}" target="_blank" class="btn btn-outline-light">
                        فتح في نافذة جديدة
                    </a>
                </div>
            `;
        }
        if (previewContent) previewContent.innerHTML = html;
        if (previewModal) previewModal.show();
    }

    // ===== Event Delegation (للدعم بعد Load More) =====
    document.addEventListener('click', function(e) {
        const copyBtn = e.target.closest('.copy-link');
        if (copyBtn) {
            const url = copyBtn.getAttribute('data-url') || '';
            if (!url) return;
            navigator.clipboard.writeText(url).then(() => flashBtn(copyBtn, true)).catch(() => flashBtn(copyBtn, false));
            return;
        }

        const previewBtn = e.target.closest('.preview-btn');
        if (previewBtn) {
            const fileUrl = previewBtn.getAttribute('data-file') || '';
            const fileType = previewBtn.getAttribute('data-type') || '';
            const fileName = previewBtn.getAttribute('data-name') || '';
            openPreview(fileUrl, fileType, fileName);
            return;
        }
    });

    if (copyFullLink) {
        copyFullLink.addEventListener('click', function() {
            if (!currentFileUrl) return;
            navigator.clipboard.writeText(currentFileUrl).then(() => {
                const original = this.innerHTML;
                this.innerHTML = '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>تم النسخ!';
                this.classList.remove('btn-outline-light');
                this.classList.add('btn-success');
                setTimeout(() => {
                    this.innerHTML = original;
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-light');
                }, 1500);
            });
        });
    }

    if (copyEmbed) {
        copyEmbed.addEventListener('click', function() {
            if (!currentFileUrl) return;

            let embedCode = '';
            if ((currentFileType || '').startsWith('image/')) {
                embedCode = `<img src="${currentFileUrl}" alt="${currentFileName}">`;
            } else if ((currentFileType || '').startsWith('video/')) {
                embedCode = `<video controls>\n  <source src="${currentFileUrl}" type="${currentFileType}">\n</video>`;
            } else {
                const label = currentFileName || currentFileUrl;
                embedCode = `<a href="${currentFileUrl}" target="_blank" rel="noopener">${label}</a>`;
            }

            navigator.clipboard.writeText(embedCode).then(() => {
                const original = this.innerHTML;
                this.innerHTML = '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>تم النسخ';
                this.classList.remove('btn-outline-info');
                this.classList.add('btn-success');
                setTimeout(() => {
                    this.innerHTML = original;
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-info');
                }, 1500);
            });
        });
    }

    // ===== Load More (25 -> 50 عند أول ضغطة) =====
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const grid = document.querySelector('.gdy-media-grid');
    const shownCountEl = document.getElementById('shownCount');

    function buildCard(item, delayIndex) {
        const fileUrl = escapeHtml(item.file_path);
        const fileName = escapeHtml(item.file_name);
        const fileType = escapeHtml(item.file_type || '');
        const typeLabel = escapeHtml(item.type_label || 'ملف');
        const sizeLabel = escapeHtml(item.file_size_formatted || '');
        const created = escapeHtml(item.created_formatted || '');
        const icon = escapeHtml(item.icon || 'fa-file');

        const previewHtml = item.is_image
            ? `
                <img src="${fileUrl}" alt="${fileName}" class="gdy-media-image" loading="lazy"
                     onload="this.style.opacity='1';"
                     data-img-error="hide-show-next-flex">
                <div class="gdy-media-icon" style="display:none;"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
              `
            : `<div class="gdy-media-icon"><svg class="gdy-icon ${icon}" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>`;

        return `
            <div class="gdy-media-card" style="animation-delay:${(delayIndex * 0.04).toFixed(2)}s">
                <div class="gdy-media-preview">
                    ${previewHtml}
                    <div class="gdy-media-overlay">
                        <div class="gdy-media-actions">
                            <a href="${fileUrl}" target="_blank" class="gdy-media-btn" title="فتح/معاينة">
                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            </a>
                            <button type="button" class="gdy-media-btn copy-link" data-url="${fileUrl}" title="نسخ الرابط">
                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            </button>
                            <button type="button" class="gdy-media-btn preview-btn"
                                    data-file="${fileUrl}" data-type="${fileType}" data-name="${fileName}" title="معاينة مفصلة">
                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="gdy-media-info">
                    <div class="gdy-media-name" title="${fileName}">${fileName}</div>
                    <div class="gdy-media-meta">
                        <span class="gdy-media-type">${typeLabel}</span>
                        <span class="gdy-media-size">${sizeLabel}</span>
                    </div>
                    ${created ? `<div class="gdy-media-date"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ${created}</div>` : ``}
                </div>
            </div>
        `;
    }

    if (loadMoreBtn && grid) {
        loadMoreBtn.addEventListener('click', async function() {
            const totalPages = parseInt(this.getAttribute('data-total-pages') || '1', 10);
            let nextPage = parseInt(this.getAttribute('data-next-page') || '2', 10);
            if (nextPage > totalPages) return;

            this.classList.add('is-loading');
            const originalHtml = this.innerHTML;
            this.innerHTML = '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> جاري التحميل...';

            try {
                const url = new URL(window.location.href);
                url.searchParams.set('ajax', '1');
                url.searchParams.set('page', String(nextPage));

                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' }});
                const data = await res.json();
                if (!data || !data.ok) throw new Error('Bad response');

                const items = Array.isArray(data.items) ? data.items : [];
                const startIndex = grid.children.length;

                const temp = document.createElement('div');
                const frag = document.createDocumentFragment();

                items.forEach((it, i) => {
                    temp.innerHTML = buildCard(it, startIndex + i);
                    frag.appendChild(temp.firstElementChild);
                });

                grid.appendChild(frag);

                if (shownCountEl) shownCountEl.textContent = String(grid.children.length);

                nextPage += 1;
                this.setAttribute('data-next-page', String(nextPage));

                if (nextPage > totalPages) {
                    this.style.display = 'none';
                }

            } catch (err) {
                this.innerHTML = '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> فشل التحميل';
                setTimeout(() => { this.innerHTML = originalHtml; }, 1500);
            } finally {
                this.classList.remove('is-loading');
                if (this.style.display !== 'none') this.innerHTML = originalHtml;
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
