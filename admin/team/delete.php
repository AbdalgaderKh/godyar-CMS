<?php
declare(strict_types=1);


require_once __DIR__ . '/../_admin_guard.php';
// admin/team/delete.php — حذف عضو فريق

require_once __DIR__ . '/../../includes/bootstrap.php';

$authFile = __DIR__ . '/../../includes/auth.php';
if (is_file($authFile)) {
    require_once $authFile;
}

use Godyar\Auth;

$currentPage = 'team';
$pageTitle   = __('t_05b4c0a9c3', 'حذف عضو');

try {
    if (class_exists(Auth::class) && method_exists(Auth::class,'isLoggedIn')) {
        if (!Auth::isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
    } else {
        if (empty($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') === 'guest')) {
            header('Location: ../login.php');
            exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Admin Team Delete] Auth: '.$e->getMessage());
    header('Location: ../login.php');
    exit;
}

$pdo = gdy_pdo_safe();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = (int)(($method === 'POST') ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));

if ($method !== 'POST') {
    if ($id <= 0) {
        header('Location: ./index.php');
        exit;
    }
    // Show a confirmation page to avoid destructive GET (CSRF-safe)
    echo "<!doctype html><html lang='ar' dir='rtl'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>حذف عضو الفريق</title>" .
         "<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;background:#f6f7fb}.card{max-width:560px;margin:10vh auto;background:#fff;border:1px solid #e7e9ef;border-radius:14px;padding:18px}.btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #d7d9e0;text-decoration:none}.danger{background:#c0392b;color:#fff;border-color:#c0392b}.muted{color:#6b7280}</style></head><body>" .
         "<div class='card'><h2 style='margin:0 0 10px'>حذف عضو الفريق</h2><p class='muted'>سيتم حذف عضو الفريق. تأكد قبل المتابعة.</p>" .
         "<form method='post' style='margin-top:14px'>"; 
    csrf_field();
    echo "<input type='hidden' name='id' value='" . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . "'>" .
         "<button class='btn danger' type='submit'>تأكيد</button> " .
         "<a class='btn' href='./index.php'>إلغاء</a>" .
         "</form></div></body></html>";
    exit;
}

verify_csrf();

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
    } catch (Throwable $e) {
        @error_log('[Admin Team Delete] delete: '.$e->getMessage());
    }
}

header('Location: index.php?deleted=1');
exit;
