<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/reports/index.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

$currentPage = 'reports';
$pageTitle   = __('t_95bc86fefd', 'التقارير والإحصائيات');

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// إحصائيات بسيطة (يمكن تطويرها لاحقاً)
$stats = [
    'users'       => 0,
    'news'        => 0,
    'pages'       => 0,
    'media'       => 0,
    'ads_active'  => 0,
    'contacts_new'=> 0,
];

if ($pdo instanceof PDO) {
    $queries = [
        'users'        => "SELECT COUNT(*) FROM users",
        'news'         => "SELECT COUNT(*) FROM news",
        'pages'        => "SELECT COUNT(*) FROM pages",
        'media'        => "SELECT COUNT(*) FROM media",
        'ads_active'   => "SELECT COUNT(*) FROM ads WHERE is_active = 1",
        'contacts_new' => "SELECT COUNT(*) FROM contact_messages WHERE status = 'new'",
    ];

    foreach ($queries as $key => $sql) {
        try {
            $stmt = $pdo->query($sql);
            $stats[$key] = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            @error_log('[Godyar Reports] ' . $key . ': ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<div class="admin-content container-fluid py-4">
  <div class="mb-4">
    <h1 class="h4 mb-1 text-white"><?= h(__('t_95bc86fefd', 'التقارير والإحصائيات')) ?></h1>
    <p class="mb-0" style="color:#e5e7eb;">
      <?= h(__('t_2ba628bc6d', 'لمحة رقمية عن أداء الموقع والمحتوى والتفاعل.')) ?>
    </p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card glass-card text-center" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
        <div class="card-body py-3">
          <svg class="gdy-icon mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <h6><?= h(__('t_4a6ec64a15', 'عدد المستخدمين')) ?></h6>
          <p class="fs-4 mb-0 fw-bold"><?= (int)$stats['users'] ?></p>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card glass-card text-center" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
        <div class="card-body py-3">
          <svg class="gdy-icon mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg>
          <h6><?= h(__('t_a1d2f1590c', 'عدد الأخبار')) ?></h6>
          <p class="fs-4 mb-0 fw-bold"><?= (int)$stats['news'] ?></p>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card glass-card text-center" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
        <div class="card-body py-3">
          <svg class="gdy-icon mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <h6><?= h(__('t_ef63c8093d', 'عدد الصفحات')) ?></h6>
          <p class="fs-4 mb-0 fw-bold"><?= (int)$stats['pages'] ?></p>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card glass-card text-center" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
        <div class="card-body py-3">
          <svg class="gdy-icon mb-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
          <h6><?= h(__('t_8f564ddff8', 'إعلانات فعّالة')) ?></h6>
          <p class="fs-4 mb-0 fw-bold"><?= (int)$stats['ads_active'] ?></p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card glass-card gdy-card" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
        <div class="card-header" style="background:#020617;border-bottom:1px solid #1f2937;">
          <h2 class="h6 mb-0">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_06dd6988d0', 'مكتبة الوسائط')) ?>
          </h2>
        </div>
        <div class="card-body">
          <p class="mb-1"><?= h(__('t_b33e1c959a', 'عدد العناصر في مكتبة الوسائط:')) ?></p>
          <p class="fs-4 mb-0 fw-bold"><?= (int)$stats['media'] ?></p>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card glass-card gdy-card" style="background:rgba(15,23,42,.95);color:#e5e7eb;">
        <div class="card-header" style="background:#020617;border-bottom:1px solid #1f2937;">
          <h2 class="h6 mb-0">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_ef4521073d', 'رسائل جديدة من نموذج التواصل')) ?>
          </h2>
        </div>
        <div class="card-body">
          <p class="mb-1"><?= h(__('t_79a7329372', 'عدد الرسائل الجديدة:')) ?></p>
          <p class="fs-4 mb-0 fw-bold"><?= (int)$stats['contacts_new'] ?></p>
          <a class="btn btn-sm btn-outline-light mt-2" href="../contact/">
            <?= h(__('t_ad7dce327c', 'عرض رسائل التواصل')) ?>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
