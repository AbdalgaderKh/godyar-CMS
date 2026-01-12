<?php
declare(strict_types=1);

/**
 * godyar/article_embeds.php
 * تضمين معاينات المرفقات داخل متن الخبر (PDF/Word/Excel) بطريقة آمنة.
 *
 * يعتمد على وجود $baseUrl من base_url().
 */
$__base = isset($baseUrl) ? rtrim((string)$baseUrl, '/') : '';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($__base . '/assets/css/gdy-embeds.css', ENT_QUOTES, 'UTF-8') ?>?v=2">
<script>
  window.GDY_BASE_URL = <?= json_encode($__base, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= htmlspecialchars($__base . '/assets/js/gdy-embeds.js', ENT_QUOTES, 'UTF-8') ?>?v=2" defer></script>