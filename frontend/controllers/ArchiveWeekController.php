<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$year=(int)($_GET['year']??0); $week=(int)($_GET['week']??0);
if (!$year||!$week) { http_response_code(404); exit('Not found'); }
$dto = new DateTime();
$dto->setISODate($year, $week);
$start = $dto->format('Y-m-d 00:00:00');
$dto->modify('+6 days');
$end = $dto->format('Y-m-d 23:59:59');

$page=max(1,(int)($_GET['page']??1)); $perPage=12; $offset=($page-1)*$perPage;

$items=[]; $total=0;
try {
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM news WHERE status='published' AND publish_at BETWEEN :s AND :e");
  $cnt->execute([':s'=>$start,':e'=>$end]); $total=(int)$cnt->fetchColumn();
  $lim=(int)$perPage; $off=(int)$offset;
  $sql="SELECT id,slug,featured_image,title,excerpt,publish_at FROM news WHERE status='published' AND publish_at BETWEEN :s AND :e ORDER BY publish_at DESC LIMIT $lim OFFSET $off";
  $st=$pdo->prepare($sql); $st->execute([':s'=>$start,':e'=>$end]); $items=$st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e){ error_log('WEEK_ARCHIVE_ERROR: '.$e->getMessage()); }

$pages=max(1,(int)ceil($total/$perPage));
$seo_title="أرشيف الأسبوع {$year}-W{$week}"; $seo_description="جميع الأخبار خلال هذا الأسبوع"; $canonical="/archive/week/%d-W%d";
require __DIR__ . '/../views/archive_week.php';
