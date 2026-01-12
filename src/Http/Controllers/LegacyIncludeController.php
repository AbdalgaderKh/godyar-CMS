<?php
declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * Wrapper to include legacy procedural controllers while we migrate.
 */
final class LegacyIncludeController
{
    /** @var string */
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/');
    }

    /**
     * @param array<string, string|int|float|bool|null> $get
     */
    public function include(string $relativeFile, array $get = []): void
    {
        foreach ($get as $k => $v) {
            $_GET[$k] = is_bool($v) ? ($v ? '1' : '0') : (string)($v ?? '');
        }

        $file = $this->baseDir . '/' . ltrim($relativeFile, '/');
        if (!is_file($file)) {
            http_response_code(500);
            echo 'Controller not found.';
            exit;
        }

        require $file;
        exit;
    }
}
