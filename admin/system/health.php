<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require __DIR__ . '/../includes/admin_layout.php';
render_page(__('t_640a46691d', 'حالة النظام'), '/admin/system/health', function() { ?>

<h1 class="h4 mb-3"><?= h(__('t_640a46691d', 'حالة النظام')) ?></h1>
<div class="card p-3">
  <p class="mb-0"><?= h(__('t_c26d359d9c', 'هذه صفحة حالة النظام (نموذج أولي). يمكنك بدء دمج قاعدة البيانات والوظائف الفعلية هنا.')) ?></p>
</div>

<?php });
