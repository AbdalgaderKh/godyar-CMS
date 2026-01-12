<?php

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';

\Godyar\Auth::requirePermission('manage_roles');
require_once __DIR__ . '/../../includes/security.php';

$pdo = \Godyar\DB::pdo();

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$roleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($roleId <= 0) {
    echo __('t_4cc774a1db', '<div class="admin-content"><div class="alert alert-danger">معرّف الدور غير صالح.</div></div>');
    require_once __DIR__ . '/../layout/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, label, description, is_system FROM roles WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $roleId]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    echo __('t_7ac9a6f22d', '<div class="admin-content"><div class="alert alert-danger">لم يتم العثور على الدور المطلوب.</div></div>');
    require_once __DIR__ . '/../layout/footer.php';
    exit;
}

$permissionsStmt = $pdo->query("SELECT id, name, label, description FROM permissions ORDER BY name ASC");
$allPermissions = $permissionsStmt ? $permissionsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$currentPermsStmt = $pdo->prepare("SELECT p.name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :rid");
$currentPermsStmt->execute([':rid' => $roleId]);
$currentPermNames = $currentPermsStmt->fetchAll(PDO::FETCH_COLUMN);

$currentPermsSet = [];
foreach ($currentPermNames as $pname) {
    $currentPermsSet[$pname] = true;
}

$successMessage = null;
$errorMessage   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('validate_csrf_token') || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = __('t_fbbc004136', 'رمز الحماية (CSRF) غير صالح، يرجى إعادة المحاولة.');
    } else {
        $selected = isset($_POST['permissions']) && is_array($_POST['permissions'])
            ? array_map('strval', $_POST['permissions'])
            : [];

        try {
            $pdo->beginTransaction();

            $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = :rid");
            $del->execute([':rid' => $roleId]);

            if (!empty($selected)) {
                $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) SELECT :rid, p.id FROM permissions p WHERE p.name = :pname LIMIT 1");
                foreach ($selected as $permName) {
                    $ins->execute([
                        ':rid'   => $roleId,
                        ':pname' => $permName,
                    ]);
                }
            }

            $pdo->commit();

            $successMessage = __('t_df1fbc9bdc', 'تم تحديث صلاحيات الدور بنجاح.');
            $currentPermsSet = [];
            foreach ($selected as $pname) {
                $currentPermsSet[$pname] = true;
            }

        } catch (\Throwable $e) {
            $pdo->rollBack();
            @error_log('[RBAC] update role permissions error: ' . $e->getMessage());
            $errorMessage = __('t_48babd483a', 'حدث خطأ أثناء تحديث الصلاحيات، يرجى المحاولة لاحقاً.');
        }
    }
}
?>
<div class="admin-content">
  <div class="admin-header">
    <h1><?= h(__('t_08f5732603', 'تعديل صلاحيات الدور')) ?></h1>
    <p class="text-muted">
      <?= h(__('t_b2279fd438', 'الدور:')) ?> <strong><?= h($role['label']) ?></strong>
      <span class="mx-2">(<code><?= h($role['name']) ?></code>)</span>
    </p>
    <a href="index.php" class="btn btn-secondary btn-sm"><?= h(__('t_d8123b0f46', '← الرجوع لقائمة الأدوار')) ?></a>
  </div>

  <?php if ($successMessage): ?>
    <div class="alert alert-success"><?= h($successMessage) ?></div>
  <?php endif; ?>

  <?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?= h($errorMessage) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title mb-0"><?= h(__('t_036bfd7509', 'الصلاحيات المتاحة')) ?></h2>
    </div>
    <div class="card-body">
      <?php if (empty($allPermissions)): ?>
        <p><?= h(__('t_c6991f176a', 'لا توجد صلاحيات مسجلة.')) ?></p>
      <?php else: ?>
        <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="row">
            <?php foreach ($allPermissions as $perm): ?>
              <?php
                $checked = !empty($currentPermsSet[$perm['name']]);
                $inputId = 'perm_' . (int)$perm['id'];
              ?>
              <div class="col-md-4 mb-3">
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    name="permissions[]"
                    id="<?= $inputId ?>"
                    value="<?= h($perm['name']) ?>"
                    <?= $checked ? 'checked' : '' ?>
                  >
                  <label class="form-check-label" for="<?= $inputId ?>">
                    <strong><?= h($perm['label']) ?></strong><br>
                    <small class="text-muted">
                      <code><?= h($perm['name']) ?></code><br>
                      <?= nl2br(h($perm['description'] ?? '')) ?>
                    </small>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-primary">
              <?= h(__('t_02f31ae27c', 'حفظ التغييرات')) ?>
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
