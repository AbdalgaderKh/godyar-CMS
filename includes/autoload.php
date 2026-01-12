<?php
// godyar/includes/autoload.php
// Internal PSR-4-ish autoloader. Keeps backwards compatibility while standardizing namespaces.

spl_autoload_register(function (string $class) {
    $prefixes = [
        // Prefer includes/classes (namespaced classes), fallback to includes/ for legacy files (e.g., includes/auth.php)
        'Godyar\\' => [__DIR__ . '/classes/', __DIR__ . '/'],
        'App\\'    => [__DIR__ . '/../src/'],
    ];

    foreach ($prefixes as $prefix => $baseDirs) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $relativePath  = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        foreach ((array)$baseDirs as $baseDir) {
            $file = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $relativePath;
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }

        return;
    }
});
