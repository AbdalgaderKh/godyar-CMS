<?php
// /includes/lang.php
// i18n helper (Frontend + Admin) - Backward compatible + safe defaults

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * Supported languages
 */
$GLOBALS['SUPPORTED_LANGS'] = isset($GLOBALS['SUPPORTED_LANGS']) && is_array($GLOBALS['SUPPORTED_LANGS'])
    ? $GLOBALS['SUPPORTED_LANGS']
    : ['ar', 'en', 'fr'];

/**
 * Detect language by priority:
 * ?lang -> session -> cookie -> default ar
 */
function detect_lang()
{
    $supported = isset($GLOBALS['SUPPORTED_LANGS']) && is_array($GLOBALS['SUPPORTED_LANGS']) ? $GLOBALS['SUPPORTED_LANGS'] : ['ar'];

    $lang = null;
    if (isset($_GET['lang'])) {
        $lang = strtolower(trim((string)$_GET['lang']));
    } elseif (isset($_SESSION['lang'])) {
        $lang = strtolower(trim((string)$_SESSION['lang']));
    } elseif (isset($_COOKIE['lang'])) {
        $lang = strtolower(trim((string)$_COOKIE['lang']));
    } elseif (isset($_COOKIE['gdy_lang'])) {
        $lang = strtolower(trim((string)$_COOKIE['gdy_lang']));
    }

    if (!$lang || !in_array($lang, $supported, true)) {
        $lang = 'ar';
    }

    $_SESSION['lang'] = $lang;
    @setcookie('lang', $lang, time() + (90 * 24 * 60 * 60), '/', '', false, true);
    // keep admin in sync
    @setcookie('gdy_lang', $lang, time() + (90 * 24 * 60 * 60), '/', '', false, true);
    return $lang;
}

/**
 * Public helper used by templates
 */
function gdy_lang()
{
    if (!isset($GLOBALS['lang']) || !$GLOBALS['lang']) {
        $GLOBALS['lang'] = detect_lang();
    }
    return $GLOBALS['lang'];
}

/**
 * Set language explicitly (optional helper)
 */
function gdy_set_lang($lang)
{
    $lang = strtolower(trim((string)$lang));
    $supported = isset($GLOBALS['SUPPORTED_LANGS']) && is_array($GLOBALS['SUPPORTED_LANGS']) ? $GLOBALS['SUPPORTED_LANGS'] : ['ar'];
    if (!in_array($lang, $supported, true)) {
        $lang = 'ar';
    }
    $GLOBALS['lang'] = $lang;
    $_SESSION['lang'] = $lang;
    @setcookie('lang', $lang, time() + (90 * 24 * 60 * 60), '/', '', false, true);
    // keep admin in sync
    @setcookie('gdy_lang', $lang, time() + (90 * 24 * 60 * 60), '/', '', false, true);
    return $lang;
}

/**
 * RTL?
 */
function is_rtl($lang = null)
{
    $lang = $lang ?: (isset($GLOBALS['lang']) ? $GLOBALS['lang'] : 'ar');
    return $lang === 'ar';
}

/**
 * Backward-compat aliases
 */
function gdy_is_rtl($lang = null) { return is_rtl($lang); }

/**
 * Build a language switch URL.
 *
 * الهدف (حسب طلب العميل):
 *  - روابط عامة: /en , /fr , /ar (بدون ?lang=)
 *  - داخل لوحة التحكم /admin نستمر باستخدام ?lang= حتى لا نكسر المسارات
 *
 * ملاحظة:
 *  - هذه الدالة كانت سابقاً تبني ?lang= فقط.
 *  - تم جعلها متوافقة مع الاستدعاء المنتشر حالياً: gdy_lang_url('en')
 */
