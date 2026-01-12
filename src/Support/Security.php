<?php
namespace App\Support;

class Security
{
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function checkCsrf(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function headers(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');

        $strict = getenv('CSP_STRICT') === '1' || strtolower((string)getenv('CSP_STRICT')) === 'true';

        if ($strict) {
            // ✅ CSP بدون unsafe-inline (يحتاج إضافة nonce في أي <script>/<style> inline)
            $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION['csp_nonce'] = $nonce;

            $csp = "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; " .
                   "img-src 'self' data: blob: https:; font-src 'self' data: https:; " .
                   "style-src 'self' 'nonce-{$nonce}'; script-src 'self' 'nonce-{$nonce}';";
            header('Content-Security-Policy: ' . $csp);
        } else {
            // Legacy (متوافق مع القوالب الحالية)
            header("Content-Security-Policy: default-src 'self' data: blob:; img-src 'self' data: blob: https:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
        }
    }
