<?php
declare(strict_types=1);

// Bootstrap واحد لكل المشروع (DB + Session + Security Headers)
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Backward compatibility: بعض القوالب تتوقع وجود $pdo
$pdo = gdy_pdo_safe();
