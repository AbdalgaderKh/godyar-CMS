<?php
declare(strict_types=1);

require_once __DIR__ . '/../_admin_guard.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

$currentPage  = 'analytics';
$pageTitle    = __('t_admin_heatmap', 'خريطة النشاط');
$pageSubtitle = __('t_admin_heatmap_sub', 'عرض أكثر أوقات النشاط للنشر والتعليقات (اليوم/الساعة)');

$adminBase = rtrim((string)(defined('GODYAR_BASE_URL') ? GODYAR_BASE_URL : ''), '/') . '/admin';
$breadcrumbs = [__('t_a06ee671f4', 'لوحة التحكم') => $adminBase . '/index.php', __('t_admin_analytics','التحليلات') => $adminBase . '/analytics/reports.php', $pageTitle => null];

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$days = (int)($_GET['days'] ?? 30);
if ($days < 7) $days = 7;
if ($days > 180) $days = 180;

$pdo = null;
try { $pdo = \Godyar\DB::pdo(); } catch (\Throwable $e) {}

$mapNews = array_fill(0, 7, array_fill(0, 24, 0));
$mapComments = array_fill(0, 7, array_fill(0, 24, 0));

if ($pdo) {
  // News heatmap
  try {
    $stmt = $pdo->prepare("
      SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS hr, COUNT(*) AS c
      FROM news
      WHERE created_at >= (NOW() - INTERVAL :days DAY)
      GROUP BY dow, hr
    ");
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
      $dow = (int)($r['dow'] ?? 0); // 1=Sunday .. 7=Saturday
      $hr  = (int)($r['hr'] ?? 0);
      $c   = (int)($r['c'] ?? 0);
      if ($dow >= 1 && $dow <= 7 && $hr >= 0 && $hr <= 23) {
        $mapNews[$dow-1][$hr] = $c;
      }
    }
  } catch (\Throwable $e) { /* ignore */ }

  // Comments heatmap (optional)
  try {
    $chk = $pdo->query("SHOW TABLES LIKE 'comments'");
    $has = $chk && $chk->fetchColumn();
    if ($has) {
      $stmt = $pdo->prepare("
        SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS hr, COUNT(*) AS c
        FROM comments
        WHERE created_at >= (NOW() - INTERVAL :days DAY)
        GROUP BY dow, hr
      ");
      $stmt->bindValue(':days', $days, PDO::PARAM_INT);
      $stmt->execute();
      foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
        $dow = (int)($r['dow'] ?? 0);
        $hr  = (int)($r['hr'] ?? 0);
        $c   = (int)($r['c'] ?? 0);
        if ($dow >= 1 && $dow <= 7 && $hr >= 0 && $hr <= 23) {
          $mapComments[$dow-1][$hr] = $c;
        }
      }
    }
  } catch (\Throwable $e) { /* ignore */ }
}

$pageActionsHtml = '<form class="d-flex gap-2 align-items-center" method="get" action="">' .
  '<label class="form-label mb-0 small text-muted">'.h(__('t_admin_period_days','الفترة (يوم)')).'</label>' .
  '<select name="days" class="form-select form-select-sm" style="width:auto">' .
    implode('', array_map(function($v) use ($days) {
      $sel = ($v===$days) ? ' selected' : '';
      return '<option value="'.(int)$v.'"'.$sel.'>'.(int)$v.'</option>';
    }, [7,14,30,60,90,180])) .
  '</select>' .
  '<button class="btn btn-primary btn-sm" type="submit">'.h(__('t_admin_apply','تطبيق')).'</button>' .
  '</form>';

require_once __DIR__ . '/../layout/app_start.php';

$daysLabels = [
  __('t_admin_sun','الأحد'),
  __('t_admin_mon','الإثنين'),
  __('t_admin_tue','الثلاثاء'),
  __('t_admin_wed','الأربعاء'),
  __('t_admin_thu','الخميس'),
  __('t_admin_fri','الجمعة'),
  __('t_admin_sat','السبت'),
];

function render_map(array $map, array $daysLabels, string $title): void {
  $max = 0;
  foreach ($map as $row) foreach ($row as $v) $max = max($max, (int)$v);
  if ($max < 1) $max = 1;

  echo '<div class="card mb-4">';
  echo '<div class="card-header"><b>'.h($title).'</b></div>';
  echo '<div class="card-body">';
  echo '<div class="table-responsive">';
  echo '<table class="table table-sm table-bordered align-middle text-center" style="table-layout:fixed">';
  echo '<thead><tr><th style="width:90px"></th>';
  for ($h=0; $h<24; $h++) echo '<th class="small text-muted">'.$h.'</th>';
  echo '</tr></thead><tbody>';
  for ($d=0; $d<7; $d++) {
    echo '<tr><th class="text-start">'.h($daysLabels[$d] ?? '').'</th>';
    for ($h=0; $h<24; $h++) {
      $v = (int)$map[$d][$h];
      $alpha = 0.08 + (0.55 * ($v / $max));
      $style = 'background: rgba(13,110,253,'.number_format($alpha,3,'.','').');';
      $tip = h((string)$v);
      echo '<td title="'.$tip.'" style="'.$style.'font-size:12px">'.($v>0? $v : '').'</td>';
    }
    echo '</tr>';
  }
  echo '</tbody></table></div>';
  echo '<div class="text-muted small mt-2">'.h(__('t_admin_heatmap_hint','ملاحظة: كل خلية تمثل عدد العناصر في ساعة معينة داخل يوم الأسبوع.')).'</div>';
  echo '</div></div>';
}
?>

<?php if (!$pdo): ?>
  <div class="alert alert-warning"><?= h(__('t_admin_db_error','تعذر الاتصال بقاعدة البيانات.')) ?></div>
<?php else: ?>
  <?php render_map($mapNews, $daysLabels, __('t_admin_heatmap_news','نشاط إنشاء الأخبار')); ?>
  <?php render_map($mapComments, $daysLabels, __('t_admin_heatmap_comments','نشاط التعليقات (إن وجد الجدول)')); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../layout/app_end.php'; ?>
