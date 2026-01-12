<?php
declare(strict_types=1);
/**
 * admin/i18n.php
 * Lightweight i18n for Admin (AR/EN/FR)
 * - Supports __($key, $fallbackArabic = null)
 * - Auto-translate (optional) for fallbackArabic via OpenAI, cached to JSON file.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/** Determine base path */
$__ADMIN_DIR = __DIR__;
$__LANG_DIR  = $__ADMIN_DIR . '/lang';
$__CACHE_DIR = $__ADMIN_DIR . '/cache';
if (!is_dir($__CACHE_DIR)) {
    @mkdir($__CACHE_DIR, 0775, true);
}

if (!function_exists('gdy_current_lang')) {
    function gdy_current_lang(): string {
        $lang = (string)($_SESSION['lang'] ?? $_COOKIE['gdy_lang'] ?? $_COOKIE['lang'] ?? 'ar');
        $lang = strtolower($lang);
        if (!in_array($lang, ['ar','en','fr'], true)) $lang = 'ar';
        return $lang;
    }
}

if (!function_exists('gdy_set_lang')) {
    function gdy_set_lang(string $lang): void {
        $lang = strtolower(trim($lang));
        if (!in_array($lang, ['ar','en','fr'], true)) $lang = 'ar';
        $_SESSION['lang'] = $lang;
        // cookie 30 days
        @setcookie('gdy_lang', $lang, time() + 60*60*24*30, '/');
        @setcookie('lang', $lang, time() + 60*60*24*30, '/', '', false, true);
    }
}

// Allow switching with ?lang=
if (isset($_GET['lang'])) {
    gdy_set_lang((string)$_GET['lang']);
}

$GLOBALS['GDY_LANG'] = gdy_current_lang();

/** Load dictionaries */
$GLOBALS['GDY_DICTS'] = $GLOBALS['GDY_DICTS'] ?? [];
if (empty($GLOBALS['GDY_DICTS'])) {
    foreach (['ar','en','fr'] as $__l) {
        $__file = $__LANG_DIR . '/' . $__l . '.php';
        if (is_file($__file)) {
            try {
                $arr = require $__file;
                if (is_array($arr)) {
                    $GLOBALS['GDY_DICTS'][$__l] = $arr;
                }
            } catch (Throwable $e) {
                @error_log('[Admin i18n] Failed loading ' . $__file . ': ' . $e->getMessage());
            }
        } else {
            $GLOBALS['GDY_DICTS'][$__l] = [];
        }
    }
}

/** Auto-translation cache */
$__UI_CACHE_FILE = $__CACHE_DIR . '/ui_translations.json';
if (!isset($GLOBALS['GDY_UI_CACHE'])) {
    $GLOBALS['GDY_UI_CACHE'] = [];
    if (is_file($__UI_CACHE_FILE)) {
        try {
            $raw = @file_get_contents($__UI_CACHE_FILE);
            $j = json_decode((string)$raw, true);
            if (is_array($j)) $GLOBALS['GDY_UI_CACHE'] = $j;
        } catch (Throwable $e) {
            // ignore
        }
    }
}

if (!function_exists('gdy_is_rtl')) {
    function gdy_is_rtl(): bool {
        return gdy_current_lang() === 'ar';
    }
}

if (!function_exists('__')) {
    /**
     * Translate key.
     * - If $fallback is provided, it is used when translation is missing.
     * - If translation missing and AUTO UI translation enabled, will attempt OpenAI translate once & cache.
     */
    function __(string $key, ?string $fallback = null): string {
        $lang = gdy_current_lang();
        $dicts = $GLOBALS['GDY_DICTS'] ?? [];
        $fallback = $fallback ?? $key;

        // First: dictionary
        if (isset($dicts[$lang]) && array_key_exists($key, $dicts[$lang])) {
            return (string)$dicts[$lang][$key];
        }

        // Arabic: return fallback directly
        if ($lang === 'ar') {
            // If ar dict has it, ok; else fallback
            if (isset($dicts['ar']) && array_key_exists($key, $dicts['ar'])) {
                return (string)$dicts['ar'][$key];
            }
            return $fallback;
        }

        // If we have cached translation by key
        $cache = $GLOBALS['GDY_UI_CACHE'] ?? [];
        if (isset($cache[$key]) && is_array($cache[$key]) && isset($cache[$key][$lang])) {
            $v = (string)$cache[$key][$lang];
            if ($v !== '') return $v;
        }

        // Optional: auto translate when fallback seems Arabic
        $auto = getenv('UI_AUTO_TRANSLATE');
        $auto = $auto === false ? '1' : (string)$auto; // default ON
        if ($auto !== '1') return $fallback;

        // Only auto translate when fallback contains Arabic letters (avoid translating keys)
        if (!preg_match('/[\x{0600}-\x{06FF}]/u', (string)$fallback)) {
            return $fallback;
        }

        $translated = gdy_openai_translate_ui((string)$fallback, $lang);
        if ($translated === '') return $fallback;

        // store cache
        $cache[$key] = $cache[$key] ?? [];
        $cache[$key]['ar'] = $cache[$key]['ar'] ?? $fallback;
        $cache[$key][$lang] = $translated;
        $GLOBALS['GDY_UI_CACHE'] = $cache;

        // try write (best effort)
        try {
            $fp = @fopen($__UI_CACHE_FILE = (__DIR__ . '/cache/ui_translations.json'), 'c+');
            if ($fp) {
                @flock($fp, LOCK_EX);
                @ftruncate($fp, 0);
                @rewind($fp);
                @fwrite($fp, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                @fflush($fp);
                @flock($fp, LOCK_UN);
                @fclose($fp);
            }
        } catch (Throwable $e) {
            // ignore
        }

        return $translated;
    }
}

if (!function_exists('gdy_openai_translate_ui')) {
    function gdy_openai_translate_ui(string $arabicText, string $targetLang): string {
        $apiKey = getenv('OPENAI_API_KEY');
        if (!$apiKey) return '';

        $model = getenv('OPENAI_MODEL_UI');
        if (!$model) $model = getenv('OPENAI_MODEL');
        if (!$model) $model = 'gpt-4o-mini';

        $target = $targetLang === 'fr' ? 'French' : 'English';

        $prompt = "Translate the following Arabic UI text to {$target}. "
                . "Return only the translation, no quotes, no extra text.\n\n"
                . $arabicText;

        $payload = [
            'model' => $model,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt]
                    ]
                ]
            ],
            'max_output_tokens' => 120,
        ];

        if (!function_exists('curl_init')) return '';

        $ch = curl_init('https://api.openai.com/v1/responses');
        if (!$ch) return '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 20,
        ]);

        $res = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($res === false || $http < 200 || $http >= 300) {
            @error_log('[Admin i18n] OpenAI translate failed HTTP ' . $http . ' err=' . $err);
            return '';
        }

        $j = json_decode((string)$res, true);
        if (!is_array($j)) return '';

        // Responses API: output_text convenience may exist; else parse output
        if (isset($j['output_text']) && is_string($j['output_text'])) {
            return trim($j['output_text']);
        }

        // Fallback parse
        $text = '';
        if (isset($j['output']) && is_array($j['output'])) {
            foreach ($j['output'] as $out) {
                if (!isset($out['content']) || !is_array($out['content'])) continue;
                foreach ($out['content'] as $c) {
                    if (($c['type'] ?? '') === 'output_text' && isset($c['text'])) {
                        $text .= (string)$c['text'];
                    } elseif (($c['type'] ?? '') === 'text' && isset($c['text'])) {
                        $text .= (string)$c['text'];
                    }
                }
            }
        }
        return trim($text);
    }
}
