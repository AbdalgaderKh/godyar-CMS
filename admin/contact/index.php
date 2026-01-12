<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'contact';
$pageTitle   = __('t_cab8942d73', 'رسائل التواصل');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

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
    @error_log('[Godyar Contact] Auth error: ' . $e->getMessage());
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

// تحديث حالة الرسالة إلى __('t_9e21ea7aee', "مقروءة") (POST + CSRF)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['seen']) && ctype_digit((string)$_POST['seen'])) {
    verify_csrf();
    $id = (int)$_POST['seen'];
    try {
        $stmt = $pdo->prepare("
            UPDATE contact_messages
            SET status = 'seen', updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        @error_log('[Godyar Contact] Seen error: ' . $e->getMessage());
        header('Location: index.php?error=1');
        exit;
    }
}

if (isset($_GET['seen']) && ctype_digit((string)$_GET['seen'])) {
    $id = (int)$_GET['seen'];

    echo "<!doctype html><html lang='ar' dir='rtl'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>تأكيد</title>" .
         "<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;background:#f6f7fb}.card{max-width:560px;margin:10vh auto;background:#fff;border:1px solid #e7e9ef;border-radius:14px;padding:18px}.btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #d7d9e0;text-decoration:none}.muted{color:#6b7280}</style></head><body>" .
         "<div class='card'><h2 style='margin:0 0 10px'>تأكيد تغيير الحالة</h2><p class='muted'>سيتم تعليم الرسالة كمقروءة.</p>" .
         "<form method='post' style='margin-top:14px'>";
    csrf_field();
    echo "<input type='hidden' name='seen' value='" . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . "'>" .
         "<button class='btn' type='submit'>تأكيد</button> " .
         "<a class='btn' href='index.php'>إلغاء</a>" .
         "</form></div></body></html>";
    exit;
}


