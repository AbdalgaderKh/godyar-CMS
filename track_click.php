<?php
declare(strict_types=1);

/**
 * Canonical ad click tracking endpoint.
 *
 * Accepts:
 *  - ad_id (int)
 *  - redirect (legacy param): ignored for security. Redirect is resolved from DB only.
 *
 * Notes:
 *  - Counter update is schema-tolerant: it tries `click_count` first, then `clicks`.
 *  - Click log insert (ad_clicks) is optional and will be skipped if the table doesn't exist.
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$pdo = gdy_pdo_safe();

$adId = (int)($_GET['ad_id'] ?? 0);
$redirectUrl = (string)($_GET['redirect'] ?? ''); // legacy param (ignored for security)

$resolvedRedirect = '';

// Resolve redirect from DB only (prevents open-redirect attacks)
if ($adId > 0 && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT target_url FROM ads WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $adId]);
        $target = (string)$stmt->fetchColumn();
        if (!empty($target) && filter_var($target, FILTER_VALIDATE_URL)) {
            $resolvedRedirect = $target;
        }
    } catch (Throwable $e) {
        @error_log('Click redirect resolve error: ' . $e->getMessage());
    }
}

// Tracking (best-effort)
if ($adId > 0 && $pdo instanceof PDO) {
    // 1) Update counter - tolerate old/new schemas.
    try {
        $stmt = $pdo->prepare("UPDATE ads SET click_count = click_count + 1 WHERE id = :id");
        $stmt->execute([':id' => $adId]);
    } catch (Throwable $e1) {
        // Fallback: older schema might use `clicks`.
        try {
            $stmt = $pdo->prepare("UPDATE ads SET clicks = clicks + 1 WHERE id = :id");
            $stmt->execute([':id' => $adId]);
        } catch (Throwable $e2) {
            @error_log('Click counter update error: ' . $e2->getMessage());
        }
    }

    // 2) Optional click log - ignore if table doesn't exist.
    try {
        $logStmt = $pdo->prepare(
            "INSERT INTO ad_clicks (ad_id, ip_address, user_agent, referer, clicked_at)
             VALUES (:ad_id, :ip, :ua, :ref, NOW())"
        );
        $logStmt->execute([
            ':ad_id' => $adId,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ':ref' => $_SERVER['HTTP_REFERER'] ?? '',
        ]);
    } catch (Throwable $e) {
        // no-op
    }
}

// Redirect to resolved URL, else home.
if (!empty($resolvedRedirect)) {
    header('Location: ' . $resolvedRedirect, true, 302);
} else {
    header('Location: /', true, 302);
}
exit;
