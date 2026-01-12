<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// استعادة خبر من سلة المحذوفات

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

use Godyar\Auth;

if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

Auth::requirePermission('posts.edit');

Auth::requirePermission('posts.edit');

$isWriter = Auth::isWriter();
$userId   = (int)($_SESSION['user']['id'] ?? 0);

// 🚫 الكاتب/المؤلف لا يملك صلاحية الاستعادة
if ($isWriter) {
    header('Location: index.php?error=forbidden');
    exit;
}

$userId   = (int)($_SESSION['user']['id'] ?? 0);

$pdo = gdy_pdo_safe();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = (int)(($method === 'POST') ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));

if ($method !== 'POST') {
    if ($id <= 0) {
        header('Location: ./trash.php');
        exit;
    }
    // Show a confirmation page to avoid destructive GET (CSRF-safe)
    echo "<!doctype html><html lang='ar' dir='rtl'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>استعادة الخبر</title>" .
         "<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;background:#f6f7fb}.card{max-width:560px;margin:10vh auto;background:#fff;border:1px solid #e7e9ef;border-radius:14px;padding:18px}.btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #d7d9e0;text-decoration:none}.danger{background:#c0392b;color:#fff;border-color:#c0392b}.muted{color:#6b7280}</style></head><body>" .
         "<div class='card'><h2 style='margin:0 0 10px'>استعادة الخبر</h2><p class='muted'>سيتم استعادة الخبر من سلة المحذوفات.</p>" .
         "<form method='post' style='margin-top:14px'>"; 
    csrf_field();
    echo "<input type='hidden' name='id' value='" . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . "'>" .
         "<button class='btn danger' type='submit'>تأكيد</button> " .
         "<a class='btn' href='./trash.php'>إلغاء</a>" .
         "</form></div></body></html>";
    exit;
}

verify_csrf();

if ($id > 0 && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("UPDATE news SET deleted_at = NULL WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
    } catch (Throwable $e) {
        @error_log('[Godyar News] restore: ' . $e->getMessage());
    }
}

header('Location: trash.php');
exit;
