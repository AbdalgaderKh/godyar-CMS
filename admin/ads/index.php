<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'ads';
$pageTitle   = __('t_3d3316a8ed', 'إدارة الإعلانات');

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
    @error_log('[Godyar Ads] Auth error: ' . $e->getMessage());
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

// التحقق من وجود جدول الإعدادات
$settingsTableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'settings'");
    $settingsTableExists = $check && $check->fetchColumn();
} catch (Exception $e) {
    error_log(__('t_1e751ed21f', 'خطأ في التحقق من جدول الإعدادات: ') . $e->getMessage());
}

// ============= التحقق من إعداد البنر الجانبي (قراءة) =============
$sidebarAdEnabled = 1; // افتراضيًا مفعل
if ($pdo instanceof PDO) {
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
        if ($checkTable) {
            $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'sidebar_ad_enabled'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $sidebarAdEnabled = (int)$result['value'];
            }
        }
    } catch (Exception $e) {
        error_log(__('t_6dd4c10eab', 'خطأ في جلب إعداد البنر الجانبي: ') . $e->getMessage());
    }
}

// ========================
// معالجة POST (إعداد البنر + حذف + تفعيل/تعطيل)
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('verify_csrf')) {
        try {
            verify_csrf();
        } catch (Throwable $e) {
            @error_log('[Godyar Ads] CSRF error: ' . $e->getMessage());
            $_SESSION['error_message'] = __('t_3dde9f5e86', 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.');
            header('Location: index.php');
            exit;
        }
    }

    // حفظ إعداد البنر الجانبي
    if (isset($_POST['update_sidebar_setting']) && $settingsTableExists) {
        try {
            $newVal = isset($_POST['sidebar_ad_enabled']) ? 1 : 0;

            $sql = "INSERT INTO settings (`key`, `value`)
                    VALUES ('sidebar_ad_enabled', :v)
                    ON DUPLICATE KEY UPDATE
                        `value` = VALUES(`value`),
                        `updated_at` = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':v' => (string)$newVal]);

            $sidebarAdEnabled = $newVal;
            $_SESSION['success_message'] = __('t_f0f0e8f2a4', 'تم تحديث إعداد البنر الجانبي بنجاح.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            @error_log('[Godyar Ads] Sidebar setting save error: ' . $e->getMessage());
            $_SESSION['error_message'] = __('t_0d59660eea', 'حدث خطأ أثناء حفظ إعداد البنر الجانبي.');
            header('Location: index.php');
            exit;
        }
    }

    // حذف إعلان
    if (isset($_POST['delete_id']) && ctype_digit((string)$_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM ads WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $_SESSION['success_message'] = __('t_c2c9781a0e', 'تم حذف الإعلان بنجاح.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            @error_log('[Godyar Ads] Delete error: ' . $e->getMessage());
            $_SESSION['error_message'] = __('t_27c981c2bf', 'حدث خطأ أثناء حذف الإعلان.');
            header('Location: index.php');
            exit;
        }
    }

    // تبديل حالة الإعلان
    if (isset($_POST['toggle_id']) && ctype_digit((string)$_POST['toggle_id'])) {
        $id = (int)$_POST['toggle_id'];
        try {
            $stmt = $pdo->prepare("UPDATE ads SET is_active = NOT is_active WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $_SESSION['success_message'] = __('t_0b081a116d', 'تم تحديث حالة الإعلان بنجاح.');
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            @error_log('[Godyar Ads] Toggle error: ' . $e->getMessage());
            $_SESSION['error_message'] = __('t_aed0cfa650', 'حدث خطأ أثناء تحديث حالة الإعلان.');
            header('Location: index.php');
            exit;
        }
    }
}

// فلاتر البحث
$status   = $_GET['status']   ?? '';
$location = $_GET['location'] ?? '';
$search   = $_GET['search']   ?? '';

// جلب الإعلانات مع الفلاتر + الإحصائيات
$ads        = [];
$totalAds   = 0;
$activeAds  = 0;
$expiredAds = 0;

if ($tableExists) {
    try {
        $where  = ['1=1'];
        $params = [];

        if ($status === 'active') {
            $where[] = 'is_active = 1 AND (ends_at IS NULL OR ends_at >= CURDATE())';
        } elseif ($status === 'inactive') {
            $where[] = 'is_active = 0';
        } elseif ($status === 'expired') {
            $where[] = 'ends_at < CURDATE()';
        }

        if ($location && $location !== 'all') {
            $where[]             = 'location = :location';
            $params[':location'] = $location;
        }

        if ($search) {
            $where[]           = '(title LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql  = "SELECT * FROM ads WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalAds   = (int)$pdo->query("SELECT COUNT(*) FROM ads")->fetchColumn();
        $activeAds  = (int)$pdo->query("SELECT COUNT(*) FROM ads WHERE is_active = 1 AND (ends_at IS NULL OR ends_at >= CURDATE())")->fetchColumn();
        $expiredAds = (int)$pdo->query("SELECT COUNT(*) FROM ads WHERE ends_at < CURDATE()")->fetchColumn();
    } catch (Throwable $e) {
        @error_log('[Godyar Ads] Fetch error: ' . $e->getMessage());
    }
}

// جلب المواضع الفريدة للإعلانات
$locations = [];
if ($tableExists) {
    try {
        $locationStmt = $pdo->query("SELECT DISTINCT location FROM ads WHERE location IS NOT NULL");
        $locations    = $locationStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log(__('t_ea553b763b', 'خطأ في جلب المواضع: ') . $e->getMessage());
    }
}

// Professional unified admin shell
$pageSubtitle = __('t_ef0bd79f4b', 'إدارة أماكن الإعلانات وإنشاء/تعديل الحملات');
$breadcrumbs = [
    __('t_3aa8578699', 'الرئيسية') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/index.php',
    __('t_5750d13d2c', 'الإعلانات') => null,
];
$pageActionsHtml = __('t_0c05a7a08b', '<a href="create.php" class="btn btn-gdy btn-gdy-primary"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إنشاء إعلان</a>');
require_once __DIR__ . '/../layout/app_start.php';
?>

<style>
:root{
    /* نفس "التصميم الموحد للعرض" المستخدم في باقي الصفحات */
    --gdy-shell-max: min(880px, 100vw - 360px);
}

/* NOTE: global shell styles handled by admin-ui.css */

/* رأس الصفحة */
.gdy-page-header{
    margin-bottom:0.75rem;
}

/* باقي تنسيقات صفحة الإعلانات */
.gdy-ads-grid {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    padding: 2rem;
}

.gdy-ad-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1rem;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    height: 100%;
}

.gdy-ad-card:hover {
    transform: translateY(-8px);
    border-color: #0ea5e9;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.gdy-ad-image {
    position: relative;
    overflow: hidden;
    background: #0f172a;
    aspect-ratio: 16/9;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gdy-ad-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gdy-ad-card:hover .gdy-ad-image img {
    transform: scale(1.05);
}

.gdy-ad-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 40%, rgba(15, 23, 42, 0.95) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    align-items: flex-end;
    padding: 1rem;
}

.gdy-ad-card:hover .gdy-ad-overlay {
    opacity: 1;
}

.gdy-ad-actions {
    display: flex;
    gap: 0.5rem;
    width: 100%;
    justify-content: center;
}

.gdy-ad-btn {
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 0.5rem;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e293b;
    text-decoration: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.gdy-ad-btn:hover {
    background: #0ea5e9;
    color: white;
    transform: scale(1.1);
}

.gdy-ad-info {
    padding: 1.5rem;
}

.gdy-ad-title {
    font-weight: 600;
    color: #e5e7eb;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
    line-height: 1.4;
}

.gdy-ad-location {
    color: #0ea5e9;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.gdy-ad-url {
    color: #94a3b8;
    font-size: 0.85rem;
    margin-bottom: 1rem;
    word-break: break-all;
}

.gdy-ad-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: #64748b;
}

.gdy-ad-status {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-weight: 500;
    font-size: 0.75rem;
}

.gdy-ad-status.inactive {
    background: rgba(100, 116, 139, 0.2);
    color: #64748b;
}

.gdy-ad-status.expired {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.gdy-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #94a3b8;
}

.gdy-empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

.gdy-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.gdy-stat {
    background: rgba(30, 41, 59, 0.6);
    padding: 1.5rem;
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    text-align: center;
    transition: all 0.3s ease;
}

.gdy-stat:hover {
    transform: translateY(-3px);
    border-color: #0ea5e9;
}

.gdy-stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: #0ea5e9;
    display: block;
    line-height: 1;
}

