<?php
$seo_title = $seo_title ?? ($page_title ?? ($site_name ?? ''));
$seo_description = $seo_description ?? '';
$seo_image = $seo_image ?? '';
$canonical = $canonical ?? ($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
?>
<title><?= htmlspecialchars($seo_title) ?></title>
<meta name="description" content="<?= htmlspecialchars($seo_description) ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonical) ?>" />
<meta property="og:title" content="<?= htmlspecialchars($seo_title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($seo_description) ?>">
<?php if ($seo_image): ?><meta property="og:image" content="<?= htmlspecialchars($seo_image) ?>"><?php endif; ?>
<meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
<meta property="og:type" content="article">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($seo_title) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($seo_description) ?>">
<?php if ($seo_image): ?><meta name="twitter:image" content="<?= htmlspecialchars($seo_image) ?>"><?php endif; ?>
