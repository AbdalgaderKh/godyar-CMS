<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
  if (function_exists('csrf_verify_or_die')) { csrf_verify_or_die(); }
$name = trim($_POST['name'] ?? '');
 $email= trim($_POST['email'] ?? ''); $pass = $_POST['password'] ?? '';
  // Generate a safe username (required by schema)
  $baseUsername = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', strstr($email, '@', true) ?: $name));
  $baseUsername = trim($baseUsername, '_');
  if ($baseUsername === '') { $baseUsername = 'user'; }
  $username = $baseUsername;
  // Ensure uniqueness
  $i = 0;
  while (true) {
    $chk = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
    $chk->execute([':u' => $username]);
    if (!$chk->fetch()) { break; }
    $i++;
    $username = $baseUsername . $i;
    if ($i > 9999) { throw new Exception('Unable to generate unique username'); }
  }
 $email= trim($_POST['email'] ?? ''); $pass = $_POST['password'] ?? '';
  try {
    $st = $pdo->prepare("INSERT INTO users (name,username,email,password_hash,password,role,is_admin,status,created_at,updated_at) VALUES (:n,:u,:e,:h,:h,'user',0,'active',NOW(),NOW())");
    $st->execute([':n'=>$name, ':u'=>$username, ':e'=>$email, ':h'=>password_hash($pass, PASSWORD_DEFAULT)]);
    header("Location: /godyar/login"); exit;
  } catch (Throwable $e) { $error = 'لا يمكن إنشاء الحساب'; error_log('REGISTER_ERROR: '.$e->getMessage()); }
}
?>
<!doctype html><html lang="ar" dir="rtl"><head>
    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تسجيل</title>
<link rel="stylesheet" href="/godyar/assets/css/vendors/bootstrap.min.css"></head><body class="p-3">
<div class="container" style="max-width:420px">
  <h1 class="h4 my-3 text-center">إنشاء حساب</h1>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <?php if (function_exists('csrf_field')) { csrf_field(); } ?>
    <div class="mb-3"><label class="form-label">الاسم</label><input class="form-control" name="name" required></div>
    <div class="mb-3"><label class="form-label">البريد</label><input class="form-control" type="email" name="email" required></div>
    <div class="mb-3"><label class="form-label">كلمة المرور</label><input class="form-control" type="password" name="password" required></div>
    <button class="btn btn-primary w-100">تسجيل</button>
  </form>
</div>
</body></html>
