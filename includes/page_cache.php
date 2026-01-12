<?php

/**
 * PageCache - كاش لصفحات كاملة (HTML) باستخدام Cache
 */

class PageCache
{
    public static function serveIfCached(string $key): bool
    {
        if (!class_exists('Cache')) {
            return false;
        }

        $html = Cache::get('page_' . $key);
        if (!is_string($html) || $html === '') {
            return false;
        }

        echo $html;
        return true;
    }

    public static function store(string $key, int $ttl = null): void
    {
        if (!class_exists('Cache')) {
            return;
        }

        $html = ob_get_contents();
        if ($html === false || $html === '') {
            return;
        }

        Cache::put('page_' . $key, $html, $ttl);
    }
}
