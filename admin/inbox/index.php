<?php declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../includes/admin_layout.php';
render_page(__('t_4bee789b25', 'الرسائل'),'/admin/inbox', function(){ ?>
  <div class="card p-3"><h1 class="h5 mb-3"><svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_5c3b30feca', 'الوارد')) ?></h1>
    <div class="list-group">
      <a class="list-group-item list-group-item-action bg-transparent text-white d-flex justify-content-between align-items-center">
        <?= h(__('t_936c64cc70', 'رسالة تجريبية')) ?> <span class="badge bg-info"><?= h(__('t_3bbfbd8b86', 'غير مقروء')) ?></span>
      </a>
    </div>
  </div>
<?php }); ?>
