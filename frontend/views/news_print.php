<?php
/**
 * GDY v8.6 — Newspaper-style Print/PDF template for news
 * Route: /news/print/{id}
 * Query:
 *   ?autoprint=1 triggers window.print()
 *   ?noqr=1 hides QR
 */
declare(strict_types=1);
/** @var array $post */
/** @var string $baseUrl */
/** @var string $articleUrlFull */
/** @var array|null $category */

$title   = (string)($post['title'] ?? '');
$content = (string)($post['content'] ?? ($post['body'] ?? ''));
$dateRaw = (string)($post['date'] ?? ($post['published_at'] ?? ($post['created_at'] ?? '')));
$ts      = $dateRaw !== '' ? @strtotime($dateRaw) : false;
$dateFmt = $ts ? date('Y/m/d', $ts) : ($dateRaw !== '' ? $dateRaw : '');

$autoprint = isset($_GET['autoprint']) && (string)$_GET['autoprint'] === '1';
$hideQr    = isset($_GET['noqr']) && (string)$_GET['noqr'] === '1';

$baseUrl = rtrim((string)$baseUrl, '/');
$articleUrlFull = (string)$articleUrlFull;

$site = isset($GLOBALS['site_settings']) && is_array($GLOBALS['site_settings']) ? $GLOBALS['site_settings'] : [];
$siteName = (string)($site['site_name'] ?? 'Godyar News');
$siteDesc = (string)($site['site_desc'] ?? 'منصة إخبارية متكاملة');
$logoRaw  = (string)($site['site_logo'] ?? '');

$logoUrl = '';
if ($logoRaw !== '') {
  // If it's already absolute, keep it. Otherwise prefix with baseUrl.
  if (preg_match('~^https?://~i', $logoRaw)) {
    $logoUrl = $logoRaw;
  } else {
    $logoUrl = $baseUrl . '/' . ltrim($logoRaw, '/');
  }
}

$cover = (string)($post['image'] ?? ($post['cover'] ?? ($post['thumb'] ?? '')));
$coverUrl = '';
if ($cover !== '') {
  if (preg_match('~^https?://~i', $cover)) {
    $coverUrl = $cover;
  } else {
    $coverUrl = $baseUrl . '/' . ltrim($cover, '/');
  }
}

/**
 * Strip any background/highlight styles from rich content for clean print.
 * - Removes <mark> tags
 * - Removes bgcolor attributes
 * - Cleans style="" declarations that set background/background-color (even with !important)
 */
function gdy_strip_print_highlights(string $html): string
{
  // Best-effort: remove invalid UTF-8 bytes that can crash preg_* (PCRE) and cause HTTP 500 on some articles.
  if (function_exists('iconv')) {
    $fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $html);
    if (is_string($fixed) && $fixed !== '') {
      $html = $fixed;
    }
  }

  // Replace <mark> with <span>
  $tmp = @preg_replace('~<\s*mark\b~i', '<span', $html);
  if ($tmp !== null) { $html = $tmp; }
  $tmp = @preg_replace('~</\s*mark\s*>~i', '</span>', $html);
  if ($tmp !== null) { $html = $tmp; }

  // Remove legacy bgcolor="..."
  $tmp = @preg_replace('~\sbgcolor\s*=\s*("|\').*?\1~i', '', $html);
  if ($tmp !== null) { $html = $tmp; }

  // Clean style="..." (strip background colors/highlights)
  $tmp = @preg_replace_callback('~\sstyle\s*=\s*("|\')(.*?)\1~is', function ($m) {
    $quote = $m[1];
    $style = (string)($m[2] ?? '');

    // Remove background-related props
    $style = preg_replace('~\bbackground(?:-color)?\s*:\s*[^;]+;?~i', '', $style) ?? $style;
    $style = preg_replace('~\bbox-shadow\s*:\s*[^;]+;?~i', '', $style) ?? $style;
    $style = preg_replace('~\bfilter\s*:\s*[^;]+;?~i', '', $style) ?? $style;
    $style = preg_replace('~\bopacity\s*:\s*[^;]+;?~i', '', $style) ?? $style;

    // Tidy extra ; and whitespace
    $style = trim((string)$style);
    $style = preg_replace('~;{2,}~', ';', $style) ?? $style;
    $style = trim((string)$style, " \t\n\r\0\x0B;");

    if ($style === '') {
      return '';
    }
    return ' style=' . $quote . $style . $quote;
  }, $html);
  if ($tmp !== null) { $html = $tmp; }

  return (string)$html;
}


