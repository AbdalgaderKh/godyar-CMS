<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/bootstrap.php';
$slug = $_GET['slug'] ?? '';
?><!doctype html><html lang="ar" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>خبر — <?= h($slug) ?></title>
<link href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-5">
  <h1 class="mb-3">خبر: <?= h($slug) ?></h1>
  <p class="text-muted">عرض تجريبي للخبر (Controller تجريبي).</p>
  <a href="/godyar/" class="btn btn-outline-secondary">العودة</a>
</div>
</body></html>
