<?php

require_once __DIR__ . '/../_admin_guard.php';
// admin/users/user_roles.php

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';

\Godyar\Auth::requirePermission('manage_users');
require_once __DIR__ . '/../../includes/security.php';

$pdo = \Godyar\DB::pdo();

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$usersStmt = $pdo->query("SELECT id, name, email, username, role FROM users ORDER BY id ASC");
$users = $usersStmt ? $usersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$selectedUser   = null;

if ($selectedUserId > 0) {
    $userStmt = $pdo->prepare("SELECT id, name, email, username, role FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $selectedUserId]);
    $selectedUser = $userStmt->fetch(PDO::FETCH_ASSOC);
}

$rolesStmt = $pdo->query("SELECT id, name, label, is_system FROM roles ORDER BY is_system DESC, id ASC");
$allRoles = $rolesStmt ? $rolesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$currentRolesSet = [];
if ($selectedUser) {
    $urStmt = $pdo->prepare("SELECT r.id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :uid");
    $urStmt->execute([':uid' => $selectedUserId]);
    $rids = $urStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rids as $rid) {
        $currentRolesSet[(int)$rid] = true;
    }
}

$successMessage = null;
$errorMessage   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedUser) {

    if (!verify_csrf()) {
        $errorMessage = __('t_fbbc004136', 'رمز الحماية (CSRF) غير صالح، يرجى إعادة المحاولة.');
    } else {
        $selectedRoles = isset($_POST['roles']) && is_array($_POST['roles'])
            ? array_map('intval', $_POST['roles'])
            : [];

        try {
            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :uid");
            $delStmt->execute([':uid' => $selectedUserId]);

            if (!empty($selectedRoles)) {
                $insStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");

                foreach ($selectedRoles as $rid) {
                    if ($rid <= 0) {
                        continue;
                    }
                    $insStmt->execute([
                        ':uid' => $selectedUserId,
                        ':rid' => $rid,
                    ]);
                }
            }

            $pdo->commit();

            $successMessage = __('t_9db88dea2c', 'تم تحديث أدوار المستخدم بنجاح.');
            $currentRolesSet = [];
            foreach ($selectedRoles as $rid) {
                $currentRolesSet[(int)$rid] = true;
            }

        } catch (Throwable $e) {
            $pdo->rollBack();
            @error_log('[RBAC] update user roles error: ' . $e->getMessage());
            $errorMessage = __('t_79492e49fe', 'حدث خطأ أثناء تحديث الأدوار، يرجى المحاولة لاحقاً.');
        }
    }
}
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
</style>

<div class="admin-content container-fluid py-4">
  <div class="admin-header">
    <h1><?= h(__('t_243673ce24', 'أدوار المستخدمين')) ?></h1>
    <p class="text-muted">
      <?= h(__('t_4603636bda', 'من هنا يمكنك ربط كل مستخدم بالأدوار (Roles) المعرّفة في النظام.')) ?>
    </p>
  </div>

  <div class="row">
    <div class="col-md-5">
      <div class="card mb-4">
        <div class="card-header">
          <h2 class="card-title mb-0"><?= h(__('t_39d3073371', 'المستخدمون')) ?></h2>
        </div>
        <div class="card-body">
          <?php if (empty($users)): ?>
            <p><?= h(__('t_9aa8b46001', 'لا يوجد مستخدمون.')) ?></p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover text-center">
                <thead>
                  <tr>
                    <th>#</th>
                    <th><?= h(__('t_2e8b171b46', 'الاسم')) ?></th>
                    <th><?= h(__('t_5ca6657770', 'اسم الدخول')) ?></th>
                    <th><?= h(__('t_c5b8d695f6', 'الدور الأساسي')) ?></th>
                    <th><?= h(__('t_34b595a894', 'إدارة الأدوار')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                    <tr>
                      <td><?= (int)$u['id'] ?></td>
                      <td><?= h($u['name'] ?: $u['username']) ?></td>
                      <td><code><?= h($u['username']) ?></code></td>
                      <td><?= h($u['role']) ?></td>
                      <td>
                        <a href="user_roles.php?user_id=<?= (int)$u['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                          <?= h(__('t_64ad71446c', 'تعديل الأدوار')) ?>
                        </a>
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

    <div class="col-md-7">
      <div class="card mb-4">
        <div class="card-header">
          <h2 class="card-title mb-0"><?= h(__('t_41ff0546fd', 'أدوار المستخدم المحدد')) ?></h2>
        </div>
        <div class="card-body">
          <?php if (!$selectedUser): ?>
            <p class="text-muted">
              <?= h(__('t_f95734154c', 'اختر مستخدمًا من الجدول على اليسار لتعديل أدواره.')) ?>
            </p>
          <?php else: ?>

            <?php if ($successMessage): ?>
              <div class="alert alert-success"><?= h($successMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
              <div class="alert alert-danger"><?= h($errorMessage) ?></div>
            <?php endif; ?>

            <div class="mb-3">
              <strong><?= h(__('t_ded0f6afb3', 'المستخدم:')) ?></strong>
              <?= h($selectedUser['name'] ?: $selectedUser['username']) ?>
              <br>
              <strong><?= h(__('t_b54724b18e', 'البريد:')) ?></strong>
              <?= h($selectedUser['email']) ?>
              <br>
              <strong><?= h(__('t_ba1e927092', 'الدور الأساسي (حقل users.role):')) ?></strong>
              <code><?= h($selectedUser['role']) ?></code>
            </div>

            <?php if (empty($allRoles)): ?>
              <p><?= h(__('t_660e9ff699', 'لا توجد أدوار متاحة في النظام.')) ?></p>
            <?php else: ?>

              <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <?php csrf_field(); ?>

                <input type="hidden" name="user_id" value="<?= (int)$selectedUserId ?>">

                <div class="row">
                  <?php foreach ($allRoles as $r): ?>
                    <?php
                      $rid      = (int)$r['id'];
                      $checked  = !empty($currentRolesSet[$rid]);
                      $inputId  = 'role_' . $rid;
                    ?>
                    <div class="col-md-6 mb-3">
                      <div class="form-check">
                        <input
                          class="form-check-input"
                          type="checkbox"
                          name="roles[]"
                          id="<?= $inputId ?>"
                          value="<?= $rid ?>"
                          <?= $checked ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="<?= $inputId ?>">
                          <strong><?= h($r['label']) ?></strong>
                          <br>
                          <small class="text-muted">
                            <code><?= h($r['name']) ?></code>
                            <?= $r['is_system'] ? __('t_0571328da2', ' (نظامي)') : '' ?>
                          </small>
                        </label>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="mt-3">
                  <button type="submit" class="btn btn-primary">
                    <?= h(__('t_2e8e7fc723', 'حفظ الأدوار للمستخدم')) ?>
                  </button>
                </div>

              </form>

            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
