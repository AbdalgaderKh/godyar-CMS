<?php
declare(strict_types=1);

class Cache
{
    /** @var bool هل الكاش مفعّل */
    protected static bool $enabled = true;

    /** @var string نوع الكاش (حاليًا file فقط) */
    protected static string $driver = 'file';

    /** @var string مسار مجلد الكاش */
    protected static string $path = '';

    /**
     * تهيئة الكاش من البوتستراب
     *
     * يمكن أن يُستدعى هكذا:
     *   Cache::init();
     * أو:
     *   Cache::init(['enabled' => true, 'driver' => 'file', 'path' => '/path/to/cache']);
     */
    public static function init(array $config = []): void
    {
        if (array_key_exists('enabled', $config)) {
            static::$enabled = (bool)$config['enabled'];
        }

        if (array_key_exists('driver', $config)) {
            static::$driver = (string)$config['driver'];
        }

        if (array_key_exists('path', $config) && is_string($config['path']) && $config['path'] !== '') {
            static::$path = rtrim($config['path'], DIRECTORY_SEPARATOR);
        }

        if (static::$path === '') {
            $fromEnv = getenv('CACHE_PATH');
            $base = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
            static::$path = rtrim($fromEnv ?: ($base . '/cache'), DIRECTORY_SEPARATOR);
        }

        // تأكد أن المسار موجود
        static::ensurePath();
    }

    /**
     * تفعيل/تعطيل الكاش من الكود عند الحاجة
     */
    public static function enable(bool $on = true): void
    {
        static::$enabled = $on;
    }

    /**
     * هل الكاش مفعّل؟
     */
    public static function isEnabled(): bool
    {
        return static::$enabled;
    }

    /**
     * نوع المحرك (للاطلاع فقط)
     */
    public static function getDriver(): string
    {
        return static::$driver;
    }

    /**
     * مسار مجلد الكاش (تستخدمه لوحة التحكم للعرض)
     */
    public static function getPath(): string
    {
        return static::$path;
    }

    /**
     * تخزين قيمة في الكاش لعدد ثواني محدد
     */
    public static function put(string $key, mixed $value, int $seconds = 60): void
    {
        if (!static::$enabled) {
            return;
        }

        static::ensurePath();

        $file = static::filePath($key);
        $data = [
            'expires' => time() + $seconds,
            'value'   => $value,
        ];

        $php = '<?php return ' . var_export($data, true) . ';';
        @file_put_contents($file, $php, LOCK_EX);
    }

    /**
     * جلب قيمة من الكاش
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!static::$enabled) {
            return $default;
        }

        $file = static::filePath($key);
        if (!is_file($file)) {
            return $default;
        }

        try {
            /** @noinspection PhpIncludeInspection */
            $data = include $file;
        } catch (\Throwable $e) {
            return $default;
        }

        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            return $default;
        }

        if ($data['expires'] < time()) {
            // انتهت صلاحيته – نحذف الملف
            @unlink($file);
            return $default;
        }

        return $data['value'];
    }

    /**
     * نسيان مفتاح معيّن
     */
    public static function forget(string $key): void
    {
        $file = static::filePath($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * مسح جميع ملفات الكاش
     */
    public static function flush(): void
    {
        if (!is_dir(static::$path)) {
            return;
        }

        $items = scandir(static::$path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $f = static::$path . DIRECTORY_SEPARATOR . $item;
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    /**
     * توليد مسار الملف الخاص بالمفتاح
     */
    protected static function filePath(string $key): string
    {
        $hash = sha1($key);
        return static::$path . DIRECTORY_SEPARATOR . $hash . '.phpcache';
    }

    /**
     * التأكد من وجود مجلد الكاش وصلاحيته
     */
    protected static function ensurePath(): void
    {
        if (!is_dir(static::$path)) {
            @mkdir(static::$path, 0775, true);
        }
    }
}
