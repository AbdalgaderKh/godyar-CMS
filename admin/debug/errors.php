<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../includes/admin_debug.php';

if (!gdy_is_admin_user()) {
    http_response_code(403);
    echo "403 Forbidden";
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$storageLog = __DIR__ . '/../storage/admin_debug.log';

// مسح آخر خطأ (Session)
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    unset($_SESSION['admin_last_error']);
    header('Location: errors.php');
    exit;
}

$last = gdy_admin_get_last_error(false);

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// قراءة آخر 200 سطر من ملف اللوج الداخلي (إن وجد)
$tail = '';
if (is_file($storageLog) && is_readable($storageLog)) {
    $lines = @file($storageLog, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        $slice = array_slice($lines, -200);
        $tail = implode("\n", $slice);
    }
}

?><!doctype html>
<html lang="<?= htmlspecialchars((string)(function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')), ENT_QUOTES, 'UTF-8') ?>" dir="<?= ((function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')) === 'ar' ? 'rtl' : 'ltr') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(__('t_5d0337afab', 'تشخيص الأخطاء - لوحة التحكم')) ?></title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f5f7fb;margin:0;padding:16px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px;margin-bottom:14px;box-shadow:0 6px 20px rgba(0,0,0,.04)}
    pre{white-space:pre-wrap;word-break:break-word;background:#0b1220;color:#e5e7eb;border-radius:12px;padding:12px;overflow:auto}
    .muted{color:#6b7280}
    a.btn{display:inline-block;padding:10px 12px;border-radius:12px;background:#111827;color:#fff;text-decoration:none}
    a.btn2{display:inline-block;padding:10px 12px;border-radius:12px;background:#e5e7eb;color:#111827;text-decoration:none}
    .row{display:flex;gap:10px;flex-wrap:wrap}
  </style>
</head>
<body>

  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:center">
      <div>
        <h2 style="margin:0 0 6px"><?= h(__('t_89206b9c86', 'تشخيص الأخطاء')) ?></h2>
        <div class="muted"><?= h(__('t_6a64d9ae4f', 'هذه الصفحة للمدير فقط وتعرض آخر خطأ تم التقاطه داخل اللوحة + آخر سطور من سجل admin_debug.log.')) ?></div>
      </div>
      <div class="row">
        <a class="btn2" href="/admin/"><?= h(__('t_27b62857fb', 'العودة للوحة')) ?></a>
        <a class="btn" href="?clear=1"><?= h(__('t_55db84569d', 'مسح آخر خطأ')) ?></a>
      </div>
    </div>
  </div>

  <div class="card">
    <h3 style="margin-top:0"><?= h(__('t_5459bd7ee9', 'آخر خطأ (Session)')) ?></h3>
    <?php if ($last): ?>
      <pre><?= h(json_encode($last, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) ?></pre>
    <?php else: ?>
      <div class="muted"><?= h(__('t_8a747b5900', 'لا يوجد خطأ محفوظ في الجلسة حالياً.')) ?></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 style="margin-top:0"><?= h(__('t_1ca224939e', 'آخر 200 سطر من admin_debug.log')) ?></h3>
    <?php if ($tail !== ''): ?>
      <pre><?= h($tail) ?></pre>
    <?php else: ?>
      <div class="muted">ملف السجل غير موجود/غير قابل للقراءة: <?= h($storageLog) ?></div>
    <?php endif; ?>
  </div>

</body>
</html>
