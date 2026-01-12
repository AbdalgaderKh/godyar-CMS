<?php
declare(strict_types=1);

/**
 * OAuth callbacks were disabled in the RAW/CLEAN build (no external links/calls).
 * Enable OAuth integrations intentionally by restoring provider callbacks.
 */

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "OAuth is disabled in this RAW build.";
exit;
