<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// godyar/admin/ads/view.php — عرض تفاصيل إعلان

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'ads';
$pageTitle   = __('t_b7f9df8a7e', 'عرض إعلان');

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/** @var PDO|null $pdo */
$pdo = gdy_pdo_safe();

try {
    if (!Auth::isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }
} catch (Throwable $e) {
    @error_log('[Admin Ads View] Auth error: ' . $e->getMessage());
    if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? 'guest') === 'guest')) {
        header('Location: ../login.php');
        exit;
    }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0 || !$pdo instanceof PDO) {
    header('Location: index.php');
    exit;
}

$ad = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $ad = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ad) {
        header('Location: index.php?notfound=1');
        exit;
    }
} catch (Throwable $e) {
    @error_log('[Admin Ads View] fetch: ' . $e->getMessage());
    header('Location: index.php?error=1');
    exit;
}

// م mapping قديم، لكن نتركه كما هو لمشروعك:
$positions = [
    'header'           => __('t_83d5d095db', 'هيدر الموقع'),
    'sidebar_top'      => __('t_3382cccf3d', 'أعلى العمود الجانبي'),
    'sidebar_bottom'   => __('t_298867c3cd', 'أسفل العمود الجانبي'),
    'homepage_between' => __('t_8004ea71eb', 'بين أقسام الصفحة الرئيسية'),
];

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

    <div class="gdy-page-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div>
            <h1 class="h4 mb-1 text-white"><?= h(__('t_b7f9df8a7e', 'عرض إعلان')) ?></h1>
            <p class="text-muted small mb-0"><?= h($ad['title'] ?? '') ?></p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="edit.php?id=<?= (int)$ad['id'] ?>" class="btn btn-primary btn-sm">
                <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_759fdc242e', 'تعديل')) ?>
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_8c9f484caa', 'الرجوع')) ?>
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm glass-card mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0 text-white"><?= h(__('t_9bb58d3d55', 'معلومات عامة')) ?></h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-3"><?= h(__('t_6dc6588082', 'العنوان')) ?></dt>
                        <dd class="col-sm-9"><?= h($ad['title']) ?></dd>

                        <dt class="col-sm-3">Slug</dt>
                        <dd class="col-sm-9"><code><?= h($ad['slug'] ?? '') ?></code></dd>

                        <dt class="col-sm-3"><?= h(__('t_0e260c7275', 'موقع العرض')) ?></dt>
                        <dd class="col-sm-9">
                            <?= h($positions[$ad['position']] ?? ($ad['position'] ?? '')) ?>
                            <?php if (!empty($ad['position'])): ?>
                                <small class="text-muted">(<?= h($ad['position']) ?>)</small>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-3"><?= h(__('t_72ce2dd33e', 'الرابط عند الضغط')) ?></dt>
                        <dd class="col-sm-9">
                            <?php if (!empty($ad['target_url'])): ?>
                                <a href="<?= h($ad['target_url']) ?>" target="_blank"><?= h($ad['target_url']) ?></a>
                            <?php else: ?>
                                <span class="text-muted"><?= h(__('t_9d7155f3e3', 'لا يوجد')) ?></span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-3"><?= h(__('t_1253eb5642', 'الحالة')) ?></dt>
                        <dd class="col-sm-9">
                            <?php if ((int)($ad['is_active'] ?? 0) === 1): ?>
                                <span class="badge bg-success"><?= h(__('t_8caaf95380', 'نشط')) ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= h(__('t_75e3d97ed8', 'موقوف')) ?></span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-3"><?= h(__('t_6079fc6b94', 'الفترة')) ?></dt>
                        <dd class="col-sm-9">
                            <?php if (!empty($ad['start_at'])): ?>
                                من <?= h($ad['start_at']) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($ad['end_at'])): ?>
                                إلى <?= h($ad['end_at']) ?>
                            <?php endif; ?>
                            <?php if (empty($ad['start_at']) && empty($ad['end_at'])): ?>
                                <span class="text-muted"><?= h(__('t_c1a25ec005', 'غير محددة')) ?></span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-3"><?= h(__('t_84b1e0c6ed', 'الإحصائيات')) ?></dt>
                        <dd class="col-sm-9">
                            ظهور: <?= (int)($ad['impressions'] ?? 0) ?>
                            <?php if (!empty($ad['max_impressions'])): ?>
                                <span class="text-muted">/ <?= (int)$ad['max_impressions'] ?></span>
                            <?php endif; ?>
                            &nbsp; | &nbsp;
                            نقرات: <?= (int)($ad['clicks'] ?? 0) ?>
                        </dd>

                        <dt class="col-sm-3"><?= h(__('t_d4ef3a02e7', 'تاريخ الإنشاء')) ?></dt>
                        <dd class="col-sm-9"><?= h($ad['created_at'] ?? '') ?></dd>

                        <dt class="col-sm-3"><?= h(__('t_04a22e672c', 'آخر تعديل')) ?></dt>
                        <dd class="col-sm-9"><?= h($ad['updated_at'] ?? '') ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm glass-card mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0 text-white"><?= h(__('t_529cb8b507', 'معاينة الإعلان')) ?></h2>
                </div>
                <div class="card-body bg-dark">
                    <?php if (!empty($ad['html_code'])): ?>
                        <div class="border rounded p-2 bg-light">
                            <?= $ad['html_code'] ?>
                        </div>
                    <?php elseif (!empty($ad['image_path'])): ?>
                        <a href="<?= h($ad['target_url'] ?: '#') ?>" target="<?= $ad['target_url'] ? '_blank' : '_self' ?>">
                            <img src="<?= h($ad['image_path']) ?>" alt="<?= h($ad['title']) ?>" class="img-fluid rounded">
                        </a>
                    <?php else: ?>
                        <p class="text-muted small mb-0"><?= h(__('t_9f3ace8b37', 'لا يوجد كود HTML ولا صورة لهذا الإعلان.')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm glass-card mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0 text-white"><?= h(__('t_8ec93c5b31', 'ملاحظات / تلميحات')) ?></h2>
                </div>
                <div class="card-body small text-muted">
                    <ul class="mb-0">
                        <li><?= h(__('t_cc7fad8124', 'تأكد من أن مكان الإعلان مفعّل في كود الواجهة (')) ?><code>front_ads.php</code>).</li>
                        <li><?= h(__('t_707cb753ac', 'يمكنك استخدام')) ?> <code>image_path</code> <?= h(__('t_be8807c536', 'وحده، أو')) ?> <code>html_code</code> <?= h(__('t_ca53fe71ab', 'وحده، أو كليهما.')) ?></li>
                        <li><?= h(__('t_8b92471288', 'إدارة الظهور والنقرات تتم عادة من كود الواجهة عند عرض الإعلان فعليًا.')) ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
