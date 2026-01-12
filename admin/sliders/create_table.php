<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();
$message = '';

if ($pdo instanceof PDO) {
    try {
        $sql = "
        CREATE TABLE IF NOT EXISTS sliders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            image_path VARCHAR(500) NOT NULL,
            button_text VARCHAR(100),
            button_url VARCHAR(500),
            display_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_order (display_order),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($sql);
        $message = __('t_280e05531b', "✅ تم إنشاء جدول السلايدر بنجاح!");
        
    } catch (PDOException $e) {
        $message = __('t_3c74b8bcf5', "❌ خطأ في إنشاء الجدول: ") . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string)(function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')), ENT_QUOTES, 'UTF-8') ?>" dir="<?= ((function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar')) === 'ar' ? 'rtl' : 'ltr') ?>">
<head>
    <meta charset="UTF-8">
    <title><?= h(__('t_734ed5ea50', 'إنشاء جدول السلايدر')) ?></title>
    <link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card bg-dark border-light">
                    <div class="card-body text-center">
                        <h2 class="card-title mb-4"><?= h(__('t_734ed5ea50', 'إنشاء جدول السلايدر')) ?></h2>
                        <div class="alert alert-<?= strpos($message, '✅') !== false ? 'success' : 'danger' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <a href="index.php" class="btn btn-primary mt-3"><?= h(__('t_7bd3b38e2e', 'العودة لإدارة السلايدر')) ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>