<?php
declare(strict_types=1);

namespace Godyar;

/**
 * Compatibility Auth class for admin pages that do: use Godyar\Auth;
 * This implementation is intentionally simple and session-based.
 */
final class Auth
{
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }
    }

    public static function check(): bool
    {
        self::ensureSession();
        return isset($_SESSION['user']) || isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        self::ensureSession();

        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            return $_SESSION['user'];
        }

        if (!empty($_SESSION['user_id'])) {
            return [
                'id'   => (int)$_SESSION['user_id'],
                'role' => $_SESSION['user_role'] ?? null,
                'name' => $_SESSION['user_name'] ?? null,
            ];
        }

        // Optional legacy global Auth
        $legacy = __DIR__ . '/Auth.php';
        if (is_file($legacy)) {
            require_once $legacy;
        }
        if (class_exists('\Auth') && method_exists('\Auth', 'user')) {
            try {
                $u = \Auth::user();
                return is_array($u) ? $u : null;
            } catch (\Throwable) {}
        }

        return null;
    }

    public static function hasRole(string $role): bool
    {
        $u = self::user();
        if (!$u) return false;
        $r = $u['role'] ?? null;

        if (is_array($r)) return in_array($role, $r, true);
        if ($r === null || $r === '') return false;

        $roles = array_filter(array_map('trim', explode(',', (string)$r)));
        return in_array($role, $roles, true);
    }

    /**
     * Check if current user has ANY role from given list.
     */
    public static function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if (is_string($role) && self::hasRole($role)) {
                return true;
            }
        }
        return false;
    }


    public static function isAdmin(): bool
    {
        return self::hasRole('admin');
    }

    public static function isWriter(): bool
    {
        return self::hasRole('writer') || self::hasRole('author');
    }

    public static function enforceAdminPolicy(): void
    {
        self::ensureSession();
        if (!self::check()) return;

        // writers/authors are limited in admin
        if (!self::isWriter()) return;

        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '');
        if ($path === '' || strpos($path, '/admin/') !== 0) return;

        $allowedPrefixes = ['/admin/news/'];
        foreach ($allowedPrefixes as $prefix) {
            if (strpos($path, $prefix) === 0) return;
        }

        $allowedExact = ['/admin/login','/admin/login.php','/admin/logout.php','/admin/index.php'];
        if (in_array($path, $allowedExact, true)) return;

        header('Location: /admin/news/index.php');
        exit;
    }

    public static function isLoggedIn(): bool
    {
        self::enforceAdminPolicy();
        return self::check();
    }

    // ---------------------------------------------------------------------
    // RBAC (Permissions)
    // ---------------------------------------------------------------------

    /**
     * Get user permissions from the session (if present), otherwise derive
     * a sensible default set based on role.
     *
     * Supported formats:
     * - $_SESSION['user']['permissions'] as array of strings
     * - $_SESSION['user']['permissions'] as comma-separated string
     */
    public static function permissions(): array
    {
        self::ensureSession();
        $u = self::user();
        if (!$u) return [];

        // 1) Explicit permissions on the session (if any)
        $perms = $u['permissions'] ?? null;
        if (is_array($perms)) {
            $out = [];
            foreach ($perms as $p) {
                $p = trim((string)$p);
                if ($p !== '') $out[] = $p;
            }
            return array_values(array_unique($out));
        }
        if (is_string($perms) && trim($perms) !== '') {
            $parts = array_filter(array_map('trim', explode(',', $perms)));
            return array_values(array_unique(array_map('strval', $parts)));
        }


// 2) DB-backed RBAC permissions (if tables exist)
// This enables granular permissions managed from admin/roles and admin/users/user_roles.php.
try {
    $uid = (int)($u['id'] ?? 0);
    if ($uid > 0) {
        // If permissions are already cached in session via previous request, reuse them.
        $cached = $u['rbac_permissions'] ?? null;
        if (is_array($cached) && !empty($cached)) {
            return array_values(array_unique(array_map('strval', $cached)));
        }

        $pdo = \Godyar\DB::pdo();
        $sql = "SELECT DISTINCT p.code
                FROM permissions p
                INNER JOIN role_permissions rp ON rp.permission_id = p.id
                INNER JOIN user_roles ur ON ur.role_id = rp.role_id
                WHERE ur.user_id = :uid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['uid' => $uid]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (is_array($rows) && !empty($rows)) {
            $codes = [];
            foreach ($rows as $c) {
                $c = trim((string)$c);
                if ($c !== '') $codes[] = $c;
            }
            $codes = array_values(array_unique($codes));
            // Cache separately to avoid clobbering legacy session permission formats.
            $_SESSION['user']['rbac_permissions'] = $codes;
            return $codes;
        }
    }
} catch (\Throwable $e) {
    // Fail closed to role defaults below (do not break admin)
}

// 3) Role-based defaults
        $role = (string)($u['role'] ?? '');
        $role = strtolower($role);

        if ($role === 'admin' || $role === 'super_admin') {
            return ['*'];
        }

        if ($role === 'editor') {
            return [
                'posts.*',
                'categories.*',
                'comments.*',
                'tags.*',
                'opinion_authors.*',
            ];
        }

        if ($role === 'writer' || $role === 'author') {
            // Writer/Author: restricted to their own posts via page-level guards.
            return [
                'posts.create',
                'posts.view',
                'posts.edit_own',
                // Many news pages check posts.edit; keep them workable, while
                // edit.php still enforces ownership for writers.
                'posts.edit',
            ];
        }

        return [];
    }

    /**
     * Check if the current user has a given permission.
     * Supports simple wildcards like "posts.*" and "*".
     */
    public static function hasPermission(string $permission): bool
    {
        $permission = trim($permission);
        if ($permission === '') return false;

        $perms = self::permissions();
        if (empty($perms)) return false;

        // global allow
        if (in_array('*', $perms, true)) return true;

        // direct match
        if (in_array($permission, $perms, true)) return true;

        // wildcard match: "section.*"
        foreach ($perms as $p) {
            $p = (string)$p;
            if (substr($p, -2) === '.*') {
                $prefix = substr($p, 0, -1); // keep dot
                if ($prefix !== '' && strpos($permission, $prefix) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Enforce a permission check. If not allowed, return 403 with a friendly message.
     */
    public static function requirePermission(string $permission): void
    {
        if (self::hasPermission($permission)) return;

        http_response_code(403);
        $back = '/admin/index.php';
        $role = (string)((self::user()['role'] ?? '') ?: 'guest');
        echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>صلاحيات غير كافية</title>'
           . '<style>body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,"Cairo",sans-serif;background:#020617;color:#e5e7eb;display:grid;place-items:center;min-height:100vh;padding:24px;box-sizing:border-box} '
           . '.box{max-width:560px;width:100%;background:rgba(15,23,42,.92);border:1px solid rgba(148,163,184,.25);border-radius:18px;padding:18px 18px 16px;box-shadow:0 18px 50px rgba(0,0,0,.45)} '
           . 'h1{margin:0 0 8px;font-size:1.15rem}p{margin:0 0 14px;color:#cbd5e1;line-height:1.6} '
           . 'a{display:inline-block;text-decoration:none;background:linear-gradient(135deg,#22c55e,#0ea5e9);color:#04110a;font-weight:700;padding:10px 14px;border-radius:999px}</style>'
           . '</head><body><div class="box">'
           . '<h1>غير مسموح</h1>'
           . '<p>لا تملك صلاحية (<b>' . htmlspecialchars($permission, ENT_QUOTES, 'UTF-8') . '</b>) لهذا الحساب. الدور الحالي: <b>' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . '</b>.</p>'
           . '<a href="' . htmlspecialchars($back, ENT_QUOTES, 'UTF-8') . '">العودة للوحة التحكم</a>'
           . '</div></body></html>';
        exit;
    }
}