<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/totp.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (empty($_SESSION['twofa_pending']) || !is_array($_SESSION['twofa_pending'])) {
    header('Location: /admin/login.php');
    exit;
}

$pending = $_SESSION['twofa_pending'];
$uid = (int)($pending['id'] ?? 0);

$pdo = null;
if (class_exists('Godyar\\DB') && method_exists('Godyar\\DB', 'pdo')) {
    $pdo = \Godyar\DB::pdo();
} elseif (function_exists('gdy_pdo_safe')) {
    $pdo = gdy_pdo_safe();
}

if (!$pdo instanceof PDO || $uid <= 0) {
    http_response_code(500);
    die('DB not available');
}

$st = $pdo->prepare("SELECT id, username, email, role, status, twofa_enabled, twofa_secret, session_version FROM users WHERE id = ? LIMIT 1");
$st->execute([$uid]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user || (int)($user['twofa_enabled'] ?? 0) !== 1 || empty($user['twofa_secret'])) {
    unset($_SESSION['twofa_pending']);
    header('Location: /admin/login.php');
    exit;
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $code = (string)($_POST['code'] ?? '');
    if (!totp_verify((string)$user['twofa_secret'], $code)) {
        $error = 'رمز 2FA غير صحيح.';
    } else {
        // login success now
        $_SESSION['user'] = [
            'id'       => (int)$user['id'],
            'name'     => $user['username'] ?? $user['email'],
            'username' => $user['username'] ?? null,
            'role'     => $user['role'] ?? 'admin',
            'email'    => $user['email'],
            'status'   => $user['status'] ?? 'active',
        ];

        $sv = is_numeric($user['session_version'] ?? null) ? (int)$user['session_version'] : 0;
        $_SESSION['session_version'] = $sv;

        // update last_login_at + ip if columns exist
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ? LIMIT 1")
                ->execute([$ip, (int)$user['id']]);
        } catch (Throwable $e) {}

        // audit
        if (function_exists('admin_audit_db')) {
            admin_audit_db('login_2fa_success');
        }

        unset($_SESSION['twofa_pending']);
        header('Location: /admin/index.php');
        exit;
    }
}

function h2(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>تأكيد 2FA</title>
  <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
<body class="p-3">
<div class="container" style="max-width:520px;">
  <h1 class="h5 mb-3">التحقق بخطوتين</h1>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= h2($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <p class="text-muted small mb-3">افتح تطبيق المصادقة وأدخل الرمز المكوّن من 6 أرقام.</p>

      <form method="post" class="d-flex gap-2">
        <input class="form-control" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required>
        <button class="btn btn-primary" type="submit">تأكيد</button>
      </form>

      <div class="mt-3 small">
        <a href="/admin/login.php">العودة لتسجيل الدخول</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
