<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/users/index.php — قائمة المستخدمين محسنة

require_once __DIR__ . '/../../includes/bootstrap.php';

$authFile = __DIR__ . '/../../includes/auth.php';
if (is_file($authFile)) {
    require_once $authFile;
}

use Godyar\Auth;

$currentPage = 'users';
$pageTitle   = __('t_5aa71f1bf6', 'إدارة المستخدمين');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// التحقق من الدخول
try {
    if (class_exists(Auth::class) && method_exists(Auth::class,'isLoggedIn')) {
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
    @error_log('[Admin Users] Auth: '.$e->getMessage());
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();

$roles   = [
    'superadmin' => ['label' => __('t_b06b6eb2aa', 'مشرف رئيسي'), 'color' => 'danger', 'icon' => 'fa-crown'],
    'admin' => ['label' => __('t_9150b182d1', 'مشرف'), 'color' => 'warning', 'icon' => 'fa-shield'],
    'editor' => ['label' => __('t_81807f1484', 'محرر'), 'color' => 'info', 'icon' => 'fa-edit'],
    'author' => ['label' => __('t_99cbece3bc', 'كاتب'), 'color' => 'success', 'icon' => 'fa-pen'],
    'user' => ['label' => __('t_f1beebf31c', 'مستخدم'), 'color' => 'secondary', 'icon' => 'fa-user']
];

$statuses = [
    'active' => ['label' => __('t_8caaf95380', 'نشط'), 'color' => 'success', 'icon' => 'fa-check-circle'],
    'inactive' => ['label' => __('t_1e0f5f1adc', 'غير نشط'), 'color' => 'secondary', 'icon' => 'fa-pause-circle'],
    'banned' => ['label' => __('t_e59b95cb50', 'محظور'), 'color' => 'danger', 'icon' => 'fa-ban']
];

// فلاتر
$role   = isset($_GET['role']) ? trim((string)$_GET['role']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$q      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$where  = ['1=1'];
$params = [];

if ($role !== '' && array_key_exists($role, $roles)) {
    $where[]         = 'u.role = :role';
    $params[':role'] = $role;
}
if ($status !== '' && array_key_exists($status, $statuses)) {
    $where[]           = 'u.status = :status';
    $params[':status'] = $status;
}
if ($q !== '') {
    $where[]      = '(u.username LIKE :q OR u.name LIKE :q OR u.email LIKE :q)';
    $params[':q'] = '%'.$q.'%';
}

$users = [];
$totalUsers = 0;
$activeUsers = 0;
$adminsCount = 0;

if ($pdo instanceof PDO) {
    try {
        // جلب المستخدمين
        $sql = "
            SELECT u.*
            FROM users u
            WHERE ".implode(' AND ', $where)."
            ORDER BY 
                CASE u.role 
                    WHEN 'superadmin' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'editor' THEN 3
                    WHEN 'author' THEN 4
                    ELSE 5
                END,
                u.id DESC
            LIMIT 200
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // الإحصائيات
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM users");
        $totalUsers = $totalStmt->fetchColumn();

        $activeStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $activeUsers = $activeStmt->fetchColumn();

        $adminsStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('superadmin', 'admin')");
        $adminsCount = $adminsStmt->fetchColumn();

    } catch (Throwable $e) {
        @error_log('[Admin Users] index query: '.$e->getMessage());
    }
}

$currentUser = Auth::user() ?? ($_SESSION['user'] ?? []);

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
:root{
    /* نضغط عرض محتوى صفحة إدارة المستخدمين ليكون مريحاً بجانب السايدبار */
    --gdy-shell-max: min(880px, 100vw - 360px);
}

html, body{
    overflow-x: hidden;
    background: #020617;
    color: #e5e7eb;
}

.admin-content{
    max-width: var(--gdy-shell-max);
    width: 100%;
    margin: 0 auto;
}

/* تقليل الفراغ العمودي الافتراضي داخل صفحات الإدارة */
.admin-content.container-fluid.py-4{
    padding-top: 0.75rem !important;
    padding-bottom: 1rem !important;
}

/* توحيد مسافة رأس الصفحة */
.gdy-page-header{
    margin-bottom: 0.75rem;
}

.gdy-users-grid {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
    padding: 2rem;
}

.gdy-user-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.9));
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1rem;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    height: 100%;
}

