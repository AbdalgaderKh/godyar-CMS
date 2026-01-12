<?php
namespace Frontend\Helpers;
class Cache {
  public static string $dir = __DIR__ . '/../../storage/cache';
  public static function get(string $key, int $ttl=300) {
    $f = self::file($key);
    if (!is_file($f)) return null;
    if (filemtime($f) < time()-$ttl) return null;
    return @file_get_contents($f);
  }
  public static function put(string $key, string $value) {
    $f = self::file($key);
    if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
    @file_put_contents($f, $value);
  }
  public static function remember(string $key, int $ttl, callable $cb) {
    $v = self::get($key, $ttl);
    if ($v !== null) return $v;
    $v = (string)$cb(); self::put($key, $v); return $v;
  }
  private static function file(string $key){ return rtrim(self::$dir,'/').'/'.md5($key).'.cache'; }
}
