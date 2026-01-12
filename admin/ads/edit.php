<?php
declare(strict_types=1);

// ADS_SCHEMA_COMPAT_V3
if (!function_exists('gdy_ads_pick_col')) {
    function gdy_ads_pick_col(array $columns, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $columns, true)) return $c;
        }
        return null;
    }
}
if (!function_exists('gdy_ads_schema_map')) {
    function gdy_ads_schema_map(PDO $pdo): array {
        $cols = [];
        try {
            $st = $pdo->query("SHOW COLUMNS FROM ads");
            $cols = $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        } catch (Throwable $e) { $cols = []; }

        $map = [
            'id'     => gdy_ads_pick_col($cols, ['id','ad_id']) ?? 'id',
            'title'  => gdy_ads_pick_col($cols, ['title','name','ad_title']) ?? 'title',
            'loc'    => gdy_ads_pick_col($cols, ['location','loc','position','place','slot']) ?? 'location',
            'image'  => gdy_ads_pick_col($cols, ['image_url','image','image_path','img','banner','banner_url','image_src']),
            'target' => gdy_ads_pick_col($cols, ['target_url','url','link','href','target']),
            'html'   => gdy_ads_pick_col($cols, ['html_code','html','code','script','content','body']),
            'active' => gdy_ads_pick_col($cols, ['is_active','active','enabled','status']),
            'start'  => gdy_ads_pick_col($cols, ['starts_at','start_at','start_date','date_from','from_date','start_time']),
            'end'    => gdy_ads_pick_col($cols, ['ends_at','end_at','end_date','date_to','to_date','end_time']),
            'created'=> gdy_ads_pick_col($cols, ['created_at','created','createdOn']),
            'updated'=> gdy_ads_pick_col($cols, ['updated_at','updated','updatedOn']),
        ];
        return $map;
    }
}
if (!function_exists('gdy_ads_dt')) {
    function gdy_ads_dt(?string $v): ?string {
        $v = trim((string)$v);
        if ($v === '') return null;
        $ts = @strtotime($v);
        if ($ts === false) return null;
        return date('Y-m-d H:i:s', $ts);
    }
}




require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'ads';
$pageTitle   = __('t_74db3d033e', 'تعديل إعلان');

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
    @error_log('[Godyar Ads Edit] Auth error: ' . $e->getMessage());
    header('Location: ../login.php');
    exit;
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die(__('t_acc3fac25f', '❌ لا يوجد اتصال بقاعدة البيانات.'));
}
$adsMap = gdy_ads_schema_map($pdo);


$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$errors = [];
$row    = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header('Location: index.php?notfound=1');
        exit;
    }
} catch (Throwable $e) {
    @error_log('[Godyar Ads Edit] Fetch error: ' . $e->getMessage());
    header('Location: index.php?error=1');
    exit;
}

$title    = (string)($row[$adsMap['title']] ?? '');
$location = (string)($row[$adsMap['loc']] ?? '');

// ✅ قراءة القيم حسب الأعمدة الموجودة فعلياً
$imageUrl  = (!empty($adsMap['image']))  ? (string)($row[$adsMap['image']]  ?? '') : '';
$targetUrl = (!empty($adsMap['target'])) ? (string)($row[$adsMap['target']] ?? '') : '';
$htmlCode  = (!empty($adsMap['html']))   ? (string)($row[$adsMap['html']]   ?? '') : '';

