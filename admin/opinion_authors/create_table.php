<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/opinion_authors/create_table.php
// صفحة إنشاء جدول كُتّاب الرأي opinion_authors

// 🔹 مسار صحيح لملف البوتستراب
require_once __DIR__ . '/../../includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// دالة هروب بسيطة
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// التحقق من أن المستخدم أدمن
$user    = $_SESSION['user'] ?? null;
$isAdmin = is_array($user) && (($user['role'] ?? '') === 'admin');

if (!$isAdmin) {
    header('Location: ../login.php');
    exit;
}

// محاولة جلب PDO
$pdo = gdy_pdo_safe();

$success = false;
$error   = null;

if ($pdo instanceof PDO) {
    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS opinion_authors (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                page_title VARCHAR(255) NULL,
                bio TEXT NULL,
                specialization VARCHAR(255) NULL,
                social_website VARCHAR(255) NULL,
                social_twitter VARCHAR(255) NULL,
                social_facebook VARCHAR(255) NULL,
                email VARCHAR(190) NULL,
                avatar VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                display_order INT NOT NULL DEFAULT 0,
                articles_count INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci;
        ";

        $pdo->exec($sql);
        $success = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        @error_log('[Opinion Authors] create_table error: ' . $e->getMessage());
    }
} else {
    $error = __('t_603fac120b', 'لا يمكن الاتصال بقاعدة البيانات.');
}

?>
<!doctype html>
<html lang="<?= htmlspecialchars((string)(function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')), ENT_QUOTES, 'UTF-8') ?>" dir="<?= ((function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')) === 'ar' ? 'rtl' : 'ltr') ?>">
<head>
  <meta charset="utf-8">
  <title><?= h(__('t_7f6d379bac', 'إنشاء جدول كُتّاب الرأي')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css"
    rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container py-5">

  <h1 class="h4 mb-4"><?= h(__('t_58f5f1e43d', 'إنشاء جدول كُتّاب الرأي (opinion_authors)')) ?></h1>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <?= h(__('t_d4975cae6d', '✅ تم إنشاء جدول')) ?> <strong>opinion_authors</strong> <?= h(__('t_bea7cfeb79', 'بنجاح (أو كان موجودًا بالفعل).')) ?>
    </div>
    <a href="index.php" class="btn btn-primary"><?= h(__('t_6ccaa5091f', 'الذهاب لصفحة كُتّاب الرأي')) ?></a>
    <a href="../index.php" class="btn btn-outline-light ms-2"><?= h(__('t_2f09126266', 'العودة للوحة التحكم')) ?></a>
  <?php else: ?>
    <div class="alert alert-danger">
      <?= h(__('t_344cdef245', '⚠️ تعذّر إنشاء الجدول.')) ?><br>
      <?= $error ? '<small>'.h($error).'</small>' : '' ?>
    </div>
    <a href="../index.php" class="btn btn-outline-light"><?= h(__('t_2f09126266', 'العودة للوحة التحكم')) ?></a>
  <?php endif; ?>

</div>
</body>
</html>
