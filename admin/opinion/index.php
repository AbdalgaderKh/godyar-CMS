<?php

require_once __DIR__ . '/../_admin_guard.php';
// admin/opinion/index.php - إعادة توجيه إلى opinion_authors
header('Location: ../opinion_authors/index.php');
exit;