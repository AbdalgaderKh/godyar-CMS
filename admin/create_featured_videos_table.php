<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_guard.php';
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'إنشاء جدول الفيديوهات المميزة';
$currentPage = 'videos';

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

try {
    if (class_exists('Godyar\\Auth')) {
        $auth = new Godyar\Auth();
        if (!$auth->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
        if (!$auth->hasAnyRole(['admin','super_admin'])) {
            http_response_code(403);
            die('Forbidden');
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Featured Videos] Auth error: ' . $e->getMessage());
    header('Location: login.php');
    exit;
}

$pdo = gdy_pdo_safe();
if (!$pdo instanceof PDO) {
    die('❌ لا يوجد اتصال بقاعدة البيانات.');
}

$success = false;
$error = null;
$tableExists = false;

try {
    $check = $pdo->query("SHOW TABLES LIKE 'featured_videos'");
    $tableExists = $check && $check->fetchColumn();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (!$tableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table'])) {
    try {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `featured_videos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `video_url` VARCHAR(500) NOT NULL,
  `description` TEXT NULL,
  `thumbnail_url` VARCHAR(500) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sort` (`sort_order`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $pdo->exec($sql);
        $success = true;
        $tableExists = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/layout/app_start.php';
?>

<div class="container py-4">
  <h1 class="h4 mb-3"><?= h($pageTitle) ?></h1>

  <?php if ($success): ?>
    <div class="alert alert-success">✅ تم إنشاء جدول featured_videos بنجاح.</div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger">❌ <?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($tableExists): ?>
    <div class="alert alert-info">ℹ️ الجدول موجود بالفعل.</div>
    <a class="btn btn-primary" href="manage_videos.php">العودة لإدارة الفيديوهات</a>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="create_table" value="1">
      <button class="btn btn-danger" type="submit">إنشاء جدول featured_videos</button>
    </form>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/layout/app_end.php'; ?>
