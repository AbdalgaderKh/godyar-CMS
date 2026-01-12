<?php
declare(strict_types=1);

// GDY_BUILD: v9.0

require_once __DIR__ . '/../_admin_guard.php';

$BASE_DIR = dirname(__DIR__, 2);
require_once $BASE_DIR . '/includes/bootstrap.php';

$title = 'سجل أخطاء PHP';

function gdy_tail_file(string $path, int $maxLines = 200): array {
    if (!is_file($path) || !is_readable($path)) return [];
    $lines = [];
    $fp = @fopen($path, 'rb');
    if (!$fp) return [];
    $buffer = '';
    $pos = -1;
    $lineCount = 0;
    $stat = fstat($fp);
    $size = $stat['size'] ?? 0;
    if ($size === 0) { fclose($fp); return []; }
    $chunkSize = 4096;

    while ($lineCount < $maxLines && -$pos < $size) {
        $seek = max(-$size, $pos - $chunkSize);
        fseek($fp, $seek, SEEK_END);
        $chunk = fread($fp, abs($pos - $seek));
        $buffer = $chunk . $buffer;
        $pos = $seek;
        $lineCount = substr_count($buffer, "\n");
    }
    fclose($fp);
    $all = preg_split("/\r\n|\n|\r/", $buffer) ?: [];
    $all = array_values(array_filter($all, fn($x)=>$x!=='' ));
    return array_slice($all, -$maxLines);
}

$logPath = $BASE_DIR . '/php-error.log';
$max = (int)($_GET['lines'] ?? 200);
if ($max < 50) $max = 50;
if ($max > 2000) $max = 2000;

$lines = gdy_tail_file($logPath, $max);

// Clear log (admin only)
$cleared = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log'])) {
    verify_csrf();
    // safer: truncate not delete
    if (is_file($logPath) && is_writable($logPath)) {
        @file_put_contents($logPath, '');
        $cleared = true;
        $lines = [];
    }
}

// Download
if (isset($_GET['download']) && $_GET['download'] === '1') {
    if (!is_file($logPath) || !is_readable($logPath)) {
        http_response_code(404);
        echo "log not found";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="php-error.log"');
    readfile($logPath);
    exit;
}

include __DIR__ . '/../partials/admin_header.php';
?>
<div class="container" style="max-width: 1180px; margin: 0 auto; padding: 16px;">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <h2 style="margin:0"><?= htmlspecialchars($title) ?></h2>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <a class="btn btn-outline" href="?download=1" style="text-decoration:none">تحميل</a>
      <form method="get" style="display:flex;gap:6px;align-items:center;margin:0">
        <label style="opacity:.8">عدد السطور</label>
        <input name="lines" type="number" min="50" max="2000" value="<?= (int)$max ?>" style="width:90px">
        <button class="btn btn-outline" type="submit">تحديث</button>
      </form>
      <form method="post" style="margin:0">
        <?= csrf_token() ?>
        <button class="btn btn-danger" type="submit" name="clear_log" value="1" data-confirm='مسح سجل الأخطاء؟'>مسح السجل</button>
      </form>
    </div>
  </div>

  <?php if ($cleared): ?>
    <div class="alert alert-success" style="margin-top:12px">تم مسح سجل الأخطاء.</div>
  <?php endif; ?>

  <div style="margin-top:12px; padding:12px; border-radius:12px; background:#0b1220; color:#e5e7eb; overflow:auto; max-height:70vh; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px; line-height: 1.6;">
    <?php if (!$lines): ?>
      <div style="opacity:.8">لا توجد أخطاء أو الملف غير موجود: <?= htmlspecialchars($logPath) ?></div>
    <?php else: ?>
      <?php foreach ($lines as $ln): ?>
        <div><?= htmlspecialchars($ln) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div style="margin-top:10px;opacity:.7;font-size:12px">
    المسار: <?= htmlspecialchars($logPath) ?>
  </div>
</div>
<?php include __DIR__ . '/../partials/admin_footer.php'; ?>