.gdy-user-card:hover {
    transform: translateY(-8px);
    border-color: #0ea5e9;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.gdy-user-avatar {
    position: relative;
    overflow: hidden;
    background: #0f172a;
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gdy-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gdy-user-card:hover .gdy-user-avatar img {
    transform: scale(1.05);
}

.gdy-user-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(180deg, transparent 40%, rgba(15, 23, 42, 0.95) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    align-items: flex-end;
    padding: 1rem;
}

.gdy-user-card:hover .gdy-user-overlay {
    opacity: 1;
}

.gdy-user-actions {
    display: flex;
    gap: 0.5rem;
    width: 100%;
    justify-content: center;
}

.gdy-user-btn {
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

.gdy-user-btn:hover {
    background: #0ea5e9;
    color: white;
    transform: scale(1.1);
}

.gdy-user-info {
    padding: 1.5rem;
}

.gdy-user-name {
    font-weight: 600;
    color: #e5e7eb;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
    line-height: 1.4;
}

.gdy-user-username {
    color: #94a3b8;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.gdy-user-email {
    color: #0ea5e9;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.gdy-user-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: #64748b;
}

.gdy-user-role {
    background: rgba(14, 165, 233, 0.2);
    color: #0ea5e9;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-weight: 500;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.gdy-user-status {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-weight: 500;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.gdy-user-status.inactive {
    background: rgba(100, 116, 139, 0.2);
    color: #64748b;
}

.gdy-user-status.banned {
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

@media (max-width: 768px) {
    .gdy-users-grid {
        padding: 1rem;
    }
    
    .gdy-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    
    .gdy-stat {
        padding: 1rem;
    }
    
    .gdy-stat-value {
        font-size: 2rem;
    }
}
</style>

<div class="admin-content container-fluid py-4">
    <!-- رأس الصفحة -->
    <div class="admin-content gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h1 class="h4 mb-1 text-white"><?= h(__('t_5aa71f1bf6', 'إدارة المستخدمين')) ?></h1>
            <p class="mb-0" style="color:#e5e7eb;">
                <?= h(__('t_569340c7e9', 'إدارة حسابات المستخدمين والصلاحيات في النظام')) ?>
            </p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="create.php" class="btn btn-primary">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg> <?= h(__('t_480d828737', 'إضافة مستخدم جديد')) ?>
            </a>
        </div>
    </div>

    <!-- الإحصائيات -->
    <div class="gdy-stats">
        <div class="gdy-stat">
            <span class="gdy-stat-value"><?= $totalUsers ?></span>
            <span class="gdy-stat-label"><?= h(__('t_6736adddff', 'إجمالي المستخدمين')) ?></span>
        </div>
        <div class="gdy-stat">
            <span class="gdy-stat-value"><?= $activeUsers ?></span>
            <span class="gdy-stat-label"><?= h(__('t_aa109446d1', 'مستخدمين نشطين')) ?></span>
        </div>
        <div class="gdy-stat">
            <span class="gdy-stat-value"><?= $adminsCount ?></span>
            <span class="gdy-stat-label"><?= h(__('t_5787adcd13', 'مشرفين')) ?></span>
        </div>
        <div class="gdy-stat">
            <span class="gdy-stat-value"><?= count($users) ?></span>
            <span class="gdy-stat-label"><?= h(__('t_8dea9c0652', 'نتائج البحث')) ?></span>
        </div>
    </div>

    <!-- شريط التصفية -->
    <div class="gdy-filter-bar">
        <form method="get" class="row g-3 align-items-end">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <div class="col-12 col-md-4">
                <label class="form-label small mb-1" style="color: #e5e7eb;"><?= h(__('t_ab79fc1485', 'بحث')) ?></label>
                <input type="text" name="q" class="form-control bg-dark text-light border-secondary" 
                       value="<?= h($q) ?>" 
                       placeholder="<?= h(__('t_174cbf594a', 'ابحث بالاسم، اسم المستخدم، أو البريد...')) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1" style="color: #e5e7eb;"><?= h(__('t_1647921065', 'الدور')) ?></label>
                <select name="role" class="form-select bg-dark text-light border-secondary">
                    <option value=""><?= h(__('t_7f38b9650b', 'جميع الأدوار')) ?></option>
                    <?php foreach ($roles as $value => $roleInfo): ?>
                        <option value="<?= h($value) ?>" <?= $role === $value ? 'selected' : '' ?>>
                            <?= h($roleInfo['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1" style="color: #e5e7eb;"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
                <select name="status" class="form-select bg-dark text-light border-secondary">
                    <option value=""><?= h(__('t_a4028d028a', 'جميع الحالات')) ?></option>
                    <?php foreach ($statuses as $value => $statusInfo): ?>
                        <option value="<?= h($value) ?>" <?= $status === $value ? 'selected' : '' ?>>
                            <?= h($statusInfo['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <?= h(__('t_abe3151c63', 'تطبيق')) ?>
                </button>
            </div>
        </form>

        <?php
          // Saved Filters UI
          require_once __DIR__ . '/../includes/saved_filters_ui.php';
          echo gdy_saved_filters_ui('users');
?>

        <!-- إعادة التعيين -->
        <?php if ($role || $status || $q): ?>
            <div class="mt-3">
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <?= h(__('t_abcc7085f3', 'إعادة تعيين الفلتر')) ?>
                </a>
                <span class="text-muted small ms-2">
                    <?= count($users) ?> نتيجة
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- شبكة المستخدمين -->
    <div class="gdy-users-grid">
        <?php if (empty($users)): ?>
            <div class="gdy-empty-state">
                <div class="gdy-empty-icon">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                </div>
                <h4 class="text-muted mb-3"><?= h(__('t_d0852a495b', 'لا توجد مستخدمين')) ?></h4>
                <p class="text-muted mb-4"><?= h(__('t_aa99d4b434', 'ابدأ بإضافة أول مستخدم إلى النظام')) ?></p>
                <a href="create.php" class="btn btn-primary btn-lg">
                    <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg><?= h(__('t_9e922cf9b6', 'إضافة أول مستخدم')) ?>
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($users as $user): ?>
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="gdy-user-card">
                            <!-- صورة المستخدم -->
                            <div class="gdy-user-avatar">
                                <!-- يمكن إضافة صورة المستخدم هنا -->
                                <div class="text-muted w-100 h-100 d-flex align-items-center justify-content-center">
                                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                                </div>
                                <div class="gdy-user-overlay">
                                    <div class="gdy-user-actions">
                                        <a href="edit.php?id=<?= $user['id'] ?>" 
                                           class="gdy-user-btn"
                                           title="<?= h(__('t_759fdc242e', 'تعديل')) ?>">
                                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </a>
                                        <?php if ($user['id'] !== ($currentUser['id'] ?? 0)): ?>
                                            <button class="gdy-user-btn toggle-status" 
                                                    data-id="<?= $user['id'] ?>"
                                                    data-status="<?= $user['status'] ?>"
                                                    data-username="<?= h($user['username']) ?>"
                                                    title="<?= $user['status'] === 'active' ? __('t_43ead21245', 'تعطيل') : __('t_8403358516', 'تفعيل') ?>">
                                                <svg class="gdy-icon $user['status'] === 'active' ? 'pause' : 'play' ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            </button>
                                            <button class="gdy-user-btn delete-user" 
                                                    data-id="<?= $user['id'] ?>"
                                                    data-username="<?= h($user['username']) ?>"
                                                    title="<?= h(__('t_3b9854e1bb', 'حذف')) ?>">
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                            </button>
                                        <?php else: ?>
                                            <span class="gdy-user-btn" title="<?= h(__('t_2f03db59ec', 'لا يمكن تعديل حسابك')) ?>">
                                                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- معلومات المستخدم -->
                            <div class="gdy-user-info">
                                <div class="gdy-user-name">
                                    <?= h((($user['name'] ?? '') !== '' ? $user['name'] : ($user['username'] ?? ''))) ?>
                                </div>
                                <div class="gdy-user-username">
                                    @<?= h($user['username']) ?>
                                </div>
                                <div class="gdy-user-email">
                                    <?= h($user['email']) ?>
                                </div>
                                
                                <div class="gdy-user-meta">
                                    <span class="gdy-user-role">
                                        <svg class="gdy-icon <?= $roles[$user['role']]['icon'] ?? 'fa-user' ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
                                        <?= h($roles[$user['role']]['label'] ?? $user['role']) ?>
                                    </span>
                                    <span class="gdy-user-status <?= $user['status'] ?>">
                                        <svg class="gdy-icon <?= $statuses[$user['status']]['icon'] ?? 'fa-circle' ?>" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        <?= h($statuses[$user['status']]['label'] ?? $user['status']) ?>
                                    </span>
                                </div>
                                
                                <!-- معلومات إضافية -->
                                <div class="mt-3 pt-3 border-top border-secondary">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <small class="text-muted"><?= h(__('t_f7ed03560d', 'آخر دخول')) ?></small>
                                            <div class="small">
                                                <?php $lla = $user['last_login_at'] ?? null; echo $lla ? date('Y-m-d', strtotime($lla)) : __('t_7e2dd2d275', 'لم يسجل'); ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted"><?= h(__('t_d4ef3a02e7', 'تاريخ الإنشاء')) ?></small>
                                            <div class="small">
                                                <?= date('Y-m-d', strtotime($user['created_at'])) ?>
                                            </div>
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
</div>

<!-- مودال التأكيد -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-light">
            <div class="modal-header">
                <h5 class="modal-title text-white" id="modalTitle"><?= h(__('t_d0c0c79999', 'تأكيد الإجراء')) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="modalMessage" class="text-light"><?= h(__('t_f8ccab620d', 'هل أنت متأكد من تنفيذ هذا الإجراء؟')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h(__('t_b9568e869d', 'إلغاء')) ?></button>
                <button type="button" class="btn btn-danger" id="confirmAction"><?= h(__('t_7651effd37', 'تنفيذ')) ?></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const confirmAction = document.getElementById('confirmAction');
    
    let currentAction = '';
    let currentUserId = '';
    let currentUsername = '';

    // تبديل حالة المستخدم
    document.querySelectorAll('.toggle-status').forEach(btn => {
        btn.addEventListener('click', function() {
            currentUserId = this.getAttribute('data-id');
            currentUsername = this.getAttribute('data-username');
            const currentStatus = this.getAttribute('data-status');
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            modalTitle.textContent = newStatus === 'active' ? 'تفعيل المستخدم' : 'تعطيل المستخدم';
            modalMessage.textContent = `هل أنت متأكد من ${newStatus === 'active' ? 'تفعيل' : 'تعطيل'} المستخدم "${currentUsername}"؟`;
            
            currentAction = 'toggle';
            confirmAction.textContent = newStatus === 'active' ? 'تفعيل' : 'تعطيل';
            confirmAction.className = newStatus === 'active' ? 'btn btn-success' : 'btn btn-warning';
            
            confirmModal.show();
        });
    });
    
    // حذف المستخدم
    document.querySelectorAll('.delete-user').forEach(btn => {
        btn.addEventListener('click', function() {
            currentUserId = this.getAttribute('data-id');
            currentUsername = this.getAttribute('data-username');
            
            modalTitle.textContent = 'حذف المستخدم';
            modalMessage.textContent = `هل أنت متأكد من حذف المستخدم "${currentUsername}"؟ هذا الإجراء لا يمكن التراجع عنه.`;
            
            currentAction = 'delete';
            confirmAction.textContent = 'حذف';
            confirmAction.className = 'btn btn-danger';
            
            confirmModal.show();
        });
    });
    
    // تنفيذ الإجراء
    confirmAction.addEventListener('click', function() {
        if (currentAction === 'toggle') {
            toggleUserStatus(currentUserId);
        } else if (currentAction === 'delete') {
            deleteUser(currentUserId);
        }
        confirmModal.hide();
    });
    
    function toggleUserStatus(userId) {
        fetch('toggle_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('حدث خطأ أثناء تحديث الحالة: ' + (data.message || ''));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال');
        });
    }
    
    function deleteUser(userId) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('حدث خطأ أثناء الحذف: ' + (data.message || ''));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال');
        });
    }
    
    // بحث سريع
    const searchInput = document.querySelector('input[name="q"]');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>