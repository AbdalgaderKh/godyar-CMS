<?php
declare(strict_types=1);

/**
 * Legacy ad click endpoint (backward compatibility).
 *
 * Canonical endpoint: track_click.php
 *
 * This script maps legacy parameters (id / redirect) to track_click.php.
 *
 * Supported params:
 *  - id (legacy) or ad_id
 *  - redirect (optional). If missing or invalid, try to fetch target_url from DB.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = gdy_pdo_safe();

// Legacy parameter name is usually `id`.
$adId = (int)($_GET['id'] ?? ($_GET['ad_id'] ?? 0));
$redirectUrl = (string)($_GET['redirect'] ?? '');

if ($adId <= 0) {
    header('Location: /');
    exit;
}

// If redirect is not supplied (or invalid), attempt DB lookup.
if (empty($redirectUrl) || !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT target_url FROM ads WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $adId]);
            $target = (string)$stmt->fetchColumn();
            if (!empty($target) && filter_var($target, FILTER_VALIDATE_URL)) {
                $redirectUrl = $target;
            }
        } catch (Throwable $e) {
            @error_log('[ad_click_legacy] ' . $e->getMessage());
        }
    }
}

// Build track_click URL in a subdirectory-safe way.
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($base === '') {
    $base = '/';
}
$track = ($base === '/' ? '' : $base) . '/track_click.php';

$query = http_build_query([
    'ad_id' => $adId,
    // track_click.php will validate again; keep empty if unknown.
    'redirect' => $redirectUrl,
]);

header('Location: ' . $track . '?' . $query, true, 302);
exit;
