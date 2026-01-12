<?php

require_once __DIR__ . '/../_admin_guard.php';
// admin/plugins/index.php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/loader.php';

$currentPage = 'plugins';
$pageTitle   = __('t_3be2bf6b96', 'الإضافات البرمجية');

// معالجة تفعيل/تعطيل الإضافات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_plugin') {
    $slug = $_POST['slug'] ?? '';
    $slug = preg_replace('~[^A-Za-z0-9_\-]~', '', $slug); // تنظيف بسيط

    $pluginsDir = __DIR__;
    // plugins live under /admin/plugins/*/plugin.json
    $pluginDir  = $pluginsDir . '/' . $slug;

    if ($slug !== '' && is_dir($pluginDir)) {
        $metaFile = $pluginDir . '/plugin.json';

        // بيانات مبدئية
        $meta = ['enabled' => true];

        // قراءة الملف الحالي إن وُجد
        if (is_file($metaFile)) {
            $json = @file_get_contents($metaFile);
            if (is_string($json) && $json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $meta = array_merge($meta, $decoded);
                }
            }
        }

        // قلب الحالة
        $enabled          = !empty($meta['enabled']);
        $meta['enabled']  = !$enabled;

        // حفظ التغيير
        @file_put_contents(
            $metaFile,
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    header('Location: index.php');
    exit;
}

// قراءة الإضافات من مجلد /plugins
$pluginsDir = __DIR__;
    // plugins live under /admin/plugins/*/plugin.json
$pluginRows = [];

if (is_dir($pluginsDir)) {
    $dirs = scandir($pluginsDir);
    if (is_array($dirs)) {
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $path = $pluginsDir . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }

            // بيانات افتراضية
            $meta = [
                'folder'      => $dir,
                'slug'        => $dir,
                'name'        => $dir,
                'version'     => '',
                'description' => '',
                'author'      => '',
                'enabled'     => true,
            ];

            // قراءة plugin.json إن وجد
            $metaFile = $path . '/plugin.json';
            if (is_file($metaFile)) {
                $json = @file_get_contents($metaFile);
                if (is_string($json) && $json !== '') {
                    $decoded = json_decode($json, true);
                    if (is_array($decoded)) {
                        $meta = array_merge($meta, $decoded);
                    }
                }
            }

            $enabled = $meta['enabled'] ?? true;
            if (is_string($enabled)) {
                $enabled = in_array(strtolower($enabled), ['1', 'true', 'yes', 'on'], true);
            } else {
                $enabled = (bool)$enabled;
            }
            $meta['enabled'] = $enabled;

            $pluginRows[] = $meta;
        }
    }
}

// ترتيب الإضافات بالاسم
usort($pluginRows, static function (array $a, array $b): int {
    return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
});

$currentPage = 'plugins';
$pageTitle   = __('t_95151e1274', 'الإضافات');
$pageSubtitle= __('t_19ad4371ed', 'إدارة الإضافات البرمجية');
$adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin'), '/');
$breadcrumbs = [__('t_3aa8578699', 'الرئيسية') => $adminBase.'/index.php', __('t_95151e1274', 'الإضافات') => null];
$pageActionsHtml = '';
require_once __DIR__ . '/../layout/app_start.php';
$csrf = generate_csrf_token();
?>