function gdy_lang_url($targetLang)
{
    $supported = isset($GLOBALS['SUPPORTED_LANGS']) && is_array($GLOBALS['SUPPORTED_LANGS'])
        ? $GLOBALS['SUPPORTED_LANGS']
        : ['ar', 'en', 'fr'];

    $lang = strtolower(trim((string)$targetLang));
    if ($lang === '' || !in_array($lang, $supported, true)) {
        $lang = 'ar';
    }

    // Determine base path (support subfolder install)
    $basePath = '';
    if (defined('BASE_URL')) {
        $p = parse_url((string)BASE_URL, PHP_URL_PATH);
        if (is_string($p)) {
            $basePath = rtrim($p, '/');
            if ($basePath === '/') $basePath = '';
        }
    }

    // Use the original URI (with prefix) if available
    $uri = (string)($_SERVER['GDY_ORIGINAL_REQUEST_URI'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
    $parts = @parse_url($uri) ?: [];
    $path = (string)($parts['path'] ?? '/');
    $queryStr = (string)($parts['query'] ?? '');

    // Strip basePath (support subfolder install)
    $pathNoBase = $path;
    if ($basePath !== '' && str_starts_with($pathNoBase, $basePath . '/')) {
        $pathNoBase = substr($pathNoBase, strlen($basePath));
    } elseif ($basePath !== '' && $pathNoBase === $basePath) {
        $pathNoBase = '/';
    }
    if (!str_starts_with($pathNoBase, '/')) $pathNoBase = '/' . $pathNoBase;

    // If we are inside admin, keep query-based switch (avoid /en/admin 404)
    $isAdmin = (str_starts_with($pathNoBase, '/admin') || str_starts_with($pathNoBase, '/v16/admin'));
    if ($isAdmin) {
        $q = [];
        if ($queryStr !== '') {
            parse_str($queryStr, $q);
        }
        $q['lang'] = $lang;
        $qs = http_build_query($q);
        $out = $basePath . $pathNoBase . ($qs ? ('?' . $qs) : '');
        return $out !== '' ? $out : '/';
    }

    // Public: return clean base language URLs as requested (home in that language)
    $out = $basePath . '/' . $lang;
    return $out !== '' ? $out : '/';
}

/**
 * Load translations array from /languages/{lang}.php
 * Also merges optional /languages/{lang}_patch.php (safe additive updates)
 */
function load_translations($lang)
{
    $baseDir = dirname(__DIR__); // /public_html
    $dir = $baseDir . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR;

    $file = $dir . $lang . '.php';
    $patch = $dir . $lang . '_patch.php';

    $data = [];
    if (is_file($file)) {
        $tmp = include $file;
        if (is_array($tmp)) $data = $tmp;
    }

    if (is_file($patch)) {
        $tmp2 = include $patch;
        if (is_array($tmp2)) {
            // patch overrides base
            $data = array_merge($data, $tmp2);
        }
    }

    return is_array($data) ? $data : [];
}

/**
 * Ensure language + translation map loaded
 */
function ensure_i18n_loaded()
{
    if (!isset($GLOBALS['lang']) || !$GLOBALS['lang']) {
        $GLOBALS['lang'] = detect_lang();
    }
    if (!isset($GLOBALS['translations']) || !is_array($GLOBALS['translations'])) {
        $GLOBALS['translations'] = [];
    }

    $lang = $GLOBALS['lang'];
    if (!isset($GLOBALS['translations'][$lang]) || !is_array($GLOBALS['translations'][$lang])) {
        $GLOBALS['translations'][$lang] = load_translations($lang);
    }
}

/**
 * Translate helper
 *
 * Supports:
 *  __('key')
 *  __('key', ['name'=>'Ali'])
 *  __('t_xxx', 'fallback arabic')
 *  __('t_xxx', 'fallback', ['name'=>'Ali'])
 */
function __($key, $vars = [], $fallback = null)
{
    ensure_i18n_loaded();

    // if second arg is string => fallback
    if (is_string($vars)) {
        $fallback = $vars;
        $vars = [];
    }

    // if third arg is array and vars empty => treat as vars
    if (is_array($fallback) && empty($vars)) {
        $vars = $fallback;
        $fallback = null;
    }

    if (!is_array($vars)) $vars = [];

    $lang = $GLOBALS['lang'];
    $map  = isset($GLOBALS['translations'][$lang]) && is_array($GLOBALS['translations'][$lang])
        ? $GLOBALS['translations'][$lang]
        : [];

    $text = isset($map[$key]) ? $map[$key] : (($fallback !== null) ? $fallback : $key);

    // Replace placeholders {var}
    if (!empty($vars) && is_string($text)) {
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', (string)$v, $text);
        }
    }
    return $text;
}

/**
 * Escape helper (safe default value to prevent fatal errors)
 */
if (!function_exists('h')) {
    function h($value = '')
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Convenience
 */
function current_lang()
{
    return gdy_lang();
}
