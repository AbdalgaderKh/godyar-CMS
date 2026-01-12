<?php
declare(strict_types=1);

// godyar/contact.php — واجهة موحدة تعتمد على ContactController والهوية الأمامية

require_once __DIR__ . '/includes/bootstrap.php';

// نحيل المعالجة بالكامل إلى ContactController في الواجهة الأمامية
require __DIR__ . '/frontend/controllers/ContactController.php';
