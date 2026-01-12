<?php
declare(strict_types=1);

use Godyar\DB;

/**
 * Single source of truth for PDO.
 *
 * - Prefer using Godyar\DB::pdo() directly.
 * - Use gdy_pdo_safe() in legacy scripts that previously relied on $GLOBALS['pdo'].
 */

if (!function_exists('gdy_pdo_safe')) {
    /**
     * Safe PDO getter.
     * Returns null instead of fatalling if DB connection fails.
     */
    function gdy_pdo_safe(): ?\PDO
    {
        try {
            return DB::pdo();
        } catch (\Throwable $e) {
            @error_log('[Godyar DB] PDO unavailable: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('gdy_pdo')) {
    /**
     * Strict PDO getter.
     * Throws if the DB connection cannot be established.
     */
    function gdy_pdo(): \PDO
    {
        return DB::pdo();
    }
}

if (!function_exists('gdy_register_global_pdo')) {
    /**
     * Deprecated: populate $GLOBALS['pdo'] for legacy code.
     *
     * Controlled by env LEGACY_GLOBAL_PDO (default: "1").
     */
    function gdy_register_global_pdo(): void
    {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
            return;
        }

        $raw = getenv('LEGACY_GLOBAL_PDO');
        $enabled = true;
        if ($raw !== false && $raw !== null && $raw !== '') {
            $enabled = !in_array(strtolower((string)$raw), ['0', 'false', 'off', 'no'], true);
        }

        if (!$enabled) {
            return;
        }

        $pdo = gdy_pdo_safe();
        if (!$pdo) {
            return;
        }

        $GLOBALS['pdo'] = $pdo;

        if (empty($GLOBALS['__godyar_warned_global_pdo'])) {
            $GLOBALS['__godyar_warned_global_pdo'] = true;
            @error_log('[DEPRECATED] $GLOBALS[\'pdo\'] is enabled for backward compatibility. Prefer Godyar\\DB::pdo() / gdy_pdo().');
        }
    }
}
