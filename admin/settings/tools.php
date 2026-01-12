<?php
require_once __DIR__ . '/_settings_guard.php';
require_once __DIR__ . '/_settings_meta.php';
settings_apply_context();
require_once __DIR__ . '/../layout/app_start.php';

$notice = '';
$error  = '';

$pdo = function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null;



// Download audit log
if (isset($_GET['action']) && $_GET['action'] === 'download_audit') {
    $file = ROOT_PATH . '/storage/logs/audit.log';
    if (!is_file($file)) {
        $error = __('t_audit_missing', 'لا يوجد سجل نشاط بعد.');
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="audit.log"');
        readfile($file);
        exit;
    }
}

// Clear ratelimit files
if (isset($_GET['action']) && $_GET['action'] === 'clear_ratelimit') {
    $dir = ROOT_PATH . '/storage/ratelimit';
    $deleted = 0;
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.json') as $f) {
            if (@unlink($f)) $deleted++;
        }
    }
    $notice = __('t_rl_cleared', 'تم مسح ملفات الحد من المحاولات.') . ' (' . $deleted . ')';
}
// Export settings as JSON
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    try {
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('DB not available');
        }
        $rows = $pdo->query("SELECT `key`, `value` FROM settings ORDER BY `key` ASC")->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[(string)$r['key']] = (string)($r['value'] ?? '');
        }
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="settings-export-' . date('Ymd-His') . '.json"');
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    } catch (Throwable $e) {
        $error = __('t_4fa410044f', 'حدث خطأ أثناء التصدير.');
        @error_log('[settings_tools_export] ' . $e->getMessage());
    }
}

// Actions (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (function_exists('verify_csrf')) { verify_csrf(); }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'clear_cache') {
            $cacheDir = defined('ROOT_PATH') ? ROOT_PATH . '/cache' : (__DIR__ . '/../../cache');
            $deleted = 0;

            if (is_dir($cacheDir)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($it as $f) {
                    $path = $f->getPathname();
                    // لا نحذف الدليل الجذر نفسه
                    if ($f->isDir()) {
                        // لا نحذف مجلد cache نفسه
                        if (realpath($path) !== realpath($cacheDir)) {
                            @rmdir($path);
                        }
                    } else {
                        // اترك أي ملفات حماية إن وُجدت
                        if (basename($path) === '.htaccess') continue;
                        if (@unlink($path)) $deleted++;
                    }
                }
            }

            $notice = __('t_2b91fb1389', 'تم مسح الكاش بنجاح.') . ' (' . $deleted . ')';

        } elseif ($action === 'import_settings') {
            if (!$pdo instanceof PDO) {
                throw new RuntimeException('DB not available');
            }

            $raw = (string)($_POST['settings_json'] ?? '');
            $raw = trim($raw);

            if ($raw === '') {
                throw new InvalidArgumentException(__('t_b9f81100e5', 'يرجى إدخال JSON للإعدادات.'));
            }

            $data = json_decode($raw, true);
            if (!is_array($data)) {
                throw new InvalidArgumentException(__('t_7c2eda6568', 'JSON غير صالح.'));
            }

            // Basic validation for keys
            $clean = [];
            foreach ($data as $k => $v) {
                $k = trim((string)$k);
                if ($k === '' || strlen($k) > 120) {
                    continue;
                }
                // allow letters/numbers/._-
                if (!preg_match('~^[a-zA-Z0-9._\-]+$~', $k)) {
                    continue;
                }
                $clean[$k] = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
            }

            if (!$clean) {
                throw new InvalidArgumentException(__('t_7c2eda6568', 'لا توجد مفاتيح صالحة للاستيراد.'));
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (:k,:v) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            $count = 0;
            foreach ($clean as $k => $v) {
                $stmt->execute([':k' => $k, ':v' => $v]);
                $count++;
            }
            $pdo->commit();

            $notice = __('t_36112f9024', 'تم حفظ الإعدادات بنجاح.') . ' (' . $count . ')';

        } else {
            $error = __('t_7c2eda6568', 'طلب غير معروف.');
        }

    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $error ?: __('t_4fa410044f', 'حدث خطأ أثناء التنفيذ.');
        @error_log('[settings_tools] ' . $e->getMessage());
    }
}
?>

<div class="row g-3">
  <div class="col-md-3">
    <?php include __DIR__ . '/_settings_nav.php'; ?>
  </div>

  <div class="col-md-9">
    <div class="card p-4">
      <?php if ($notice): ?>
        <div class="alert alert-success"><?= h($notice) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
      <?php endif; ?>

      <h5 class="mb-3"><?= h(__('t_1f60020959', 'أدوات الإعدادات')) ?></h5>

      <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-primary" href="tools.php?action=export">⬇️ <?= h(__('t_eb12b2c44c', 'تصدير الإعدادات (JSON)')) ?></a>

        
        <a class="btn btn-outline-secondary" href="tools.php?action=download_audit">📄 <?= h(__('t_audit_dl', 'تحميل سجل النشاط (audit.log)')) ?></a>
        <a class="btn btn-outline-warning" href="tools.php?action=clear_ratelimit">🧹 <?= h(__('t_rl_clear', 'مسح ملفات الحد من المحاولات')) ?></a>
<form method="post" class="d-inline">
          <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <?php endif; ?>
          <input type="hidden" name="action" value="clear_cache">
          <button class="btn btn-outline-danger" type="submit" data-confirm='مسح الكاش؟'>🧹 <?= h(__('t_2b91fb1389', 'مسح الكاش')) ?></button>
        </form>
      </div>

      <div class="alert alert-info">
        <div class="mb-1"><strong>PHP:</strong> <?= h(PHP_VERSION) ?></div>
        <?php if (function_exists('base_url')): ?>
          <div><strong>Base URL:</strong> <?= h((string)base_url()) ?></div>
        <?php endif; ?>
      </div>

      <hr>

      <h6 class="mb-2"><?= h(__('t_679b77f47b', 'استيراد إعدادات (JSON)')) ?></h6>
      <p class="text-muted small mb-2">الصيغة: <code>{"site.name":"...","site.desc":"..."}</code></p>

      <form method="post">
        <?php if (function_exists('csrf_token')): ?>
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php endif; ?>
        <input type="hidden" name="action" value="import_settings">

        <textarea class="form-control" name="settings_json" rows="10" placeholder='{"site.name":"Godyar","site.desc":"..."}'></textarea>
        <div class="form-text">ملاحظة: يتم استيراد المفاتيح فقط التي تطابق <code>a-zA-Z0-9._-</code>.</div>
        <button class="btn btn-primary mt-3"><?= h(__('t_871a087a1d', 'استيراد')) ?></button>
      </form>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
