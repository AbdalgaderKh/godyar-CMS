<?php
declare(strict_types=1);
// legacy route frontend/news/category.php
// تحويل إلى الصفحة الحديثة frontend/category.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$slug = isset($_GET['slug']) ? (string)$_GET['slug'] : '';
$qs   = $slug !== '' ? ('?slug=' . rawurlencode($slug)) : '';

header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/../category.php' . $qs, true, 302);
exit;
