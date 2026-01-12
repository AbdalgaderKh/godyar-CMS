<?php

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';

\Godyar\Auth::requirePermission('manage_roles');

$pdo = \Godyar\DB::pdo();

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$rolesError = null;
$roles = [];
try {
    $stmt = $pdo->query("SELECT id, name, label, description, is_system, created_at FROM roles ORDER BY is_system DESC, id ASC");
    $roles = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $rolesError = __('t_b2f64f4c64', 'تعذر تحميل الأدوار (قد يكون جدول roles غير موجود أو لا تملك صلاحية).');
    @error_log('[Roles] ' . $e->getMessage());
}
?>
<div class="admin-content">
  <div class="admin-header">
    <h1><?= h(__('t_9ea11f821b', 'الأدوار والصلاحيات')) ?></h1>
    <p class="text-muted"><?= h(__('t_f22da810e7', 'إدارة الأدوار والصلاحيات المرتبطة بلوحة التحكم.')) ?></p>
  </div>
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="card-title mb-0"><?= h(__('t_ca35b6077d', 'قائمة الأدوار')) ?></h2>
    </div>
    <div class="card-body">
      <?php if ($rolesError): ?>
        <div class="alert alert-warning mb-3"><?= h($rolesError) ?></div>
      <?php endif; ?>

      <?php if (empty($roles)): ?>
        <p><?= h(__('t_7df9308347', 'لا توجد أدوار مسجلة.')) ?></p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-striped table-hover text-center">
            <thead>
              <tr>
                <th>#</th>
                <th><?= h(__('t_cc4eb2df91', 'الاسم التقني')) ?></th>
                <th><?= h(__('t_f1c779790b', 'اسم العرض')) ?></th>
                <th><?= h(__('t_ac07b993ab', 'وصف')) ?></th>
                <th><?= h(__('t_540dd8c862', 'نظامي')) ?></th>
                <th><?= h(__('t_d4ef3a02e7', 'تاريخ الإنشاء')) ?></th>
                <th><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($roles as $role): ?>
                <tr>
                  <td><?= (int)$role['id'] ?></td>
                  <td><code><?= h($role['name']) ?></code></td>
                  <td><?= h($role['label']) ?></td>
                  <td><?= nl2br(h($role['description'] ?? '')) ?></td>
                  <td><?= $role['is_system'] ? __('t_e1dadf4c7c', 'نعم') : __('t_b27ea934ef', 'لا') ?></td>
                  <td><?= h($role['created_at']) ?></td>
                  <td>
                    <a href="edit.php?id=<?= (int)$role['id'] ?>" class="btn btn-sm btn-outline-primary">
                      <?= h(__('t_a1a4fdcec2', 'تعديل الصلاحيات')) ?>
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
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
