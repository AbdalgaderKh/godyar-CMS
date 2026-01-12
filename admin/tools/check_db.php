<?php
declare(strict_types=1);

// Backward compatibility: old path /admin/tools/check_db.php
// The updated tool is /admin/tools/db_audit.php
if (!headers_sent()) {
    header('Location: db_audit.php');
}
require_once __DIR__ . '/db_audit.php';
exit;