.gdy-stat-label {
    font-size: 0.9rem;
    color: #94a3b8;
    display: block;
    margin-top: 0.5rem;
}

.gdy-filter-bar {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.gdy-settings-card {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.setting-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: rgba(30, 41, 59, 0.6);
    border-radius: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    transition: all 0.3s ease;
}

.setting-toggle:hover {
    border-color: #0ea5e9;
    background: rgba(30, 41, 59, 0.8);
}

.setting-info {
    flex: 1;
}

.setting-title {
    font-weight: 600;
    color: #e5e7eb;
    margin-bottom: 0.25rem;
}

.setting-desc {
    font-size: 0.85rem;
    color: #94a3b8;
}

/* السويتش */
.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #64748b;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #0ea5e9;
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.ad-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
}

.ad-featured {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

/* استجابة */
@media (max-width: 992px){
    :root{
        --gdy-shell-max: 100vw;
    }
}
</style>



    <?php if (!$tableExists): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <strong><?= h(__('t_b83c3996d9', 'تنبيه:')) ?></strong> <?= h(__('t_c17dd3f8eb', 'جدول الإعلانات غير موجود.')) ?> 
            <a href="create_table.php" class="alert-link"><?= h(__('t_98b74d89fa', 'انقر هنا لإنشاء الجدول')) ?></a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- إعدادات التحكم في البنر الجانبي -->
    <div class="gdy-settings-card">
        <h5 class="text-white mb-3">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_8f4adebed7', 'إعدادات التحكم في البنر الجانبي')) ?>
        </h5>
        
        <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
            <div class="setting-toggle">
                <div class="setting-info">
                    <div class="setting-title"><?= h(__('t_86ee560fe0', 'إظهار البنر الإعلاني في الشريط الجانبي')) ?></div>
                    <div class="setting-desc">
                        <?= h(__('t_16a3dfb642', 'التحكم في ظهور البنر الإعلاني في الشريط الجانبي لصفحات المقالات')) ?>
                    </div>
                </div>
                <div class="setting-control">
                    <label class="switch">
                        <input type="checkbox" name="sidebar_ad_enabled" value="1" 
                               <?= $sidebarAdEnabled ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" name="update_sidebar_setting" class="btn btn-primary">
                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_32be3bade9', 'حفظ الإعدادات')) ?>
                </button>
                
                <span class="ms-3 text-muted small">
                    <?= h(__('t_eb82250678', 'الحالة الحالية:')) ?> 
                    <span class="<?= $sidebarAdEnabled ? 'text-success' : 'text-danger' ?>">
                        <?= $sidebarAdEnabled ? __('t_918499f2af', 'مفعل') : __('t_0bad9c165c', 'معطل') ?>
                    </span>
                </span>
            </div>
        </form>
    </div>

    <!-- الإحصائيات -->
    <?php if ($tableExists): ?>
        <div class="gdy-stats">
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= (int)$totalAds ?></span>
                <span class="gdy-stat-label"><?= h(__('t_3f3dde8f4a', 'إجمالي الإعلانات')) ?></span>
            </div>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= (int)$activeAds ?></span>
                <span class="gdy-stat-label"><?= h(__('t_c5fb63b3b0', 'إعلانات نشطة')) ?></span>
            </div>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= (int)$expiredAds ?></span>
                <span class="gdy-stat-label"><?= h(__('t_09b10c6d40', 'إعلانات منتهية')) ?></span>
            </div>
            <div class="gdy-stat">
                <span class="gdy-stat-value"><?= count($ads) ?></span>
                <span class="gdy-stat-label"><?= h(__('t_8dea9c0652', 'نتائج البحث')) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- شريط التصفية -->
    <div class="gdy-filter-bar">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1" style="color: #e5e7eb;"><?= h(__('t_ab79fc1485', 'بحث')) ?></label>
                <input type="text" name="search" class="form-control bg-dark text-light border-secondary" 
                       value="<?= h($search) ?>" 
                       placeholder="<?= h(__('t_88e1b31a27', 'ابحث في العناوين...')) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1" style="color: #e5e7eb;"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
                <select name="status" class="form-select bg-dark text-light border-secondary">
                    <option value=""><?= h(__('t_a4028d028a', 'جميع الحالات')) ?></option>
                    <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>><?= h(__('t_5074192c69', 'نشطة فقط')) ?></option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>><?= h(__('t_bdb3ebb25d', 'غير نشطة')) ?></option>
                    <option value="expired"  <?= $status === 'expired'  ? 'selected' : '' ?>><?= h(__('t_42c40cf608', 'منتهية')) ?></option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1" style="color: #e5e7eb;"><?= h(__('t_b799144cc2', 'الموضع')) ?></label>
                <select name="location" class="form-select bg-dark text-light border-secondary">
                    <option value="all"><?= h(__('t_9915aece7e', 'جميع المواضع')) ?></option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= h($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>>
                            <?= h($loc) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-primary w-100 mb-2">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <?= h(__('t_04d478c0e8', 'تطبيق الفلتر')) ?>
                </button>
                <?php if ($status || $location || $search): ?>
                    <a href="index.php" class="btn btn-outline-light w-100">
                        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        <?= h(__('t_2cb0b85c56', 'إعادة تعيين')) ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- شبكة الإعلانات -->
    <div class="gdy-ads-grid">
        <?php if (empty($ads)): ?>
            <div class="gdy-empty-state">
                <div class="gdy-empty-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </div>
                <h4 class="text-muted mb-3"><?= h(__('t_95cbfbd50d', 'لا توجد إعلانات')) ?></h4>
                <p class="text-muted mb-4"><?= h(__('t_d58c55f24d', 'ابدأ بإضافة أول إعلان إلى النظام')) ?></p>
                <a href="create.php" class="btn btn-primary btn-lg">
                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_90ce81832d', 'إضافة أول إعلان')) ?>
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($ads as $ad): ?>
                    <?php
                    $isExpired   = $ad['ends_at'] && strtotime($ad['ends_at']) < time();
                    $statusClass = $isExpired ? 'expired' : ($ad['is_active'] ? '' : 'inactive');
                    $statusText  = $isExpired ? __('t_28cbdf8313', 'منتهي') : ($ad['is_active'] ? __('t_8caaf95380', 'نشط') : __('t_1e0f5f1adc', 'غير نشط'));
                    ?>
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="gdy-ad-card">
                            <!-- صورة الإعلان -->
                            <div class="gdy-ad-image">
                                <?php if (!empty($ad['image_url'])): ?>
                                    <img src="<?= h($ad['image_url']) ?>" 
                                         alt="<?= h($ad['title']) ?>"
                                         data-img-error="hide-show-next-unhide">
                                <?php endif; ?>
                                <div class="gdy-ad-overlay">
                                    <div class="gdy-ad-actions">
                                        <a href="edit.php?id=<?= (int)$ad['id'] ?>" 
                                           class="gdy-ad-btn"
                                           title="<?= h(__('t_759fdc242e', 'تعديل')) ?>">
                                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </a>
                                        <form method="post" class="d-inline"
                                              data-confirm='هل تريد <?= $ad['is_active'] ? __('t_43ead21245', 'تعطيل') : __('t_8403358516', 'تفعيل') ?> هذا الإعلان؟'>
                                            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                            <input type="hidden" name="toggle_id" value="<?= (int)$ad['id'] ?>">
                                            <button type="submit" class="gdy-ad-btn" title="<?= $ad['is_active'] ? __('t_43ead21245', 'تعطيل') : __('t_8403358516', 'تفعيل') ?>">
                                                <svg class="gdy-icon $ad['is_active'] ? 'pause' : 'play' ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline"
                                              data-confirm='هل أنت متأكد من حذف هذا الإعلان؟'>
                                            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                            <input type="hidden" name="delete_id" value="<?= (int)$ad['id'] ?>">
                                            <button type="submit" class="gdy-ad-btn" title="<?= h(__('t_3b9854e1bb', 'حذف')) ?>">
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php if (empty($ad['image_url'])): ?>
                                    <div class="text-muted w-100 h-100 d-flex align-items-center justify-content-center">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- معلومات الإعلان -->
                            <div class="gdy-ad-info">
                                <div class="gdy-ad-title">
                                    <?= h($ad['title']) ?>
                                </div>
                                
                                <div class="gdy-ad-location">
                                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    <?= h($ad['location']) ?>
                                </div>
                                
                                <?php if (!empty($ad['target_url'])): ?>
                                    <div class="gdy-ad-url">
                                        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        <?= h($ad['target_url']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="gdy-ad-meta">
                                    <span class="gdy-ad-status <?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                    
                                    <?php if ($ad['starts_at'] || $ad['ends_at']): ?>
                                        <div class="text-muted small">
                                            <?php if ($ad['starts_at']): ?>
                                                <?= h(date('Y-m-d', strtotime($ad['starts_at']))) ?>
                                            <?php endif; ?>
                                            <?php if ($ad['ends_at']): ?>
                                                - <?= h(date('Y-m-d', strtotime($ad['ends_at']))) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- إحصائيات -->
                                <div class="mt-3 pt-3 border-top border-secondary">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <small class="text-muted"><?= h(__('t_389466dde4', 'النقرات')) ?></small>
                                            <div class="small text-primary"><?= (int)($ad['click_count'] ?? 0) ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted"><?= h(__('t_4bfff8c6af', 'المشاهدات')) ?></small>
                                            <div class="small text-info"><?= (int)($ad['view_count'] ?? 0) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // البحث التلقائي مع تأخير بسيط
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput && searchInput.form) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchInput.form.submit();
            }, 500);
        });
    }

    // تأثير التبديل للإعدادات (للاستخدام المستقبلي)
    const toggleSwitch = document.querySelector('input[name="sidebar_ad_enabled"]');
    if (toggleSwitch) {
        toggleSwitch.addEventListener('change', function() {
            console.log('تم تغيير حالة البنر الجانبي:', this.checked);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
