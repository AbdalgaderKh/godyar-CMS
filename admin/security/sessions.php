<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';

$title = 'الجلسات والأجهزة';
$error = null;
$okMsg = null;

$uid = (int)($_SESSION['user']['id'] ?? 0);

$pdo = null;
if (class_exists('Godyar\\DB') && method_exists('Godyar\\DB', 'pdo')) {
    $pdo = \Godyar\DB::pdo();
} elseif (function_exists('gdy_pdo_safe')) {
    $pdo = gdy_pdo_safe();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf('csrf_token');

    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'logout_all' && $uid > 0 && $pdo instanceof PDO) {
            $pdo->prepare("UPDATE users SET session_version = COALESCE(session_version,0) + 1 WHERE id = ? LIMIT 1")
                ->execute([$uid]);

            if (function_exists('admin_audit_db')) {
                admin_audit_db('logout_all_devices');
            }

            @session_destroy();
            header('Location: /admin/login.php?msg=logged_out_all');
            exit;
        }

        if ($action === 'logout_me') {
            if (function_exists('admin_audit_db')) {
                admin_audit_db('logout_current_device');
            }
            @session_destroy();
            header('Location: /admin/login.php?msg=logged_out');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>
  <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
<body class="p-3">
  <div class="container" style="max-width:720px;">
    <h1 class="h4 mb-3"><?= h($title) ?></h1>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-body">
        <p class="mb-3">يمكنك تسجيل الخروج من هذا الجهاز فقط، أو من جميع الأجهزة (وإبطال كل الجلسات القديمة).</p>

        <form method="post" class="d-flex gap-2 flex-wrap">
          <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
          <button class="btn btn-outline-secondary" name="action" value="logout_me" type="submit">
            تسجيل الخروج من هذا الجهاز
          </button>
          <button class="btn btn-danger" name="action" value="logout_all" type="submit" data-confirm='تأكيد تسجيل الخروج من جميع الأجهزة؟'>
            تسجيل الخروج من جميع الأجهزة
          </button>
        </form>
      </div>
    </div>

    <div class="text-muted small">
      ملاحظة: خيار "تسجيل الخروج من جميع الأجهزة" يعتمد على عمود <code>users.session_version</code>.
    </div>
  </div>
</body>
</html>
