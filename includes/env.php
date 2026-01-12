<?php
/**
 * includes/env.php — robust .env loader for shared hosting
 * UTF-8 (no BOM), no closing tag.
 */

declare(strict_types=1);

/* ---------- ABSPATH ---------- */
if (!defined('ABSPATH')) {
    define('ABSPATH', str_replace('\\', '/', dirname(__DIR__))); // /public_html
}

/* ---------- Helpers ---------- */
if (!function_exists('gody_env_bool')) {
    function gody_env_bool($v): bool {
        if (is_bool($v)) return $v;
        $s = strtolower(trim((string)$v));
        return in_array($s, ['1', 'true', 'on', 'yes', 'y'], true);
    }
}

if (!function_exists('gody_unquote')) {
    function gody_unquote(string $v): string {
        $v = trim($v);
        if ($v === '') return $v;
        $q = $v[0];
        if (($q === '"' || $q === "'") && substr($v, -1) === $q) {
            $v = substr($v, 1, -1);
        }
        return $v;
    }
}

if (!function_exists('gody_parse_env_file')) {
    /**
     * Safe, tiny .env parser (key=value), supports comments (# …) and quoted values.
     */
    function gody_parse_env_file(string $path): array {
        if (!is_file($path)) return [];
        $out = [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;

            // Split on first '=' only
            $pos = strpos($line, '=');
            if ($pos === false) continue;

            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            // Remove inline comments unless quoted
            if ($val !== '' && $val[0] !== '"' && $val[0] !== "'") {
                $hash = strpos($val, '#');
                if ($hash !== false) $val = trim(substr($val, 0, $hash));
            }

            $val = gody_unquote($val);
            if ($key !== '') $out[$key] = $val;
        }
        return $out;
    }
}

if (!function_exists('env')) {
    /**
     * Read env var from (order): $_SERVER/$_ENV/getenv/.env array → default
     */
    function env(string $key, $default = null) {
        if (array_key_exists($key, $_SERVER)) return $_SERVER[$key];
        if (array_key_exists($key, $_ENV))    return $_ENV[$key];

        $g = getenv($key);
        if ($g !== false) return $g;

        global $GODYAR_ENV_ARR;
        if (is_array($GODYAR_ENV_ARR) && array_key_exists($key, $GODYAR_ENV_ARR)) {
            return $GODYAR_ENV_ARR[$key];
        }
        return $default;
    }
}

/* ---------- Load .env (shared hosting friendly) ---------- */
$GODYAR_ENV_ARR = [];

// Try ABSPATH/.env, then ABSPATH/../.env (in case project in /public_html/godyar/)
$envCandidates = [
    ABSPATH . '/.env',
    dirname(ABSPATH) . '/.env',
];

foreach ($envCandidates as $f) {
    if (is_file($f)) {
        $GODYAR_ENV_ARR = gody_parse_env_file($f);
        break;
    }
}

/* ---------- Defaults map (NO secrets here) ---------- */
$defaults = [
    'APP_ENV'        => 'production',
    'APP_DEBUG'      => 'false',
    'APP_URL'        => '',
'DB_DRIVER'     => 'mysql',
    'DB_HOST'       => 'localhost',
    'DB_PORT'       => '3306',
    'DB_DATABASE'   => '',
    'DB_USERNAME'   => '',
    'DB_PASSWORD'   => '',
    'DB_CHARSET'    => 'utf8mb4',
    'DB_COLLATION'   => 'utf8mb4_unicode_ci',
    'DB_DSN'         => '',          // optional: override full DSN

    'TIMEZONE'       => 'Asia/Riyadh',
    'ENCRYPTION_KEY' => '',
];

/* ---------- DB naming compatibility (DB_NAME/DB_USER/DB_PASS) ---------- */
if (!function_exists("gody_env_db")) {
    function gody_env_db(string $primary, string $alt, $default = ''): string {
        $v = env($primary, null);
        if ($v !== null && $v !== '') return (string)$v;
        $v2 = env($alt, null);
        if ($v2 !== null && $v2 !== '') return (string)$v2;
        return (string)$default;
    }
}

/* ---------- Define constants if not defined ---------- */
foreach ($defaults as $key => $def) {
    if (!defined($key)) {
        if ($key === 'DB_DATABASE') {
            $val = gody_env_db('DB_DATABASE', 'DB_NAME', $def);
        } elseif ($key === 'DB_USERNAME') {
            $val = gody_env_db('DB_USERNAME', 'DB_USER', $def);
        } elseif ($key === 'DB_PASSWORD') {
            $val = gody_env_db('DB_PASSWORD', 'DB_PASS', $def);
        } else {
            $val = env($key, $def);
        }
        if ($key === 'APP_DEBUG') {
            define($key, gody_env_bool($val));
        } else {
            define($key, (string)$val);
        }
    }
}

/* ---------- Build DB_DSN if empty ---------- */
if (!defined('DB_DSN') || DB_DSN === '') {
    $drv = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
    $dsn = '';

    if ($drv === 'mysql') {
        $charset = DB_CHARSET !== '' ? DB_CHARSET : 'utf8mb4';
        $host    = DB_HOST !== '' ? DB_HOST : 'localhost';
        $port    = DB_PORT !== '' ? DB_PORT : '3306';
        $name    = DB_DATABASE;
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    }

    if (!defined('DB_DSN')) define('DB_DSN', $dsn);
}

/* ---------- Aliases (preferred naming) ---------- */
if (!defined('DB_NAME')) define('DB_NAME', defined('DB_DATABASE') ? DB_DATABASE : (string)env('DB_NAME', 'godyar'));
if (!defined('DB_USER')) define('DB_USER', defined('DB_USERNAME') ? DB_USERNAME : (string)env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', defined('DB_PASSWORD') ? DB_PASSWORD : (string)env('DB_PASS', ''));

/* ---------- Timezone ---------- */
if (defined('TIMEZONE') && TIMEZONE) {
    @date_default_timezone_set(TIMEZONE);
}

/* ---------- Error reporting per APP_DEBUG ---------- */
if (defined('APP_DEBUG') && APP_DEBUG) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

/* ---------- Optional: expose pdo factory ---------- */
if (!function_exists('gody_pdo')) {
    function gody_pdo(): ?PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        if (!defined('DB_DSN') || DB_DSN === '') return null;

        try {
            $pdo = new PDO(
                DB_DSN,
                DB_USERNAME,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // For MySQL only:
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . (DB_CHARSET ?: 'utf8mb4') .
                        " COLLATE " . (DB_COLLATION ?: 'utf8mb4_unicode_ci'),
                ]
            );
            return $pdo;
        } catch (Throwable $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[PDO] Connection failed: " . $e->getMessage());
            }
            return null;
        }
    }
}

/* ---------- Convenience: APP_URL auto-detect (if empty) ---------- */
if (defined('APP_URL') && APP_URL === '' && !headers_sent()) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    if ($host) {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $base = (strpos($uri, '/godyar/') !== false) ? '/godyar' : '';
        $auto = $scheme . '://' . $host . $base;
        if (!defined('APP_URL_AUTO')) define('APP_URL_AUTO', $auto);
    }
}