$isActive = (!empty($adsMap['active'])) ? (int)($row[$adsMap['active']] ?? 0) : 0;
$startsAt = (!empty($adsMap['start']))  ? (string)($row[$adsMap['start']]  ?? '') : '';
$endsAt   = (!empty($adsMap['end']))    ? (string)($row[$adsMap['end']]    ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (function_exists('verify_csrf')) {
        try {
            verify_csrf();
        } catch (Throwable $e) {
            @error_log('[Godyar Ads Edit] CSRF error: ' . $e->getMessage());
            $_SESSION['error_message'] = __('t_3dde9f5e86', 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.');
            header('Location: edit.php?id=' . $id);
            exit;
        }
    }

    $title     = trim((string)($_POST['title'] ?? ''));
    $location  = trim((string)($_POST['location'] ?? ''));
    // ✅ أسماء حقول الفورم قد تختلف حسب النسخ
    $imageUrl  = trim((string)($_POST['image_url'] ?? $_POST['image'] ?? $_POST['banner_url'] ?? $_POST['img'] ?? ''));
    $targetUrl = trim((string)($_POST['target_url'] ?? $_POST['url'] ?? $_POST['link'] ?? $_POST['href'] ?? ''));
    $htmlCode  = trim((string)($_POST['html_code'] ?? $_POST['html'] ?? $_POST['code'] ?? $_POST['script'] ?? $_POST['content'] ?? ''));
    $isActive  = isset($_POST['is_active']) ? 1 : 0;
    $startsAt  = trim((string)($_POST['starts_at'] ?? ''));
    $endsAt    = trim((string)($_POST['ends_at'] ?? ''));

    if ($title === '') {
        $errors[] = __('t_259733b182', 'الرجاء إدخال عنوان الإعلان.');
    }
    if ($location === '') {
        $errors[] = __('t_ac0da85686', 'الرجاء تحديد موضع ظهور الإعلان.');
    }

    if ($targetUrl !== '' && !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
        $errors[] = __('t_39bf7a8087', 'رابط الوجهة غير صالح.');
    }
    if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        $errors[] = __('t_39105b61c3', 'رابط الصورة غير صالح.');
    }

    // لازم يوجد محتوى للإعلان (صورة أو كود)
    if ($imageUrl === '' && $htmlCode === '') {
        $errors[] = __('t_9b11a2f7c1', 'الرجاء إدخال رابط صورة الإعلان أو كود HTML.');
    }

    if ($startsAt !== '' && $endsAt !== '') {
        $st = strtotime($startsAt);
        $en = strtotime($endsAt);
        if ($st && $en && $en <= $st) {
            $errors[] = __('t_499a78f40d', 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية.');
        }
    }

    if (!$errors) {
        try {
            
            // ✅ Schema-compatible UPDATE (no hardcoded image_url/target_url columns)
            $colId    = $adsMap['id'] ?? 'id';
            $set      = [];
            $params   = [':id' => $id];

            // required-ish
            if (!empty($adsMap['title'])) { $set[] = "`{$adsMap['title']}` = :title"; $params[':title'] = $title; }
            if (!empty($adsMap['loc']))   { $set[] = "`{$adsMap['loc']}` = :loc";     $params[':loc']   = $location; }

            if (!empty($adsMap['image'])) { $set[] = "`{$adsMap['image']}` = :img";  $params[':img']   = $imageUrl; }
            if (!empty($adsMap['target'])){ $set[] = "`{$adsMap['target']}` = :url"; $params[':url']   = $targetUrl; }
            if (!empty($adsMap['html']))  { $set[] = "`{$adsMap['html']}` = :html";  $params[':html']  = $htmlCode; }
            if (!empty($adsMap['active'])){ $set[] = "`{$adsMap['active']}` = :act"; $params[':act']   = $isActive ? 1 : 0; }

            if (!empty($adsMap['start'])) { $set[] = "`{$adsMap['start']}` = :st";   $params[':st']    = gdy_ads_dt($startsAt); }
            if (!empty($adsMap['end']))   { $set[] = "`{$adsMap['end']}` = :en";     $params[':en']    = gdy_ads_dt($endsAt); }

            if (!empty($adsMap['updated'])) { $set[] = "`{$adsMap['updated']}` = NOW()"; }

            if (empty($set)) {
                throw new RuntimeException('Ads table columns not detected for update.');
            }

            $sql = "UPDATE ads SET " . implode(', ', $set) . " WHERE `{$colId}` = :id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);


            $_SESSION['success_message'] = __('t_16e9a82b3b', 'تم حفظ التعديلات بنجاح.');
            header('Location: index.php');
            exit;

        } catch (Throwable $e) {
            $errors[] = __('t_18a2b72a1b', 'حدث خطأ أثناء الحفظ، الرجاء المحاولة لاحقاً.');
            @error_log('[Godyar Ads Edit] Update error: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
:root{
    --gdy-shell-max: min(880px, 100vw - 360px);
}
html, body{
    overflow-x:hidden;
    background:#020617;
    color:#e5e7eb;
}
.admin-content{
    max-width: var(--gdy-shell-max);
    width:100%;
    margin:0 auto;
}
.admin-content.container-fluid.py-4{
    padding-top:0.75rem !important;
    padding-bottom:1rem !important;
}
.gdy-page-header{
    margin-bottom:0.75rem;
}
.glass-card{
    background: rgba(15,23,42,0.96);
    border-radius: 1.25rem;
    border:1px solid rgba(148,163,184,0.35);
    box-shadow: 0 20px 45px rgba(15,23,42,0.75);
}
@media (max-width: 992px){
    :root{
        --gdy-shell-max: 100vw;
    }
}
</style>

<div class="admin-content container-fluid py-4">
    <div class="gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h1 class="h4 text-white mb-1"><?= h(__('t_74db3d033e', 'تعديل إعلان')) ?></h1>
            <p class="text-muted mb-0 small"><?= h(__('t_e62cc12417', 'تعديل بيانات إعلان حالي.')) ?></p>
        </div>
        <a href="index.php" class="btn btn-outline-light mt-3 mt-md-0">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_fed95e1016', 'عودة للقائمة')) ?>
        </a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <strong><?= h(__('t_c83b645dc3', 'حدث خطأ:')) ?></strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm glass-card">
        <div class="card-body">
            <form method="post">
                <?php
                if (function_exists('csrf_field')) {
                    echo csrf_field();
                } elseif (function_exists('csrf_token')) {
                    echo '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
                }
                ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-white"><?= h(__('t_9fabdb0201', 'عنوان الإعلان')) ?></label>
                        <input type="text" name="title" class="form-control" required value="<?= h($title) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-white"><?= h(__('t_ab9dd93492', 'موضع الظهور (location)')) ?></label>
                        <input type="text" name="location" class="form-control" value="<?= h($location) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-white"><?= h(__('t_63ead7cf13', 'رابط صورة الإعلان')) ?></label>
                        <input type="text" name="image_url" class="form-control" value="<?= h($imageUrl) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-white"><?= h(__('t_d448c9c33a', 'رابط عند النقر (URL)')) ?></label>
                        <input type="text" name="target_url" class="form-control" value="<?= h($targetUrl) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-white"><?= h(__('t_8e7ec1a12d', 'نشط؟')) ?></label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                   <?= $isActive ? 'checked' : '' ?>>
                            <label class="form-check-label text-muted" for="is_active"><?= h(__('t_ddaa41f4e3', 'إظهار الإعلان للمستخدمين')) ?></label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-white"><?= h(__('t_c90712e95b', 'تاريخ بداية الحملة')) ?></label>
                        <input type="datetime-local" name="starts_at" class="form-control" value="<?= h($startsAt) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-white"><?= h(__('t_8b05b1c719', 'تاريخ نهاية الحملة')) ?></label>
                        <input type="datetime-local" name="ends_at" class="form-control" value="<?= h($endsAt) ?>">
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_91d6db7f39', 'حفظ التعديلات')) ?>
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary"><?= h(__('t_b9568e869d', 'إلغاء')) ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
