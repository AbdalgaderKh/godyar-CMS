<?php
declare(strict_types=1);

/**
 * includes/indexnow.php
 * - إرسال إشعارات IndexNow عند نشر/تحديث روابط الأخبار
 *
 * الإعدادات في جدول settings:
 *  - seo.indexnow_enabled (0/1)
 *  - seo.indexnow_key (string)
 *  - seo.indexnow_endpoint (افتراضي: https://api.indexnow.org/indexnow)
 *  - seo.indexnow_key_location (اختياري، افتراضي: /indexnow-key.txt)
 */

use Godyar\Services\SettingsService;

if (!function_exists('gdy_indexnow_submit')) {
    /**
     * @param PDO $pdo
     * @param array<int,string> $urls
     */
    function gdy_indexnow_submit(PDO $pdo, array $urls): bool
    {
        $urls = array_values(array_unique(array_filter(array_map('trim', $urls), fn($u) => $u !== '')));
        if (empty($urls)) return false;

        if (!class_exists(SettingsService::class)) return false;
        $svc = new SettingsService($pdo);

        $enabled = (int)$svc->getValue('seo.indexnow_enabled', 0);
        if ($enabled !== 1) return false;

        $key = trim((string)$svc->getValue('seo.indexnow_key', ''));
        if ($key === '') return false;

        $endpoint = trim((string)$svc->getValue('seo.indexnow_endpoint', 'https://api.indexnow.org/indexnow'));
        if ($endpoint === '') $endpoint = 'https://api.indexnow.org/indexnow';

        $base = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
        $host = '';
        if ($base !== '') {
            $host = (string)parse_url($base, PHP_URL_HOST);
        } else {
            $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        }
        if ($host === '') return false;

        $keyLocation = trim((string)$svc->getValue('seo.indexnow_key_location', ''));
        if ($keyLocation === '') {
            $keyLocation = ($base !== '' ? ($base . '/indexnow-key.txt') : '/indexnow-key.txt');
        }

        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $keyLocation,
            'urlList' => $urls,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!$json) return false;

        // إرسال HTTP POST
        $ok = false;
        $code = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $json,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json; charset=utf-8',
                        'Content-Length: ' . strlen($json),
                    ],
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_CONNECTTIMEOUT => 4,
                ]);
                $resp = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                $ok = ($code >= 200 && $code < 300);
                if (!$ok) {
                    @error_log('[IndexNow] HTTP ' . $code . ' resp=' . (is_string($resp) ? substr($resp, 0, 300) : ''));
                }
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json; charset=utf-8\r\nContent-Length: " . strlen($json) . "\r\n",
                    'content' => $json,
                    'timeout' => 6,
                ],
            ]);
            $resp = @file_get_contents($endpoint, false, $ctx);
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) { $code = (int)$m[1]; break; }
                }
            }
            $ok = ($code >= 200 && $code < 300);
            if (!$ok) {
                @error_log('[IndexNow] HTTP ' . $code . ' resp=' . (is_string($resp) ? substr($resp, 0, 300) : ''));
            }
        }

        return $ok;
    }
}
