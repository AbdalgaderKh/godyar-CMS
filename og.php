<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/**
 * OG Image generator (GD + optional Imagick/Pango) with admin-configurable settings.
 * - Safe local template/logo only (no external fetching).
 * - Arabic shaping (optional) for better GD/Imagick rendering.
 * - Auto engine switch: prefer Imagick (+Pango when available) then GD.
 */

// =====================
// Helpers
// =====================
function og_hex_to_rgb(string $hex, array $fallback): array {
  $hex = trim($hex);
  if ($hex === '') return $fallback;
  if ($hex[0] === '#') $hex = substr($hex, 1);
  if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return $fallback;
  return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}

function og_is_local_path(string $path): bool {
  $path = trim($path);
  if ($path === '') return false;
  if (preg_match('#^https?://#i', $path)) return false; // forbid remote
  if (strpos($path, "\0") !== false) return false;
  if (strpos($path, '..') !== false) return false;
  return true;
}

function og_resolve_local(string $path): string {
  $path = ltrim(trim($path), '/');
  return rtrim((string)ROOT_PATH, '/\\') . '/' . $path;
}

// Very small Arabic shaping for GD (presentation forms) + basic bidi
function og_contains_arabic(string $s): bool {
  return (bool)preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $s);
}

function og_mb_chars(string $s): array {
  return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

// Arabic joining types
function og_ar_join_type(string $ch): int {
  // 0 = not arabic/joining, 1 = right-joining only, 2 = dual-joining
  static $rightOnly = null;
  static $dual = null;
  if ($rightOnly === null) {
    $rightOnly = array_flip([
      'ا','أ','إ','آ','د','ذ','ر','ز','و','ؤ','ء','ى','ة'
    ]);
    $dual = array_flip([
      'ب','ت','ث','ج','ح','خ','س','ش','ص','ض','ط','ظ','ع','غ','ف','ق','ك','ل','م','ن','ه','ي','ئ','پ','چ','ڤ','گ'
    ]);
  }
  if (isset($dual[$ch])) return 2;
  if (isset($rightOnly[$ch])) return 1;
  // Tatweel and some marks are non-joining; treat as 0
  return 0;
}

function og_ar_forms_map(): array {
  // Map base letter to [isolated, final, initial, medial] presentation forms
  // Only for common Arabic letters.
  return [
    'ا' => ["\u{FE8D}","\u{FE8E}","",""],
    'أ' => ["\u{FE83}","\u{FE84}","",""],
    'إ' => ["\u{FE87}","\u{FE88}","",""],
    'آ' => ["\u{FE81}","\u{FE82}","",""],
    'ب' => ["\u{FE8F}","\u{FE90}","\u{FE91}","\u{FE92}"],
    'ت' => ["\u{FE95}","\u{FE96}","\u{FE97}","\u{FE98}"],
    'ث' => ["\u{FE99}","\u{FE9A}","\u{FE9B}","\u{FE9C}"],
    'ج' => ["\u{FE9D}","\u{FE9E}","\u{FE9F}","\u{FEA0}"],
    'ح' => ["\u{FEA1}","\u{FEA2}","\u{FEA3}","\u{FEA4}"],
    'خ' => ["\u{FEA5}","\u{FEA6}","\u{FEA7}","\u{FEA8}"],
    'د' => ["\u{FEA9}","\u{FEAA}","",""],
    'ذ' => ["\u{FEAB}","\u{FEAC}","",""],
    'ر' => ["\u{FEAD}","\u{FEAE}","",""],
    'ز' => ["\u{FEAF}","\u{FEB0}","",""],
    'س' => ["\u{FEB1}","\u{FEB2}","\u{FEB3}","\u{FEB4}"],
    'ش' => ["\u{FEB5}","\u{FEB6}","\u{FEB7}","\u{FEB8}"],
    'ص' => ["\u{FEB9}","\u{FEBA}","\u{FEBB}","\u{FEBC}"],
    'ض' => ["\u{FEBD}","\u{FEBE}","\u{FEBF}","\u{FEC0}"],
    'ط' => ["\u{FEC1}","\u{FEC2}","\u{FEC3}","\u{FEC4}"],
    'ظ' => ["\u{FEC5}","\u{FEC6}","\u{FEC7}","\u{FEC8}"],
    'ع' => ["\u{FEC9}","\u{FECA}","\u{FECB}","\u{FECC}"],
    'غ' => ["\u{FECD}","\u{FECE}","\u{FECF}","\u{FED0}"],
    'ف' => ["\u{FED1}","\u{FED2}","\u{FED3}","\u{FED4}"],
    'ق' => ["\u{FED5}","\u{FED6}","\u{FED7}","\u{FED8}"],
    'ك' => ["\u{FED9}","\u{FEDA}","\u{FEDB}","\u{FEDC}"],
    'ل' => ["\u{FEDD}","\u{FEDE}","\u{FEDF}","\u{FEE0}"],
    'م' => ["\u{FEE1}","\u{FEE2}","\u{FEE3}","\u{FEE4}"],
    'ن' => ["\u{FEE5}","\u{FEE6}","\u{FEE7}","\u{FEE8}"],
    'ه' => ["\u{FEE9}","\u{FEEA}","\u{FEEB}","\u{FEEC}"],
    'و' => ["\u{FEED}","\u{FEEE}","",""],
    'ؤ' => ["\u{FE85}","\u{FE86}","",""],
    'ي' => ["\u{FEF1}","\u{FEF2}","\u{FEF3}","\u{FEF4}"],
    'ى' => ["\u{FEEF}","\u{FEF0}","",""],
    'ئ' => ["\u{FE89}","\u{FE8A}","\u{FE8B}","\u{FE8C}"],
    'ة' => ["\u{FE93}","\u{FE94}","",""],
  ];
}

function og_ar_lam_alef_ligature(string $alef): array {
  // return [isolated, final]
  switch ($alef) {
    case 'ا': return ["\u{FEFB}","\u{FEFC}"];
    case 'أ': return ["\u{FEF7}","\u{FEF8}"];
    case 'إ': return ["\u{FEF9}","\u{FEFA}"];
    case 'آ': return ["\u{FEF5}","\u{FEF6}"];
    default:  return ["",""];
  }
}

function og_ar_shape(string $s): string {
  $chars = og_mb_chars($s);
  if (empty($chars)) return $s;

  $forms = og_ar_forms_map();
  $out = [];

  for ($i=0; $i<count($chars); $i++) {
    $ch = $chars[$i];

    // Preserve whitespace and punctuation
    if (!og_contains_arabic($ch) && !preg_match('/[A-Za-z0-9]/u', $ch)) {
      $out[] = $ch;
      continue;
    }

    // Lam-Alef ligature
    if ($ch === 'ل' && isset($chars[$i+1])) {
      $next = $chars[$i+1];
      $lig = og_ar_lam_alef_ligature($next);
      if ($lig[0] !== '') {
        // Determine if it connects to previous
        $prev = $out ? end($out) : '';
        // We need prev original joining; approximated by checking original previous char
        $prevOrig = ($i>0) ? $chars[$i-1] : '';
        $prevType = og_ar_join_type($prevOrig);
        $connectPrev = ($prevType === 2); // prev can connect to left
        $out[] = $connectPrev ? $lig[1] : $lig[0];
        $i++; // skip alef
        continue;
      }
    }

    $type = og_ar_join_type($ch);
    if ($type === 0 || !isset($forms[$ch])) {
      $out[] = $ch;
      continue;
    }

    $prev = ($i>0) ? $chars[$i-1] : '';
    $next = ($i+1<count($chars)) ? $chars[$i+1] : '';

    $prevType = og_ar_join_type($prev);
    $nextType = og_ar_join_type($next);

    $canJoinPrev = ($prevType === 2) && ($type >= 1); // prev dual, current joinable on right
    $canJoinNext = ($type === 2) && ($nextType >= 1); // current dual, next joinable on right

    $f = $forms[$ch]; // [iso, fin, ini, med]
    if ($canJoinPrev && $canJoinNext && $f[3] !== '') {
      $out[] = $f[3]; // medial
    } elseif ($canJoinPrev && $f[1] !== '') {
      $out[] = $f[1]; // final
    } elseif ($canJoinNext && $f[2] !== '') {
      $out[] = $f[2]; // initial
    } else {
      $out[] = $f[0]; // isolated
    }
  }

  $shaped = implode('', $out);

  // Basic bidi: reverse full string then flip ascii runs back
  $rev = array_reverse(og_mb_chars($shaped));
  $revStr = implode('', $rev);

  // Fix numbers/latin runs: reverse each run back
  $revStr = preg_replace_callback('/[A-Za-z0-9%#@:_\.\-\/\+]+/u', function($m){
    $arr = og_mb_chars($m[0]);
    return implode('', array_reverse($arr));
  }, $revStr);

  return $revStr;
}

// Word wrap for TTF
function og_wrap_lines(string $text, int $maxChars): array {
  $words = preg_split('/\s+/', $text) ?: [];
  $lines = [];
  $line = '';
  foreach ($words as $w) {
    $try = $line === '' ? $w : ($line . ' ' . $w);
    if (mb_strlen($try) <= $maxChars) {
      $line = $try;
    } else {
      if ($line !== '') $lines[] = $line;
      $line = $w;
    }
  }
  if ($line !== '') $lines[] = $line;
  return $lines;
}

// =====================
// Imagick helpers (optional)
// =====================
function og_imagick_available(): bool {
  return extension_loaded('imagick') && class_exists('Imagick');
}

function og_imagick_has_pango(): bool {
  static $cached = null;
  if ($cached !== null) return (bool)$cached;
  $cached = false;
  if (!og_imagick_available()) return false;
  try {
    $fmts = Imagick::queryFormats();
    foreach ($fmts as $f) {
      if (strtoupper((string)$f) === 'PANGO') { $cached = true; return true; }
    }
  } catch (Throwable $e) {
    // ignore
  }
  // Final runtime probe: try rendering a tiny pango image
  try {
    $t = new Imagick();
    $t->setBackgroundColor(new ImagickPixel('transparent'));
    $t->readImage('pango:<span font_desc="DejaVu Sans Bold 18">Test</span>');
    $t->clear();
    $t->destroy();
    $cached = true;
    return true;
  } catch (Throwable $e) {
    $cached = false;
    return false;
  }
}

function og_imagick_safe_read_local(string $localPath): ?Imagick {
  try {
    if (!is_file($localPath)) return null;
    $im = new Imagick();
    $im->readImage($localPath);
    $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
    return $im;
  } catch (Throwable $e) {
    return null;
  }
}

function og_imagick_pango_text_image(string $text, string $fontFile, int $fontSize, string $hexColor): ?Imagick {
  if (!og_imagick_has_pango()) return null;
  try {
    $safe = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    // Use pango markup; font file path works on most ImageMagick builds.
    $markup = '<span font_desc="DejaVu Sans Bold ' . (int)$fontSize . '" foreground="' . $hexColor . '">' . $safe . '</span>';
    $t = new Imagick();
    $t->setBackgroundColor(new ImagickPixel('transparent'));
    // Some builds prefer fontconfig names; keep DejaVu Sans Bold as a safe default.
    $t->readImage('pango:' . $markup);
    $t->setImageFormat('png');
    return $t;
  } catch (Throwable $e) {
    return null;
  }
}

function og_output_static(string $defaultPath): void {
  header('Content-Type: image/png');
  header('Cache-Control: public, max-age=86400');
  @readfile($defaultPath);
  exit;
}

function og_render_gd(string $title, string $siteName, string $tagline, array $og, string $arabicMode): void {
  $containsArabic = og_contains_arabic($title);
  $W = 1200; $H = 630;
  $img = imagecreatetruecolor($W, $H);
  imagesavealpha($img, true);
  $alpha = imagecolorallocatealpha($img, 0, 0, 0, 127);
  imagefill($img, 0, 0, $alpha);

  $bgRGB = og_hex_to_rgb((string)$og['bg_color'], [245,245,245]);
  $fgRGB = og_hex_to_rgb((string)$og['text_color'], [20,20,20]);
  $mutRGB= og_hex_to_rgb((string)$og['muted_color'], [75,85,99]);
  $acRGB = og_hex_to_rgb((string)$og['accent_color'], [17,24,39]);

  $bg  = imagecolorallocate($img, $bgRGB[0], $bgRGB[1], $bgRGB[2]);
  $fg  = imagecolorallocate($img, $fgRGB[0], $fgRGB[1], $fgRGB[2]);
  $mut = imagecolorallocate($img, $mutRGB[0], $mutRGB[1], $mutRGB[2]);
  $ac  = imagecolorallocate($img, $acRGB[0], $acRGB[1], $acRGB[2]);

  imagefilledrectangle($img, 0, 0, $W, $H, $bg);

  // Optional template image
  $template = (string)($og['template_image'] ?? '');
  if (og_is_local_path($template)) {
    $tp = og_resolve_local($template);
    if (is_file($tp)) {
      $ext = strtolower(pathinfo($tp, PATHINFO_EXTENSION));
      $src = null;
      if (in_array($ext, ['jpg','jpeg'], true)) $src = @imagecreatefromjpeg($tp);
      elseif ($ext === 'png') $src = @imagecreatefrompng($tp);
      elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($tp);
      if ($src) {
        imagecopyresampled($img, $src, 0, 0, 0, 0, $W, $H, imagesx($src), imagesy($src));
        imagedestroy($src);
      }
    }
  }

  // Accent bar
  imagefilledrectangle($img, 0, 0, $W, 14, $ac);

  // Optional logo (local)
  $logo = (string)($og['logo_image'] ?? '');
  if (og_is_local_path($logo)) {
    $lp = og_resolve_local($logo);
    if (is_file($lp)) {
      $ext = strtolower(pathinfo($lp, PATHINFO_EXTENSION));
      $limg = null;
      if (in_array($ext, ['jpg','jpeg'], true)) $limg = @imagecreatefromjpeg($lp);
      elseif ($ext === 'png') $limg = @imagecreatefrompng($lp);
      elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $limg = @imagecreatefromwebp($lp);
      if ($limg) {
        $maxW = 160; $maxH = 160;
        $w = imagesx($limg); $h = imagesy($limg);
        $scale = min($maxW / max(1,$w), $maxH / max(1,$h), 1.0);
        $nw = (int)round($w * $scale); $nh = (int)round($h * $scale);
        imagecopyresampled($img, $limg, 80, 60, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($limg);
      }
    }
  }

  $font = __DIR__ . '/assets/fonts/DejaVuSans-Bold.ttf';
  $canTTF = is_file($font) && function_exists('imagettftext');

  // Title rendering
  $drawTitle = $title;
  if ($containsArabic && ($arabicMode === 'auto' || $arabicMode === 'shape')) {
    $drawTitle = og_ar_shape($title);
  }

  if ($canTTF) {
    $lines = og_wrap_lines($drawTitle, 28);
    $size = 44;
    $x = 80;
    $y = 260;
    foreach (array_slice($lines, 0, 3) as $ln) {
      $bbox = imagettfbbox($size, 0, $font, $ln);
      $lh = abs($bbox[7] - $bbox[1]) + 18;
      imagettftext($img, $size, 0, $x, $y, $fg, $font, $ln);
      $y += $lh;
    }
    $metaText = trim($siteName . ($tagline ? ' — ' . $tagline : ''));
    if ($metaText !== '') {
      $size2 = 26;
      imagettftext($img, $size2, 0, 80, 560, $mut, $font, $metaText);
    }
  } else {
    imagestring($img, 5, 80, 260, $title, $fg);
  }

  header('Content-Type: image/png');
  header('Cache-Control: public, max-age=86400');
  imagepng($img);
  imagedestroy($img);
  exit;
}

function og_try_render_imagick(string $title, string $siteName, string $tagline, array $og, string $arabicMode): bool {
  if (!og_imagick_available()) return false;
  try {
    $W = 1200; $H = 630;
    $bg = (string)($og['bg_color'] ?? '#F5F5F5');
    $fg = (string)($og['text_color'] ?? '#141414');
    $mut= (string)($og['muted_color'] ?? '#4B5563');
    $ac = (string)($og['accent_color'] ?? '#111827');

    $img = new Imagick();
    $img->newImage($W, $H, new ImagickPixel($bg));
    $img->setImageFormat('png');
    $img->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);

    // Optional template
    $template = (string)($og['template_image'] ?? '');
    if (og_is_local_path($template)) {
      $tp = og_resolve_local($template);
      $tpl = og_imagick_safe_read_local($tp);
      if ($tpl) {
        $tpl->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $tpl->cropThumbnailImage($W, $H);
        $img->compositeImage($tpl, Imagick::COMPOSITE_OVER, 0, 0);
        $tpl->clear();
        $tpl->destroy();
      }
    }

    // Accent bar
    $bar = new ImagickDraw();
    $bar->setFillColor(new ImagickPixel($ac));
    $bar->rectangle(0, 0, $W, 14);
    $img->drawImage($bar);

    // Optional logo
    $logo = (string)($og['logo_image'] ?? '');
    if (og_is_local_path($logo)) {
      $lp = og_resolve_local($logo);
      $lg = og_imagick_safe_read_local($lp);
      if ($lg) {
        $maxW = 160; $maxH = 160;
        $lg->thumbnailImage($maxW, $maxH, true, true);
        $img->compositeImage($lg, Imagick::COMPOSITE_OVER, 80, 60);
        $lg->clear();
        $lg->destroy();
      }
    }

    $font = __DIR__ . '/assets/fonts/DejaVuSans-Bold.ttf';
    $containsArabic = og_contains_arabic($title);
    $titleToDraw = $title;
    // If no pango, shape for better RTL in annotate mode
    if ($containsArabic && ($arabicMode === 'auto' || $arabicMode === 'shape') && !og_imagick_has_pango()) {
      $titleToDraw = og_ar_shape($title);
    }

    // Draw title lines
    $lines = og_wrap_lines($titleToDraw, 28);
    $x = 80;
    $y = 260;
    $size = 44;
    foreach (array_slice($lines, 0, 3) as $ln) {
      $lineImg = null;
      if ($containsArabic && ($arabicMode === 'auto' || $arabicMode === 'shape')) {
        // Prefer Pango for perfect Arabic shaping/bidi
        $lineImg = og_imagick_pango_text_image($ln, $font, $size, $fg);
      }
      if ($lineImg) {
        $img->compositeImage($lineImg, Imagick::COMPOSITE_OVER, $x, $y - $size);
        $h = (int)$lineImg->getImageHeight();
        $lineImg->clear();
        $lineImg->destroy();
        $y += max(1, $h) + 18;
      } else {
        $draw = new ImagickDraw();
        if (is_file($font)) $draw->setFont($font);
        $draw->setFillColor(new ImagickPixel($fg));
        $draw->setFontSize($size);
        $img->annotateImage($draw, $x, $y, 0, $ln);
        $y += $size + 22;
      }
    }

    $metaText = trim($siteName . ($tagline ? ' — ' . $tagline : ''));
    if ($metaText !== '') {
      $m = new ImagickDraw();
      if (is_file($font)) $m->setFont($font);
      $m->setFillColor(new ImagickPixel($mut));
      $m->setFontSize(26);
      $img->annotateImage($m, 80, 560, 0, $metaText);
    }

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    echo $img->getImageBlob();
    $img->clear();
    $img->destroy();
    return true;
  } catch (Throwable $e) {
    return false;
  }
}

// =====================
// Input + Settings
// =====================
$title = isset($_GET['title']) ? trim((string)$_GET['title']) : 'Godyar';
$title = strip_tags($title);
if (mb_strlen($title) > 140) $title = mb_substr($title, 0, 140) . '…';

// Pull settings
$siteName = 'Godyar';
$tagline = '';
$og = [
  'enabled' => true,
  'mode' => 'dynamic',
  'engine' => 'auto', // auto | imagick | gd
  'default_image' => 'assets/images/og-default.png',
  'template_image' => '',
  'logo_image' => '',
  'bg_color' => '#F5F5F5',
  'text_color' => '#141414',
  'muted_color' => '#4B5563',
  'accent_color' => '#111827',
  'site_name' => '',
  'tagline' => '',
  'arabic_mode' => 'auto',
];

try {
  $pdo = gdy_pdo_safe();
  if ($pdo instanceof PDO) {
    $keys = [
      'site_name','site_tagline',
      'og.enabled','og.mode','og.engine','og.default_image','og.template_image','og.logo_image',
      'og.bg_color','og.text_color','og.muted_color','og.accent_color',
      'og.site_name','og.tagline','og.arabic_mode'
    ];
    $in = "'" . implode("','", array_map('addslashes', $keys)) . "'";
    $stmt = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN ($in)");
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $map = [];
    foreach ($rows as $r) { $map[(string)($r['key'] ?? '')] = (string)($r['value'] ?? ''); }

    if (!empty($map['site_name'])) $siteName = $map['site_name'];
    if (!empty($map['site_tagline'])) $tagline = $map['site_tagline'];

    foreach ($og as $k => $v) {
      $full = 'og.' . $k;
      if (isset($map[$full]) && $map[$full] !== '') $og[$k] = $map[$full];
    }
    if (isset($map['og.enabled'])) $og['enabled'] = ($map['og.enabled'] === '1');
    if (isset($map['og.site_name']) && $map['og.site_name'] !== '') $siteName = $map['og.site_name'];
    if (isset($map['og.tagline']) && $map['og.tagline'] !== '') $tagline = $map['og.tagline'];
  }
} catch (Throwable $e) {}

$mode = in_array($og['mode'], ['dynamic','static'], true) ? $og['mode'] : 'dynamic';
$arabicMode = in_array($og['arabic_mode'], ['auto','shape','static'], true) ? $og['arabic_mode'] : 'auto';
$containsArabic = og_contains_arabic($title);

$defaultPath = og_is_local_path((string)$og['default_image']) ? og_resolve_local((string)$og['default_image']) : '';
if (!$defaultPath || !is_file($defaultPath)) {
  $defaultPath = __DIR__ . '/assets/images/og-default.png';
}

if (!$og['enabled'] || $mode === 'static' || ($containsArabic && $arabicMode === 'static')) {
  og_output_static($defaultPath);
}

$engine = in_array((string)($og['engine'] ?? 'auto'), ['auto','imagick','gd'], true)
  ? (string)$og['engine']
  : 'auto';

// Prefer Imagick/Pango when possible (for excellent Arabic shaping/bidi)
if ($engine !== 'gd') {
  if (og_try_render_imagick($title, $siteName, $tagline, $og, $arabicMode)) {
    exit;
  }
}

// Fallback to GD (always available on most hosts)
if (function_exists('imagecreatetruecolor')) {
  og_render_gd($title, $siteName, $tagline, $og, $arabicMode);
}

// Last resort
og_output_static($defaultPath);
