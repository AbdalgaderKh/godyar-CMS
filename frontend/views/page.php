<?php
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// baseUrl (لدعم التثبيت داخل مجلد فرعي)
$baseUrl = function_exists('base_url')
    ? rtrim((string)base_url(), '/')
    : (defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : ((isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] : '')));
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>

    <?php require ROOT_PATH . '/frontend/views/partials/theme_head.php'; ?>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<?php include __DIR__ . '/partials/seo_head.php'; ?>
<link rel="stylesheet" href="<?= h($baseUrl . '/assets/css/home.css?v=1') ?>" />
</head>
<body>
<header class="site-header">
  <div class="container">
	    <div class="branding"><a href="<?= h($baseUrl . '/') ?>" class="logo"><?= h($site_name ?? 'Godyar') ?></a></div>
    <nav class="main-nav">
      <?php foreach (($main_menu ?? []) as $item): ?>
        <a href="<?= h($item['url'] ?? '#') ?>"><?= h($item['title'] ?? '') ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<main class="container">
  <article style="max-width:860px;margin:22px auto">
    <h1 style="margin:12px 0"><?= h($pageRow['title'] ?? '') ?></h1>
    <div class="content" style="line-height:1.9;font-size:17px">
      <?= $pageRow['content'] ?? '' /* HTML موثوق من لوحة التحكم */ ?>
    </div>
  </article>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div><h4>عن الموقع</h4><p><?= h($footer_about ?? '') ?></p></div>
    <div><h4>روابط</h4><?php foreach (($footer_links ?? []) as $l): ?><a href="<?= h($l['url'] ?? '#') ?>"><?= h($l['title'] ?? '') ?></a><br><?php endforeach; ?></div>
    <div><h4>تابعنا</h4><div class="socials"><?php foreach (($social_links ?? []) as $s): ?><a href="<?= h($s['url'] ?? '#') ?>"><?= h($s['icon'] ?? ($s['name'] ?? '')) ?></a><?php endforeach; ?></div></div>
  </div>
  <div class="copy">© <?= date('Y') ?> <?= h($site_name ?? 'Godyar') ?></div>
</footer>
</body>
</html>
