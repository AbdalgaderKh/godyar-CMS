<?php
declare(strict_types=1);

/**
 * Simple rate limiter (file-based)
 * - default: 5 attempts / 10 minutes
 * - storage: ROOT_PATH/storage/ratelimit
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

function gody_rate_limit(string $bucket, int $max = 5, int $windowSeconds = 600): bool
{
    $dir = rtrim(ROOT_PATH, '/\\') . '/storage/ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', $bucket . '|' . $ip);
    $file = $dir . '/' . $key . '.json';

    $now = time();
    $data = ['count' => 0, 'reset' => $now + $windowSeconds];

    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            if (is_array($j) && isset($j['count'], $j['reset'])) {
                $data['count'] = (int)$j['count'];
                $data['reset'] = (int)$j['reset'];
            }
        }
    }

    if ($now > $data['reset']) {
        $data = ['count' => 0, 'reset' => $now + $windowSeconds];
    }

    $data['count'] += 1;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    return $data['count'] <= $max;
}

function gody_rate_limit_retry_after(string $bucket): int
{
    $dir = rtrim(ROOT_PATH, '/\\') . '/storage/ratelimit';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', $bucket . '|' . $ip);
    $file = $dir . '/' . $key . '.json';
    if (!is_file($file)) return 0;
    $raw = @file_get_contents($file);
    $j = json_decode($raw ?: '', true);
    $reset = isset($j['reset']) ? (int)$j['reset'] : 0;
    $now = time();
    return max(0, $reset - $now);
}
