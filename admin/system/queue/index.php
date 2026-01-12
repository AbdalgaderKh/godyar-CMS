<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_admin_guard.php';

// المدير فقط
if (class_exists('Godyar\\Auth') && method_exists('Godyar\\Auth','requireRole')) {
    \Godyar\Auth::requireRole('admin');
} else {
    if (($_SESSION['user']['role'] ?? '') !== 'admin') { http_response_code(403); exit('403 Forbidden'); }
}

require_once __DIR__ . '/../../queue.php';
require_once __DIR__ . '/token.php';

// -----------------------------------------------------------------------------
// Bootstrap (PDO) — دعم أكثر من تركيب للمشروع (داخل الجذر أو داخل مجلد)
// -----------------------------------------------------------------------------
$bootstrapPaths = [
    __DIR__ . '/../../../includes/bootstrap.php',
    __DIR__ . '/../../../godyar/includes/bootstrap.php',
    __DIR__ . '/../../../bootstrap.php',
];
foreach ($bootstrapPaths as $p) {
    if (is_file($p)) {
        require_once $p;
        break;
    }
}

$pdo = gdy_pdo_safe();

// -----------------------------------------------------------------------------
// تسجيل مهمة جدولة الأخبار
// -----------------------------------------------------------------------------
gdy_queue_register('news_scheduler', function (array $payload) use ($pdo): void {
    if (!$pdo) return;

    // publish due
    $pdo->exec("UPDATE news SET status='published', published_at=NOW() \
               WHERE status IN ('draft','pending') AND publish_at IS NOT NULL AND publish_at <= NOW() \
                 AND (deleted_at IS NULL)");

    // unpublish due
    $pdo->exec("UPDATE news SET status='draft' \
               WHERE status='published' AND unpublish_at IS NOT NULL AND unpublish_at <= NOW() \
                 AND (deleted_at IS NULL)");
});

// -----------------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'run_scheduler_now') {
        GdyQueue::enqueue('news_scheduler', [], time());
        header('Location: index.php?ok=1');
        exit;
    }

    if ($action === 'clear_done') {
        $n = GdyQueue::clearDone();
        header('Location: index.php?cleared=' . (int)$n);
        exit;
    }
}

$jobs = GdyQueue::all();

// -----------------------------------------------------------------------------
// Layout
// -----------------------------------------------------------------------------
$baseUrl  = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';
$adminUrl = $baseUrl . '/admin';

$currentPage = 'queue';
$pageTitle   = __('t_6a694bd722', 'الطابور والجدولة');

require_once __DIR__ . '/../../layout/header.php';
require_once __DIR__ . '/../../layout/sidebar.php';
?>

<div class="admin-content">
  <div class="gdy-admin-page">
    <div class="container-xxl" style="max-width:1200px;">

      <div class="gdy-page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h1 class="h4 text-white mb-1"><svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_6a694bd722', 'الطابور والجدولة')) ?></h1>
          <p class="text-muted mb-0"><?= h(__('t_afed5076f5', 'تنفيذ المهام المؤجلة + جدولة نشر/إلغاء نشر الأخبار.')) ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <form method="post" class="m-0">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <input type="hidden" name="action" value="run_scheduler_now">
            <button class="btn btn-outline-info btn-sm"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_70c528f1d0', 'تشغيل جدولة الأخبار الآن')) ?></button>
          </form>
          <form method="post" class="m-0">
            <input type="hidden" name="action" value="clear_done">
            <button class="btn btn-outline-warning btn-sm"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_15dad1b337', 'حذف المكتمل')) ?></button>
          </form>
          <a class="btn btn-outline-success btn-sm" href="worker.php" target="_blank"><svg class="gdy-icon me-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_eb011f743d', 'تشغيل العامل')) ?></a>
        </div>
      </div>

      <?php if (!empty($_GET['ok'])): ?>
        <div class="alert alert-success"><?= h(__('t_478e73c965', 'تمت إضافة مهمة الجدولة للطابور.')) ?></div>
      <?php endif; ?>
      <?php if (isset($_GET['cleared'])): ?>
        <div class="alert alert-info">تم حذف <?= (int)$_GET['cleared'] ?> مهمة مكتملة.</div>
      <?php endif; ?>

      <div class="card glass-card gdy-card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-dark table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th><?= h(__('t_a70b017205', 'النوع')) ?></th>
                  <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                  <th class="d-none d-md-table-cell">run_at</th>
                  <th><?= h(__('t_e32b869f05', 'محاولات')) ?></th>
                  <th class="d-none d-lg-table-cell"><?= h(__('t_c454b8eb8f', 'آخر خطأ')) ?></th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($jobs)): ?>
                <tr><td colspan="6" class="text-muted"><?= h(__('t_b5d8d30bfc', 'لا توجد مهام حالياً.')) ?></td></tr>
              <?php else: foreach ($jobs as $i => $j): ?>
                <?php $st = (string)($j['status'] ?? 'queued'); ?>
                <tr>
                  <td><?= (int)$i + 1 ?></td>
                  <td><?= htmlspecialchars((string)($j['type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <span class="badge bg-<?= $st==='done' ? 'success' : ($st==='failed' ? 'danger' : 'secondary') ?>">
                      <?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>
                  <td class="small d-none d-md-table-cell"><?= isset($j['run_at']) ? date('Y-m-d H:i:s', (int)$j['run_at']) : '' ?></td>
                  <td><?= (int)($j['attempts'] ?? 0) ?></td>
                  <td class="small text-warning d-none d-lg-table-cell"><?= htmlspecialchars((string)($j['last_error'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <div class="alert alert-info mt-3 mb-0">
            <div class="fw-bold mb-1"><?= h(__('t_e2f92dc484', 'تشغيل تلقائي (Cron)')) ?></div>
            <div class="small"><?= h(__('t_234ab75780', 'فعّل Cron لاستدعاء العامل كل دقيقة أو دقيقتين:')) ?></div>
            <?php
              $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
              $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
              $cronUrl = $scheme . '://' . $host . $adminUrl . '/system/queue/worker.php?token=' . gdy_queue_get_token();
            ?>
            <pre class="mb-0 small">curl -s "<?= htmlspecialchars($cronUrl, ENT_QUOTES, 'UTF-8') ?>" >/dev/null 2>&1</pre>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
