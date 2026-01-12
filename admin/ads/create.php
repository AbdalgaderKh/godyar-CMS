<?php

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'ads';
$pageTitle   = __('t_289196f7a2', 'إضافة إعلان جديد');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// التحقق من تسجيل الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class, 'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Godyar Ads Create] Auth error: ' . $e->getMessage());
    header('Location: ../login.php');
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die(__('t_acc3fac25f', '❌ لا يوجد اتصال بقاعدة البيانات.'));
}

// التحقق من وجود جدول الإعلانات
$tableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'ads'");
    $tableExists = $check && $check->fetchColumn();
} catch (Exception $e) {
    error_log(__('t_7668227e2f', 'خطأ في التحقق من جدول الإعلانات: ') . $e->getMessage());
}

// قيم النموذج الافتراضية
$data = [
    'title'       => '',
    'location'    => '',
    'image_url'   => '',
    'target_url'  => '',
    'description' => '',
    'content'     => '',
    'ad_type'     => 'banner', // banner | text
    'is_active'   => 1,
    'is_featured' => 0,
    'starts_at'   => '',
    'ends_at'     => '',
    'max_clicks'  => 0,
    'max_views'   => 0,
];

$errors  = [];
$success = null;
$adId    = null;

// المواضع الممكنة
$availableLocations = [
    'header_top'     => __('t_3e30ebad34', 'أعلى الهيدر'),
    'header_bottom'  => __('t_5d8aa19fbf', 'أسفل الهيدر'),
    'sidebar_top'    => __('t_e1cbc0394b', 'أعلى الشريط الجانبي'),
    'sidebar_middle' => __('t_e39fe6aac1', 'منتصف الشريط الجانبي'),
    'home_under_featured_video' => __('t_home_under_featured_video', 'تحت الفيديو المميز (الرئيسية)'),
    'sidebar_bottom' => __('t_99e8d05b69', 'أسفل الشريط الجانبي'),
    'content_top'    => __('t_8880101e94', 'أعلى المحتوى'),
    'content_middle' => __('t_9d99d96891', 'منتصف المحتوى'),
    'content_bottom' => __('t_ebb9292366', 'أسفل المحتوى'),
    'footer_top'     => __('t_5c55eec399', 'أعلى الفوتر'),
    'footer_bottom'  => __('t_565eaea7eb', 'أسفل الفوتر'),
    'popup'          => __('t_8b5a3581c9', 'نافذة منبثقة'),
    'notification'   => __('t_552052f5b2', 'إشعار'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تحقّق من CSRF إن وجد
    if (function_exists('verify_csrf')) {
        try {
            verify_csrf();
        } catch (Throwable $e) {
            @error_log('[Godyar Ads Create] CSRF error: ' . $e->getMessage());
            $errors[] = __('t_3dde9f5e86', 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.');
        }
    }

    // جمع البيانات
    $data['title']       = trim((string)($_POST['title'] ?? ''));
    $data['location']    = trim((string)($_POST['location'] ?? ''));
    $data['image_url']   = trim((string)($_POST['image_url'] ?? ''));
    $data['target_url']  = trim((string)($_POST['target_url'] ?? ''));
    $data['description'] = trim((string)($_POST['description'] ?? ''));
    $data['content']     = trim((string)($_POST['content'] ?? ''));
    $data['ad_type']     = ($_POST['ad_type'] ?? 'banner') === 'text' ? 'text' : 'banner';
    $data['is_active']   = isset($_POST['is_active']) ? 1 : 0;
    $data['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $data['starts_at']   = trim((string)($_POST['starts_at'] ?? ''));
    $data['ends_at']     = trim((string)($_POST['ends_at'] ?? ''));
    $data['max_clicks']  = (int)($_POST['max_clicks'] ?? 0);
    $data['max_views']   = (int)($_POST['max_views'] ?? 0);

    // التحقق من الصحة
    if ($data['title'] === '') {
        $errors[] = __('t_0db9a5bd26', 'عنوان الإعلان مطلوب.');
    } elseif (mb_strlen($data['title']) > 255) {
        $errors[] = __('t_2345fc3360', 'العنوان يجب أن لا يتجاوز 255 حرفاً.');
    }

    if ($data['location'] === '') {
        $errors[] = __('t_d48f80c73c', 'موضع الإعلان مطلوب.');
    } elseif (!array_key_exists($data['location'], $availableLocations)) {
        $errors[] = __('t_2ab89a0b27', 'موضع الإعلان غير صالح.');
    }

    if ($data['ad_type'] === 'banner') {
        if ($data['image_url'] === '') {
            $errors[] = __('t_5a308832be', 'رابط الصورة مطلوب للإعلانات المصورة.');
        }
    }

    if ($data['image_url'] !== '' && !filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
        $errors[] = __('t_39105b61c3', 'رابط الصورة غير صالح.');
    }

    if ($data['target_url'] !== '' && !filter_var($data['target_url'], FILTER_VALIDATE_URL)) {
        $errors[] = __('t_39bf7a8087', 'رابط الوجهة غير صالح.');
    }

    if ($data['ad_type'] === 'text' && $data['content'] === '') {
        $errors[] = __('t_e0c886b782', 'المحتوى النصي مطلوب للإعلانات النصية.');
    }

    if ($data['max_clicks'] < 0) {
        $errors[] = __('t_d1b493152e', 'عدد النقرات الأقصى يجب أن يكون رقمًا موجبًا أو صفر.');
    }

    if ($data['max_views'] < 0) {
        $errors[] = __('t_be4d714123', 'عدد المشاهدات الأقصى يجب أن يكون رقمًا موجبًا أو صفر.');
    }

    if ($data['starts_at'] !== '' && $data['ends_at'] !== '') {
        $startTime = strtotime($data['starts_at']);
        $endTime   = strtotime($data['ends_at']);
        if ($startTime !== false && $endTime !== false && $endTime <= $startTime) {
            $errors[] = __('t_499a78f40d', 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية.');
        }
    }

    if (!$tableExists) {
        $errors[] = __('t_792dabdf9c', 'جدول الإعلانات غير موجود. يرجى إنشاء الجدول أولاً.');
    }

    if (!$errors && $tableExists) {
        try {
            // === Schema detection (compat with old DB columns) ===
$adsColNames = [];
try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM ads");
    $cols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($cols as $c) {
        if (!empty($c['Field'])) $adsColNames[] = (string)$c['Field'];
    }
} catch (Throwable $e) {
    $adsColNames = [];
}

$pickCol = function (array $cands) use ($adsColNames): ?string {
    foreach ($cands as $c) {
        if (in_array($c, $adsColNames, true)) return $c;
    }
    return null;
};

$colTitle      = $pickCol(['title','name']) ?? 'title';
$colLocation   = $pickCol(['location','pos','placement']);
$colImage      = $pickCol(['image_url','image','image_path','img','banner','banner_url','image_src']);
$colTarget     = $pickCol(['target_url','url','link','href','redirect_url']);
$colDescription= $pickCol(['description','desc']);
$colContent    = $pickCol(['content','html','code','html_code']);
$colAdType     = $pickCol(['ad_type','type']);
$colIsActive   = $pickCol(['is_active','active','enabled']);
$colIsFeatured = $pickCol(['is_featured','featured']);
$colStartsAt   = $pickCol(['starts_at','start_at','start_date','starts_on']);
$colEndsAt     = $pickCol(['ends_at','end_at','end_date','ends_on']);
$colMaxClicks  = $pickCol(['max_clicks']);
$colMaxViews   = $pickCol(['max_views']);
$colClickCount = $pickCol(['click_count','clicks']);
$colViewCount  = $pickCol(['view_count','views']);
$colCreatedAt  = $pickCol(['created_at']);
$colUpdatedAt  = $pickCol(['updated_at']);

// Build INSERT dynamically (only existing columns)
$insCols = [];
$insVals = [];
$params  = [];

$insCols[] = "`{$colTitle}`";      $insVals[] = ":title";      $params[':title'] = $data['title'];

if ($colLocation) {
    $insCols[] = "`{$colLocation}`"; $insVals[] = ":location"; $params[':location'] = $data['location'];
}

// Banner image/url
if ($colImage) {
    $insCols[] = "`{$colImage}`"; $insVals[] = ":image"; $params[':image'] = $data['image_url'];
}
if ($colTarget) {
    $insCols[] = "`{$colTarget}`"; $insVals[] = ":target"; $params[':target'] = $data['target_url'];
}

if ($colDescription) {
    $insCols[] = "`{$colDescription}`"; $insVals[] = ":description"; $params[':description'] = $data['description'];
}
if ($colContent) {
    $insCols[] = "`{$colContent}`"; $insVals[] = ":content"; $params[':content'] = $data['content'];
}
if ($colAdType) {
    $insCols[] = "`{$colAdType}`"; $insVals[] = ":ad_type"; $params[':ad_type'] = $data['ad_type'];
}
if ($colIsActive) {
    $insCols[] = "`{$colIsActive}`"; $insVals[] = ":is_active"; $params[':is_active'] = $data['is_active'];
}
if ($colIsFeatured) {
    $insCols[] = "`{$colIsFeatured}`"; $insVals[] = ":is_featured"; $params[':is_featured'] = $data['is_featured'];
}
if ($colStartsAt) {
    $insCols[] = "`{$colStartsAt}`"; $insVals[] = ":starts_at"; $params[':starts_at'] = ($data['starts_at'] ?: null);
}
if ($colEndsAt) {
    $insCols[] = "`{$colEndsAt}`"; $insVals[] = ":ends_at"; $params[':ends_at'] = ($data['ends_at'] ?: null);
}
if ($colMaxClicks) {
    $insCols[] = "`{$colMaxClicks}`"; $insVals[] = ":max_clicks"; $params[':max_clicks'] = $data['max_clicks'];
}
if ($colMaxViews) {
    $insCols[] = "`{$colMaxViews}`"; $insVals[] = ":max_views"; $params[':max_views'] = $data['max_views'];
}
if ($colClickCount) {
    $insCols[] = "`{$colClickCount}`"; $insVals[] = "0";
}
if ($colViewCount) {
    $insCols[] = "`{$colViewCount}`"; $insVals[] = "0";
}
if ($colCreatedAt) {
    $insCols[] = "`{$colCreatedAt}`"; $insVals[] = "NOW()";
}
if ($colUpdatedAt) {
    $insCols[] = "`{$colUpdatedAt}`"; $insVals[] = "NOW()";
}

$sql = "INSERT INTO ads (" . implode(',', $insCols) . ") VALUES (" . implode(',', $insVals) . ")";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$adId    = (int)$pdo->lastInsertId();
$success = __('t_c96e687008', 'تم إضافة الإعلان بنجاح.');// إعادة التوجيه حسب الزر
            if (isset($_POST['save_and_list'])) {
                header('Location: index.php?success=created');
                exit;
            } elseif (isset($_POST['save_and_edit']) && $adId > 0) {
                header('Location: edit.php?id=' . $adId . '&success=created');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = __('t_16052fe4fe', 'حدث خطأ أثناء حفظ الإعلان.');
            @error_log('[Godyar Ads Create] Insert error: ' . $e->getMessage());
        }
    }
}

// Professional unified admin shell
$pageSubtitle = __('t_7b00f16055', 'إنشاء إعلان جديد وتحديد مكان الظهور والمدة');
$breadcrumbs = [
    __('t_3aa8578699', 'الرئيسية') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/index.php',
    __('t_5750d13d2c', 'الإعلانات') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/ads/index.php',
    __('t_ad613dea26', 'إنشاء إعلان') => null,
];
$pageActionsHtml = __('t_acdad3d9f7', '<a href="index.php" class="btn btn-gdy btn-gdy-outline"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> العودة</a>');
require_once __DIR__ . '/../layout/app_start.php';
?>

<style>
:root{
    --gdy-shell-max: min(880px, 100vw - 360px);
}

/* NOTE: global shell styles handled by admin-ui.css */

.gdy-page-header{
    margin-bottom:0.75rem;
}

.gdy-form-card {
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 1.25rem;
    border: 1px solid rgba(148, 163, 184, 0.25);
}

.gdy-form-sidebar {
    background: rgba(15, 23, 42, 0.85);
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.25);
}

.image-preview {
    width: 100%;
    max-width: 320px;
    height: 160px;
    border: 2px dashed #374151;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    overflow: hidden;
    background: #020617;
}

.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.ad-type-switch {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.ad-type-option {
    flex: 1;
    text-align: center;
    padding: 1rem;
    border: 2px solid #1f2937;
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all .2s ease;
    background: rgba(15,23,42,0.9);
}

.ad-type-option:hover {
    border-color: #0ea5e9;
}

.ad-type-option.active {
    border-color: #0ea5e9;
    background: rgba(14,165,233,0.08);
}

.ad-type-option input {
    display: none;
}

.location-info {
    background: rgba(15,23,42,0.9);
    border-radius: .5rem;
    border:1px solid rgba(148,163,184,0.35);
    padding:.75rem .9rem;
    font-size:.85rem;
    color:#9ca3af;
    margin-top:.4rem;
}

.char-count {
    font-size: 0.8rem;
    color: #64748b;
}
.char-count.warning { color:#f59e0b; }
.char-count.danger  { color:#ef4444; }

@media (max-width: 992px){
    :root{
        --gdy-shell-max: 100vw;
    }
}
</style>



    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">
            <?= h(__('t_64b2ab32d2', 'جدول')) ?> <code>ads</code> <?= h(__('t_6a688805de', 'غير موجود. الرجاء إنشاء الجدول أولاً.')) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($success) ?>
            <?php if ($adId): ?>
                <div class="mt-2">
                    <a href="edit.php?id=<?= $adId ?>" class="btn btn-sm btn-success me-2"><?= h(__('t_e656de3c13', 'تعديل الإعلان')) ?></a>
                    <a href="index.php" class="btn btn-sm btn-outline-success"><?= h(__('t_1acb4f4494', 'عرض جميع الإعلانات')) ?></a>
                </div>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong><?= h(__('t_4e7e8d83c3', 'حدثت الأخطاء التالية:')) ?></strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="gdy-form-card card shadow-sm">
                    <div class="card-body">
                        <!-- نوع الإعلان -->
                        <h5 class="mb-3 text-white"><?= h(__('t_b56f6dfe69', 'نوع الإعلان')) ?></h5>
                        <div class="ad-type-switch">
                            <label class="ad-type-option <?= $data['ad_type'] === 'banner' ? 'active' : '' ?>" id="optBanner">
                                <input type="radio" name="ad_type" value="banner" <?= $data['ad_type'] === 'banner' ? 'checked' : '' ?>>
                                <div class="fw-semibold text-white mb-1"><?= h(__('t_3ab68064f1', 'إعلان مصوَّر')) ?></div>
                                <div class="small text-muted"><?= h(__('t_84d4ccad1e', 'بانر بصورة + رابط')) ?></div>
                            </label>
                            <label class="ad-type-option <?= $data['ad_type'] === 'text' ? 'active' : '' ?>" id="optText">
                                <input type="radio" name="ad_type" value="text" <?= $data['ad_type'] === 'text' ? 'checked' : '' ?>>
                                <div class="fw-semibold text-white mb-1"><?= h(__('t_05f6894d94', 'إعلان نصي')) ?></div>
                                <div class="small text-muted"><?= h(__('t_da4d1e2464', 'نص إعلاني منسق')) ?></div>
                            </label>
                        </div>

                        <!-- العنوان والوصف -->
                        <div class="mb-3">
                            <label class="form-label text-white"><?= h(__('t_8bb8a2cf87', 'عنوان الإعلان *')) ?></label>
                            <input type="text"
                                   name="title"
                                   class="form-control"
                                   value="<?= h($data['title']) ?>"
                                   maxlength="255"
                                   required>
                            <div class="char-count mt-1" id="titleCount"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white"><?= h(__('t_81edd198f5', 'وصف مختصر')) ?></label>
                            <textarea name="description"
                                      class="form-control"
                                      rows="2"
                                      maxlength="500"><?= h($data['description']) ?></textarea>
                            <div class="char-count mt-1" id="descCount"></div>
                        </div>

                        <!-- قسم البنر -->
                        <div id="bannerSection">
                            <hr class="border-secondary">
                            <h5 class="mb-3 text-white"><?= h(__('t_572d7c464b', 'روابط الإعلان المصوَّر')) ?></h5>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-white">رابط الصورة <?= $data['ad_type']==='banner' ? '*' : '' ?></label>
                                    <input type="url"
                                           name="image_url"
                                           id="imageUrl"
                                           class="form-control"
                                           placeholder="https://example.com/banner.jpg"
                                           value="<?= h($data['image_url']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-white"><?= h(__('t_2a37b264ac', 'رابط الوجهة (اختياري)')) ?></label>
                                    <input type="url"
                                           name="target_url"
                                           id="bannerTargetUrl"
                                           class="form-control"
                                           placeholder="https://example.com"
                                           value="<?= h($data['target_url']) ?>">
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label text-white"><?= h(__('t_0075044f10', 'معاينة الصورة')) ?></label>
                                <div class="image-preview" id="imagePreview">
                                    <?php if ($data['image_url']): ?>
                                        <img src="<?= h($data['image_url']) ?>" alt="<?= h(__('t_529cb8b507', 'معاينة الإعلان')) ?>">
                                    <?php else: ?>
                                        <div class="text-muted text-center">
                                            <svg class="gdy-icon mb-2 d-block" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            <small><?= h(__('t_8700a282e6', 'سيظهر معاينة الصورة هنا')) ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- قسم النص -->
                        <div id="textSection">
                            <hr class="border-secondary">
                            <h5 class="mb-3 text-white"><?= h(__('t_815decdd5a', 'المحتوى النصي للإعلان')) ?></h5>
                            <div class="mb-3">
                                <label class="form-label text-white"><?= h(__('t_6037748e36', 'النص الإعلاني')) ?></label>
                                <textarea name="content"
                                          id="textContent"
                                          class="form-control"
                                          rows="6"
                                          placeholder="<?= h(__('t_dc0087ab25', 'اكتب نص الإعلان هنا...')) ?>"><?= h($data['content']) ?></textarea>
                                <div class="form-text"><?= h(__('t_fd9015e752', 'يمكنك إدخال HTML بسيط (روابط، أسطر، عناوين صغيرة).')) ?></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-white"><?= h(__('t_2a37b264ac', 'رابط الوجهة (اختياري)')) ?></label>
                                <input type="url"
                                       name="target_url"
                                       id="textTargetUrl"
                                       class="form-control"
                                       placeholder="https://example.com"
                                       value="<?= h($data['target_url']) ?>">
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <div class="gdy-form-sidebar card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-white"><?= h(__('t_1883ca2097', 'موضع ظهور الإعلان *')) ?></label>
                            <select name="location" id="locationSelect" class="form-select" required>
                                <option value=""><?= h(__('t_2f6be11313', 'اختر موضعاً...')) ?></option>
                                <?php foreach ($availableLocations as $key => $label): ?>
                                    <option value="<?= h($key) ?>" <?= $data['location'] === $key ? 'selected' : '' ?>>
                                        <?= h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="location-info" id="locationDescription">
                                <?= h(__('t_358a29880c', 'اختر موضعاً لعرض وصفه هنا.')) ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white"><?= h(__('t_cbd1188e75', 'إعدادات الحالة')) ?></label>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= $data['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label text-muted" for="is_active"><?= h(__('t_4b0ef8d9d0', 'الإعلان نشط (يظهر للزوار)')) ?></label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" value="1" <?= $data['is_featured'] ? 'checked' : '' ?>>
                                <label class="form-check-label text-muted" for="is_featured"><?= h(__('t_c87d21b5c4', 'إعلان مميز')) ?></label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white"><?= h(__('t_0ae7138eb3', 'فترة العرض')) ?></label>
                            <div class="mb-2">
                                <small class="text-muted d-block mb-1"><?= h(__('t_2c73fe3b1b', 'تاريخ البداية')) ?></small>
                                <input type="datetime-local" name="starts_at" id="starts_at" class="form-control" value="<?= h($data['starts_at']) ?>">
                            </div>
                            <div class="mb-2">
                                <small class="text-muted d-block mb-1"><?= h(__('t_845e41c487', 'تاريخ الانتهاء')) ?></small>
                                <input type="datetime-local" name="ends_at" id="ends_at" class="form-control" value="<?= h($data['ends_at']) ?>">
                            </div>
                            <div class="form-text"><?= h(__('t_25aac2b06c', 'اترك الحقول فارغة لعدم تحديد فترة.')) ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white"><?= h(__('t_1840c7c18b', 'حدود الحملة (اختياري)')) ?></label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <small class="text-muted d-block mb-1"><?= h(__('t_d1f0764419', 'أقصى نقرات')) ?></small>
                                    <input type="number" name="max_clicks" class="form-control" min="0" value="<?= h((string)$data['max_clicks']) ?>">
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block mb-1"><?= h(__('t_7bce279c87', 'أقصى مشاهدات')) ?></small>
                                    <input type="number" name="max_views" class="form-control" min="0" value="<?= h((string)$data['max_views']) ?>">
                                </div>
                            </div>
                            <div class="form-text"><?= h(__('t_74e0062dd6', '0 يعني بدون حد.')) ?></div>
                        </div>

                        <div class="border-top pt-3 mt-3">
                            <button type="submit" name="save" class="btn btn-primary w-100 mb-2">
                                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_9adf67ab83', 'حفظ الإعلان')) ?>
                            </button>
                            <button type="submit" name="save_and_edit" class="btn btn-outline-primary w-100 mb-2">
                                <?= h(__('t_dda4dd7c65', 'حفظ والمتابعة في التعديل')) ?>
                            </button>
                            <button type="submit" name="save_and_list" class="btn btn-outline-light w-100">
                                <?= h(__('t_e934ff7404', 'حفظ والعودة للقائمة')) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-light">
                        <strong><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_cc272853cb', 'نصائح')) ?></strong>
                    </div>
                    <div class="card-body small text-muted">
                        <ul class="mb-0">
                            <li><?= h(__('t_9a34e3d290', 'استخدم صوراً بنسبة 16:9 لإعلانات البانر.')) ?></li>
                            <li><?= h(__('t_97dffc932d', 'اختر موضع الإعلان حسب نوعه وجمهوره.')) ?></li>
                            <li><?= h(__('t_09c7c0b7a6', 'يمكنك تحديد فترة وقيود للنقرات والمشاهدات.')) ?></li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const titleInput  = document.querySelector('input[name="title"]');
    const descInput   = document.querySelector('textarea[name="description"]');
    const titleCount  = document.getElementById('titleCount');
    const descCount   = document.getElementById('descCount');
    const imageUrl    = document.getElementById('imageUrl');
    const imagePrev   = document.getElementById('imagePreview');
    const optBanner   = document.getElementById('optBanner');
    const optText     = document.getElementById('optText');
    const bannerSec   = document.getElementById('bannerSection');
    const textSec     = document.getElementById('textSection');
    const locationSel = document.getElementById('locationSelect');
    const locDesc     = document.getElementById('locationDescription');

    const locationDescriptions = {
        'header_top': 'يظهر في أعلى الصفحة، مناسب للإعلانات الرئيسية.',
        'header_bottom': 'يظهر أسفل الهيدر، مناسب للحملات العامة.',
        'sidebar_top': 'أعلى الشريط الجانبي، مناسب لإعلانات لافتة.',
        'sidebar_middle': 'منتصف الشريط الجانبي، مناسب لمحتوى داعم.',
        'sidebar_bottom': 'أسفل الشريط الجانبي، مناسب لإعلانات ثانوية.',
        'content_top': 'أعلى منطقة المحتوى، مناسب لإعلانات مرتبطة بالمحتوى.',
        'content_middle': 'منتصف المحتوى، للإعلانات المدمجة داخل القراءة.',
        'content_bottom': 'أسفل المحتوى، المناسب لعروض ختامية.',
        'footer_top': 'أعلى الفوتر، ثابت في أسفل الصفحة.',
        'footer_bottom': 'أسفل الفوتر، مناسب للروابط القانونية وما شابه.',
        'popup': 'نافذة منبثقة للعروض الخاصة.',
        'notification': 'إشعار أعلى/أسفل الصفحة للتنبيهات السريعة.'
    };

    function updateTitleCount() {
        const len = titleInput.value.length;
        titleCount.textContent = len + '/255';
        titleCount.className = 'char-count mt-1 ' +
            (len > 200 ? 'warning ' : '') +
            (len > 240 ? 'danger ' : '');
    }

    function updateDescCount() {
        const len = descInput.value.length;
        descCount.textContent = len + '/500';
        descCount.className = 'char-count mt-1 ' +
            (len > 400 ? 'warning ' : '') +
            (len > 480 ? 'danger ' : '');
    }

    function updateImagePreview() {
        const url = imageUrl.value.trim();
        if (!url) {
            imagePrev.innerHTML =
                '<div class="text-muted text-center">' +
                '<svg class="gdy-icon mb-2 d-block" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>' +
                '<small>سيظهر معاينة الصورة هنا</small>' +
                '</div>';
            return;
        }
        imagePrev.innerHTML =
            '<img src="' + url.replace(/"/g, '&quot;') + '" alt="معاينة الإعلان" ' +
            'data-img-error="replace-parent-error">';
    }

    function updateLocationDescription() {
        const val = locationSel.value;
        locDesc.textContent = locationDescriptions[val] || 'اختر موضعاً لعرض وصفه.';
    }

    function syncTypeUI() {
        const selected = document.querySelector('input[name="ad_type"]:checked')?.value || 'banner';
        const isBanner = selected === 'banner';
        optBanner.classList.toggle('active', isBanner);
        optText.classList.toggle('active', !isBanner);
        bannerSec.style.display = isBanner ? '' : 'none';
        textSec.style.display   = !isBanner ? '' : 'none';
    }

    if (titleInput) {
        updateTitleCount();
        titleInput.addEventListener('input', updateTitleCount);
    }
    if (descInput) {
        updateDescCount();
        descInput.addEventListener('input', updateDescCount);
    }
    if (imageUrl && imagePrev) {
        updateImagePreview();
        imageUrl.addEventListener('input', updateImagePreview);
    }
    if (locationSel && locDesc) {
        updateLocationDescription();
        locationSel.addEventListener('change', updateLocationDescription);
    }

    optBanner?.addEventListener('click', function () {
        const radio = optBanner.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
        syncTypeUI();
    });
    optText?.addEventListener('click', function () {
        const radio = optText.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
        syncTypeUI();
    });
    syncTypeUI();
});
</script>

<?php
require_once __DIR__ . '/../layout/app_end.php';

/**
 * يمكن لاحقاً استخدام هذه الدالة في نفس الملف أو ملفات أخرى لوصف المواضع
 */
function getLocationDescription(string $location): string
{
    $descriptions = [
        'header_top'     => 'يظهر في أعلى الصفحة، مناسب للإعلانات الرئيسية.',
        'header_bottom'  => 'يظهر أسفل الهيدر.',
        'sidebar_top'    => 'أعلى الشريط الجانبي.',
        'sidebar_middle' => 'منتصف الشريط الجانبي.',
        'sidebar_bottom' => 'أسفل الشريط الجانبي.',
        'content_top'    => 'أعلى المحتوى.',
        'content_middle' => 'منتصف المحتوى.',
        'content_bottom' => 'أسفل المحتوى.',
        'footer_top'     => 'أعلى الفوتر.',
        'footer_bottom'  => 'أسفل الفوتر.',
        'popup'          => 'نافذة منبثقة.',
        'notification'   => 'شريط إشعارات.',
    ];

    return $descriptions[$location] ?? '';
}
