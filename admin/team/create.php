<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

use Godyar\Auth;

$currentPage = 'team';
$pageTitle   = __('t_5a7e9cd1d4', 'إضافة عضو جديد');

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
    @error_log('[Godyar Team Create] Auth error: ' . $e->getMessage());
    if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
        header('Location: ../login.php');
        exit;
    }
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('Database connection not available.');
}

// إنشاء جدول team_members تلقائياً إن لم يكن موجوداً
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `team_members` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(190) NOT NULL,
          `role` VARCHAR(190) DEFAULT NULL,
          `email` VARCHAR(190) DEFAULT NULL,
          `photo_url` VARCHAR(255) DEFAULT NULL,
          `bio` TEXT NULL,
          `status` ENUM('active','hidden') NOT NULL DEFAULT 'active',
          `sort_order` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_status_sort` (`status`,`sort_order`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Throwable $e) {
    @error_log('[Godyar Team AutoTable Create] ' . $e->getMessage());
}

$errors    = [];
$name      = '';
$roleTxt   = '';
$email     = '';
$photoUrl  = '';
$bio       = '';
$status    = 'active';
$sortOrder = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim((string)($_POST['name'] ?? ''));
    $roleTxt   = trim((string)($_POST['role'] ?? ''));
    $email     = trim((string)($_POST['email'] ?? ''));
    $photoUrl  = trim((string)($_POST['photo_url'] ?? ''));
    $bio       = trim((string)($_POST['bio'] ?? ''));
    $status    = (string)($_POST['status'] ?? 'active');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $errors[] = __('t_02c0eef516', 'الرجاء إدخال اسم العضو.');
    }

    if ($status !== 'hidden') {
        $status = 'active';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO team_members
                    (name, role, email, photo_url, bio, status, sort_order, created_at, updated_at)
                VALUES
                    (:name, :role, :email, :photo_url, :bio, :status, :sort_order, NOW(), NOW())
            ");
            $stmt->execute([
                'name'      => $name,
                'role'      => $roleTxt,
                'email'     => $email,
                'photo_url' => $photoUrl,
                'bio'       => $bio,
                'status'    => $status,
                'sort_order'=> $sortOrder,
            ]);
            header('Location: index.php?saved=1');
            exit;
        } catch (Throwable $e) {
            $errors[] = __('t_70a78374f2', 'حدث خطأ أثناء الحفظ: ') . h($e->getMessage());
            @error_log('[Godyar Team Create] Insert error: ' . $e->getMessage());
        }
    }
}

// Professional unified admin shell
$pageSubtitle = __('t_0e8fd73ddd', 'إضافة عضو جديد إلى فريق العمل مع صورة وسيرة مختصرة');
$breadcrumbs = [
    __('t_3aa8578699', 'الرئيسية') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/index.php',
    __('t_cd54bc26ba', 'فريق العمل') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/team/index.php',
    __('t_c77021cc64', 'إضافة عضو') => null,
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
    padding:.9rem 1.1rem .8rem;
    margin-bottom:.9rem;
    border-radius:1rem;
    background:radial-gradient(circle at top, #020617 0%, #020617 55%, #020617 100%);
    border:1px solid rgba(148,163,184,0.35);
    box-shadow:0 8px 20px rgba(15,23,42,0.85);
}
.gdy-page-header h1{
    color:#f9fafb;
}
.gdy-page-header p{
    font-size:.85rem;
}
.glass-card{
    background:rgba(15,23,42,0.96);
    border-radius:16px;
    border:1px solid #1f2937;
}
@media (max-width:991.98px){
    :root{ --gdy-shell-max:100vw; }
}
</style>



  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
          <li><?= h($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm glass-card">
    <div class="card-body">
      <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label text-white"><?= h(__('t_90f9115ac9', 'الاسم الكامل')) ?></label>
            <input type="text" name="name" class="form-control" required value="<?= h($name) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label text-white"><?= h(__('t_4ee2ec16ed', 'المنصب / الوظيفة')) ?></label>
            <input type="text" name="role" class="form-control" value="<?= h($roleTxt) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label text-white"><?= h(__('t_2436aacc18', 'البريد الإلكتروني')) ?></label>
            <input type="email" name="email" class="form-control" value="<?= h($email) ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label text-white"><?= h(__('t_d5fe2d443f', 'رابط الصورة (URL)')) ?></label>
            <input type="text" name="photo_url" class="form-control" value="<?= h($photoUrl) ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label text-white"><?= h(__('t_1253eb5642', 'الحالة')) ?></label>
            <select name="status" class="form-select">
              <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= h(__('t_8caaf95380', 'نشط')) ?></option>
              <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>><?= h(__('t_a39aacaa71', 'مخفي')) ?></option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label text-white"><?= h(__('t_2fcc9e97b9', 'ترتيب العرض')) ?></label>
            <input type="number" name="sort_order" class="form-control" value="<?= (int)$sortOrder ?>">
          </div>

          <div class="col-12">
            <label class="form-label text-white"><?= h(__('t_68bb9eb8bf', 'نبذة مختصرة')) ?></label>
            <textarea name="bio" rows="4" class="form-control"><?= h($bio) ?></textarea>
          </div>
        </div>

        <div class="mt-3 d-flex justify-content-end gap-2">
          <button type="submit" class="btn btn-primary">
            <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_871a087a1d', 'حفظ')) ?>
          </button>
        </div>
      </form>
    </div>
  </div>
<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
