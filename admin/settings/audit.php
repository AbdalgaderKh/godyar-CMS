<?php
declare(strict_types=1);

require_once __DIR__ . '/_settings_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

$logFile = ROOT_PATH . '/storage/logs/audit.log';
$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 300);
if ($limit <= 0 || $limit > 3000) $limit = 300;

$lines = [];
if (is_file($logFile) && is_readable($logFile)) {
    // read last N lines efficiently
    $data = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $data = array_slice($data, max(0, count($data) - $limit));
    if ($q !== '') {
        foreach ($data as $ln) {
            if (stripos($ln, $q) !== false) $lines[] = $ln;
        }
    } else {
        $lines = $data;
    }
}

$pageTitle = 'سجل النشاط (Audit Log)';
?>
<?php include __DIR__ . '/../_header.php'; ?>

<div class="container my-4">
  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="m-0"><?= h($pageTitle) ?></h4>
      <div class="d-flex gap-2">
        <?php if (is_file($logFile)): ?>
          <a class="btn btn-sm btn-outline-primary" href="/admin/settings/audit_download.php" target="_blank" rel="noopener">تحميل الملف</a>
        <?php endif; ?>
        <a class="btn btn-sm btn-secondary" href="/admin/settings/tools.php">رجوع</a>
      </div>
    </div>

    <form class="row g-2 mb-3" method="get">
      <div class="col-md-6">
        <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="ابحث داخل السجل (IP / user / action) ...">
      </div>
      <div class="col-md-2">
        <input class="form-control" name="limit" type="number" value="<?= (int)$limit ?>" min="50" max="3000" step="50">
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">عرض</button>
        <a class="btn btn-outline-secondary" href="/admin/settings/audit.php">مسح البحث</a>
      </div>
    </form>

    <?php if (!is_file($logFile)): ?>
      <div class="alert alert-warning mb-0">لا يوجد ملف audit.log حتى الآن. سيتم إنشاؤه تلقائيًا عند تسجيل دخول/خروج أو أي أحداث تدعم السجل.</div>
    <?php else: ?>
      <div style="max-height:65vh; overflow:auto; background:#0b1220; color:#e5e7eb; border-radius:12px; padding:12px; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace; font-size:12px;">
        <?php if (!$lines): ?>
          <div style="opacity:.8">لا توجد نتائج.</div>
        <?php else: ?>
          <?php foreach ($lines as $ln): ?>
            <div><?= h($ln) ?></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../_footer.php'; ?>