<style>
  /* التصميم الموحد للعرض */
  .gdy-inner{max-width:1200px;margin:0 auto;}
  .gdy-plugin-desc{color:#cbd5e1;}
  .gdy-plugin-meta code{background:rgba(15,23,42,.6);padding:.15rem .35rem;border-radius:8px;}
</style>

<div class="admin-content">
  <div class="">
    <div class="container-xxl gdy-inner">
  <div class="gdy-page-header d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 text-white mb-1"><?= h(__('t_3be2bf6b96', 'الإضافات البرمجية')) ?></h1>
      <p class="text-muted mb-0">
        <?= h(__('t_a1d9064a32', 'عرض الإضافات الموجودة داخل مجلد')) ?> <code>/plugins</code>.
      </p>
    </div>
  </div>

  <div class="card glass-card gdy-card mb-3">
    <div class="card-body">

      <?php if (empty($pluginRows)): ?>
        <p class="mb-0"><?= h(__('t_7b963d34c0', 'لا توجد إضافات حتى الآن.')) ?></p>
      <?php else: ?>
        <!-- جدول (شاشات كبيرة) -->
        <div class="table-responsive d-none d-lg-block">
          <table class="table table-dark table-striped align-middle mb-0">
            <thead>
              <tr>
                <th style="width:32px;">#</th>
                <th><?= h(__('t_e6ad5db8a9', 'الإضافة')) ?></th>
                <th class="d-none d-xl-table-cell"><?= h(__('t_f58d38d563', 'الوصف')) ?></th>
                <th class="d-none d-md-table-cell"><?= h(__('t_8c0c06316b', 'الإصدار')) ?></th>
                <th><?= h(__('t_1253eb5642', 'الحالة')) ?></th>
                <th><?= h(__('t_5446a35e92', 'المجلد / الـ Slug')) ?></th>
                <th class="d-none d-xl-table-cell"><?= h(__('t_dd21f0b9d2', 'المطوِّر')) ?></th>
                <th style="width:190px;"><?= h(__('t_901efe9b1c', 'إجراءات')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pluginRows as $i => $pl): ?>
                <?php
                  $folder = $pl['folder'] ?? '';
                  if ($folder === '') { continue; }
                  $slug = $folder; // نستخدم اسم المجلد كأساس ثابت
                ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><?= htmlspecialchars($pl['name'] ?? $slug, ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="small d-none d-xl-table-cell">
                    <span class="gdy-plugin-desc"><?= htmlspecialchars($pl['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                  <td class="d-none d-md-table-cell"><?= htmlspecialchars($pl['version'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <?php if (!empty($pl['enabled'])): ?>
                      <span class="badge bg-success"><?= h(__('t_641298ecec', 'مفعّلة')) ?></span>
                    <?php else: ?>
                      <span class="badge bg-secondary"><?= h(__('t_2fab10b091', 'معطّلة')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <code><?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?></code>
                  </td>
                  <td class="d-none d-xl-table-cell"><?= htmlspecialchars($pl['author'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <form method="post" class="d-inline">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                      <input type="hidden" name="action" value="toggle_plugin">
                      <input type="hidden" name="slug"
                             value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit"
                              class="btn btn-sm <?= !empty($pl['enabled']) ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                        <?= !empty($pl['enabled']) ? __('t_43ead21245', 'تعطيل') : __('t_8403358516', 'تفعيل') ?>
                      </button>
                    </form>

                    <a href="settings.php?slug=<?= urlencode($slug) ?>"
                       class="btn btn-sm btn-outline-info">
                      <?= h(__('t_1f60020959', 'الإعدادات')) ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- عرض بطاقات (موبايل/شاشات صغيرة) -->
        <div class="d-lg-none">
          <?php foreach ($pluginRows as $pl): ?>
            <?php $folder = $pl['folder'] ?? ''; if ($folder === '') { continue; } $slug = $folder; ?>
            <div class="card glass-card gdy-card mb-2">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <div class="fw-semibold"><?= htmlspecialchars($pl['name'] ?? $slug, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="small gdy-plugin-meta text-muted"><?= h(__('t_9c31d5ad17', 'مجلد:')) ?> <code><?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?></code></div>
                  </div>
                  <div>
                    <?php if (!empty($pl['enabled'])): ?>
                      <span class="badge bg-success"><?= h(__('t_641298ecec', 'مفعّلة')) ?></span>
                    <?php else: ?>
                      <span class="badge bg-secondary"><?= h(__('t_2fab10b091', 'معطّلة')) ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <?php if (!empty($pl['description'])): ?>
                  <div class="small mt-2 gdy-plugin-desc"><?= htmlspecialchars($pl['description'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mt-3">
                  <form method="post" class="m-0">
                    <input type="hidden" name="action" value="toggle_plugin">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-sm <?= !empty($pl['enabled']) ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                      <?= !empty($pl['enabled']) ? __('t_43ead21245', 'تعطيل') : __('t_8403358516', 'تفعيل') ?>
                    </button>
                  </form>

                  <a href="settings.php?slug=<?= urlencode($slug) ?>" class="btn btn-sm btn-outline-info"><?= h(__('t_1f60020959', 'الإعدادات')) ?></a>
                </div>

                <div class="small text-muted mt-2">
                  <?= !empty($pl['version']) ? (__('t_1425cbc31c', 'الإصدار: ').htmlspecialchars($pl['version'], ENT_QUOTES, 'UTF-8')) : '' ?>
                  <?= (!empty($pl['version']) && !empty($pl['author'])) ? ' • ' : '' ?>
                  <?= !empty($pl['author']) ? (__('t_d86ca392f0', 'المطوِّر: ').htmlspecialchars($pl['author'], ENT_QUOTES, 'UTF-8')) : '' ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
