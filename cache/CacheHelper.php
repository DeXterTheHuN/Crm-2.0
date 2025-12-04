<?php
class CacheHelper {
    private static $cacheDir = __DIR__ . '/data/';
    private static $ttl = 300; // 5 perc
    
    public static function get($key) {
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file)) < self::$ttl) {
            return unserialize(file_get_contents($file));
        }
        return null;
    }
    
    public static function set($key, $data) {
        if (!is_dir(self::$cacheDir)) mkdir(self::$cacheDir, 0755, true);
        file_put_contents(self::$cacheDir . md5($key) . '.cache', serialize($data));
    }
    
    public static function clear($key = null) {
        if ($key) {
            @unlink(self::$cacheDir . md5($key) . '.cache');
        } else {
            array_map('unlink', glob(self::$cacheDir . '*.cache'));
        }
    }
}
