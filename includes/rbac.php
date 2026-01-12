<?php
// includes/rbac.php — Helpers for RBAC

use Godyar\Auth;

/**
 * فحص هل المستخدم يملك دور معيّن
 */
if (!function_exists('g_is')) {
    function g_is(string $role): bool {
        if (!class_exists(Auth::class)) {
            return false;
        }
        return Auth::hasRole($role);
    }
}

/**
 * فحص هل المستخدم يملك صلاحية معيّنة
 */
if (!function_exists('g_can')) {
    function g_can(string $permission): bool {
        if (!class_exists(Auth::class)) {
            return false;
        }
        return Auth::hasPermission($permission);
    }
}
