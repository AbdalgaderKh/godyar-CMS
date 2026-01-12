<?php

require_once __DIR__ . '/../_admin_guard.php';
// admin/logs/admin_logs.php

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';

\Godyar\Auth::requirePermission('manage_security');

$pdo = \Godyar\DB::pdo();

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$usersStmt = $pdo->query("
    SELECT id, username, name, email
    FROM users
    ORDER BY id ASC
");
$users = $usersStmt ? $usersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$filterAction = isset($_GET['action'])  ? trim((string)$_GET['action']) : '';

$where  = [];
$params = [];

if ($filterUserId > 0) {
    $where[]           = 'al.user_id = :uid';
    $params[':uid']    = $filterUserId;
}
if ($filterAction !== '') {
    $where[]           = 'al.action = :action';
    $params[':action'] = $filterAction;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT
      al.id,
      al.user_id,
      al.action,
      al.entity_type,
      al.entity_id,
      al.ip_address,
      al.user_agent,
      al.details,
      al.created_at,
      u.username,
      u.name,
      u.email
    FROM admin_logs al
    LEFT JOIN users u ON u.id = al.user_id
    $whereSql
    ORDER BY al.id DESC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function format_details(?string $details): string {
    if (!$details) {
        return '';
    }
    $decoded = json_decode($details, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $out = [];
        foreach ($decoded as $k => $v) {
            if (is_scalar($v)) {
                $out[] = '<strong>' . h($k) . ':</strong> ' . h((string)$v);
            } else {
                $out[] = '<strong>' . h($k) . ':</strong> ' . h(json_encode($v, JSON_UNESCAPED_UNICODE));
            }
        }
        return implode('<br>', $out);
    }
    return nl2br(h($details));
}
?>

<div class="admin-content">
  <div class="admin-header">
    <h1>سجل النشاط الإداري</h1>
    <p class="text-muted">
      يعرض أحدث الأحداث التي قام بها المدراء والمحررون (تسجيل الدخول، تعديل الإعدادات، إدارة المحتوى، ...إلخ).
    </p>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <h2 class="card-title mb-0">فلاتر البحث</h2>
    </div>
    <div class="card-body">
      <form method="get" class="row g-3">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">


        <div class="col-md-4">
          <label class="form-label">المستخدم</label>
          <select name="user_id" class="form-select">
            <option value="0">الكل</option>
            <?php foreach ($users as $u): ?>
              <?php
                $id   = (int)$u['id'];
                $text = $u['name'] ?: $u['username'] ?: ('User #' . $id);
              ?>
              <option value="<?= $id ?>" <?= $filterUserId === $id ? 'selected' : '' ?>>
                <?= h($text) ?> (ID: <?= $id ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">نوع الحدث (action)</label>
          <input
            type="text"
            name="action"
            class="form-control"
            placeholder="مثال: login_success, news_create"
            value="<?= h($filterAction) ?>"
          >
        </div>

        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary me-2">
            تطبيق الفلاتر
          </button>
          <a href="admin_logs.php" class="btn btn-secondary">
            مسح الفلاتر
          </a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title mb-0">أحدث السجلات</h2>
    </div>
    <div class="card-body">
      <?php if (empty($logs)): ?>
        <p>لا توجد سجلات مطابقة للفلاتر الحالية.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-striped table-hover text-center">
            <thead>
              <tr>
                <th>#</th>
                <th>التاريخ</th>
                <th>المستخدم</th>
                <th>الحدث</th>
                <th>الكيان</th>
                <th>IP</th>
                <th>تفاصيل</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $log): ?>
                <tr>
                  <td><?= (int)$log['id'] ?></td>
                  <td><?= h($log['created_at']) ?></td>
                  <td>
                    <?php if ($log['user_id']): ?>
                      <div>
                        <strong><?= h($log['name'] ?: $log['username'] ?: ('User #' . $log['user_id'])) ?></strong><br>
                        <small><?= h($log['email'] ?? '') ?></small><br>
                        <small>ID: <?= (int)$log['user_id'] ?></small>
                      </div>
                    <?php else: ?>
                      <span class="text-muted">غير محدد</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <code><?= h($log['action']) ?></code>
                  </td>
                  <td>
                    <?php if ($log['entity_type']): ?>
                      <div>
                        <strong><?= h($log['entity_type']) ?></strong><br>
                        <?php if ($log['entity_id']): ?>
                          <small>ID: <?= (int)$log['entity_id'] ?></small>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <small><?= h($log['ip_address'] ?: '-') ?></small>
                  </td>
                  <td style="max-width: 260px; text-align: left;">
                    <div style="white-space: normal; direction: ltr; text-align: left;">
                      <?= format_details($log['details']) ?>
                    </div>
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
