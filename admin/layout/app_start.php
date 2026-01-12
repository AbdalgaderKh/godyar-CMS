<?php
declare(strict_types=1);

// Shared start wrapper for admin pages.
// Usage (in a page):
//   $currentPage = 'ads';
//   $pageTitle = __('t_5750d13d2c', 'الإعلانات');
//   $pageSubtitle = __('t_45584085be', 'إدارة أماكن الإعلانات'); // optional
//   $breadcrumbs = [__('t_3aa8578699', 'الرئيسية') => $adminBase.'/index.php', __('t_5750d13d2c', 'الإعلانات') => null]; // optional
//   $pageActionsHtml = __('t_f8c668155c', '<a ...>زر</a>'); // optional

$__base = defined('GODYAR_BASE_URL') ? rtrim((string)GODYAR_BASE_URL, '/') : '';

// Ensure CSS is loaded once.
if (!isset($pageHead)) { $pageHead = ''; }
if (strpos((string)$pageHead, 'admin-ui.css') === false) {
    // Cache busting (CSS might be cached aggressively in some shared hosts)
    $adminUiFile = __DIR__ . '/../assets/css/admin-ui.css';
    $v = is_file($adminUiFile) ? (int)@filemtime($adminUiFile) : time();
    $pageHead = '<link rel="stylesheet" href="' . $__base . '/admin/assets/css/admin-ui.css?v=' . $v . '">' . "\n" . $pageHead;
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$pageSubtitle = $pageSubtitle ?? '';
$breadcrumbs = $breadcrumbs ?? [];
$pageActionsHtml = $pageActionsHtml ?? '';

// -----------------------------------------------------------------------------
// Header tools (Notifications dropdown)
// -----------------------------------------------------------------------------
$headerToolsHtml = '';
try {
  $uid = 0;
  if (class_exists('\Godyar\Auth') && method_exists('\Godyar\Auth', 'user')) {
    $u = \Godyar\Auth::user();
    $uid = (int)($u['id'] ?? 0);
  } else {
    $uid = (int)($_SESSION['user']['id'] ?? 0);
  }

  $pdo = null;
  if (class_exists('\Godyar\DB') && method_exists('\Godyar\DB', 'pdo')) {
    $pdo = \Godyar\DB::pdo();
  }

  $hasNotifTable = false;
  if ($pdo instanceof \PDO) {
    $chk = $pdo->query("SHOW TABLES LIKE 'admin_notifications'");
    $hasNotifTable = (bool)($chk && $chk->fetchColumn());
  }

  if ($pdo instanceof \PDO && $hasNotifTable && $uid > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_notifications WHERE is_read=0 AND (user_id IS NULL OR user_id=:uid)");
    $stmt->execute(['uid'=>$uid]);
    $unread = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id,title,link,is_read,created_at FROM admin_notifications WHERE (user_id IS NULL OR user_id=:uid) ORDER BY id DESC LIMIT 6");
    $stmt->execute(['uid'=>$uid]);
    $recent = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $badge = $unread > 0 ? '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.65rem;">'.(int)$unread.'</span>' : '';
    $csrf  = function_exists('csrf_token') ? (string)csrf_token() : '';

    $itemsHtml = '';
    foreach ($recent as $n) {
      $nid = (int)($n['id'] ?? 0);
      $t   = (string)($n['title'] ?? '');
      $lnk = (string)($n['link'] ?? '');
      $isRead = (int)($n['is_read'] ?? 0) === 1;
      $dt  = (string)($n['created_at'] ?? '');
      $href = $lnk !== '' ? $lnk : (rtrim((string)$adminBase,'/') . '/notifications/index.php');
      $itemsHtml .= '<a class="list-group-item list-group-item-action gdy-notif-item" href="'.h($href).'" style="background:transparent;border-color:rgba(148,163,184,.14);">'
        . '<div class="d-flex justify-content-between gap-2">'
        . '<div class="text-truncate" style="max-width:280px;">'
        . ($isRead ? '' : '<span class="badge bg-primary me-1">'.h(function_exists('__')?__('t_admin_unread','غير مقروء'):'غير مقروء').'</span>')
        . h($t !== '' ? $t : ('#'.$nid))
        . '</div>'
        . '<small class="text-muted">'.h($dt).'</small>'
        . '</div></a>';
    }
    if ($itemsHtml === '') {
      $itemsHtml = '<div class="p-3 text-muted small">'.h(function_exists('__')?__('t_admin_no_notifications','لا توجد إشعارات بعد.'):'لا توجد إشعارات بعد.').'</div>';
    } else {
      $itemsHtml = '<div class="list-group list-group-flush">' . $itemsHtml . '</div>';
    }

    $headerToolsHtml =
      '<div class="dropdown">'
      . '<button class="btn btn-gdy-outline position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="'.h(function_exists('__')?__('t_admin_notifications','الإشعارات'):'الإشعارات').'">'
      . '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>' . $badge . '</button>'
      . '<div class="dropdown-menu dropdown-menu-end p-0 gdy-notif-menu">'
      . '<div class="p-3 border-bottom" style="border-color:rgba(148,163,184,.18)!important;">'
      .   '<div class="d-flex justify-content-between align-items-center">'
      .     '<div class="fw-bold">'.h(function_exists('__')?__('t_admin_notifications','الإشعارات'):'الإشعارات').'</div>'
      .     '<span class="text-muted small">'.(int)$unread.'</span>'
      .   '</div>'
      .   '<div class="text-muted small">'.h(function_exists('__')?__('t_admin_notifications_sub','مركز إشعارات لوحة التحكم'):'مركز إشعارات لوحة التحكم').'</div>'
      . '</div>'
      . $itemsHtml
      . '<div class="p-2 border-top d-flex justify-content-between gap-2" style="border-color:rgba(148,163,184,.18)!important;">'
      .   '<a class="btn btn-sm btn-outline-light" href="'.h(rtrim((string)$adminBase,'/').'/notifications/index.php').'">'.h(function_exists('__')?__('t_admin_all','عرض الكل'):'عرض الكل').'</a>'
      .   '<form method="post" action="'.h(rtrim((string)$adminBase,'/').'/notifications/mark_all.php').'" class="m-0">'
      .     '<input type="hidden" name="csrf_token" value="'.h($csrf).'">'
      .     '<button class="btn btn-sm btn-outline-success" type="submit">'.h(function_exists('__')?__('t_admin_mark_all_read','تعليم الكل كمقروء'):'تعليم الكل كمقروء').'</button>'
      .   '</form>'
      . '</div>'
      . '</div></div>';
  }
} catch (\Throwable $e) {
  // ignore
}

?>

<main class="admin-content">
  <div class="gdy-admin-shell">
    <div class="gdy-page-header mb-4">
      <?php if (!empty($breadcrumbs)): ?>
        <div class="gdy-breadcrumb mb-2">
          <?php
            $i = 0;
            foreach ($breadcrumbs as $label => $href) {
              if ($i > 0) echo ' / ';
              $i++;
              if ($href) {
                echo '<a href="' . h($href) . '" style="color:rgba(255,255,255,.95);text-decoration:none;">' . h($label) . '</a>';
              } else {
                echo '<span>' . h($label) . '</span>';
              }
            }
          ?>
        </div>
      <?php endif; ?>

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div>
          <h1 class="gdy-page-title"><?= h($pageTitle ?? __('t_a06ee671f4', 'لوحة التحكم')) ?></h1>
          <?php if ($pageSubtitle !== ''): ?>
            <p class="gdy-page-subtitle"><?= h($pageSubtitle) ?></p>
          <?php endif; ?>
        </div>
        <?php if (($pageActionsHtml ?? '') !== '' || ($headerToolsHtml ?? '') !== ''): ?>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if (($pageActionsHtml ?? '') !== ''): ?><?= $pageActionsHtml ?><?php endif; ?>
            <?php if (($headerToolsHtml ?? '') !== ''): ?><?= $headerToolsHtml ?><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
