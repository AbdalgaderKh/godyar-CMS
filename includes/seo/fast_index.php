<?php
declare(strict_types=1);

/**
 * Fast indexing helpers:
 * - Ping Google/Bing with sitemap
 * - Submit URLs to IndexNow (Bing, Yandex, etc.)
 *
 * Configure:
 * - settings key: seo.indexnow_key (optional)
 * - key file: /6e896143ae5ccb7b9d7c29790ae431f3.txt (auto added)
 */

if (!function_exists('gdy_indexnow_key')) {
    function gdy_indexnow_key(): string
    {
        // From settings table (preferred)
        $k = '';
        if (isset($GLOBALS['site_settings']) && is_array($GLOBALS['site_settings'])) {
            $k = (string)($GLOBALS['site_settings']['seo.indexnow_key'] ?? '');
        }
        if ($k !== '') return trim($k);

        // Fallback to bundled key
        return '6e896143ae5ccb7b9d7c29790ae431f3';
    }
}

if (!function_exists('gdy_ping_sitemaps')) {
    function gdy_ping_sitemaps(): void
    {
        $base = function_exists('gdy_base_url') ? rtrim(gdy_base_url(), '/') : '';
        if ($base === '') return;

        $sitemap = rawurlencode($base . '/sitemap.xml');

        $urls = [
            'https://www.google.com/ping?sitemap=' . $sitemap,
            'https://www.bing.com/ping?sitemap=' . $sitemap,
        ];

        foreach ($urls as $u) {
            try {
                @file_get_contents($u);
            } catch (Throwable $e) {
                // ignore
            }
        }
    }
}

if (!function_exists('gdy_indexnow_submit')) {
    function gdy_indexnow_submit(array $urlList): bool
    {
        $base = function_exists('gdy_base_url') ? rtrim(gdy_base_url(), '/') : '';
        if ($base === '' || empty($urlList)) return false;

        $origin = function_exists('gdy_base_origin') ? gdy_base_origin() : '';
        $host = parse_url($origin, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? '');

        $key = gdy_indexnow_key();
        $keyLocation = $base . '/' . $key . '.txt';

        $payload = [
            'host' => $host,
            'key'  => $key,
            'keyLocation' => $keyLocation,
            'urlList' => array_values(array_unique($urlList)),
        ];

        $data = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($data === false) return false;

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $data,
                'timeout' => 6,
            ]
        ]);

        try {
            $resp = @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
            // if no error, treat as ok
            return $resp !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('gdy_fast_index_news')) {
    function gdy_fast_index_news(int $newsId, string $slug = ''): void
    {
        $base = function_exists('gdy_base_url') ? rtrim(gdy_base_url(), '/') : '';
        if ($base === '') return;

        // Ping sitemap (helps Google/Bing discover updates quicker)
        gdy_ping_sitemaps();

        // IndexNow (Bing etc.)
        $urls = [];
        if ($slug !== '') {
            $urls[] = $base . '/news/' . rawurlencode($slug);
        }
        $urls[] = $base . '/news/id/' . $newsId;

        // Also submit homepage + sitemap (small boost)
        $urls[] = $base . '/';
        $urls[] = $base . '/sitemap.xml';

        @gdy_indexnow_submit($urls);
    }
}
