<?php
declare(strict_types=1);

/**
 * includes/seo/seo_tools.php
 * SEO helpers: ping sitemap + submit IndexNow (Bing/Yandex-like ecosystem).
 * Note: Google does not support IndexNow.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/indexnow_key.php';

if (!function_exists('gdy_http_get')) {
    function gdy_http_get(string $url, int $timeoutSeconds = 5): array
    {
        $url = trim($url);
        if ($url === '') return ['ok' => false, 'code' => 0, 'body' => '', 'error' => 'empty_url'];

        // Prefer curl if available
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'GodyarCMS/SEO (+sitemap)',
            ]);
            $body = (string)curl_exec($ch);
            $err  = (string)curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return ['ok' => $err === '', 'code' => $code, 'body' => $body, 'error' => $err];
        }

        // Fallback
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'header' => "User-Agent: GodyarCMS/SEO (+sitemap)\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        $ok = $body !== false;
        return ['ok' => $ok, 'code' => $ok ? 200 : 0, 'body' => $ok ? (string)$body : '', 'error' => $ok ? '' : 'file_get_contents_failed'];
    }
}

if (!function_exists('gdy_http_post_json')) {
    function gdy_http_post_json(string $url, array $payload, int $timeoutSeconds = 8): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return ['ok' => false, 'code' => 0, 'body' => '', 'error' => 'json_encode_failed'];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeoutSeconds,
            ]);
            $body = (string)curl_exec($ch);
            $err  = (string)curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return ['ok' => $err === '', 'code' => $code, 'body' => $body, 'error' => $err];
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $timeoutSeconds,
                'header' => "Content-Type: application/json; charset=UTF-8\r\n",
                'content' => $json,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        $ok = $body !== false;
        return ['ok' => $ok, 'code' => $ok ? 200 : 0, 'body' => $ok ? (string)$body : '', 'error' => $ok ? '' : 'file_get_contents_failed'];
    }
}

if (!function_exists('gdy_ping_sitemap')) {
    function gdy_ping_sitemap(string $sitemapUrl): void
    {
        $sitemapUrl = trim($sitemapUrl);
        if ($sitemapUrl === '') return;

        // Google Sitemap Ping (still commonly used)
        $g = 'https://www.google.com/ping?sitemap=' . rawurlencode($sitemapUrl);
        // Bing ping
        $b = 'https://www.bing.com/ping?sitemap=' . rawurlencode($sitemapUrl);

        try { gdy_http_get($g, 4); } catch (Throwable $e) {}
        try { gdy_http_get($b, 4); } catch (Throwable $e) {}
    }
}

if (!function_exists('gdy_indexnow_submit')) {
    function gdy_indexnow_submit(array $urls): array
    {
        $urls = array_values(array_unique(array_filter(array_map('trim', $urls))));
        if (!$urls) return ['ok' => false, 'error' => 'no_urls'];

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') return ['ok' => false, 'error' => 'missing_host'];

        $base = rtrim((string)base_url(), '/');
        $key = defined('GDY_INDEXNOW_KEY') ? (string)GDY_INDEXNOW_KEY : '';
        $keyFile = defined('GDY_INDEXNOW_KEY_FILE') ? (string)GDY_INDEXNOW_KEY_FILE : '';

        if ($key === '' || $keyFile === '') return ['ok' => false, 'error' => 'missing_key'];

        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $base !== '' ? ($base . '/' . $keyFile) : ('/' . $keyFile),
            'urlList' => $urls,
        ];

        $resp = gdy_http_post_json('https://api.indexnow.org/indexnow', $payload, 8);
        $resp['payload'] = $payload;
        return $resp;
    }
}

if (!function_exists('gdy_seo_notify_publish')) {
    /**
     * Call when a news/page is published/updated.
     */
    function gdy_seo_notify_publish(string $publicUrl): void
    {
        $publicUrl = trim($publicUrl);
        if ($publicUrl === '') return;

        // 1) Ping sitemaps (general + news)
        $base = rtrim((string)base_url(), '/');
        $sitemap = $base . '/sitemap.xml';
        $sitemapNews = $base . '/sitemap-news.xml';
        try { gdy_ping_sitemap($sitemap); } catch (Throwable $e) {}
        try { gdy_ping_sitemap($sitemapNews); } catch (Throwable $e) {}

        // 2) IndexNow (Bing and some other engines)
        try { gdy_indexnow_submit([$publicUrl]); } catch (Throwable $e) {}
    }
}
