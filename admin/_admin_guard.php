<?php
declare(strict_types=1);
/**
 * admin/_admin_guard.php
 * حارس عام لكل صفحات لوحة التحكم:
 * - يفرض تسجيل الدخول (ويحول إلى /admin/login عند عدم تسجيل الدخول).
 * - ثم يطبق قيود الكاتب/المؤلف عبر _role_guard.php.
 *
 * ملاحظة: هذا الحارس مصمم ليكون آمن حتى لو لم توجد بعض الكلاسات/الملفات.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$loginUrl = '/admin/login';

// تحميل bootstrap + auth إن وُجدا
$bootstrap = __DIR__ . '/../includes/bootstrap.php';
if (is_file($bootstrap)) {
    require_once $bootstrap;
}


// Language / i18n (Admin)
$__i18n = __DIR__ . '/i18n.php';
if (is_file($__i18n)) {
    require_once $__i18n;
}

// Escaper helper (ensure available for translated snippets)
if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$authFile = __DIR__ . '/../includes/auth.php';
if (is_file($authFile)) {
    require_once $authFile;
}

// Optional DB audit logger (Ultra Pack)
$__auditDb = __DIR__ . '/includes/audit_db.php';
if (is_file($__auditDb)) {
    require_once $__auditDb;
}

// تحقق من تسجيل الدخول
try {
    if (class_exists('Godyar\\Auth') && method_exists('Godyar\\Auth', 'isLoggedIn')) {
        if (!\Godyar\Auth::isLoggedIn()) {
			if (defined('GDY_ADMIN_JSON') && GDY_ADMIN_JSON) {
				if (!headers_sent()) {
					header('Content-Type: application/json; charset=utf-8');
				}
				http_response_code(401);
				echo json_encode(['ok' => false, 'msg' => 'auth']);
				exit;
			}
			header('Location: ' . $loginUrl);
			exit;
        }
    } else {
        // بديل بسيط
        if (empty($_SESSION['user']['id']) || (($_SESSION['user']['role'] ?? 'guest') === 'guest')) {
			if (defined('GDY_ADMIN_JSON') && GDY_ADMIN_JSON) {
				if (!headers_sent()) {
					header('Content-Type: application/json; charset=utf-8');
				}
				http_response_code(401);
				echo json_encode(['ok' => false, 'msg' => 'auth']);
				exit;
			}
			header('Location: ' . $loginUrl);
			exit;
        }
    }
} catch (Throwable $e) {
    @error_log('[Admin Guard] ' . $e->getMessage());
    if (empty($_SESSION['user']['id'])) {
		if (defined('GDY_ADMIN_JSON') && GDY_ADMIN_JSON) {
			if (!headers_sent()) {
				header('Content-Type: application/json; charset=utf-8');
			}
			http_response_code(401);
			echo json_encode(['ok' => false, 'msg' => 'auth']);
			exit;
		}
		header('Location: ' . $loginUrl);
		exit;
    }
}

// بعد التأكد من الدخول: طبّق حارس الكاتب/المؤلف
require_once __DIR__ . '/_role_guard.php';

// -----------------------------------------------------------------------------
// Session invalidation (Logout all devices) via users.session_version
// -----------------------------------------------------------------------------
try {
    $uid = (int)($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0));
    if ($uid > 0) {
        $pdo = null;
        if (class_exists('Godyar\\DB') && method_exists('Godyar\\DB', 'pdo')) {
            $pdo = \Godyar\DB::pdo();
        } elseif (function_exists('gdy_pdo_safe')) {
            $pdo = gdy_pdo_safe();
        }
        if ($pdo instanceof PDO) {
            $st = $pdo->prepare("SELECT session_version FROM users WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            $dbSv = $st->fetchColumn();
            $dbSv = is_numeric($dbSv) ? (int)$dbSv : 0;

            if (!isset($_SESSION['session_version']) || !is_numeric($_SESSION['session_version'])) {
                $_SESSION['session_version'] = $dbSv;
            } else {
                $sessSv = (int)$_SESSION['session_version'];
                if ($sessSv !== $dbSv) {
                    // Old session -> force logout
                    if (defined('GDY_ADMIN_JSON') && GDY_ADMIN_JSON) {
                        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
                        http_response_code(401);
                        echo json_encode(['ok' => false, 'msg' => 'session_expired']);
                        exit;
                    }
                    @session_destroy();
                    header('Location: ' . $loginUrl . '?msg=session_expired');
                    exit;
                }
            }
        }
    }
} catch (Throwable $e) {
    @error_log('[Admin Guard] session_version: ' . $e->getMessage());
}


// -----------------------------------------------------------------------------
// CSRF helpers (global for admin)
// -----------------------------------------------------------------------------
// كثير من صفحات لوحة التحكم تستخدم generate_csrf_token()/verify_csrf_token().
// في بعض التركيبات قد لا تكون هذه الدوال معرفة داخل includes/، مما كان يتسبب
// بتوقف تنفيذ PHP منتصف الصفحة (فتظهر الصفحة وكأنها __('t_eb2d2a6edb', "بدون محتوى")).
// نعرّفها هنا مرة واحدة لضمان عمل جميع صفحات الإدارة.

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['_csrf_token'])) {
            try {
                $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                // fallback ضعيف لكن يمنع توقف الصفحة
                $_SESSION['_csrf_token'] = sha1(uniqid('', true));
            }
        }
        return (string)$_SESSION['_csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool {
        $token = (string)($token ?? '');
        $sessionToken = (string)($_SESSION['_csrf_token'] ?? '');
        if ($token === '' || $sessionToken === '') return false;
        return hash_equals($sessionToken, $token);
    }
}

// -----------------------------------------------------------------------------
// CSRF sugar helpers used across admin/settings pages
// -----------------------------------------------------------------------------
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return generate_csrf_token();
    }
}

// Many admin pages expect csrf_field() helper to exist.
// Some older layouts defined it with a different session key, causing CSRF failures.
// We provide a single canonical implementation here that matches verify_csrf().
if (!function_exists('csrf_field')) {
    /**
     * Outputs a hidden CSRF field named "csrf_token".
     * Returns an empty string so that pages using "echo csrf_field()" won't duplicate output.
     */
    function csrf_field(): string {
        $t = csrf_token();
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
        return '';
    }
}

if (!function_exists('verify_csrf')) {
    /**
     * Verifies CSRF token on POST.
     * Returns true on success. Dies with HTTP 400 on failure.
     */
    function verify_csrf(string $fieldName = 'csrf_token'): bool {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return true;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $sent = (string)($_POST[$fieldName] ?? '');
        if (!verify_csrf_token($sent)) {
            http_response_code(400);
            die('CSRF validation failed');
        }
        return true;
    }
}