$contentClean = gdy_strip_print_highlights($content);

// Normalize images for print (no callbacks; avoids Closure-in-preg_replace issues on some deployments)
// Add loading/decoding if missing.
$tmpImg = preg_replace('~<img\b(?![^>]*\bloading=)([^>]*?)>~i', '<img loading="lazy" decoding="async"$1>', (string)$contentClean);
if ($tmpImg !== null) { $contentClean = $tmpImg; }
$catName = is_array($category) ? (string)($category['name'] ?? '') : '';
$catSlug = is_array($category) ? (string)($category['slug'] ?? '') : '';
$catUrl  = ($catSlug !== '') ? ($baseUrl . '/category/' . rawurlencode($catSlug)) : '';

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Optional Arabic webfont (falls back gracefully if blocked) -->
  <style>
    :root{
      --ink:#0b1220;
      --muted:#556076;
      --rule:#e5e7eb;
      --accent:#0ea5e9;
    }

    @page { size: A4; margin: 14mm 12mm; }

    html, body{
      margin:0; padding:0;
      background:#fff;
      color:var(--ink);
      font-family: "Cairo","Noto Naskh Arabic","Tajawal","Segoe UI",Tahoma,Arial,sans-serif;
      -webkit-text-size-adjust:100%;
      text-rendering:optimizeLegibility;
    }

    /* Container */
    .paper{
      max-width: 820px;
      margin: 0 auto;
      padding: 0;
    }

    /* Masthead */
    .mast{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:16px;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--ink);
      margin-bottom: 14px;
    }
    .brand{
      display:flex; align-items:center; gap:10px;
      min-width: 0;
    }
    .logo{
      width:44px; height:44px;
      border-radius: 10px;
      object-fit: contain;
    }
    .brandText{
      line-height:1.1;
      min-width: 0;
    }
    .siteName{
      font-size: 18px;
      font-weight: 900;
      letter-spacing: .2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .siteDesc{
      font-size: 12px;
      color: var(--muted);
      margin-top: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .meta{
      text-align:left;
      font-size: 12px;
      color: var(--muted);
      line-height: 1.6;
      white-space: nowrap;
    }
    .meta strong{ color: var(--ink); font-weight: 900; }

    /* Title */
    h1{
      font-size: 32px;
      line-height: 1.25;
      margin: 10px 0 8px;
      font-weight: 900;
      color:#000;
      opacity: 1 !important;
      filter: none !important;
      -webkit-text-fill-color: #000 !important;
    }

    /* Under-title bar */
    .subbar{
      display:flex;
      flex-wrap:wrap;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      padding: 8px 0 10px;
      border-bottom: 1px solid var(--rule);
      margin-bottom: 12px;
      font-size: 12px;
      color: var(--muted);
    }
    .subbar a{ color: var(--ink); text-decoration:none; font-weight: 800; }
    .subbar .dot{ margin: 0 6px; color: #9aa3b2; }

    .cover{
      margin: 12px 0 14px;
      break-inside: avoid;
      page-break-inside: avoid;
    }
    .cover img{
      width:100%;
      max-height: 420px;
      object-fit: cover;
      border-radius: 14px;
      display:block;
    }

    /* QR + URL strip */
    .qrline{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      padding: 10px 0 12px;
      border-bottom: 1px dashed var(--rule);
      margin-bottom: 14px;
    }
    .qr{
      display:flex;
      align-items:center;
      gap:10px;
      min-width: 0;
    }
    .qr img{
      width:92px; height:92px;
      display:block;
    }
    .qr small{
      color: var(--muted);
      font-size: 11px;
      display:block;
    }
    .url{
      font-size: 11px;
      color: var(--muted);
      text-align:left;
      direction:ltr;
      unicode-bidi: plaintext;
      overflow:hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 52%;
    }

    /* Content */
    .content{
      font-size: 18px;
      line-height: 1.95;
      word-break: break-word;
      overflow-wrap: anywhere;
    }
    .content p{
      margin: 0 0 14px;
      text-align: justify;
    }
    .content h2, .content h3, .content h4{
      margin: 18px 0 10px;
      font-weight: 900;
      color: #000;
      break-after: avoid;
      page-break-after: avoid;
    }
    .content img{
      max-width: 100%;
      height:auto;
      display:block;
      margin: 14px auto;
      border-radius: 12px;
      break-inside: avoid;
      page-break-inside: avoid;
    }
    .content figure{ margin: 14px 0; break-inside: avoid; page-break-inside: avoid; }
    .content figcaption{
      color: var(--muted);
      font-size: 12px;
      margin-top: 6px;
      text-align:center;
    }

    /* Hard reset of backgrounds/highlights */
    .content, .content *{
      background: transparent !important;
      background-color: transparent !important;
      box-shadow: none !important;
      text-shadow: none !important;
      border-image: none !important;
      filter: none !important;
      opacity: 1 !important;
      -webkit-text-fill-color: currentColor !important;
    }

    /* Print-only footer */
    .foot{
      margin-top: 18px;
      padding-top: 10px;
      border-top: 1px solid var(--rule);
      font-size: 11px;
      color: var(--muted);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
    }
    .foot .left{
      direction:ltr;
      unicode-bidi: plaintext;
      overflow:hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 55%;
    }
    .badge{
      font-weight: 900;
      color: var(--ink);
    }

    /* Clean print */
    @media print{
      a{ color: inherit !important; text-decoration: none !important; }
      .paper{ max-width: none; }
      .url{ max-width: 60%; }
    }
  </style>
</head>
<body>
  <div class="paper">

    <div class="mast">
      <div class="brand">
        <?php if ($logoUrl !== ''): ?>
          <img class="logo" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="logo"/>
        <?php endif; ?>
        <div class="brandText">
          <div class="siteName"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="siteDesc"><?= htmlspecialchars($siteDesc, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>

      <div class="meta">
        <?php if ($catName !== ''): ?>
          <div><strong>القسم:</strong> <?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($dateFmt !== ''): ?>
          <div><strong>التاريخ:</strong> <?= htmlspecialchars($dateFmt, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
      </div>
    </div>

    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

    <div class="subbar">
      <div>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>">الرئيسية</a>
        <?php if ($catName !== '' && $catUrl !== ''): ?>
          <span class="dot">•</span>
          <a href="<?= htmlspecialchars($catUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
      </div>
      <div class="url"><?= htmlspecialchars($articleUrlFull, ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <?php if ($coverUrl !== ''): ?>
      <div class="cover"><img src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="cover"/></div>
    <?php endif; ?>

    <?php if (!$hideQr): ?>
      <div class="qrline">
        <div class="qr">
          <div>
            <small>للوصول السريع للخبر عبر الهاتف</small>
            <small class="badge">امسح QR أو افتح الرابط</small>
          </div>
        </div>
        <div class="url"><?= htmlspecialchars($articleUrlFull, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    <?php endif; ?>

    <div class="content">
      <?= $contentClean /* trusted already in CMS */ ?>
    </div>

    <div class="foot">
      <div class="left"><?= htmlspecialchars($articleUrlFull, ENT_QUOTES, 'UTF-8') ?></div>
      <div>نسخة للطباعة — <span class="badge">GDY_BUILD: v9.0</span></div>
    </div>

  </div>

  <?php if ($autoprint): ?>
  <script>
    window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 250); });
  </script>
  <?php endif; ?>
</body>
</html>