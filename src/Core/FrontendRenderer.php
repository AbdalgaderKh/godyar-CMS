<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Frontend renderer that wraps a view with the shared header/footer.
 *
 * Notes:
 * - View variables are injected in an isolated scope.
 * - Controllers should not include templates directly.
 */
final class FrontendRenderer
{
    private string $rootDir;
    private string $basePrefix;

    public function __construct(string $rootDir, string $basePrefix = '')
    {
        $this->rootDir = rtrim($rootDir, '/');
        $this->basePrefix = rtrim($basePrefix, '/');
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $meta
     */
    public function render(string $viewRelative, array $data = [], array $meta = []): void
    {
        $header = $this->rootDir . '/frontend/templates/header.php';
        $footer = $this->rootDir . '/frontend/templates/footer.php';
        $view   = $this->rootDir . '/' . ltrim($viewRelative, '/');

        if (!is_file($view)) {
            http_response_code(500);
            echo 'View not found.';
            exit;
        }

        // Provide common values
        $data['baseUrl'] = $data['baseUrl'] ?? $this->basePrefix;
        $data['basePrefix'] = $data['basePrefix'] ?? $this->basePrefix;

        $vars = array_merge($meta, $data);

        (static function (string $header, string $view, string $footer, array $vars): void {
            foreach ($vars as $k => $v) {
                if (is_string($k) && preg_match('~^[a-zA-Z_][a-zA-Z0-9_]*$~', $k)) {
                    ${$k} = $v;
                }
            }
            if (is_file($header)) {
                require $header;
            }
            require $view;
            if (is_file($footer)) {
                require $footer;
            }
        })($header, $view, $footer, $vars);

        exit;
    }
}
