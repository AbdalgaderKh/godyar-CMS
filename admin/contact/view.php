<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'contact';
$pageTitle   = __('t_1b986b8f54', 'عرض رسالة');

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
    @error_log('[Godyar Contact View] Auth error: ' . $e->getMessage());
    if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
        header('Location: ../login.php');
        exit;
    }
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database connection not available.');
}

// =====================
// 1) جلب ID الرسالة
// =====================
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// =====================
// 2) معالجة POST لتغيير الحالة
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_status') {
    // التحقق من CSRF إن وُجدت دالة المشروع
    if (function_exists('verify_csrf')) {
        try {
            verify_csrf();
        } catch (Throwable $e) {
            @error_log('[Godyar Contact View] CSRF error: ' . $e->getMessage());
            $_SESSION['error_message'] = __('t_3dde9f5e86', 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.');
            header('Location: view.php?id=' . $id);
            exit;
        }
    }

    $newStatus = $_POST['status'] ?? '';
    $allowed   = ['new', 'seen', 'replied'];

    if (!in_array($newStatus, $allowed, true)) {
        $_SESSION['error_message'] = __('t_7dbf55664a', 'حالة غير صالحة.');
        header('Location: view.php?id=' . $id);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE contact_messages 
            SET status = :status, updated_at = NOW() 
            WHERE id = :id 
            LIMIT 1
        ");
        $stmt->execute([
            ':status' => $newStatus,
            ':id'     => $id,
        ]);

        $_SESSION['success_message'] = __('t_db278f2c1c', 'تم تحديث حالة الرسالة بنجاح.');
        header('Location: view.php?id=' . $id);
        exit;
    } catch (Throwable $e) {
        @error_log('[Godyar Contact View] Status update error: ' . $e->getMessage());
        $_SESSION['error_message'] = __('t_cb1835a2c3', 'حدث خطأ أثناء تحديث حالة الرسالة.');
        header('Location: view.php?id=' . $id);
        exit;
    }
}

