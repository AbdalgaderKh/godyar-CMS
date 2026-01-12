<?php
declare(strict_types=1);

// منع الوصول المباشر لهذا الملف (يسمح بالـ include فقط)
try {
    $isDirect = (PHP_SAPI !== 'cli') && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);
} catch (Throwable $e) {
    $isDirect = false;
}
if ($isDirect) {
    require_once __DIR__ . '/_admin_guard.php';
    if (class_exists('Godyar\\Auth') && method_exists('Godyar\\Auth','requireRole')) {
        \Godyar\Auth::requireRole('admin');
    } else {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') { http_response_code(403); exit('403 Forbidden'); }
    }
    header('Location: /admin/system/queue/index.php');
    exit;
}

/**
 * Lightweight file-based Queue for shared hosting
 * Storage: /godyar/admin/cache/queue/jobs.json
 * Each job:
 *  - id (string)
 *  - type (string)
 *  - payload (array)
 *  - run_at (int unix ts)
 *  - status: pending|running|done|failed
 *  - attempts (int)
 *  - last_error (string|null)
 */

final class GdyQueue {
    private static function baseDir(): string {
        return dirname(__DIR__, 1) . '/cache/queue'; // /admin/cache/queue
    }
    private static function jobsFile(): string {
        return self::baseDir() . '/jobs.json';
    }
    private static function ensureStorage(): void {
        $dir = self::baseDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = self::jobsFile();
        if (!is_file($file)) {
            @file_put_contents($file, json_encode([], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        }
    }

    public static function all(): array {
        self::ensureStorage();
        $data = json_decode((string)@file_get_contents(self::jobsFile()), true);
        return is_array($data) ? $data : [];
    }

    private static function save(array $jobs): void {
        self::ensureStorage();
        @file_put_contents(self::jobsFile(), json_encode(array_values($jobs), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    }

    public static function enqueue(string $type, array $payload = [], ?int $runAt = null): string {
        self::ensureStorage();
        $jobs = self::all();
        $id = bin2hex(random_bytes(10));
        $jobs[] = [
            'id' => $id,
            'type' => $type,
            'payload' => $payload,
            'run_at' => $runAt ?? time(),
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
            'created_at' => time(),
        ];
        self::save($jobs);
        return $id;
    }

    public static function clearDone(): int {
        $jobs = self::all();
        $before = count($jobs);
        $jobs = array_values(array_filter($jobs, fn($j)=>($j['status']??'')!=='done'));
        self::save($jobs);
        return $before - count($jobs);
    }

    public static function run(int $limit = 10): array {
        self::ensureStorage();
        $jobs = self::all();
        $now = time();
        $ran = [];

        // sort by run_at asc
        usort($jobs, fn($a,$b)=>($a['run_at']??0)<=>($b['run_at']??0));

        $handlers = $GLOBALS['GDY_QUEUE_HANDLERS'] ?? [];
        $count = 0;

        foreach ($jobs as &$job) {
            if ($count >= $limit) break;
            if (($job['status'] ?? '') !== 'pending') continue;
            if (($job['run_at'] ?? 0) > $now) continue;

            $type = (string)($job['type'] ?? '');
            if (!isset($handlers[$type]) || !is_callable($handlers[$type])) {
                $job['status'] = 'failed';
                $job['last_error'] = 'No handler registered for type: ' . $type;
                $ran[] = $job;
                $count++;
                continue;
            }

            $job['status'] = 'running';
            $job['attempts'] = (int)($job['attempts'] ?? 0) + 1;

            try {
                $handlers[$type]($job['payload'] ?? []);
                $job['status'] = 'done';
                $job['last_error'] = null;
            } catch (\Throwable $e) {
                $job['status'] = 'failed';
                $job['last_error'] = $e->getMessage();
            }

            $ran[] = $job;
            $count++;
        }
        unset($job);

        self::save($jobs);
        return $ran;
    }
}

function gdy_queue_register(string $type, callable $handler): void {
    $GLOBALS['GDY_QUEUE_HANDLERS'] = $GLOBALS['GDY_QUEUE_HANDLERS'] ?? [];
    $GLOBALS['GDY_QUEUE_HANDLERS'][$type] = $handler;
}
