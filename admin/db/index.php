<?php declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../includes/admin_layout.php';
render_page(__('t_fa678d5458', 'قاعدة البيانات'),'/admin/db', function(){ ?>
  <div class="card p-3"><h1 class="h5 mb-3"><svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_f63664d6dc', 'عمليات قاعدة البيانات')) ?></h1>
    <button class="btn btn-outline-warning"><?= h(__('t_5fcf019e9b', 'نسخ احتياطي')) ?></button>
    <button class="btn btn-outline-info"><?= h(__('t_37a02177f0', 'استيراد')) ?></button>
  </div>
<?php }); ?>
