<?php
// /includes/lang_prefix.php
// دعم بادئة اللغة في الرابط: /en , /fr , /ar
// الهدف:
//  - جعل الروابط نظيفة: /en, /fr, /ar بدل ?lang=
//  - دعم الروابط القديمة ?lang= مع تحويلها تلقائياً (301)
//  - إزالة البادئة داخلياً حتى لا تتأثر الراوترات الحالية

if (!defined('GDY_LANG_PREFIX_BOOTSTRAPPED')) {
    define('GDY_LANG_PREFIX_BOOTSTRAPPED', true);

    $supported = $GLOBALS['SUPPORTED_LANGS'] ?? ['ar', 'en', 'fr'];
    if (!is_array($supported) || empty($supported)) {
        $supported = ['ar', 'en', 'fr'];
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if (!isset($_SERVER['GDY_ORIGINAL_REQUEST_URI'])) {
        // نحتفظ بالطلب الأصلي لاستخدامه في بناء الروابط لاحقاً (مثل dropdown اللغة)
        $_SERVER['GDY_ORIGINAL_REQUEST_URI'] = $uri;
    }

    $path  = parse_url($uri, PHP_URL_PATH);
    $query = parse_url($uri, PHP_URL_QUERY);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }

    // Helper: redirect (only for GET/HEAD)
    $canRedirect = in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['GET', 'HEAD'], true);

    // 1) لو الرابط بدون بادئة لكن فيه ?lang=ar|en|fr → حوّله إلى /{lang}{path}
    //    (باستثناء لوحة التحكم /admin لأنها لا تعمل مع بادئة اللغة)
    $qLang = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : '';
    $isAdminPath = (str_starts_with($path, '/admin') || str_starts_with($path, '/v16/admin'));
    if ($canRedirect && !$isAdminPath && $qLang !== '' && in_array($qLang, $supported, true) && !preg_match('#^/(ar|en|fr)(/|$)#', $path)) {
        // ابني Query بدون lang
        $params = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
        }
        unset($params['lang']);
        $qs = http_build_query($params);

        $target = '/' . $qLang . ($path === '/' ? '' : $path);
        if ($qs) {
            $target .= '?' . $qs;
        }

        header('Location: ' . $target, true, 301);
        exit;
    }

    // 2) لو الرابط فيه بادئة لغة (/en/...) – نجعل البادئة هي المصدر الوحيد للغة
    if (preg_match('#^/(ar|en|fr)(/.*)?$#', $path, $m)) {
        $prefixLang = $m[1];

        // إذا كان هناك ?lang= (حتى لو صحيح) نحذفه ونحوّل للرابط النظيف
        if ($canRedirect && isset($_GET['lang'])) {
            $params = [];
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
            }
            unset($params['lang']);
            $qs = http_build_query($params);

            $cleanPath = '/' . $prefixLang . (($m[2] ?? '/') === '/' ? '' : (string)$m[2]);
            if ($cleanPath === '') {
                $cleanPath = '/' . $prefixLang;
            }
            if ($qs) {
                $cleanPath .= '?' . $qs;
            }

            header('Location: ' . $cleanPath, true, 301);
            exit;
        }

        // نثبت اللغة من البادئة
        $_GET['lang'] = $prefixLang;

        // إزالة البادئة من المسار داخلياً (لأن الراوتر الحالي لا يدعم /en/...)
        $newPath = $m[2] ?? '/';
        if (!is_string($newPath) || $newPath === '') {
            $newPath = '/';
        }

        // لا نضيف query هنا؛ لأن الراوتر الحالي يتعامل مع $_GET مباشرة
        $_SERVER['REQUEST_URI'] = $newPath . ($query ? ('?' . $query) : '');
    }
}
