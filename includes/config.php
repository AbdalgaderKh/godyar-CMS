<?php
declare(strict_types=1);

/**
 * includes/config.php (legacy compatibility)
 * -----------------------------------------
 * بعض سكربتات cron كانت تعتمد على هذا الملف.
 * هذه النسخة تقوم فقط بتحميل includes/env.php لضمان تعريف إعدادات قاعدة البيانات
 * من ملف .env الذي ينشئه المثبّت.
 *
 * لا تضع أسراراً داخل المشروع. استخدم ملف .env في جذر المشروع.
 */

require_once __DIR__ . '/env.php';

// Aliases (in case any legacy code expects them)
if (!defined('DB_NAME') && defined('DB_DATABASE')) define('DB_NAME', DB_DATABASE);
if (!defined('DB_USER') && defined('DB_USERNAME')) define('DB_USER', DB_USERNAME);
if (!defined('DB_PASS') && defined('DB_PASSWORD')) define('DB_PASS', DB_PASSWORD);
