<?php
declare(strict_types=1);

/**
 * config/database.php
 * مصدر إعدادات قاعدة البيانات (بدون أسرار داخل المشروع)
 *
 * القيم تأتي من ENV/.env (خارج webroot).
 */

require_once dirname(__DIR__) . '/includes/env.php';

return [
    'dsn'       => defined('DB_DSN') ? DB_DSN : '',
    'host'      => defined('DB_HOST') ? DB_HOST : '',
    'port'      => defined('DB_PORT') ? DB_PORT : '',
    'name'      => defined('DB_NAME') ? DB_NAME : '',
    'user'      => defined('DB_USER') ? DB_USER : '',
    'charset'   => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
    'collation' => defined('DB_COLLATION') ? DB_COLLATION : 'utf8mb4_unicode_ci',
];
