<?php
namespace App\Core;

/**
 * Core application bootstrap (PHP 8 safe)
 */
final class App
{
    public static $basePath = '';
    public static $env = [];

    public static function boot($basePath)
    {
        self::$basePath = rtrim($basePath, '/');

        self::loadEnv();

        $tz = self::env('TIMEZONE', 'Asia/Riyadh');
        if (function_exists('date_default_timezone_set')) {
            @date_default_timezone_set($tz);
        }

        $debug = self::env('APP_DEBUG', 'false');
        $isDebug = ($debug === 'true' || $debug === true || $debug === 1 || $debug === '1');
        if ($isDebug) {
            @ini_set('display_errors', '1');
            @ini_set('display_startup_errors', '1');
            error_reporting(E_ALL & ~E_DEPRECATED);
        } else {
            @ini_set('display_errors', '0');
            @ini_set('display_startup_errors', '0');
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        }
    }

    public static function env($key, $default = null)
    {
        return array_key_exists($key, self::$env) ? self::$env[$key] : $default;
    }

    private static function loadEnv()
    {
        // دعم .env داخل المشروع أو خارجه (مثلاً public_html/.env عند وجود المشروع داخل public_html/godyar/)
        $candidates = [
            self::$basePath . '/.env',
            dirname(self::$basePath) . '/.env',
            self::$basePath . '/.env.example',
        ];

        $data = [];
        foreach ($candidates as $f) {
            if (file_exists($f)) {
                $parsed = @parse_ini_file($f, false, defined('INI_SCANNER_RAW') ? INI_SCANNER_RAW : 0);
                if (is_array($parsed)) {
                    $data = $parsed;
                    break;
                }
            }
        }

        foreach ($data as $k => $v) self::$env[$k] = (string)$v;
    }
}
