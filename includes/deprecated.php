<?php
declare(strict_types=1);

require_once __DIR__ . '/legacy_monitor.php';

/**
 * Helpers for deprecated endpoints (Legacy PHP pages).
 * Keep responses consistent across the project.
 */

function deprecated_redirect(string $target, int $code = 301): void
{
        legacy_log('deprecated_redirect');
header('Location: ' . $target, true, $code);
    exit;
}

function deprecated_gone(string $hint): void
{
        legacy_log('deprecated_gone', ['hint' => $hint]);
http_response_code(410);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Deprecated endpoint. Use {$hint} instead.";
    exit;
}
