<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/totp.php';

$title = 'التحقق بخطوتين (2FA)';
$uid = (int)($_SESSION['user']['id'] ?? 0);

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

$st = $pdo->prepare("SELECT email, twofa_enabled, twofa_secret FROM users WHERE id = ? LIMIT 1");
$st->execute([$uid]);
$user = $st->fetch(PDO::FETCH_ASSOC) ?: [];
$email = (string)($user['email'] ?? '');
$enabled = (int)($user['twofa_enabled'] ?? 0) === 1;
$secret = (string)($user['twofa_secret'] ?? '');

$msg = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf('csrf_token');
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'disable') {
            $pdo->prepare("UPDATE users SET twofa_enabled = 0, twofa_secret = NULL WHERE id = ? LIMIT 1")->execute([$uid]);
            if (function_exists('admin_audit_db')) admin_audit_db('2fa_disabled');
            $enabled = false;
            $secret = '';
            $msg = 'تم إيقاف 2FA.';
        } elseif ($action === 'start') {
            $_SESSION['twofa_setup_secret'] = totp_generate_secret(16);
            $secret = (string)$_SESSION['twofa_setup_secret'];
        } elseif ($action === 'confirm') {
            $setupSecret = (string)($_SESSION['twofa_setup_secret'] ?? '');
            $code = (string)($_POST['code'] ?? '');
            if ($setupSecret === '') throw new Exception('ابدأ التفعيل أولاً.');
            if (!totp_verify($setupSecret, $code)) throw new Exception('رمز غير صحيح. حاول مرة أخرى.');
            $pdo->prepare("UPDATE users SET twofa_secret = ?, twofa_enabled = 1 WHERE id = ? LIMIT 1")->execute([$setupSecret, $uid]);
            unset($_SESSION['twofa_setup_secret']);
            if (function_exists('admin_audit_db')) admin_audit_db('2fa_enabled');
            $enabled = true;
            $secret = $setupSecret;
            $msg = 'تم تفعيل 2FA بنجاح.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$setupSecret = (string)($_SESSION['twofa_setup_secret'] ?? '');
$issuer = 'Godyar Admin';
$otpauth = $setupSecret !== '' ? totp_otpauth_url($issuer, $email ?: ('user'.$uid), $setupSecret) : '';
$qrUrl = '';
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
<div class="container" style="max-width:760px;">
  <h1 class="h4 mb-3"><?= h($title) ?></h1>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <?php if ($enabled): ?>
        <p>2FA مفعل على حسابك.</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
          <button class="btn btn-outline-danger" name="action" value="disable" type="submit"
                  data-confirm='تأكيد إيقاف 2FA؟'>إيقاف 2FA</button>
        </form>
      <?php else: ?>
        <p class="mb-3">فعّل التحقق بخطوتين باستخدام تطبيق مثل Google Authenticator أو Authy.</p>

        <?php if ($setupSecret === ''): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
            <button class="btn btn-primary" name="action" value="start" type="submit">بدء التفعيل</button>
          </form>
        <?php else: ?>
          <div class="row g-3 align-items-center">
            <div class="col-md-4">
              <div class="p-3 border rounded" style="background: rgba(0,0,0,.03);">
                <div class="fw-semibold mb-2">إضافة يدوية</div>
                <div class="small text-muted mb-2">إذا لم يظهر QR (أو لا ترغب في استخدام خدمات خارجية)، أدخل البيانات يدويًا في تطبيق المصادقة.</div>
                <div class="mb-2">Secret: <code><?= h($setupSecret) ?></code></div>
                <div class="mb-0">OTPAuth URI: <code style="word-break:break-all;"><?= h($otpauth) ?></code></div>
              </div>
            </div>
            <div class="col-md-8">
              <div class="mb-2">الرمز (Secret): <code><?= h($setupSecret) ?></code></div>
              <div class="text-muted small mb-3">امسح QR ثم أدخل الرمز المكوّن من 6 أرقام للتأكيد.</div>

              <form method="post" class="d-flex gap-2 flex-wrap">
                <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                <input type="text" name="code" class="form-control" style="max-width:220px;" placeholder="123456" required>
                <button class="btn btn-success" name="action" value="confirm" type="submit">تأكيد التفعيل</button>
              </form>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="text-muted small">
    إن فقدت الوصول للتطبيق، ستحتاج إلى تدخل الإدارة لإلغاء 2FA من قاعدة البيانات.
  </div>
</div>
</body>
</html>