// =====================
// 3) جلب الرسالة
// =====================
$row = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header('Location: index.php');
        exit;
    }

    // لو كانت جديدة نحدّثها لمقروءة تلقائياً
    if (($row['status'] ?? '') === 'new') {
        $pdo->prepare("
            UPDATE contact_messages 
            SET status = 'seen', updated_at = NOW() 
            WHERE id = :id 
            LIMIT 1
        ")->execute(['id' => $id]);
        $row['status'] = 'seen';
    }
} catch (Throwable $e) {
    @error_log('[Godyar Contact View] Fetch error: ' . $e->getMessage());
    header('Location: index.php?error=1');
    exit;
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
:root{
    /* "التصميم الموحد للعرض" */
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

/* تقليل الفراغ العمودي */
.admin-content.container-fluid.py-4{
    padding-top:0.75rem !important;
    padding-bottom:1rem !important;
}

/* رأس الصفحة */
.gdy-page-header{
    margin-bottom:0.75rem;
}

/* الكرت */
.gdy-glass-card{
    background:rgba(15,23,42,0.96);
    border-radius:16px;
    border:1px solid rgba(31,41,55,0.9);
}

/* معلومات الرسالة */
.gdy-meta-list dt{
    font-size:0.85rem;
    color:#9ca3af;
}
.gdy-meta-list dd{
    font-size:0.95rem;
    margin-bottom:.35rem;
}

/* حالة الرسالة */
.badge-gdy{
    border-radius:999px;
    padding:0.25rem 0.7rem;
    font-size:0.7rem;
}
.badge-new{
    background:rgba(59,130,246,0.18);
    color:#bfdbfe;
    border:1px solid rgba(59,130,246,0.5);
}
.badge-replied{
    background:rgba(22,163,74,0.18);
    color:#bbf7d0;
    border:1px solid rgba(22,163,74,0.5);
}
.badge-seen{
    background:rgba(148,163,184,0.18);
    color:#e5e7eb;
    border:1px solid rgba(148,163,184,0.4);
}

/* صندوق الرسالة */
.gdy-message-box{
    background:#020617;
    border-radius:12px;
    border:1px solid #1f2937;
    padding:12px 14px;
    font-size:0.95rem;
    line-height:1.7;
    white-space:pre-wrap;
}

/* روابط صغيرة */
.gdy-email-link{
    font-size:0.9rem;
}

/* شارة صغيرة للميتا */
.gdy-chip{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:2px 8px;
    border-radius:999px;
    background:rgba(15,23,42,0.9);
    border:1px solid rgba(31,41,55,0.9);
    font-size:0.75rem;
    color:#9ca3af;
}

/* استجابة */
@media (max-width: 992px){
    :root{
        --gdy-shell-max: 100vw;
    }
}
</style>

<div class="admin-content container-fluid py-4">
    <!-- رأس الصفحة -->
    <div class="admin-content gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h4 text-white mb-1">
                <?= h(__('t_99081e2fc8', 'عرض الرسالة')) ?>
                <span class="gdy-chip ms-1">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= (int)$row['id'] ?>
                </span>
            </h1>
            <p class="text-muted mb-1 small">
                <?= h(__('t_4abf0bf376', 'تفاصيل رسالة التواصل الواردة من نموذج اتصل بنا.')) ?>
            </p>
            <div class="d-flex flex-wrap gap-2 mt-1">
                <span class="gdy-chip">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    أرسلت في: <?= h($row['created_at'] ?? '') ?>
                </span>
                <?php if (!empty($row['updated_at'])): ?>
                    <span class="gdy-chip">
                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                        آخر تحديث: <?= h($row['updated_at']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="index.php" class="btn btn-sm btn-outline-light">
                <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_fed95e1016', 'عودة للقائمة')) ?>
            </a>
        </div>
    </div>

    <!-- رسائل النجاح / الخطأ -->
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success py-2">
            <?= h($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger py-2">
            <?= h($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm gdy-glass-card">
        <div class="card-body">
            <div class="mb-3">
                <dl class="row mb-0 gdy-meta-list">
                    <dt class="col-sm-3 col-md-2"><?= h(__('t_901675875a', 'المرسل')) ?></dt>
                    <dd class="col-sm-9 col-md-10 text-white">
                        <?= h($row['name'] ?? '') ?>
                    </dd>

                    <dt class="col-sm-3 col-md-2"><?= h(__('t_c707d7f2bb', 'البريد')) ?></dt>
                    <dd class="col-sm-9 col-md-10">
                        <?php if (!empty($row['email'])): ?>
                            <a class="gdy-email-link" href="mailto:<?= h($row['email']) ?>">
                                <?= h($row['email']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted small"><?= h(__('t_6a6189403f', 'غير مذكور')) ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-3 col-md-2"><?= h(__('t_881c23b1d1', 'الموضوع')) ?></dt>
                    <dd class="col-sm-9 col-md-10 text-white">
                        <?= h($row['subject'] ?? '') ?>
                    </dd>

                    <dt class="col-sm-3 col-md-2"><?= h(__('t_1253eb5642', 'الحالة')) ?></dt>
                    <dd class="col-sm-9 col-md-10 d-flex align-items-center gap-2 flex-wrap">
                        <?php $status = $row['status'] ?? ''; ?>
                        <?php if ($status === 'new'): ?>
                            <span class="badge-gdy badge-new"><?= h(__('t_da694c6d97', 'جديدة')) ?></span>
                        <?php elseif ($status === 'replied'): ?>
                            <span class="badge-gdy badge-replied"><?= h(__('t_37d8352070', 'تم الرد')) ?></span>
                        <?php else: ?>
                            <span class="badge-gdy badge-seen"><?= h(__('t_9e21ea7aee', 'مقروءة')) ?></span>
                        <?php endif; ?>

                        <!-- أزرار تغيير الحالة -->
                        <form method="post" class="d-flex flex-wrap gap-1 ms-2">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                            <input type="hidden" name="action" value="set_status">
                            <button type="submit" name="status" value="new"
                                    class="btn btn-xs btn-outline-primary <?= $status === 'new' ? 'active' : '' ?>">
                                <?= h(__('t_6b5d82a1e3', 'علامة كـ جديدة')) ?>
                            </button>
                            <button type="submit" name="status" value="seen"
                                    class="btn btn-xs btn-outline-secondary <?= $status === 'seen' ? 'active' : '' ?>">
                                <?= h(__('t_699c051c1f', 'علامة كـ مقروءة')) ?>
                            </button>
                            <button type="submit" name="status" value="replied"
                                    class="btn btn-xs btn-outline-success <?= $status === 'replied' ? 'active' : '' ?>">
                                <?= h(__('t_839dc0aca8', 'علامة كـ تم الرد')) ?>
                            </button>
                        </form>
                    </dd>
                </dl>
            </div>

            <hr class="border-secondary opacity-25">

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 text-white">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <?= h(__('t_6a3c8bac29', 'نص الرسالة')) ?>
                </h6>
                <button type="button" id="copyMessageBtn" class="btn btn-xs btn-outline-secondary">
                    <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_fb2df472a1', 'نسخ نص الرسالة')) ?>
                </button>
            </div>

            <div class="gdy-message-box" id="messageContent">
                <?= h($row['message'] ?? '') ?>
            </div>

            <!-- الأزرار أسفل نص الرسالة كما طلبت -->
            <div class="d-flex flex-wrap gap-2 mt-3 justify-content-between">
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($row['email'])): ?>
                        <button type="button" id="copyEmailBtn" class="btn btn-sm btn-outline-light">
                            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_e489e850f9', 'نسخ البريد')) ?>
                        </button>

                        <a href="mailto:<?= h($row['email']) ?>?subject=<?= rawurlencode(__('t_3435d61012', 'رد على: ') . (string)($row['subject'] ?? '')) ?>"
                           class="btn btn-sm btn-primary">
                            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_8095aeba44', 'الرد عبر البريد')) ?>
                        </a>
                    <?php endif; ?>

                    <a href="reply.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-success">
                        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_5ac782ffd1', 'الرد من لوحة التحكم')) ?>
                    </a>
                </div>

                <!-- زر الرجوع هنا أيضاً إن حبيت يكون قريب من الأزرار -->
                <div class="mt-2 mt-md-0">
                    <a href="index.php" class="btn btn-sm btn-outline-light">
                        <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_fed95e1016', 'عودة للقائمة')) ?>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // نسخ البريد
    const copyEmailBtn = document.getElementById('copyEmailBtn');
    if (copyEmailBtn) {
        copyEmailBtn.addEventListener('click', function () {
            const email = <?= json_encode((string)($row['email'] ?? '')) ?>;
            if (!email) return;
            navigator.clipboard.writeText(email).then(function () {
                copyEmailBtn.innerHTML = '<svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_5c43a7b9ec', 'تم النسخ\';
                setTimeout(function () {
                    copyEmailBtn.innerHTML = \'')) ?><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_79da066ba6', 'نسخ البريد\';
                }, 1500);
            });
        });
    }

    // نسخ نص الرسالة
    const copyMessageBtn = document.getElementById(\'copyMessageBtn\');
    const messageContent = document.getElementById(\'messageContent\');
    if (copyMessageBtn && messageContent) {
        copyMessageBtn.addEventListener(\'click\', function () {
            const text = messageContent.innerText || messageContent.textContent || \'\';
            if (!text) return;
            navigator.clipboard.writeText(text).then(function () {
                copyMessageBtn.innerHTML = \'')) ?><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_ce244ff56c', 'تم النسخ\';
                setTimeout(function () {
                    copyMessageBtn.innerHTML = \'')) ?><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_e815215918', 'نسخ نص الرسالة\';
                }, 1500);
            });
        });
    }
});')) ?>
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
