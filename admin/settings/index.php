<?php
require_once __DIR__ . '/_settings_guard.php';
require_once __DIR__ . '/_settings_meta.php';
settings_apply_context();
require_once __DIR__ . '/../layout/app_start.php';
?>

<div class="row g-3">
    <div class="col-md-3">
      <?php include __DIR__ . '/_settings_nav.php'; ?>
    </div>

    <div class="col-md-9">
      <div class="card p-4">
        <h4 class="mb-2"><?= h(__('t_0387d588d4', 'إعدادات الموقع')) ?></h4>
        <p class="text-muted mb-0">
          <?= h(__('t_ea85c4354b', 'اختر قسم الإعدادات من القائمة الجانبية لإدارته.')) ?>
        </p>

        <hr>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-1"><?= h(__('t_7a744d0f00', 'تنظيم احترافي')) ?></div>
              <div class="text-muted small">
                <?= h(__('t_a91cd32f2e', 'كل الإعدادات مقسّمة لصفحات مستقلة لتقليل الأخطاء ومنع تداخل الواجهة وتسهيل التوسّع لاحقاً.')) ?>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-1"><?= h(__('t_6691c9b346', 'نصيحة سريعة')) ?></div>
              <div class="text-muted small">
                <?= h(__('t_1f4bd0d7f0', 'بعد تعديل الألوان/SEO/الكاش، امسح الكاش (إن كان مفعّل) للتأكد من ظهور النتائج فوراً.')) ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>


</div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
