<?php
declare(strict_types=1);

/**
 * Godyar - Production Bootstrap (Final & Stable)
 * ----------------------------------------------
 * - strict_types صحيح
 * - إعدادات إنتاج آمنة
 * - اتصال قاعدة البيانات
 * - تتبع الزيارات
 * - أدوات Schema
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
// Register internal autoloader (and composer autoload if present)
$autoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// تحميل ENV/.env (مصدر واحد للإعدادات)
require_once ROOT_PATH . '/includes/env.php';

// DB helpers (PDO source of truth)
require_once ROOT_PATH . '/includes/db.php';

/* =========================
   PHP 7.4 compatibility polyfills
   ========================= */
// بعض الاستضافات ما زالت على PHP 7.4 — نضيف Polyfills لدوال PHP 8
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

/* =========================
   Error reporting (PRODUCTION)
   ========================= */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

$logDir  = ROOT_PATH . '/storage/logs';
$logFile = $logDir . '/php-error.log';

if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logFile);

/* =========================
   Session (secure)
   ========================= */
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    @session_start();
}

/* =========================
   PDO connection (single source of truth: Godyar\DB)
   ========================= */
// Guard: PDO MySQL driver must be enabled on the server (pdo_mysql)
if (!class_exists('PDO') || !extension_loaded('pdo_mysql')) {
    @error_log('[Bootstrap] Missing PHP extension: pdo_mysql (PDO MySQL driver).');
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="font-family:Tahoma,Arial;max-width:720px;margin:40px auto;line-height:1.8">';
    echo '<h2>خطأ في إعدادات PHP</h2>';
    echo '<p>يلزم تفعيل امتداد <b>pdo_mysql</b> على الاستضافة حتى يعمل النظام.</p>';
    echo '<p>من لوحة الاستضافة (cPanel): <b>Select PHP Version</b> → <b>Extensions</b> → فعّل: <b>pdo</b> و <b>pdo_mysql</b> (ويُفضّل أيضًا <b>mysqli</b>).</p>';
    echo '<p>بعد التفعيل أعد تحميل الصفحة.</p>';
    echo '</div>';
    exit;
}

try {
    $pdo = \Godyar\DB::pdo();
} catch (Throwable $e) {
    http_response_code(500);
    exit('فشل الاتصال بقاعدة البيانات.');
}

// Legacy compatibility: only if LEGACY_GLOBAL_PDO=1
gdy_register_global_pdo();

/* =========================
   DI Container + legacy DB adapter
   ========================= */
try {
    if (class_exists('Godyar\\Container')) {
        $container = new \Godyar\Container($pdo);
        $GLOBALS['container'] = $container;
    }

    // Legacy $database adapter (used by older classes in includes/classes/*.php)
    if (env('LEGACY_DATABASE_ADAPTER', '1') !== '0' && class_exists('Godyar\\Legacy\\DatabaseAdapter')) {
        $database = new \Godyar\Legacy\DatabaseAdapter($pdo);
        $GLOBALS['database'] = $database;
    }
} catch (Throwable $e) {
    @error_log('[Bootstrap] container/legacy adapter init: ' . $e->getMessage());
}

/* =========================
   DB helpers
   ========================= */
function db_table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = :t
        LIMIT 1
    ");
    $st->execute([':t'=>$table]);
    return (bool)$st->fetchColumn();
}

/* =========================
   Visits table (auto create)
   ========================= */
if (!db_table_exists($pdo, 'visits')) {
    $pdo->exec("
        CREATE TABLE visits (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          page VARCHAR(60) NOT NULL,
          news_id INT NULL,
          source VARCHAR(20) NOT NULL DEFAULT 'direct',
          referrer VARCHAR(255) NULL,
          user_ip VARCHAR(45) NULL,
          user_agent VARCHAR(255) NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_created (created_at),
          KEY idx_news (news_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/* =========================
   Visits analytics
   ========================= */
function gdy_classify_source(string $ref): string {
    if ($ref === '') return 'direct';
    $h = strtolower(parse_url($ref, PHP_URL_HOST) ?? '');
    if ($h === '') return 'referral';

    foreach (['google','bing','yahoo','duckduckgo'] as $s)
        if (str_contains($h, $s)) return 'search';

    foreach (['facebook','twitter','x.com','instagram','t.me','telegram','tiktok'] as $s)
        if (str_contains($h, $s)) return 'social';

    return 'referral';
}

function gdy_track_visit(string $page, ?int $newsId=null): void {
    $pdo = gdy_pdo_safe();
    if (!$pdo instanceof PDO) return;

    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

    $key = 'visit_'.$page.'_'.($newsId ?? 0);
    if (isset($_SESSION[$key]) && time() - $_SESSION[$key] < 600) return;
    $_SESSION[$key] = time();

    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $pdo->prepare("
        INSERT INTO visits (page,news_id,source,referrer,user_ip,user_agent)
        VALUES (?,?,?,?,?,?)
    ")->execute([
        $page,
        $newsId,
        gdy_classify_source($ref),
        $ref,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,250)
    ]);
}

/* =========================
   Base URL
   ========================= */
function base_url(string $path = ''): string {
    $base = '';
    if (defined('APP_URL') && APP_URL) {
        $base = rtrim(APP_URL, '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        if ($host) $base = $scheme . '://' . $host;
    }
    if ($base === '') return '';
    if ($path === '') return $base;
    return $base . '/' . ltrim($path, '/');
}

// Backward-compatibility: some legacy files still rely on BASE_URL.
// We keep it defined (with a trailing slash) so concatenation like BASE_URL . 'admin/' works.
if (!defined('BASE_URL')) {
    $b = base_url();
    if ($b !== '') {
        define('BASE_URL', rtrim($b, '/') . '/');
    } elseif (defined('APP_URL_AUTO') && APP_URL_AUTO) {
        define('BASE_URL', rtrim((string)APP_URL_AUTO, '/') . '/');
    } else {
        // Last-resort fallback (relative root)
        define('BASE_URL', '/');
    }
}

/* =========================
   Security headers
   ========================= */
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
}


date_default_timezone_set((defined('TIMEZONE') && TIMEZONE) ? TIMEZONE : 'Asia/Riyadh');