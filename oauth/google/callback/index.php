<?php
declare(strict_types=1);

// حمّل bootstrap من جذر public_html
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

// شغّل ملف الكولباك الحقيقي (الموجود عندك داخل /oauth/google/)
require_once dirname(__DIR__) . '/google_callback.php';
