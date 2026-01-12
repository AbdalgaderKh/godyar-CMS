<?php
declare(strict_types=1);

/**
 * Godyar Plugin System (برو)
 * Path: /godyar/includes/plugins.php
 *
 * - كل إضافة توضع داخل /godyar/plugins/{PluginFolder}
 * - داخل كل إضافة ملف Plugin.php يعيد كائن يطبّق GodyarPluginInterface
 * - (اختياري) ملف plugin.json لتعريف الاسم و enabled وغيرها
 *
 * - PluginManager:
 *     - loadAll(): تحميل جميع الإضافات المفعّلة
 *     - addHook(): تسجيل hook
 *     - doHook(): تنفيذ hook (يشبه actions)
 *     - filter(): تمربر قيمة عبر سلسلة من الفلاتر
 *
 * - دوال مساعدة:
 *     - g_plugins()
 *     - g_do_hook($hook, ...)
 *     - g_apply_filters($hook, $value, ...)
 */

interface GodyarPluginInterface
{
    /**
     * تستدعى عند تحميل الإضافة.
     * الإضافة تستخدم $pm->addHook() لتسجيل الهواكس.
     */
    public function register(PluginManager $pm): void;
}

final class PluginManager
{
    private static ?PluginManager $instance = null;

    /** @var array<string,object> slug => instance */
    private array $plugins = [];

    /** @var array<string,array> slug => meta */
    private array $meta = [];

    /** @var array<string,array<int,array{int,callable}>> hook => [ [priority, callback], ... ] */
    private array $hooks = [];

    private function __construct() {}

    public static function instance(): PluginManager
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * تحميل جميع الإضافات من مجلد /plugins
     * كل إضافة داخل مجلد:
     *   /plugins/PluginFolder/plugin.json (اختياري)
     *   /plugins/PluginFolder/Plugin.php (إلزامي)
     *
     * Plugin.php يجب أن يُرجع (return) كائن يطبّق GodyarPluginInterface.
     */
    public function loadAll(?string $baseDir = null): void
    {
        $base = $baseDir ?: dirname(__DIR__) . '/plugins';
        if (!is_dir($base)) {
            return;
        }

        $dirs = scandir($base);
        if (!is_array($dirs)) {
            return;
        }

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $pluginPath = $base . '/' . $dir;
            if (!is_dir($pluginPath)) {
                continue;
            }

            $slug = $dir;

            // قراءة meta من plugin.json (إن وجد)
            $meta = [
                'slug'    => $slug,
                'enabled' => true,
            ];
            $metaFile = $pluginPath . '/plugin.json';
            if (is_file($metaFile)) {
                $json = @file_get_contents($metaFile);
                if (is_string($json) && $json !== '') {
                    $decoded = json_decode($json, true);
                    if (is_array($decoded)) {
                        $meta = array_merge($meta, $decoded);
                    }
                }
            }

            // حقل enabled
            $enabled = $meta['enabled'] ?? true;
            if (is_string($enabled)) {
                $enabled = in_array(strtolower($enabled), ['1','true','yes','on'], true);
            } else {
                $enabled = (bool)$enabled;
            }
            if (!$enabled) {
                continue; // الإضافة معطّلة
            }

            $main = $pluginPath . '/Plugin.php';
            if (!is_file($main)) {
                continue;
            }

            try {
                // يجب أن يُرجع كائن يطبّق GodyarPluginInterface
                $instance = include $main;

                if ($instance instanceof GodyarPluginInterface) {
                    $this->meta[$slug]    = $meta;
                    $this->plugins[$slug] = $instance;
                    $instance->register($this);
                }
            } catch (\Throwable $e) {
                @error_log('[Godyar Plugin] Failed to load plugin ' . $slug . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * تسجيل hook
     */
    public function addHook(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks[$hook][] = [$priority, $callback];
        usort($this->hooks[$hook], static function ($a, $b) {
            return $a[0] <=> $b[0];
        });
    }

    /**
     * تنفيذ hook (يشبه action)
     * يسمح بتمرير المتغيرات بالـ reference لمنح الإضافات صلاحية التعديل على المصفوفات.
     */
    public function doHook(string $hook, &...$args): void
    {
        if (empty($this->hooks[$hook])) {
            return;
        }

        foreach ($this->hooks[$hook] as [$priority, $cb]) {
            try {
                $cb(...$args);
            } catch (\Throwable $e) {
                @error_log('[Godyar Plugin] Error in hook ' . $hook . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * فلاتر لإرجاع قيمة بعد تمريرها على الإضافات.
     */
    public function filter(string $hook, $value, ...$args)
    {
        if (empty($this->hooks[$hook])) {
            return $value;
        }

        $result = $value;

        foreach ($this->hooks[$hook] as [$priority, $cb]) {
            try {
                $result = $cb($result, ...$args);
            } catch (\Throwable $e) {
                @error_log('[Godyar Plugin] Error in filter ' . $hook . ': ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * جميع الإضافات المحمّلة
     * @return array<string,object>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * معلومات meta لجميع الإضافات أو لإضافة معيّنة.
     */
    public function meta(?string $slug = null): array
    {
        if ($slug === null) {
            return $this->meta;
        }
        return $this->meta[$slug] ?? [];
    }
}

// دوال مساعدة global
if (!function_exists('g_plugins')) {
    function g_plugins(): PluginManager
    {
        return PluginManager::instance();
    }
}

if (!function_exists('g_do_hook')) {
    /**
     * تنفيذ hook (يشبه action)
     */
    function g_do_hook(string $hook, &...$args): void
    {
        PluginManager::instance()->doHook($hook, ...$args);
    }
}

if (!function_exists('g_apply_filters')) {
    /**
     * تمريـر قيمة على فلاتر الإضافات
     */
    function g_apply_filters(string $hook, $value, ...$args)
    {
        return PluginManager::instance()->filter($hook, $value, ...$args);
    }
}
