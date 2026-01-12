<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

use Godyar\Auth;

$currentPage  = 'notifications';
$pageTitle    = __('t_admin_notifications', 'الإشعارات');
$pageSubtitle = __('t_admin_notifications_sub', 'مركز إشعارات لوحة التحكم');

$adminBase = rtrim((string)(defined('GODYAR_BASE_URL') ? GODYAR_BASE_URL : ''), '/') . '/admin';
$breadcrumbs = [__('t_a06ee671f4', 'لوحة التحكم') => $adminBase . '/index.php', $pageTitle => null];

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$pdo = null;
$tableExists = false;
try {
  $pdo = \Godyar\DB::pdo();
  $chk = $pdo->query("SHOW TABLES LIKE 'admin_notifications'");
  $tableExists = (bool)($chk && $chk->fetchColumn());
} catch (\Throwable $e) { $pdo = null; }

if (!$tableExists) {
  $pageActionsHtml = '';
  require_once __DIR__ . '/../layout/app_start.php';
  ?>
  <div class="alert alert-warning">
    <b><?= h(__('t_admin_table_missing', 'الجدول غير موجود.')) ?></b>
    <div class="mt-2"><?= h(__('t_admin_run_migration', 'نفّذ ملف SQL التالي مرة واحدة في phpMyAdmin:')) ?></div>
    <div class="mt-2"><code>/admin/db/migrations/2026_01_04_admin_notifications.sql</code></div>
  </div>
  <?php
  require_once __DIR__ . '/../layout/app_end.php';
  exit;
}

$user = Auth::user();
$uid = (int)($user['id'] ?? 0);

// Mark read/unread
if ($pdo && $uid > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf()) {
    $_SESSION['flash_error'] = __('t_fbbc004136', 'رمز الحماية (CSRF) غير صالح، يرجى إعادة المحاولة.');
    header("Location: index.php");
    exit;
  }

  $action = (string)($_POST['action'] ?? '');
  $nid = (int)($_POST['id'] ?? 0);

  if ($nid > 0 && in_array($action, ['read','unread','delete'], true)) {
    try {
      if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM admin_notifications WHERE id=:id AND (user_id=:uid OR user_id IS NULL)");
        $stmt->execute(['id'=>$nid,'uid'=>$uid]);
      } else {
        $isRead = ($action === 'read') ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE admin_notifications SET is_read=:r WHERE id=:id AND (user_id=:uid OR user_id IS NULL)");
        $stmt->execute(['r'=>$isRead,'id'=>$nid,'uid'=>$uid]);
      }
    } catch (\Throwable $e) {}
  }

  header("Location: index.php");
  exit;
}

// Fetch
$only = (string)($_GET['only'] ?? 'all'); // all|unread
$where = "(user_id = :uid OR user_id IS NULL)";
if ($only === 'unread') $where .= " AND is_read = 0";

$items = [];
$unreadCount = 0;

try {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_notifications WHERE (user_id = :uid OR user_id IS NULL) AND is_read = 0");
  $stmt->execute(['uid'=>$uid]);
  $unreadCount = (int)$stmt->fetchColumn();

  $stmt = $pdo->prepare("SELECT id, title, body, link, is_read, created_at FROM admin_notifications WHERE $where ORDER BY id DESC LIMIT 200");
  $stmt->execute(['uid'=>$uid]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {}

$pageActionsHtml =
  '<div class="d-flex gap-2 flex-wrap">' .
    '<a class="btn btn-outline-secondary btn-sm" href="index.php?only=all">'.h(__('t_admin_all','الكل')).'</a>' .
    '<a class="btn btn-outline-secondary btn-sm" href="index.php?only=unread">'.h(__('t_admin_unread','غير مقروء')).' (' . (int)$unreadCount . ')</a>' .
  '</div>';

require_once __DIR__ . '/../layout/app_start.php';
?>

<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger"><?= h((string)$_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<?php if (empty($items)): ?>
  <div class="text-muted"><?= h(__('t_admin_no_notifications','لا توجد إشعارات بعد.')) ?></div>
<?php else: ?>
  <div class="list-group">
    <?php foreach ($items as $n): ?>
      <?php
        $isRead = (int)($n['is_read'] ?? 0) === 1;
        $title = (string)($n['title'] ?? '');
        $body  = (string)($n['body'] ?? '');
        $link  = (string)($n['link'] ?? '');
      ?>
      <div class="list-group-item">
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2">
              <span class="badge <?= $isRead ? 'bg-secondary' : 'bg-primary' ?>"><?= $isRead ? h(__('t_admin_read','مقروء')) : h(__('t_admin_unread','غير مقروء')) ?></span>
              <div class="fw-bold"><?= h($title) ?></div>
            </div>
            <?php if ($body !== ''): ?>
              <div class="text-muted small mt-1"><?= h($body) ?></div>
            <?php endif; ?>
            <?php if ($link !== ''): ?>
              <div class="mt-2"><a href="<?= h($link) ?>" class="text-decoration-none"><?= h(__('t_admin_open','فتح')) ?></a></div>
            <?php endif; ?>
            <div class="text-muted small mt-2"><?= h((string)($n['created_at'] ?? '')) ?></div>
          </div>
          <div class="d-flex flex-column gap-2">
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
              <input type="hidden" name="action" value="<?= $isRead ? 'unread' : 'read' ?>">
              <button type="submit" class="btn btn-sm btn-outline-primary"><?= $isRead ? h(__('t_admin_mark_unread','تعليم كغير مقروء')) : h(__('t_admin_mark_read','تعليم كمقروء')) ?></button>
            </form>
            <form method="post" data-confirm='<?= h(__('t_admin_confirm_delete','تأكيد الحذف؟')) ?>'>
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn btn-sm btn-outline-danger"><?= h(__('t_admin_delete','حذف')) ?></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
