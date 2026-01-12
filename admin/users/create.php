<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/users/create.php — إضافة مستخدم محسّن

require_once __DIR__ . '/../../includes/bootstrap.php';

$authFile = __DIR__ . '/../../includes/auth.php';
if (is_file($authFile)) {
    require_once $authFile;
}

use Godyar\Auth;

$currentPage = 'users';
$pageTitle   = __('t_480d828737', 'إضافة مستخدم جديد');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// دالة لتوليد كلمة مرور عشوائية
function generateRandomPassword($length = 12): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// التحقق من الدخول والصلاحيات
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
    
    // التحقق من صلاحية إضافة مستخدمين (مشرف أو أعلى)
    $currentUser = $_SESSION['user'] ?? [];
    $allowedRoles = ['superadmin', 'admin'];
    if (!in_array($currentUser['role'] ?? '', $allowedRoles)) {
        header('Location: index.php?error=permission_denied');
        exit;
    }
} catch (Throwable $e) {
    @error_log('[Admin Users Create] Auth: '.$e->getMessage());
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();

// Helper: get table columns even if db_table_columns() isn't available in this context.
if (!function_exists('gdy_table_columns')) {
    function gdy_table_columns(PDO $pdo, string $table): array {
        try {
            if (function_exists('db_table_columns')) {
                $cols = db_table_columns($pdo, $table);
                if (is_array($cols) && !empty($cols)) return $cols;
            }
            $safeTable = str_replace('`', '', $table);
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$safeTable}`");
            if (!$stmt) return [];
            $cols = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['Field'])) $cols[] = (string)$row['Field'];
            }
            return $cols;
        } catch (Throwable $e) {
            return [];
        }
    }
}
// اكتشاف أعمدة جدول users لضمان التوافق (password/password_hash/...)
$userCols = [];
$passwordCol = null;
$hasCreatedAt = false;
$hasUpdatedAt = false;
try {
    if ($pdo instanceof PDO) {
        $userCols = gdy_table_columns($pdo, 'users');
        if (!empty($userCols)) {
            foreach (['password_hash','pass_hash','password','passwd','password_digest'] as $c) {
                if (in_array($c, $userCols, true)) { $passwordCol = $c; break; }
            }
            $hasCreatedAt = in_array('created_at', $userCols, true);
            $hasUpdatedAt = in_array('updated_at', $userCols, true);
        }
    }
} catch (Throwable $e) {
    $userCols = [];
}

$roles    = ['superadmin' => __('t_b06b6eb2aa', 'مشرف رئيسي'), 'admin' => __('t_9150b182d1', 'مشرف'), 'editor' => __('t_81807f1484', 'محرر'), 'author' => __('t_99cbece3bc', 'كاتب'), 'user' => __('t_ba02c97b6a', 'مستخدم عادي')];
$statuses = ['active' => __('t_8caaf95380', 'نشط'), 'inactive' => __('t_1e0f5f1adc', 'غير نشط'), 'banned' => __('t_e59b95cb50', 'محظور')];

$data = [
    'username' => '',
    'name'     => '',
    'email'    => '',
    'role'     => 'editor',
    'status'   => 'active',
];
$errors = [];
$success = null;

// التحقق من وجود جدول users
$tableExists = false;
if ($pdo instanceof PDO) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'users'");
        $tableExists = $check && $check->fetchColumn();
    } catch (Exception $e) {
        error_log(__('t_dfca1f1cd5', 'خطأ في التحقق من جدول المستخدمين: ') . $e->getMessage());
    }
}

// CSRF
if (empty($_SESSION['users_csrf'])) {
    $_SESSION['users_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['users_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? '';
    if (!hash_equals($csrf, $token)) {
        $errors[] = __('t_1961026b59', 'انتهت صلاحية النموذج، أعد تحميل الصفحة وحاول مرة أخرى.');
    } else {
        $data['username'] = trim((string)($_POST['username'] ?? ''));
        $data['name']     = trim((string)($_POST['name'] ?? ''));
        $data['email']    = trim((string)($_POST['email'] ?? ''));
        $data['role']     = trim((string)($_POST['role'] ?? 'editor'));
        $data['status']   = trim((string)($_POST['status'] ?? 'active'));
        $password         = (string)($_POST['password'] ?? '');
        $passwordConfirm  = (string)($_POST['password_confirm'] ?? '');
        $sendEmail        = isset($_POST['send_email']);

        // التحقق من الصحة
        if (empty($data['username'])) {
            $errors[] = __('t_e4b3a035cc', 'اسم المستخدم مطلوب.');
        } else {
            // ✅ اسم المستخدم: 3-30 (يدعم العربية) ويمكن أن يحتوي على أرقام و . _ -
            // نقبل مسافة داخلية بين الكلمات (بدون مسافة في البداية/النهاية)
            $data['username'] = preg_replace('~\s+~u', ' ', $data['username']);
            $ulen = mb_strlen($data['username'], 'UTF-8');

            if ($ulen < 3 || $ulen > 30) {
                $errors[] = __('t_af7bb71df2', 'اسم المستخدم يجب أن يكون 3-30 حرف (يدعم العربية)، ويمكن أن يحتوي على أرقام و . _ -');
            } elseif (!preg_match('~^[\p{L}\p{M}\p{N}._-]+(?: [\p{L}\p{M}\p{N}._-]+)*$~u', $data['username'])) {
                $errors[] = __('t_af7bb71df2', 'اسم المستخدم يجب أن يكون 3-30 حرف (يدعم العربية)، ويمكن أن يحتوي على أرقام و . _ -');
            }
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('t_c7e0030d94', 'بريد إلكتروني صالح مطلوب.');
        }

        if (empty($password) || strlen($password) < 8) {
            $errors[] = __('t_a7e3062406', 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.');
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = __('t_ce45965d6d', 'كلمة المرور يجب أن تحتوي على حرف كبير، حرف صغير، ورقم على الأقل.');
        }

        if ($password !== $passwordConfirm) {
            $errors[] = __('t_8267fda130', 'تأكيد كلمة المرور غير مطابق.');
        }

        if (!array_key_exists($data['role'], $roles)) {
            $errors[] = __('t_e5ede8cea8', 'دور غير صالح.');
        }

        if (!array_key_exists($data['status'], $statuses)) {
            $errors[] = __('t_7dbf55664a', 'حالة غير صالحة.');
        }

        if (!$errors && $pdo instanceof PDO && $tableExists) {
            // التأكد من عدم تكرار username / email
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u OR email = :e");
                $stmt->execute([':u' => $data['username'], ':e' => $data['email']]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = __('t_c6ed943adb', 'اسم المستخدم أو البريد الإلكتروني مستخدم مسبقاً.');
                }
            } catch (Throwable $e) {
                @error_log('[Admin Users Create] check unique: '.$e->getMessage());
                $errors[] = __('t_9c5a2ca0a6', 'حدث خطأ في التحقق من البيانات.');
            }
        }

        if (!$errors && $pdo instanceof PDO && $tableExists) {
            try {
                if (!$passwordCol) {
                    $errors[] = __('t_9f2b9c0f3c', 'لا يمكن إنشاء مستخدم: جدول المستخدمين لا يحتوي على عمود مناسب لحفظ كلمة المرور.');
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $insertCols = ['username', 'name', 'email', $passwordCol, 'role', 'status'];
                    $insertVals = [':u', ':n', ':e', ':p', ':r', ':s'];
                    if ($hasCreatedAt) {
                        $insertCols[] = 'created_at';
                        $insertVals[] = 'NOW()';
                    }
                    if ($hasUpdatedAt) {
                        $insertCols[] = 'updated_at';
                        $insertVals[] = 'NOW()';
                    }

                    $sql = "INSERT INTO users (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':u' => $data['username'],
                        ':n' => $data['name'],
                        ':e' => $data['email'],
                        ':p' => $hash,
                        ':r' => $data['role'],
                        ':s' => $data['status'],
                    ]);
                }

                // إذا لم يتم إدراج المستخدم بسبب أخطاء (مثل عدم وجود عمود كلمة المرور)
                // لا نعرض رسالة نجاح ولا نقوم بتحويل.
                if (!$errors) {
                    $userId = $pdo->lastInsertId();

                    // إعادة توليد CSRF token
                    $_SESSION['users_csrf'] = bin2hex(random_bytes(32));

                    // عرض رسالة نجاح مع خيارات متعددة
                    $success = __('t_5d2755e98f', 'تم إنشاء المستخدم بنجاح!');

                    if (isset($_POST['save_and_list'])) {
                        header('Location: index.php?success=created&id=' . $userId);
                        exit;
                    } elseif (isset($_POST['save_and_edit']) && $userId) {
                        header('Location: edit.php?id=' . $userId . '&success=created');
                        exit;
                    }
                }
                
            } catch (Throwable $e) {
                @error_log('[Admin Users Create] insert: '.$e->getMessage());
                $errors[] = __('t_89f7c31032', 'حدث خطأ أثناء حفظ المستخدم الجديد: ') . $e->getMessage();
            }
        } elseif (!$tableExists) {
            $errors[] = __('t_845f1fa89f', 'جدول المستخدمين غير موجود. يرجى التواصل مع المسؤول.');
        }
    }
}

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

.gdy-form-card {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
}

.gdy-form-sidebar {
    background: rgba(30, 41, 59, 0.6);
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.2);
}

.password-strength {
    height: 6px;
    border-radius: 3px;
    margin-top: 5px;
    transition: all 0.3s ease;
}

.strength-0 { background: #ef4444; width: 20%; }
.strength-1 { background: #f59e0b; width: 40%; }
.strength-2 { background: #eab308; width: 60%; }
.strength-3 { background: #84cc16; width: 80%; }
.strength-4 { background: #22c55e; width: 100%; }

.strength-text {
    font-size: 0.85rem;
    margin-top: 5px;
}

.char-count {
    font-size: 0.8rem;
    color: #64748b;
}

.role-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.role-superadmin { background: #dc2626; color: white; }
.role-admin { background: #ea580c; color: white; }
.role-editor { background: #d97706; color: white; }
.role-author { background: #059669; color: white; }
.role-user { background: #2563eb; color: white; }
</style>

<div class="admin-content container-fluid py-4">
    <!-- رأس الصفحة -->
    <div class="admin-content gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h1 class="h4 text-white mb-1"><?= h(__('t_480d828737', 'إضافة مستخدم جديد')) ?></h1>
            <p class="text-muted mb-0 small">
                <?= h(__('t_f0742094fd', 'إنشاء حساب مستخدم جديد في النظام')) ?>
            </p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                <?= h(__('t_5a029c710d', 'العودة لقائمة المستخدمين')) ?>
            </a>
        </div>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <strong><?= h(__('t_b83c3996d9', 'تنبيه:')) ?></strong> <?= h(__('t_56df17d34d', 'جدول المستخدمين غير موجود.')) ?> 
            <a href="create_table.php" class="alert-link"><?= h(__('t_98b74d89fa', 'انقر هنا لإنشاء الجدول')) ?></a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
            <?= h($success) ?>
            <div class="mt-2">
                <a href="index.php" class="btn btn-sm btn-outline-success me-2"><?= h(__('t_30997e79c2', 'عرض جميع المستخدمين')) ?></a>
                <?php if (isset($userId)): ?>
                    <a href="edit.php?id=<?= $userId ?>" class="btn btn-sm btn-success"><?= h(__('t_b8b7a31a32', 'تعديل المستخدم')) ?></a>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

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

    <form method="post" id="userForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <input type="hidden" name="_token" value="<?= h($csrf) ?>">
        
        <div class="row g-4">
            <!-- العمود الرئيسي -->
            <div class="col-lg-8">
                <div class="gdy-form-card card shadow-sm">
                    <div class="card-body">
                        <!-- المعلومات الأساسية -->
                        <h5 class="card-title mb-4"><?= h(__('t_0f6bc67891', 'المعلومات الأساسية')) ?></h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?= h(__('t_f6767f689d', 'اسم المستخدم *')) ?></label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?= h($data['username']) ?>" 
                                       placeholder="<?= h(__('t_ceee6a9885', 'يجب أن يكون باللغة الإنجليزية')) ?>"
                                       required
                                       minlength="3"
                                       pattern="[a-zA-Z0-9_]+"
                                       title="<?= h(__('t_0c98826c2d', 'أحرف إنجليزية، أرقام، وشرطة سفلية فقط')) ?>">
                                <div class="form-text"><?= h(__('t_2fbec81ac4', '3 أحرف على الأقل، إنجليزية وأرقام فقط')) ?></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?= h(__('t_90f9115ac9', 'الاسم الكامل')) ?></label>
                                <input type="text" name="name" class="form-control"
                                       value="<?= h($data['name']) ?>"
                                       placeholder="<?= h(__('t_aeab31a759', 'الاسم الكامل للمستخدم')) ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-semibold"><?= h(__('t_b052caae3b', 'البريد الإلكتروني *')) ?></label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= h($data['email']) ?>"
                                       placeholder="example@domain.com"
                                       required>
                            </div>
                        </div>

                        <!-- كلمة المرور -->
                        <h5 class="card-title mt-5 mb-4"><?= h(__('t_bcb75ee312', 'كلمة المرور')) ?></h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?= h(__('t_88fd9f793b', 'كلمة المرور *')) ?></label>
                                <div class="input-group">
                                    <input type="password" name="password" class="form-control" 
                                           id="passwordInput"
                                           placeholder="<?= h(__('t_2c598bb67d', '8 أحرف على الأقل')) ?>"
                                           required
                                           minlength="8">
                                    <button type="button" class="btn btn-outline-secondary" 
                                            id="togglePassword">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" 
                                            id="generatePassword">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                                <div class="strength-text" id="strengthText"></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?= h(__('t_a255bc3d65', 'تأكيد كلمة المرور *')) ?></label>
                                <input type="password" name="password_confirm" class="form-control" 
                                       id="passwordConfirm"
                                       placeholder="<?= h(__('t_9558ea3c2b', 'أعد إدخال كلمة المرور')) ?>"
                                       required>
                                <div class="form-text" id="confirmText"></div>
                            </div>
                        </div>

                        <!-- متطلبات كلمة المرور -->
                        <div class="alert alert-info mt-3">
                            <h6 class="alert-heading"><?= h(__('t_1fafb80446', 'متطلبات كلمة المرور:')) ?></h6>
                            <ul class="mb-0 small">
                                <li><?= h(__('t_2c598bb67d', '8 أحرف على الأقل')) ?></li>
                                <li><?= h(__('t_38525f503e', 'حرف كبير واحد على الأقل (A-Z)')) ?></li>
                                <li><?= h(__('t_fa4ef508eb', 'حرف صغير واحد على الأقل (a-z)')) ?></li>
                                <li><?= h(__('t_82fffeba1b', 'رقم واحد على الأقل (0-9)')) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <div class="gdy-form-sidebar card shadow-sm">
                    <div class="card-body">
                        <!-- الصلاحيات والحالة -->
                        <h6 class="card-title mb-3"><?= h(__('t_1f60020959', 'الإعدادات')) ?></h6>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= h(__('t_b658c151c4', 'دور المستخدم')) ?></label>
                            <select name="role" class="form-select" id="roleSelect">
                                <?php foreach ($roles as $value => $label): ?>
                                    <option value="<?= h($value) ?>" 
                                            <?= $data['role'] === $value ? 'selected' : '' ?>
                                            data-description="<?= h(getRoleDescription($value)) ?>">
                                        <?= h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text mt-2" id="roleDescription">
                                <?= getRoleDescription($data['role']) ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold"><?= h(__('t_a9460d87de', 'حالة الحساب')) ?></label>
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $value => $label): ?>
                                    <option value="<?= h($value) ?>" 
                                            <?= $data['status'] === $value ? 'selected' : '' ?>>
                                        <?= h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- إعدادات إضافية -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_email" 
                                       id="sendEmail" value="1">
                                <label class="form-check-label" for="sendEmail">
                                    <?= h(__('t_3636d551f1', 'إرسال تفاصيل الحساب بالبريد الإلكتروني')) ?>
                                </label>
                            </div>
                            <div class="form-text"><?= h(__('t_2f509ef1b1', 'سيتم إرسال اسم المستخدم وكلمة المرور للبريد المدخل')) ?></div>
                        </div>

                        <!-- أزرار الحفظ -->
                        <div class="border-top pt-4">
                            <button type="submit" name="save" class="btn btn-primary w-100 mb-2">
                                <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <?= h(__('t_d876a2e373', 'حفظ المستخدم')) ?>
                            </button>
                            
                            <button type="submit" name="save_and_edit" class="btn btn-outline-primary w-100 mb-2">
                                <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <?= h(__('t_dda4dd7c65', 'حفظ والمتابعة في التعديل')) ?>
                            </button>
                            
                            <button type="submit" name="save_and_list" class="btn btn-outline-light w-100">
                                <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                <?= h(__('t_e934ff7404', 'حفظ والعودة للقائمة')) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- معلومات الأدوار -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-dark text-light">
                        <h6 class="card-title mb-0">
                            <svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(__('t_8111cbf1a9', 'معلومات الأدوار')) ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($roles as $value => $label): ?>
                            <div class="mb-2 d-flex align-items-center">
                                <span class="badge role-<?= $value ?> role-badge me-2"><?= h($label) ?></span>
                                <small class="text-muted"><?= getRoleDescription($value) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('passwordInput');
    const passwordConfirm = document.getElementById('passwordConfirm');
    const togglePassword = document.getElementById('togglePassword');
    const generatePassword = document.getElementById('generatePassword');
    const passwordStrength = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('strengthText');
    const confirmText = document.getElementById('confirmText');
    const roleSelect = document.getElementById('roleSelect');
    const roleDescription = document.getElementById('roleDescription');

    // وصف الأدوار
    const roleDescriptions = {
        'superadmin': 'صلاحيات كاملة على النظام بما في ذلك إدارة المستخدمين والإعدادات',
        'admin': 'يمكنه إدارة المحتوى والمستخدمين (عدا المشرفين الرئيسيين)',
        'editor': 'يمكنه تحرير ونشر المحتوى لكن لا يمكنه إدارة المستخدمين',
        'author': 'يمكنه كتابة المقالات وإدارتها لكن لا يمكنه النشر',
        'user': 'يمكنه الوصول للوحة التحكم لكن بصلاحيات محدودة'
    };

    // تبديل إظهار/إخفاء كلمة المرور
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        passwordConfirm.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>' : '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>';
    });

    // توليد كلمة مرور عشوائية
    generatePassword.addEventListener('click', function() {
        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        let password = '';
        for (let i = 0; i < 12; i++) {
            password += chars[Math.floor(Math.random() * chars.length)];
        }
        passwordInput.value = password;
        passwordConfirm.value = password;
        checkPasswordStrength();
        checkPasswordMatch();
    });

    // التحقق من قوة كلمة المرور
    function checkPasswordStrength() {
        const password = passwordInput.value;
        let strength = 0;
        let text = '';
        let color = '';

        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        switch (strength) {
            case 0:
            case 1:
                text = 'ضعيفة جداً';
                color = 'strength-0';
                break;
            case 2:
                text = 'ضعيفة';
                color = 'strength-1';
                break;
            case 3:
                text = 'جيدة';
                color = 'strength-2';
                break;
            case 4:
                text = 'قوية';
                color = 'strength-3';
                break;
            case 5:
                text = 'قوية جداً';
                color = 'strength-4';
                break;
        }

        passwordStrength.className = 'password-strength ' + color;
        strengthText.textContent = text;
        strengthText.style.color = getComputedStyle(passwordStrength).backgroundColor;
    }

    // التحقق من تطابق كلمتي المرور
    function checkPasswordMatch() {
        if (passwordConfirm.value === '') {
            confirmText.textContent = '';
            confirmText.className = 'form-text';
        } else if (passwordInput.value === passwordConfirm.value) {
            confirmText.textContent = '✓ كلمتا المرور متطابقتان';
            confirmText.className = 'form-text text-success';
        } else {
            confirmText.textContent = '✗ كلمتا المرور غير متطابقتان';
            confirmText.className = 'form-text text-danger';
        }
    }

    // تحديث وصف الدور
    function updateRoleDescription() {
        const selectedRole = roleSelect.value;
        roleDescription.textContent = roleDescriptions[selectedRole] || '';
    }

    // الأحداث
    passwordInput.addEventListener('input', checkPasswordStrength);
    passwordInput.addEventListener('input', checkPasswordMatch);
    passwordConfirm.addEventListener('input', checkPasswordMatch);
    roleSelect.addEventListener('change', updateRoleDescription);

    // التهيئة الأولية
    checkPasswordStrength();
    checkPasswordMatch();
    updateRoleDescription();

    // منع إرسال النموذج إذا كانت كلمة المرور ضعيفة
    document.getElementById('userForm').addEventListener('submit', function(e) {
        const password = passwordInput.value;
        if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
            e.preventDefault();
            alert('يرجى إدخال كلمة مرور قوية تفي بالمتطلبات');
            passwordInput.focus();
        }
    });
});
</script>

<?php 
// دالة مساعدة لوصف الأدوار
function getRoleDescription($role): string {
    $descriptions = [
        'superadmin' => __('t_0ef12810c4', 'صلاحيات كاملة على النظام بما في ذلك إدارة المستخدمين والإعدادات'),
        'admin' => __('t_a6c2e2fe2e', 'يمكنه إدارة المحتوى والمستخدمين (عدا المشرفين الرئيسيين)'),
        'editor' => __('t_35e0738385', 'يمكنه تحرير ونشر المحتوى لكن لا يمكنه إدارة المستخدمين'),
        'author' => __('t_b75ff65957', 'يمكنه كتابة المقالات وإدارتها لكن لا يمكنه النشر'),
        'user' => __('t_8dbb4c994c', 'يمكنه الوصول للوحة التحكم لكن بصلاحيات محدودة')
    ];
    return $descriptions[$role] ?? __('t_60c1c939a6', 'دور غير معروف');
}

require_once __DIR__ . '/../layout/footer.php'; 
?>
