<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/system/logs/index.php — سجلات النظام

require_once __DIR__ . '/../../../includes/bootstrap.php';

if (!class_exists(\Godyar\Auth::class)) {
    $authFile = __DIR__ . '/../../../includes/auth.php';
    if (is_file($authFile)) require_once $authFile;
}

use Godyar\Auth;

$currentPage = 'system_logs';
$pageTitle   = 'سجلات النظام';

try {
    $user = null;
    if (class_exists(Auth::class)) {
        if (method_exists(Auth::class, 'isLoggedIn') && !Auth::isLoggedIn()) {
            header('Location: ../../login.php');
            exit;
        }
        if (method_exists(Auth::class, 'user')) {
            $user = Auth::user();
        } elseif (!empty($_SESSION['user'])) {
            $user = $_SESSION['user'];
        }
    } else {
        $user = $_SESSION['user'] ?? null;
        if (!$user || (($user['role'] ?? 'guest') === 'guest')) {
            header('Location: ../../login.php');
            exit;
        }
    }

    $role = $user['role'] ?? 'user';
    $allowedRoles = ['superadmin','admin'];
    if (!in_array($role, $allowedRoles, true)) {
        header('Location: ../../index.php');
        exit;
    }
} catch (Throwable $e) {
    @error_log('[Godyar Logs] Auth error: ' . $e->getMessage());
    header('Location: ../../login.php');
    exit;
}

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$pdo = gdy_pdo_safe();
$dbError = null;
$rows    = [];

if ($pdo instanceof PDO) {
    try {
        $sql = "
            SELECT al.id, al.user_id, al.action, al.entity_type, al.entity_id,
                   al.details, al.created_at,
                   u.username, u.name
            FROM admin_logs al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.id DESC
            LIMIT 100
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
        @error_log('[Godyar Logs] DB error: ' . $e->getMessage());
    }
} else {
    $dbError = 'لا يمكن الاتصال بقاعدة البيانات حالياً.';
}

require_once __DIR__ . '/../../layout/header.php';
require_once __DIR__ . '/../../layout/sidebar.php';
?>
<div class="admin-content container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div>
      <h1 class="h4 mb-1">سجلات النظام</h1>
      <p class="text-muted mb-0">متابعة النشاطات الإدارية والأحداث المهمة داخل لوحة التحكم.</p>
    </div>
  </div>

  <?php if ($dbError): ?>
    <div class="alert alert-danger py-2 small">
      <strong>خطأ قاعدة البيانات:</strong> <?= h($dbError) ?>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm glass-card">
    <div class="card-header">
      <h2 class="h6 mb-0">
        <svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> آخر 100 عملية
      </h2>
    </div>
    <div class="card-body p-0">
      <?php if (empty($rows)): ?>
        <p class="text-muted p-3 mb-0">لا توجد سجلات لعرضها حالياً.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle text-center">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>التاريخ</th>
                <th>الحدث</th>
                <th>المستخدم</th>
                <th>الكيان</th>
                <th>التفاصيل</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $log): ?>
                <tr>
                  <td><?= (int)$log['id'] ?></td>
                  <td><small><?= h($log['created_at']) ?></small></td>
                  <td><code class="small"><?= h($log['action']) ?></code></td>
                  <td>
                    <?php if ($log['user_id']): ?>
                      <small><?= h($log['name'] ?: $log['username'] ?: ('User #'.$log['user_id'])) ?></small>
                    <?php else: ?>
                      <span class="text-muted small">غير محدد</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <small><?= h(($log['entity_type'] ?? '').'#'.($log['entity_id'] ?? '')) ?></small>
                  </td>
                  <td style="max-width: 260px;">
                    <small class="text-muted">
                      <?= h(mb_substr((string)($log['details'] ?? ''), 0, 120, 'UTF-8')) ?>
                    </small>
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

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
