<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/news/categories.php — تصنيفات الأخبار (محمي بالصلاحيات)

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

Auth::requirePermission('categories.*');

$currentPage = 'categories';
$pageTitle   = __('t_6f1c0837e2', 'تصنيفات الأخبار');

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<main class="admin-content container-fluid py-4 gdy-admin-page">
  <h2 class="h5 mb-4"><?= h(__('t_6f1c0837e2', 'تصنيفات الأخبار')) ?></h2>
  <div class="card p-3"><?= h(__('t_e568c79dcf', 'هذه الصفحة تحتاج تنفيذ CRUD للتصنيفات. (مخفية/ممنوعة للكاتب)')) ?></div>
</main>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
