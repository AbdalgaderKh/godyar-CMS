<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/glossary/index.php — إدارة قاموس المصطلحات

require_once __DIR__ . '/../../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$pdo = gdy_pdo_safe();

// ===== حماية لوحة التحكم (نفس أسلوب باقي صفحات admin) =====
if (empty($_SESSION['user']['id']) || empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ' . base_url('/login'));
    exit;
}

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$message = '';
$error   = '';

// إنشاء جدول القاموس لو لم يكن موجوداً
if ($pdo instanceof PDO) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS gdy_glossary (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                term VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                short_definition TEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (term),
                INDEX (slug),
                INDEX (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Throwable $e) {
        $error = __('t_30ebee6d81', 'تعذّر التحقق من جدول القاموس: ') . $e->getMessage();
    }
} else {
    $error = __('t_f83146e65c', 'لا يوجد اتصال بقاعدة البيانات.');
}

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !$error && $pdo instanceof PDO) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $term       = trim($_POST['term'] ?? '');
        $definition = trim($_POST['definition'] ?? '');
        $active     = isset($_POST['is_active']) ? 1 : 0;

        if ($term === '' || $definition === '') {
            $error = __('t_ba4332eb0b', 'الرجاء إدخال المصطلح والشرح.');
        } else {
            $slug = strtolower(preg_replace('~\s+~u', '-', $term));

            $stmt = $pdo->prepare("
                INSERT INTO gdy_glossary (term, slug, short_definition, is_active)
                VALUES (:term, :slug, :def, :active)
            ");
            $stmt->execute([
                ':term'   => $term,
                ':slug'   => $slug,
                ':def'    => $definition,
                ':active' => $active,
            ]);
            $message = __('t_16f52ea541', 'تمت إضافة المصطلح بنجاح.');
        }
    } elseif ($action === 'toggle' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE gdy_glossary SET is_active = 1 - is_active WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $message = __('t_2ace64333d', 'تم تحديث حالة المصطلح.');
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM gdy_glossary WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $message = __('t_45fbaaf85e', 'تم حذف المصطلح.');
    }
}

// جلب المصطلحات
$rows = [];
if (!$error && $pdo instanceof PDO) {
    $stmt = $pdo->query("SELECT * FROM gdy_glossary ORDER BY id DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// لتفعيل العنصر في السايدبار
$currentPage = 'glossary';

// Professional unified admin shell
$pageTitle = $pageTitle ?? __('t_a3a56bad36', 'قاموس المصطلحات');
$pageSubtitle = __('t_23ca63146d', 'أضف المصطلحات التي تريد شرحها داخل المقالات (تمييز تلقائي للقارئ).');
$breadcrumbs = [
    __('t_3aa8578699', 'الرئيسية') => (function_exists('base_url') ? rtrim(base_url(),'/') : '') . '/admin/index.php',
    __('t_a3a56bad36', 'قاموس المصطلحات') => null,
];
require_once __DIR__ . '/../layout/app_start.php';
?>

    <?php if ($message): ?>
      <div class="alert alert-success"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-body">
        <h2 class="h5 mb-3"><?= h(__('t_71d3bef41a', 'إضافة مصطلح جديد')) ?></h2>
        <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label"><?= h(__('t_d8189e371d', 'المصطلح')) ?></label>
            <input type="text" name="term" class="form-control" required placeholder="<?= h(__('t_71693152a0', 'مثال: الفيدرالي الأمريكي')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label"><?= h(__('t_50ac8aeda7', 'الشرح المختصر')) ?></label>
            <textarea name="definition" class="form-control" rows="3" required placeholder="<?= h(__('t_4453b55e04', 'شرح بسيط للمصطلح يظهر فوق الكلمة للقارئ.')) ?>"></textarea>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="glossaryActive" name="is_active" checked>
            <label class="form-check-label" for="glossaryActive"><?= h(__('t_918499f2af', 'مفعل')) ?></label>
          </div>
          <button type="submit" class="btn btn-primary px-4"><?= h(__('t_2492ab02ed', 'حفظ المصطلح')) ?></button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h2 class="h5 mb-3"><?= h(__('t_eb29792630', 'المصطلحات المسجلة')) ?></h2>
        <?php if (!$rows): ?>
          <p class="text-muted mb-0"><?= h(__('t_f24713abfa', 'لا توجد مصطلحات بعد.')) ?></p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>#</th>
                  <th><?= h(__('t_d8189e371d', 'المصطلح')) ?></th>
                  <th><?= h(__('t_4c9678230f', 'الشرح')) ?></th>
                  <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                  <th><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= h($row['term']) ?></td>
                    <td style="max-width:400px"><?= nl2br(h($row['short_definition'])) ?></td>
                    <td>
                      <?php if ((int)$row['is_active'] === 1): ?>
                        <span class="badge bg-success"><?= h(__('t_918499f2af', 'مفعل')) ?></span>
                      <?php else: ?>
                        <span class="badge bg-secondary"><?= h(__('t_0bad9c165c', 'معطل')) ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="action" value="toggle">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                          <?= h(__('t_01a5d8d953', 'تبديل الحالة')) ?>
                        </button>
                      </form>
                      <form method="post" class="d-inline" data-confirm='هل أنت متأكد من حذف هذا المصطلح؟'>
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                          <?= h(__('t_3b9854e1bb', 'حذف')) ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
