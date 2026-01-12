<?php declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../includes/admin_layout.php';
render_page(__('t_e79f344357', 'التواصل الاجتماعي'),'/admin/social', function(){ ?>
  <div class="card p-3"><h1 class="h5 mb-3"><svg class="gdy-icon me-2" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('t_90ce376fda', 'الروابط الاجتماعية')) ?></h1>
    <div class="row g-2">
      <div class="col-md-4"><input class="form-control" placeholder="Facebook URL"></div>
      <div class="col-md-4"><input class="form-control" placeholder="X/Twitter URL"></div>
      <div class="col-md-4"><input class="form-control" placeholder="YouTube URL"></div>
      <div class="col-12"><button class="btn btn-primary mt-2"><?= h(__('t_871a087a1d', 'حفظ')) ?></button></div>
    </div>
  </div>
<?php }); ?>
