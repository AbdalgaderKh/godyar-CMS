<?php
/**
 * Partial: home_under_featured_video slot (slot #5)
 * يوضع مباشرة تحت الفيديو المميز داخل الصفحة الرئيسية.
 *
 * يعتمد على: \Godyar\Services\AdService
 */

$__adHtml = '';
try {
    if (isset($pdo) && $pdo instanceof PDO && class_exists('\Godyar\Services\AdService')) {
        $svc = new \Godyar\Services\AdService($pdo);
        $__adHtml = $svc->render('home_under_featured_video', $baseUrl ?? '');
    }
} catch (Throwable $e) {
    $__adHtml = '';
}

if ($__adHtml === '') {
    return;
}
?>
<style>
  /* Slot 5: تحت الفيديو المميز */
  .hm-under-featured-ad{
    margin-top: 14px;
  }
  .hm-under-featured-ad .gdy-ad-slot{
    width: 100%;
  }
  .hm-under-featured-ad .gdy-ad-link,
  .hm-under-featured-ad .gdy-ad-html{
    display:block;
    width:100%;
    aspect-ratio: 325 / 528;   /* نفس نسبة الصورة المرفقة */
    border-radius: 18px;
    overflow: hidden;
    background: #0b1120;
    border: 1px solid rgba(15,23,42,0.10);
    box-shadow: 0 16px 34px rgba(15,23,42,0.10);
  }
  .hm-under-featured-ad img{
    width:100%;
    height:100%;
    object-fit: cover;
    display:block;
  }
</style>

<div class="hm-under-featured-ad" aria-label="Advertisement">
  <?= $__adHtml ?>
</div>