// جلب الرسائل
$rows = [];
try {
    $stmt = $pdo->query("
        SELECT id, name, email, subject, status, created_at
        FROM contact_messages
        ORDER BY id DESC
        LIMIT 100
    ");
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    @error_log('[Godyar Contact] Fetch error: ' . $e->getMessage());
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>

<style>
:root{
    /* نفس نمط "التصميم الموحد للعرض" */
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

/* كارت موحّد */
.gdy-glass-card{
    background:rgba(15,23,42,0.96);
    border-radius:16px;
    border:1px solid rgba(31,41,55,0.9);
}

/* جدول موحّد الألوان */
.gdy-table-wrapper{
    border-radius: 0 0 16px 16px;
    overflow: hidden;
}

.gdy-table{
    background:#020617;
    color:#e5e7eb;
    margin-bottom:0;
    font-size:0.875rem;
}

.gdy-table thead{
    background:rgba(15,23,42,0.9);
}

.gdy-table thead th{
    border-bottom-color:#1f2937;
    font-weight:600;
    color:#cbd5f5;
}

.gdy-table tbody tr{
    transition:background .15s ease, transform .1s ease;
}

.gdy-table tbody tr:hover{
    background:rgba(30,64,175,0.25);
    transform:translateY(-1px);
}

/* تمييز الرسائل الجديدة */
.gdy-row-new{
    background:rgba(30,64,175,0.2);
}

.gdy-row-new td:first-child::before{
    content:"●";
    color:#3b82f6;
    font-size:0.6rem;
    margin-left:4px;
}

/* أعمدة */
.gdy-col-id{ width:60px; }
.gdy-col-status{ width:120px; }
.gdy-col-date{ width:150px; }
.gdy-col-actions{ width:120px; }

/* الشيبس / البادجات */
.badge-gdy{
    border-radius:999px;
    padding:0.25rem 0.65rem;
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

/* أزرار صغيرة */
.btn-gdy-xs{
    --bs-btn-padding-y: .15rem;
    --bs-btn-padding-x: .45rem;
    --bs-btn-font-size: .7rem;
}

/* نص داخل الجدول */
.gdy-cell-name{
    max-width:160px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.gdy-cell-subject{
    max-width:220px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.gdy-cell-email{
    max-width:200px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

/* شريط معلومات أعلى الكارت (عدد الرسائل) */
.gdy-meta-bar{
    display:flex;
    flex-wrap:wrap;
    justify-content:space-between;
    align-items:center;
    gap:.5rem;
    padding:.75rem 1rem .25rem;
    border-bottom:1px solid rgba(31,41,55,0.9);
}

.gdy-meta-stat{
    font-size:0.8rem;
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
    <div class="admin-content gdy-page-header d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 text-white mb-1"><?= h(__('t_cab8942d73', 'رسائل التواصل')) ?></h1>
            <p class="text-muted mb-0 small"><?= h(__('t_e4184e64d9', 'إدارة الرسائل الواردة من نموذج اتصل بنا.')) ?></p>
        </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger py-2 mb-3">
            <?= h(__('t_8390c993b9', 'حدث خطأ، الرجاء المحاولة لاحقاً.')) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm gdy-glass-card">
        <div class="gdy-meta-bar">
            <div class="gdy-meta-stat">
                <span><?= h(__('t_5258127f34', 'إجمالي الرسائل:')) ?> </span>
                <strong><?= count($rows) ?></strong>
            </div>
            <div class="gdy-meta-stat">
                <span class="badge-gdy badge-new"><?= h(__('t_da694c6d97', 'جديدة')) ?></span>
                <span class="badge-gdy badge-replied ms-1"><?= h(__('t_37d8352070', 'تم الرد')) ?></span>
                <span class="badge-gdy badge-seen ms-1"><?= h(__('t_9e21ea7aee', 'مقروءة')) ?></span>
            </div>
        </div>

        <div class="card-body p-0 gdy-table-wrapper">
            <?php if (empty($rows)): ?>
                <p class="mb-0 p-3 text-muted text-center">
                    <?= h(__('t_d5cbdd3879', 'لا توجد رسائل حالياً.')) ?>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center gdy-table">
                        <thead>
                        <tr>
                            <th class="gdy-col-id">#</th>
                            <th><?= h(__('t_901675875a', 'المرسل')) ?></th>
                            <th><?= h(__('t_c707d7f2bb', 'البريد')) ?></th>
                            <th><?= h(__('t_881c23b1d1', 'الموضوع')) ?></th>
                            <th class="gdy-col-status"><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                            <th class="gdy-col-date"><?= h(__('t_8456f22b47', 'التاريخ')) ?></th>
                            <th class="gdy-col-actions"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $status = $r['status'] ?? '';
                            $rowClass = $status === 'new' ? 'gdy-row-new' : '';
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= (int)$r['id'] ?></td>
                                <td class="text-start gdy-cell-name">
                                    <?= h($r['name']) ?>
                                </td>
                                <td class="gdy-cell-email">
                                    <small><?= h($r['email']) ?></small>
                                </td>
                                <td class="text-start gdy-cell-subject">
                                    <?= h($r['subject']) ?>
                                </td>
                                <td>
                                    <?php if ($status === 'new'): ?>
                                        <span class="badge-gdy badge-new"><?= h(__('t_da694c6d97', 'جديدة')) ?></span>
                                    <?php elseif ($status === 'replied'): ?>
                                        <span class="badge-gdy badge-replied"><?= h(__('t_37d8352070', 'تم الرد')) ?></span>
                                    <?php else: ?>
                                        <span class="badge-gdy badge-seen"><?= h(__('t_9e21ea7aee', 'مقروءة')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= h($r['created_at']) ?></small>
                                </td>
                                <td>
                                    <a href="view.php?id=<?= (int)$r['id'] ?>"
                                       class="btn btn-outline-info btn-gdy-xs"
                                       title="<?= h(__('t_99081e2fc8', 'عرض الرسالة')) ?>">
                                        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                    </a>
                                    <?php if ($status === 'new'): ?>
                                        <a href="index.php?seen=<?= (int)$r['id'] ?>"
                                           class="btn btn-outline-secondary btn-gdy-xs"
                                           title="<?= h(__('t_0eca496ea9', 'تعيين كمقروءة')) ?>">
                                            <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
