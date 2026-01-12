<?php
declare(strict_types=1);

/**
 * Lightweight i18n (AR/EN/FR)
 * ---------------------------------
 * - Default language: Arabic (ar)
 * - Set language via: ?lang=ar|en|fr  (stored in session + cookie)
 * - Fallback: if a key is missing, returns the original string.
 */

if (!function_exists('gdy_supported_langs')) {
    function gdy_supported_langs(): array
    {
        $envList = function_exists('env') ? (string)env('SUPPORTED_LANGS', 'ar,en,fr') : 'ar,en,fr';
        $langs = array_values(array_filter(array_map('trim', explode(',', $envList))));
        if (!$langs) $langs = ['ar', 'en', 'fr'];
        // Ensure Arabic is always available as fallback
        if (!in_array('ar', $langs, true)) array_unshift($langs, 'ar');
        return array_values(array_unique($langs));
    }
}

if (!function_exists('gdy_lang')) {
    function gdy_lang(): string
    {
        if (defined('GDY_LANG') && is_string(GDY_LANG) && GDY_LANG !== '') {
            return GDY_LANG;
        }

        $supported = gdy_supported_langs();

        $lang = '';
        if (isset($_GET['lang'])) {
            $lang = strtolower(trim((string)$_GET['lang']));
        }

        if ($lang !== '' && in_array($lang, $supported, true)) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION['lang'] = $lang;
            if (!headers_sent()) {
                @setcookie('lang', $lang, [
                    'expires'  => time() + 60 * 60 * 24 * 30,
                    'path'     => '/',
                    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
            }
        } else {
            if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['lang'])) {
                $lang = strtolower((string)$_SESSION['lang']);
            } elseif (!empty($_COOKIE['lang'])) {
                $lang = strtolower((string)$_COOKIE['lang']);
            }
        }

        if ($lang === '' || !in_array($lang, $supported, true)) {
            $lang = 'ar';
        }

        define('GDY_LANG', $lang);
        return $lang;
    }
}

if (!function_exists('gdy_dir')) {
    function gdy_dir(?string $lang = null): string
    {
        $lang = $lang ?: gdy_lang();
        return $lang === 'ar' ? 'rtl' : 'ltr';
    }
}

if (!function_exists('gdy_is_rtl')) {
    function gdy_is_rtl(?string $lang = null): bool
    {
        return gdy_dir($lang) === 'rtl';
    }
}

if (!function_exists('gdy_i18n_load')) {
    function gdy_i18n_load(string $lang): array
    {
                $lang = strtolower(trim($lang));
        $base = ROOT_PATH . '/languages/' . $lang . '.php';
        if (!is_file($base)) {
            return [];
        }
        $data = require $base;
        $data = is_array($data) ? $data : [];

        // Optional additive patch file: /languages/{lang}_patch.php
        $patch = ROOT_PATH . '/languages/' . $lang . '_patch.php';
        if (is_file($patch)) {
            $tmp = require $patch;
            if (is_array($tmp)) {
                // patch overrides base
                $data = array_merge($data, $tmp);
            }
        }

        return $data;
    }
}

if (!function_exists('__')) {
    /**
     * Translate a string.
     * Usage: __("الرئيسية") or __("home")
     */
    function __(string $key, array $vars = []): string
    {
        $lang = gdy_lang();
        static $cache = [];
        if (!isset($cache[$lang])) {
            $cache[$lang] = gdy_i18n_load($lang);
        }
        $dict = $cache[$lang] ?? [];
        $out = $dict[$key] ?? $key;

        if ($vars) {
            foreach ($vars as $k => $v) {
                $out = str_replace('{' . $k . '}', (string)$v, $out);
            }
        }
        return $out;
    }
}

if (!function_exists('__e')) {
    function __e(string $key, array $vars = []): void
    {
        echo __($key, $vars);
    }
}

if (!function_exists('gdy_url_with_lang')) {
    function gdy_url_with_lang(string $lang, ?string $uri = null): string
    {
        $lang = strtolower(trim($lang));
        $uri = $uri ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['lang'] = $lang;
        $qs = http_build_query($query);
        return $path . ($qs ? ('?' . $qs) : '');
    }
}

if (!function_exists('gdy_i18n_boot')) {
    function gdy_i18n_boot(): void
    {
        // Trigger language resolution early
        gdy_lang();
    }
}
