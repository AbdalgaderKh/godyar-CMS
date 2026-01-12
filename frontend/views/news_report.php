<?php
// godyar/frontend/views/news_report.php
// تقرير/خبر بنمط قريب من صفحات التقارير (عنوان + بيانات + شريط أدوات + ملخص + أقسام + جداول)

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// baseUrl
if (!isset($baseUrl) || $baseUrl === '') {
    if (function_exists('base_url')) {
        $baseUrl = rtrim((string)base_url(), '/');
    } elseif (defined('BASE_URL')) {
        $baseUrl = rtrim((string)BASE_URL, '/');
    } else {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }
} else {
    $baseUrl = rtrim((string)$baseUrl, '/');
}

if (!function_exists('gdy_image_url')) {
    /**
     * Build an absolute URL for a news image while normalizing old/duplicated paths.
     * Accepts:
     *  - full http(s) URL
     *  - absolute path /uploads/news/...
     *  - relative path (stored in DB) like uploads/news/..., or filename.jpg
     *
     * Fixes duplicated paths like: /uploads/news/uploads/news/...
     */
    function gdy_image_url(string $baseUrl, ?string $path): ?string
    {
        $path = trim((string)$path);
        if ($path === '') return null;

        // Full URL
        if (preg_match('~^https?://~i', $path)) return $path;

        // Normalize duplicated segments and leading slashes
        $path = ltrim($path, '/');
        // remove duplicated "uploads/news/uploads/news/"
        $path = preg_replace('~^(?:uploads/news/)+uploads/news/~i', 'uploads/news/', $path);
        // if stored with "uploads/news/" prefix, strip it because we will prefix once
        $path = preg_replace('~^uploads/news/~i', '', $path);
        $path = ltrim($path, '/');

        // If original was absolute path like "/something", keep it (after normalization)
        // (Note: we already trimmed leading '/', so handle explicitly)
        // If the normalized path still contains a leading directory like "assets/..." leave it as-is by returning baseUrl + "/" + path.
        if (preg_match('~^(assets|static|images?)/~i', $path)) {
            return rtrim($baseUrl, '/') . '/' . $path;
        }

        return rtrim($baseUrl, '/') . '/uploads/news/' . $path;
    }
}


if (!function_exists('gdy_plaintext')) {
    function gdy_plaintext(string $html): string
    {
        $txt = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // حوّل الوسوم الكتلية إلى أسطر للحفاظ على الفقرات
        $txt = preg_replace('~<\s*br\s*/?\s*>~i', "
", $txt);
        $txt = preg_replace('~</\s*(p|div|li|h1|h2|h3|h4|h5|h6|tr|blockquote)\s*>~i', "
", $txt);
        $txt = preg_replace('~<\s*(p|div|li|h1|h2|h3|h4|h5|h6|tr|blockquote)(\s[^>]*)?>~i', '', $txt);

        $txt = strip_tags($txt);
        $txt = preg_replace("/[ 	]+/u", " ", $txt);
        // وحّد الأسطر
        $txt = preg_replace("/
?/u", "
", $txt);
        $txt = preg_replace("/
{3,}/u", "

", $txt);
        return trim($txt);
    }
}

if (!function_exists('gdy_auto_summary_lines')) {
    /**
     * توليد ملخص سريع محلي (بدون API) من النص.
     * يحاول استخراج 4–6 جمل، وإن تعذر يأخذ مقاطع من بداية النص.
     */
    function gdy_auto_summary_lines(string $bodyHtml, int $maxLines = 5): array
    {
        $txt = gdy_plaintext($bodyHtml);
        if ($txt === '') return [];

        // قسم النص إلى جمل (عربي/إنجليزي)
        $parts = preg_split('~(?<=[\.!\?؟])\s+|[

]+~u', $txt);
        $lines = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;

            // اقبل الجمل الأقصر (العربية أحياناً قصيرة)
            $words = preg_split('/\s+/u', $p);
            if (count($words) < 4 && mb_strlen($p, 'UTF-8') < 40) continue;

            if (mb_strlen($p, 'UTF-8') > 180) {
                $p = mb_substr($p, 0, 180, 'UTF-8');
                $p = rtrim($p, " 	

\0
،,.") . '…';
            }
            $lines[] = $p;
            if (count($lines) >= $maxLines) break;
        }

        // احتياطي 1: فقرات
        if (count($lines) < 2) {
            $paras = preg_split("/
{2,}/u", $txt);
            foreach ($paras as $para) {
                $para = trim($para);
                if ($para === '') continue;
                if (mb_strlen($para, 'UTF-8') > 220) {
                    $para = mb_substr($para, 0, 220, 'UTF-8') . '…';
                }
                $lines[] = $para;
                if (count($lines) >= $maxLines) break;
            }
        }

        // احتياطي 2: خذ من بداية النص وقطّعه على الفواصل العربية
        if (count($lines) < 2) {
            $head = mb_substr($txt, 0, 520, 'UTF-8');
            $chunks = preg_split('~[،؛

\-]+~u', $head);
            foreach ($chunks as $c) {
                $c = trim($c);
                if ($c === '') continue;
                if (mb_strlen($c, 'UTF-8') < 25) continue;
                if (mb_strlen($c, 'UTF-8') > 200) $c = mb_substr($c, 0, 200, 'UTF-8') . '…';
                $lines[] = $c;
                if (count($lines) >= $maxLines) break;
            }
        }

        // إزالة التكرار
        $uniq = [];
        foreach ($lines as $l) {
            $k = mb_strtolower(preg_replace('/\s+/u',' ', $l), 'UTF-8');
            $uniq[$k] = $l;
        }
        return array_values($uniq);
    }
}

if (!function_exists('gdy_auto_summary_html')) {
    function gdy_auto_summary_html(string $bodyHtml): string
    {
        $lines = gdy_auto_summary_lines($bodyHtml, 5);
        if (!$lines) return '';
        $out = '<ul class="gdy-ai-list">';
        foreach ($lines as $l) {
            $out .= '<li>' . htmlspecialchars($l, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $out .= '</ul>';
        return $out;
    }
}

if (!function_exists('gdy_slugify_ar')) {
    // مبسط: يولّد id للحواشي/العناوين — لا يعتمد عليه كرابط عام
    function gdy_slugify_ar(string $s): string {
        $s = trim($s);
        $s = preg_replace('~\s+~u', ' ', $s);
        $s = mb_substr($s, 0, 80, 'UTF-8');
        // أبقِ العربية، واحذف الرموز الغريبة
        $s = preg_replace('~[^\p{L}\p{N}\s\-]+~u', '', $s);
        $s = preg_replace('~\s+~u', '-', $s);
        $s = trim($s, '-');
        return $s !== '' ? $s : 'sec';
    }
}

if (!function_exists('gdy_build_toc')) {
    /**
     * يضيف ids تلقائياً لـ h2/h3 ويبني جدول محتويات
     * @return array{html:string,toc:array<int,array{level:int,id:string,text:string}>}
     */
    function gdy_build_toc(string $html): array
    {
        $toc = [];
        $used = [];

        $cb = function(array $m) use (&$toc, &$used) {
            $tag = strtolower($m[1]); // h2/h3
            $attrs = (string)$m[2];
            $inner = (string)$m[3];

            $text = trim(strip_tags($inner));
            if ($text === '') {
                return $m[0];
            }

            $level = ($tag === 'h2') ? 2 : 3;

            // هل يوجد id؟
            if (preg_match('~\bid\s*=\s*"([^"]+)"~i', $attrs, $idm)) {
                $id = $idm[1];
            } else {
                $id = gdy_slugify_ar($text);
            }

            // ضمان التفرد
            $base = $id;
            $i = 2;
            while (isset($used[$id])) {
                $id = $base . '-' . $i;
                $i++;
            }
            $used[$id] = true;

            if (!preg_match('~\bid\s*=~i', $attrs)) {
                $attrs = trim($attrs) . ' id="' . h($id) . '"';
            }

            $toc[] = ['level' => $level, 'id' => $id, 'text' => $text];

            return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
        };

        // التقط h2/h3 مع محتواها (بدون كسر بنية معقدة)
        $html2 = preg_replace_callback('~<(h2|h3)([^>]*)>(.*?)</\1>~isu', $cb, $html);

        return ['html' => $html2 ?? $html, 'toc' => $toc];
    }
}

if (!function_exists('gdy_wrap_tables')) {
    function gdy_wrap_tables(string $html): string
    {
        // أضف class للجداول ولفّها بحاوية سكرول
        $html = preg_replace('~<table(\s[^>]*)?>~i', '<div class="gdy-table-wrap"><table$1 class="gdy-report-table">', $html);
        $html = preg_replace('~</table>~i', '</table></div>', $html);
        return $html;
    }
}

// ------------------------------------------------------------
// Data
// ------------------------------------------------------------
$post = $news ?? $article ?? [];
$postId = (int)($post['id'] ?? 0);


// URL of this article (used for sharing + QR)
$newsUrl = '';
if ($postId > 0) {
    $newsUrl = rtrim((string)$baseUrl, '/') . '/news/id/' . $postId;
}
$pageUrl = $newsUrl;

// QR API (fallback)
$qrApi = (isset($qrApi) && $qrApi !== '') ? (string)$qrApi : 'https://api.qrserver.com/v1/create-qr-code/?';
$qrApi = trim($qrApi);
if (strpos($qrApi, '?') === false) { $qrApi .= '?'; }
elseif (!str_ends_with($qrApi, '?') && !str_ends_with($qrApi, '&')) { $qrApi .= '&'; }

$pdo = $pdo ?? (function_exists('gdy_pdo_safe') ? gdy_pdo_safe() : null);

$title   = ($pdo instanceof PDO) ? (string)gdy_news_field($pdo, $post, 'title') : (string)($post['title'] ?? '');
$body    = ($pdo instanceof PDO) ? (string)gdy_news_field($pdo, $post, 'content') : (string)($post['content'] ?? ($post['body'] ?? ''));

// ✅ تم إزالة ميزة الترجمة نهائياً (لا يتم استخدام ?lang لترجمة المقال)
$excerpt = ($pdo instanceof PDO) ? (string)gdy_news_field($pdo, $post, 'excerpt') : (string)($post['excerpt'] ?? ($post['summary'] ?? ''));
$cover   = (string)($post['featured_image'] ?? ($post['image'] ?? ''));

$categoryName = (string)($post['category_name'] ?? ($category['name'] ?? 'أخبار عامة'));
$categorySlug = (string)($post['category_slug'] ?? ($category['slug'] ?? 'general-news'));

$date = (string)($post['published_at'] ?? ($post['publish_at'] ?? ($post['created_at'] ?? '')));
$views = (int)($post['views'] ?? 0);
$readMinutes = (int)($post['read_time'] ?? ($readingTime ?? 0));
if ($readMinutes <= 0) $readMinutes = 1;

// مصدر/كاتب
$sourceLabel = (string)($post['source'] ?? ($post['source_name'] ?? ''));
$authorName  = (string)($post['author_name'] ?? ($post['opinion_author_name'] ?? ''));
$opinionAuthorId = (int)($post['opinion_author_id'] ?? 0);
$opinionAuthorRow = null;

$authorUrl   = '';
if ($opinionAuthorId > 0) {
    // جلب بيانات كاتب الرأي (للصفحة + الكرت) إن توفرت
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmtOA = $pdo->prepare("
                SELECT id, name, slug, page_title, avatar, social_facebook, social_twitter, social_website, email
                FROM opinion_authors
                WHERE id = :id AND is_active = 1
                LIMIT 1
            ");
            $stmtOA->execute([':id' => $opinionAuthorId]);
            $opinionAuthorRow = $stmtOA->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $opinionAuthorRow = null;
        }
    }

    $slugOA = trim((string)($opinionAuthorRow['slug'] ?? ''));
    if ($slugOA !== '') {
        $authorUrl = rtrim($baseUrl, '/') . '/opinion_author.php?slug=' . rawurlencode($slugOA);
    } else {
        $authorUrl = rtrim($baseUrl, '/') . '/opinion_author.php?id=' . $opinionAuthorId;
    }

    // استخدام اسم الكاتب من جدول كتّاب الرأي إن وُجد
    if (!empty($opinionAuthorRow['name'])) {
        $authorName = (string)$opinionAuthorRow['name'];
    }
}

$showOpinionAuthorCard = ($opinionAuthorId > 0 && $opinionAuthorRow !== null);
$pageUrl = (string)$newsUrl;
// بيانات كرت الكاتب + روابط التواصل (فيس/تويتر/واتساب/إيميل)
$oaName      = $authorName;
$oaPageTitle = $showOpinionAuthorCard ? trim((string)($opinionAuthorRow['page_title'] ?? '')) : '';
$oaAvatarRaw = $showOpinionAuthorCard ? trim((string)($opinionAuthorRow['avatar'] ?? '')) : '';
$oaAvatar    = rtrim($baseUrl,'/') . '/assets/images/avatar.png';
if ($oaAvatarRaw !== '') {
    $oaAvatar = preg_match('~^https?://~i', $oaAvatarRaw)
        ? $oaAvatarRaw
        : (rtrim($baseUrl, '/') . '/' . ltrim($oaAvatarRaw, '/'));
}

$oaFacebook = $showOpinionAuthorCard ? trim((string)($opinionAuthorRow['social_facebook'] ?? '')) : '';
$oaTwitter  = $showOpinionAuthorCard ? trim((string)($opinionAuthorRow['social_twitter'] ?? '')) : '';
$oaEmail    = $showOpinionAuthorCard ? trim((string)($opinionAuthorRow['email'] ?? '')) : '';
$oaWebsite  = $showOpinionAuthorCard ? trim((string)($opinionAuthorRow['social_website'] ?? '')) : '';

$shareWhatsapp = 'https://wa.me/?text=' . rawurlencode($pageUrl);
$shareEmail    = 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($pageUrl);
// ملخص AI (اختياري) — إن لم يوجد يتم توليد ملخص سريع محلياً
$aiSummaryDb = (string)($post['ai_summary'] ?? ($post['summary_ai'] ?? ''));
$aiSummaryMode = $aiSummaryDb !== '' ? 'db' : 'auto';
$aiSummary = $aiSummaryDb !== '' ? $aiSummaryDb : gdy_auto_summary_html($body);
if ($aiSummaryDb === '' && $aiSummary === '') {
    $plain = gdy_plaintext($body);
    if ($plain !== '') {
        $plain = mb_substr($plain, 0, 320, 'UTF-8');
        $aiSummary = '<div style="line-height:1.9">' . h($plain) . (mb_strlen($plain,'UTF-8')>=320 ? '…' : '') . '</div>';
    }
}

$aiBtnLabel = ($aiSummaryMode === 'db') ? 'ملخص بالذكاء الاصطناعي' : 'ملخص سريع';
$aiBtnNote  = ($aiSummaryMode === 'auto') ? 'تم توليده تلقائياً' : '';


// (already defined earlier) $newsUrl is available here.
$coverUrl = gdy_image_url($baseUrl, $cover) ?: null;

// LCP: مرِّر صورة الغلاف للهيدر ليعمل Preload مبكراً + امنع التحميل الكسول
$pagePreloadImages = !empty($coverUrl) ? [$coverUrl] : [];

// SEO (الهيدر الموحد يقرأ $pageSeo)
$seoDesc = $excerpt !== '' ? $excerpt : mb_substr(trim(strip_tags($body)), 0, 170, 'UTF-8');
$publishedIso = '';
if ($date !== '') {
    $ts = @strtotime($date);
    if ($ts) $publishedIso = date('c', $ts);
}
$pageSeo = [
    'title' => $title !== '' ? ($title . (isset($siteName) && $siteName ? ' - ' . (string)$siteName : '')) : ((string)($siteName ?? '')),
    'description' => $seoDesc,
    'image' => $coverUrl,
    'url' => $newsUrl,
    'type' => 'article',
    'published_time' => $publishedIso,
];

// ------------------------------------------------------------
// Header include (if not wrapped by TemplateEngine)
// ------------------------------------------------------------
$isPrintMode = false;
try {
    $isPrintMode = isset($_GET['print']) && (string)$_GET['print'] === '1';
} catch (Throwable $e) {
    $isPrintMode = false;
}

// في وضع الطباعة/PDF: نضيف class للجسم لإخفاء هيدر/فوتر الموقع أثناء المعاينة أيضاً
if ($isPrintMode) {
    $themeClass = trim(((string)($themeClass ?? 'theme-default')) . ' gdy-print-mode');
}


// === Metered/Members Paywall logic moved BEFORE header include (fix headers already sent) ===
$membersOnly = isset($membersOnly)
    ? (bool)$membersOnly
    : (((int)($post['is_members_only'] ?? 0) === 1) || ((int)($category['is_members_only'] ?? 0) === 1));

$canReadFull = isset($canReadFull)
    ? (bool)$canReadFull
    : (!empty($isLoggedIn) || !empty($_SESSION['user']) || !empty($_SESSION['user_id']));

$meteredLocked = false;

$gdyMeterCookieToSet = null;
$gdyMeterCookieMaxAge = null;
// Metered Paywall (الخيار 2): حد قراءة مجاني للزائر (بدون تسجيل)
$meterLimit = 3; // 3 مقالات
$meterWindowSeconds = 7 * 24 * 60 * 60; // خلال أسبوع
$meterCount = 0;
$meterCurrentInWindow = false;

$isGuest = !$canReadFull;
if ($isGuest && !$membersOnly && $postId > 0) {
    $raw = (string)($_COOKIE['gdy_meter'] ?? '');
    $rawDecoded = $raw !== '' ? rawurldecode($raw) : '';
    $items = [];
    if ($raw !== '') {
        $decoded = json_decode($rawDecoded, true);
        if (is_array($decoded)) $items = $decoded;
    }

    $now = time();
    $fresh = [];
    $seen = [];
    foreach ($items as $it) {
        $id = (int)($it['id'] ?? 0);
        $t  = (int)($it['t'] ?? 0);
        if ($id > 0 && $t > 0 && ($now - $t) <= $meterWindowSeconds) {
            $fresh[] = ['id' => $id, 't' => $t];
            $seen[$id] = true;
        }
    }

    $meterCount = count($seen);
    $meterCurrentInWindow = isset($seen[$postId]);

    // إذا تجاوز حد القراءة المجانية (3/أسبوع) وقمت بفتح مقال جديد → قفل ذكي
    if ($meterCount >= $meterLimit && !$meterCurrentInWindow) {
        $meteredLocked = true;
    }

    // احفظ النسخة المُصفّاة في كوكي (لتقليل الحجم)
    if ($raw !== '' || !empty($fresh)) {
        // NOTE: لا نستخدم setcookie هنا لتجنب تحذيرات headers already sent على بعض الاستضافات.
        $gdyMeterCookieToSet = rawurlencode(json_encode($fresh, JSON_UNESCAPED_UNICODE));
        $gdyMeterCookieMaxAge = 30 * 24 * 60 * 60; // 30 days
    }
}


// === End moved block ===

$header = __DIR__ . '/partials/header.php';
$footer = __DIR__ . '/partials/footer.php';
if (!defined('GDY_TPL_WRAPPED') && is_file($header)) {
    require $header;
}

// Meter cookie update via JS (avoids headers already sent)
if (!empty($gdyMeterCookieToSet)) {
    $val = h($gdyMeterCookieToSet);
    $max = (int)($gdyMeterCookieMaxAge ?? (30*24*60*60));
    echo "<script>try{document.cookie='gdy_meter=' + '$val' + '; path=/; max-age=' + $max + '; samesite=lax';}catch(e){}</script>";
}

// Not found guard
$newsExists = $postId > 0 && $title !== '';
if (!$newsExists) {
    http_response_code(404);
    ?>
    <main class="layout-main">
      <div class="container">
        <div class="gdy-notfound">
          <div class="gdy-notfound-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>
          <h1>الخبر غير موجود</h1>
          <p>عذراً، لم نتمكن من العثور على الخبر الذي تبحث عنه.</p>
          <div class="gdy-notfound-actions">
            <a class="btn-primary" href="<?= h($baseUrl) ?>/">الرئيسية</a>
            <a class="btn-secondary" href="<?= h($baseUrl) ?>/trending">الأكثر تداولاً</a>
          </div>
        </div>
      </div>
    </main>
    <?php
    if (!defined('GDY_TPL_WRAPPED') && is_file($footer)) require $footer;
    return;
}

// ------------------------------------------------------------
// Members-only (Option A: show list + lock badge + paywall)
// ------------------------------------------------------------
$isPaywalled = ($membersOnly && !$canReadFull) || $meteredLocked;

$paywallBoxHtml = '';

// Paywall (الخيار 2): عرض جزء كبير من المقال وإخفاء الجزء الأخير فقط.
// نحدد مصدر منفصل لـ TTS حتى لا يقرأ نص صندوق الـ Paywall.
$gdyBodyForTts = null;

if ($isPaywalled) {
    $fullBody = (string)$body;

    // محاولة قصّ HTML على مستوى الفقرات للحفاظ على تنسيق المقال.
    $previewHtml = '';
    $paras = [];
    if (preg_match_all('~<p\b[^>]*>.*?</p>~is', $fullBody, $m)) {
        $paras = $m[0] ?? [];
    }

    if (!empty($paras)) {
        $count = count($paras);
        // اعرض 85% من الفقرات كحد افتراضي (مع حد أدنى 2) وبشرط أن يبقى جزء محجوب.
        $keep = (int)ceil($count * 0.85);
        $keep = max(2, $keep);
        $keep = min($keep, $count - 1);

        $previewHtml = implode("\n", array_slice($paras, 0, $keep));
    } else {
        // احتياطي: قصّ نصي إذا كان المحتوى بدون فقرات.
        $txt = trim((string)gdy_plaintext($fullBody));
        $txt = preg_replace('~\s+~u', ' ', (string)$txt);
        $more = (mb_strlen((string)$txt, 'UTF-8') > 1200);
        $txt = mb_substr((string)$txt, 0, 1200, 'UTF-8');
        $previewHtml = '<p style="margin:0">' . nl2br(h($txt)) . ($more ? '…' : '') . '</p>';
    }

    $loginUrl    = rtrim((string)$baseUrl, '/') . '/login.php?next=' . rawurlencode((string)$newsUrl);
    $registerUrl = rtrim((string)$baseUrl, '/') . '/register.php?next=' . rawurlencode((string)$newsUrl);

    if ($meteredLocked) {
    $paywallBoxHtml =
        '<div class="gdy-paywall-box" role="region" aria-label="حد القراءة المجانية">' .
            '<div class="gdy-paywall-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>' .
            '<div class="gdy-paywall-content">' .
                '<strong>وصلت لحد القراءة المجانية</strong>' .
                '<div class="gdy-paywall-sub">يمكنك قراءة ' . (int)$meterLimit . ' مقالات مجاناً خلال أسبوع. سجّل دخولك أو أنشئ حساباً لمتابعة القراءة.</div>' .
                '<div class="gdy-paywall-sub" style="opacity:.9">قرأت: ' . (int)$meterCount . ' / ' . (int)$meterLimit . '</div>' .
                '<div class="gdy-paywall-actions">' .
                    '<a class="gdy-paywall-btn primary" href="' . h($loginUrl) . '">' .
                        '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg> تسجيل الدخول' .
                    '</a>' .
                    '<a class="gdy-paywall-btn" href="' . h($registerUrl) . '">' .
                        '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إنشاء حساب' .
                    '</a>' .
                '</div>' .
            '</div>' .
        '</div>';
} else {
    $paywallBoxHtml =
        '<div class="gdy-paywall-box" role="region" aria-label="محتوى للأعضاء">' .
            '<div class="gdy-paywall-icon"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></div>' .
            '<div class="gdy-paywall-content">' .
                '<strong>هذا المقال للأعضاء فقط</strong>' .
                '<div class="gdy-paywall-sub">سجّل دخولك أو أنشئ حساباً لمتابعة قراءة المقال بالكامل.</div>' .
                '<div class="gdy-paywall-actions">' .
                    '<a class="gdy-paywall-btn primary" href="' . h($loginUrl) . '">' .
                        '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg> تسجيل الدخول' .
                    '</a>' .
                    '<a class="gdy-paywall-btn" href="' . h($registerUrl) . '">' .
                        '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> إنشاء حساب' .
                    '</a>' .
                '</div>' .
            '</div>' .
        '</div>';
}


    // الخيار 2: اعرض المعاينة داخل المقال ثم ضع صندوق الـ Paywall في نهاية المعاينة.


    // TTS يعتمد على نص المعاينة فقط
    $gdyBodyForTts = $previewHtml;
}

// ------------------------------------------------------------
// Prepare content (TOC + tables)
// ------------------------------------------------------------
if (!$isPaywalled) {
  $body = gdy_wrap_tables($body);
  $built = gdy_build_toc($body);
  $body = $built['html'];
  $toc = $built['toc'];

  // ------------------------------------------------------------
  // Inline blocks (TOC + Poll داخل المقال)
  // ------------------------------------------------------------
  $inlineTocHtml = '';
  if (!empty($toc) && is_array($toc)) {
    $inlineTocHtml .= '<div class="gdy-inline-toc" id="gdyInlineToc">';
    $inlineTocHtml .= '<div class="gdy-inline-toc-h"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h(__('فهرس المحتوى')) . '</div>';
    $inlineTocHtml .= '<div class="gdy-inline-toc-b">';
    foreach ($toc as $item) {
      $cls = ($item['level'] ?? 2) === 3 ? 'lv3' : 'lv2';
      $id  = (string)($item['id'] ?? '');
      $tx  = (string)($item['text'] ?? '');
      if ($id === '' || $tx === '') continue;
      $inlineTocHtml .= '<div class="' . $cls . '"><a href="#' . h($id) . '">' . h($tx) . '</a></div>';
    }
    $inlineTocHtml .= '</div></div>';
  }

  // Poll placeholder (سيتم ملؤه عبر JS إذا كان هناك استطلاع لهذا المقال)
  $pollHtml = '';
  if ($postId > 0) {
    $pollHtml = '<div class="gdy-inline-poll"><div class="gdy-inline-poll-h"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ' . h(__('استطلاع')) . '</div><div id="gdy-poll" data-news-id="' . (int)$postId . '"></div></div>';
  }

  // إدراج الاستطلاع بعد أول فقرة إن أمكن
  $bodyWithPoll = $body;
  if ($pollHtml !== '') {
    if (stripos($bodyWithPoll, '</p>') !== false) {
      $bodyWithPoll = preg_replace('~</p>~i', '</p>' . $pollHtml, $bodyWithPoll, 1);
    } else {
      $bodyWithPoll = $pollHtml . $bodyWithPoll;
    }
  }

  // TOC داخل المقال (يظهر خصوصاً على الجوال) + محتوى المقال
  $body = $inlineTocHtml . $bodyWithPoll;
} else {
  $toc = [];
}

?>
<style>
/* Paywall (members only) */
.gdy-paywall-box{
  display:flex;
  gap:12px;
  align-items:flex-start;
  padding:14px 14px;
  border-radius:16px;
  background: rgba(var(--primary-rgb), .06);
  color:#0f172a;
  border:1px solid rgba(148,163,184,.28);
  box-shadow: 0 14px 30px rgba(15,23,42,.10);
  margin: 14px 0 16px;
}
.gdy-paywall-icon{
  width:44px;
  height:44px;
  border-radius:14px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(var(--primary-rgb),.14);
  color:rgba(var(--primary-rgb), .9);
  flex:0 0 auto;
}
.gdy-paywall-content strong{ font-size:1rem; display:block; margin-bottom:4px; }
.gdy-paywall-sub{ font-size:.9rem; color:#334155; line-height:1.7; }
.gdy-paywall-actions{ display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }
.gdy-paywall-btn{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:10px 12px;
  border-radius:14px;
  text-decoration:none;
  color:#0f172a;
  border:1px solid rgba(148,163,184,.28);
  background:rgba(15,23,42,.55);
}
.gdy-paywall-btn.primary{ background: var(--primary); border-color: var(--primary); color:#ffffff; }
.gdy-paywall-preview{
  padding: 12px 0 2px;
  color:#0f172a;
  font-size:1.05rem;
  line-height:2;
}
html.theme-dark .gdy-paywall-preview{ color:#e5e7eb; }

/* تأثير تلاشي بسيط قبل صندوق الـ Paywall (يُوحي بوجود محتوى محجوب) */
.gdy-paywall-fade{
  height: 72px;
  margin-top: -10px;
  pointer-events: none;
  background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1));
}
html.theme-dark .gdy-paywall-fade{
  background: linear-gradient(to bottom, rgba(2,6,23,0), rgba(2,6,23,1));
}

/* ================================
   Godyar Report Template (RTL)
   ================================ */
.gdy-progress {
  position: fixed;
  top: 0;
  right: 0;
  height: 3px;
  width: 0%;
  background: var(--primary);
  z-index: 9999;
}

.gdy-report-page {
  padding: 22px 0 50px;
}

/* رأس مخصص للطباعة/PDF (بدون هيدر/فوتر الموقع) */
.gdy-print-head{ display:none; }
.gdy-print-head-inner{
  background:#ffffff;
  border:1px solid rgba(15,23,42,.14);
  border-radius: 16px;
  padding: 12px 14px;
  box-shadow: var(--shadow-soft);
}
.gdy-print-brand{
  display:flex;
  align-items:center;
  justify-content: space-between;
  gap: 12px;
}
.gdy-print-right{
  display:flex;
  align-items:center;
  gap: 12px;
}
.gdy-print-qr{
  width:78px;
  height:78px;
  border:1px solid rgba(15,23,42,.14);
  border-radius: 12px;
  padding: 4px;
  background:#fff;
}
.gdy-print-qr img{
  width:100%;
  height:100%;
  object-fit: contain;
  display:block;
}
.gdy-print-brand .name{
  font-weight: 900;
  font-size: 1.05rem;
  color:#0f172a;
}
.gdy-print-brand .url{
  direction:ltr;
  font-size: .88rem;
  color:var(--primary-dark);
  text-decoration: none;
  border-bottom: 1px dashed rgba(var(--primary-rgb),.45);
}
.gdy-print-brand .url:hover{ border-bottom-style: solid; }

.gdy-print-sub{
  margin-top:8px;
  color:#64748b;
  font-size:.82rem;
  display:flex;
  flex-wrap:wrap;
  gap: 8px 12px;
}
.gdy-print-sub span{ display:inline-flex; align-items:center; gap:6px; }

.gdy-report-shell {
  display: grid;
  /* Sidebar (يسار) + محتوى (يمين) — لإبراز الخبر */
  grid-template-columns: 340px minmax(0, 1fr);
  grid-template-areas: "side content";
  gap: 18px;
  align-items: start;
}

.gdy-report-shell > section { grid-area: content; }
.gdy-report-shell > aside.gdy-right { grid-area: side; }

@media (max-width: 1200px){
  .gdy-report-shell{
    grid-template-columns: 1fr;
    grid-template-areas:
      "content"
      "side";
  }
}

.gdy-report-hero {
  position: relative;
  background: linear-gradient(135deg, rgba(var(--primary-rgb),.10), rgba(255,255,255,.96));
  color: #0f172a;
  border-radius: 18px;
  padding: 18px 18px 14px;
  box-shadow: var(--shadow-soft);
  border: 1px solid rgba(var(--primary-rgb), .18);
}

.gdy-breadcrumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  font-size: .85rem;
  color: #64748b;
  margin-bottom: 10px;
}
.gdy-breadcrumbs a{
  color: var(--primary);
  text-decoration: none;
  border-bottom: 1px dashed rgba(var(--primary-rgb), .35);
  font-weight: 800;
}
.gdy-breadcrumbs a:hover{ border-bottom-style: solid; }

.gdy-report-title {
  font-size: clamp(1.25rem, 2vw, 1.9rem);
  line-height: 1.35;
  font-weight: 800;
  margin: 0 0 12px;
}

.gdy-meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
  align-items: center;
  color: #64748b;
  font-size: .85rem;
}

.gdy-pill {
  display: inline-flex;
  gap: 8px;
  align-items: center;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(255,255,255,.86);
  border: 1px solid rgba(var(--primary-rgb), .22);
  color: #0f172a;
  box-shadow: 0 8px 18px rgba(15,23,42,.06);
  backdrop-filter: blur(10px);
}
.gdy-pill i{
  color: var(--primary);
}

.gdy-actions {
  margin-top: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}


.gdy-actions.sticky{
  position: sticky;
  top: 88px;
  z-index: 25;
  padding: 10px;
  border-radius: 16px;
  border: 1px solid rgba(var(--primary-rgb),.22);
  background: linear-gradient(135deg, rgba(var(--primary-rgb),.14), rgba(255,255,255,.88) 60%);
  backdrop-filter: blur(10px);
}

.gdy-badge{
  display:inline-flex;
  align-items:center;
  padding:3px 8px;
  border-radius:999px;
  font-size:.78rem;
  font-weight:800;
  background: rgba(var(--primary-rgb),.14);
  border:1px solid rgba(var(--primary-rgb),.35);
  color:var(--primary);
}

.gdy-ai-list{
  margin: 10px 0 0;
  padding-right: 18px;
}
.gdy-ai-list li{ margin: 6px 0; }

body.gdy-reading-mode .gdy-shell{ max-width: 920px; }
body.gdy-reading-mode .gdy-sidebar{ display:none; }
body.gdy-reading-mode .gdy-hero-title{ font-size: clamp(1.6rem, 2.6vw, 2.4rem); }
body.gdy-reading-mode .gdy-article{ font-size: 1.06rem; line-height: 2.05; }

@media (max-width: 768px){
  .gdy-actions.sticky{ top: 10px; }
}

/* وضع الطباعة/PDF: اخفِ هيدر/فوتر الموقع والسايدبار وأظهر رأس بسيط (اسم الموقع + رابط المقال) */
body.gdy-print-mode .site-header,
body.gdy-print-mode .gdy-footer,
body.gdy-print-mode .gdy-actions,
body.gdy-print-mode .gdy-toc,
body.gdy-print-mode .gdy-sidebar,
body.gdy-print-mode .gdy-qr-box,
body.gdy-print-mode .gdy-progress{ display:none !important; }
body.gdy-print-mode .gdy-print-head{ display:block; margin: 14px 0 12px; }
body.gdy-print-mode .gdy-report-hero{ background:#fff; color:#0f172a; border-color: rgba(15,23,42,.14); }
body.gdy-print-mode .gdy-breadcrumbs, body.gdy-print-mode .gdy-meta-row{ color:#334155; }
body.gdy-print-mode .gdy-pill{ background:#fff; color:#0f172a; border-color: rgba(15,23,42,.14); }
body.gdy-print-mode .gdy-shell{ grid-template-columns: 1fr !important; }

@media print{
  .site-header, .gdy-footer{ display:none !important; }
  .gdy-actions, .gdy-toc, .gdy-sidebar, .gdy-qr-box, .gdy-progress{ display:none !important; }
  .gdy-print-head{ display:block !important; margin: 0 0 12px !important; }
  .gdy-report-hero{ background:#fff !important; color:#0f172a !important; box-shadow:none !important; border-color:#ddd !important; }
  .gdy-shell{ grid-template-columns: 1fr !important; }
  .gdy-card{ box-shadow:none !important; border-color:#ddd !important; }
  a{ color:#0f172a; }
}
.gdy-act {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  border-radius: 12px;
  border: 1px solid rgba(var(--primary-rgb),.22);
  background: rgba(255,255,255,.85);
  color: #0f172a;
  text-decoration: none;
  cursor: pointer;
  font-weight: 700;
  font-size: .9rem;
  transition: .2s ease;
}
.gdy-act:hover { transform: translateY(-1px); border-color: rgba(var(--primary-rgb),.55); background: rgba(var(--primary-rgb),.10); }
.gdy-act i { color: var(--primary); }

.gdy-act.secondary {
  background: var(--primary);
  color: #ffffff;
  border-color: var(--primary);
}
.gdy-act.secondary i { color: #ffffff; }

.gdy-right {
  position: sticky;
  top: calc(var(--header-h) + 14px);
}
@media (max-width: 1200px){
  .gdy-right { position: static; }
}

.gdy-card {
  background: #ffffff;
  border: 1px solid var(--card-border);
  border-radius: 18px;
  box-shadow: var(--shadow-soft);
  overflow: hidden;
}

.gdy-card-h {
  padding: 12px 14px;
  background: linear-gradient(135deg, rgba(var(--primary-rgb),.12), rgba(2,6,23,0) 60%);
  border-bottom: 1px solid rgba(15,23,42,.08);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.gdy-card-h strong { font-size: .95rem; color: #0f172a; }
.gdy-card-b { padding: 12px 14px; }

.gdy-toc a{
  display: block;
  padding: 7px 8px;
  border-radius: 10px;
  color: #0f172a;
  text-decoration: none;
  font-size: .92rem;
  line-height: 1.4;
}
.gdy-toc a:hover { background: rgba(var(--primary-rgb),.1); }

.gdy-toc .lv3 a { padding-right: 18px; opacity: .9; font-size: .88rem; }

.gdy-ai-toggle {
  border: none;
  background: rgba(var(--primary-rgb),.14);
  color: var(--primary-dark);
  padding: 6px 10px;
  border-radius: 999px;
  cursor: pointer;
  font-weight: 800;
  font-size: .8rem;
}
.gdy-ai-box {
  margin-top: 14px;
  border-radius: 18px;
  border: 1px solid rgba(var(--primary-rgb),.25);
  background: rgba(var(--primary-rgb),.06);
  padding: 12px 14px;
  color: #0f172a;
}
.gdy-ai-note {
  margin-top: 10px;
  color: #64748b;
  font-size: .85rem;
}

.gdy-qr-box{
  margin-top: 12px;
  border-radius: 18px;
  border: 1px solid rgba(15,23,42,.10);
  background: #ffffff;
  padding: 12px 14px;
  box-shadow: var(--shadow-soft);
  color: #0f172a;
}
.gdy-qr-inner{
  display:flex;
  gap: 14px;
  align-items:center;
  flex-wrap:wrap;
  margin-top: 10px;
}
.gdy-qr-img{
  width: 140px;
  height: 140px;
  border: 1px solid rgba(15,23,42,.12);
  border-radius: 16px;
  padding: 8px;
  background:#fff;
}
.gdy-qr-img img{
  width: 100%;
  height: 100%;
  object-fit: contain;
  display:block;
}
.gdy-qr-url{
  direction:ltr;
  display:inline-block;
  padding: 10px 12px;
  border-radius: 14px;
  border: 1px dashed rgba(var(--primary-rgb),.45);
  color: var(--primary-dark);
  text-decoration:none;
  font-weight: 900;
  max-width: 520px;
  overflow:hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.gdy-qr-url:hover{
  border-style: solid;
}

.gdy-article {
  padding: 14px;
}
.gdy-article-cover {
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(15,23,42,.08);
  margin-bottom: 14px;
}
.gdy-article-cover img { display:block; width:100%; height:auto; }
.gdy-article-cover.gdy-cover-empty { border-style: dashed; }
.gdy-cover-placeholder{
  width:100%;
  aspect-ratio: 16 / 9;
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(2,6,23,.06), rgba(2,6,23,.02));
}

.gdy-article-body {
  color: var(--text-main);
  font-size: var(--gdy-font-size, 1.05rem);
  line-height: 1.9;
}
.gdy-article-body h2, .gdy-article-body h3{
  margin: 22px 0 12px;
  font-weight: 900;
  color: #0f172a;
}
.gdy-article-body h2 { font-size: 1.35rem; }
.gdy-article-body h3 { font-size: 1.15rem; }
.gdy-article-body p { margin: 0 0 12px; }
.gdy-article-body a { color: var(--primary-dark); font-weight: 800; }
.gdy-article-body blockquote{
  margin: 14px 0;
  padding: 12px 14px;
  border-right: 4px solid var(--primary);
  background: rgba(2,6,23,.03);
  border-radius: 14px;
}

.gdy-table-wrap {
  width: 100%;
  overflow: auto;
  border: 1px solid rgba(15,23,42,.08);
  border-radius: 14px;
  background: #fff;
  margin: 14px 0;
}

.gdy-report-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 680px;
  font-size: .95rem;
}
.gdy-report-table th, .gdy-report-table td{
  padding: 10px 12px;
  border-bottom: 1px solid rgba(15,23,42,.08);
  text-align: right;
  vertical-align: middle;
  white-space: nowrap;
}
.gdy-report-table th{
  position: sticky;
  top: 0;
  z-index: 2;
  background: #f8fafc;
  font-weight: 900;
}
.gdy-report-table tbody tr:hover{ background: rgba(var(--primary-rgb),.06); }

.gdy-side-list {
  display: grid;
  gap: 10px;
}
.gdy-side-item {
  display: block;
  text-decoration: none;
  color: #0f172a;
  padding: 10px 10px;
  border-radius: 14px;
  border: 1px solid rgba(15,23,42,.08);
  background: #fff;
  transition: .2s ease;
}
.gdy-side-item:hover{ transform: translateY(-1px); border-color: rgba(var(--primary-rgb),.35); }

.gdy-side-item .t { font-weight: 900; line-height: 1.4; }
.gdy-side-item .m { color: #64748b; font-size: .82rem; margin-top: 4px; display:flex; gap:8px; align-items:center; }

.gdy-notfound{
  min-height: 60vh;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  text-align:center;
  gap:10px;
}
.gdy-notfound-actions{ display:flex; gap:10px; flex-wrap:wrap; justify-content:center;}
.btn-primary, .btn-secondary{
  padding: 10px 14px;
  border-radius: 12px;
  text-decoration:none;
  font-weight:900;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
}
.btn-primary{ background: var(--primary); color:#fff; }
.btn-secondary{ background: #fff; color:#0f172a; border:1px solid rgba(15,23,42,.12); }

.gdy-hero-qr{
  position:absolute;
  left:18px;
  top:18px;
  width:140px;
  height:140px;
  border-radius:18px;
  border:1px solid rgba(255,255,255,.22);
  background: rgba(2,6,23,.35);
  backdrop-filter: blur(10px);
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
  box-shadow: 0 10px 30px rgba(0,0,0,.18);
}
.gdy-hero-qr img{ width:120px; height:120px; border-radius:12px; background:#fff; padding:6px; }
.gdy-hero-qr.hidden{ display:none; }
.gdy-hero-qr-tip{
  position:absolute;
  left:18px;
  top:166px;
  font-size:.78rem;
  color: rgba(226,232,240,.9);
  background: rgba(2,6,23,.35);
  padding:6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,.16);
}
.gdy-hero-qr-tip.hidden{ display:none; }

/* ===== تفاعلات سريعة (إيموجي فقط) ===== */
.gdy-react-row{display:flex;flex-wrap:wrap;gap:10px}
.gdy-react-btn{border:1px solid rgba(148,163,184,.35);background:#fff;border-radius:999px;padding:8px 12px;display:inline-flex;align-items:center;gap:8px;font-weight:900;cursor:pointer}
.gdy-react-btn .emo{font-size:1.25rem;line-height:1;display:inline-flex;align-items:center;justify-content:center}
.gdy-react-btn .cnt{font-weight:900;color:#0f172a;background:rgba(2,6,23,.06);padding:2px 8px;border-radius:999px;min-width: 26px;text-align:center}
.gdy-react-btn.active{background:rgba(var(--primary-rgb),.14);border-color:rgba(var(--primary-rgb),.45)}
.gdy-react-btn:disabled{opacity:.65;cursor:not-allowed}

@media (max-width: 576px){
  .gdy-react-row{gap:8px}
  .gdy-react-btn{padding:8px 10px}
}

/* ===== إصلاح تداخل QR مع كرت الكاتب على الجوال ===== */
@media (max-width: 768px){
  .gdy-hero-qr{
    position: static;
    width: 110px;
    height: 110px;
    margin: 0 auto 10px;
    left: auto;
    top: auto;
  }
  .gdy-hero-qr img{ width: 92px; height: 92px; }
  .gdy-hero-qr-tip{
    position: static;
    display: inline-block;
    margin: 0 auto 12px;
    left: auto;
    top: auto;
    text-align: center;
  }
}
.gdy-font-badge{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:34px;
  height:22px;
  padding:0 8px;
  border-radius:999px;
  background: rgba(148,163,184,.22);
  border: 1px solid rgba(226,232,240,.22);
  color:#e2e8f0;
  font-weight: 900;
  font-size:.78rem;
  margin-inline-start:8px;
}

/* وضع قراءة مريح */

body.gdy-reading-mode .gdy-article-body{ font-size: 1.12rem; line-height: 2.05; }
/* ===== تحسين رأس الخبر: صندوق المهام لا يتداخل مع المحتوى ===== */
.gdy-actions{ position: relative; z-index: 5; }
/* إلغاء السلوك اللاصق لتجنب تداخل صندوق المهام مع محتوى الخبر */
.gdy-actions.sticky{ position: static; top: auto; }

/* ===== كرت كاتب المقال (لمقالات الرأي) ===== */
.gdy-opinion-author-card{
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(148,163,184,.20);
  border-radius: 18px;
  padding: 14px 14px 12px;
  display:flex;
  flex-direction:column;
  align-items:center;
  text-align:center;
  gap: 10px;
}
.gdy-opinion-author-avatar{
  width: 80px;
  height: 80px;
  border-radius: 999px;
  overflow:hidden;
  border: 3px solid rgba(var(--primary-rgb),.55);
  background:#0b1220;
  box-shadow: 0 14px 30px rgba(0,0,0,.25);
}
.gdy-opinion-author-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
.gdy-opinion-author-name{
  font-weight: 900;
  font-size: 1.05rem;
  color: #f8fafc;
  line-height: 1.2;
}
.gdy-opinion-author-pill{
  background:#fff;
  color:#0f172a;
  border-radius: 999px;
  padding: 6px 14px;
  font-weight: 800;
  font-size: .86rem;
  box-shadow: 0 10px 22px rgba(0,0,0,.18);
  max-width: 100%;
}
.gdy-opinion-author-pill span{
  display:block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.gdy-opinion-social{
  display:flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content:center;
  margin-top: 2px;
}
.gdy-opinion-social a{
  width: 40px;
  height: 40px;
  border-radius: 999px;
  display:flex;
  align-items:center;
  justify-content:center;
  text-decoration:none;
  background: rgba(255,255,255,.10);
  border: 1px solid rgba(148,163,184,.26);
  color: #e2e8f0;
  transition: transform .15s ease, background .15s ease, border-color .15s ease;
}
.gdy-opinion-social a:hover{
  transform: translateY(-2px);
  background: rgba(var(--primary-rgb),.18);
  border-color: rgba(var(--primary-rgb),.60);
  color: #fff;
}
.gdy-opinion-divider{
  height: 1px;
  width: 100%;
  background: rgba(148,163,184,.22);
  margin: 14px 0 12px;
}
.gdy-opinion-article-badge{
  display:inline-flex;
  align-items:center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(var(--primary-rgb),.14);
  border: 1px solid rgba(var(--primary-rgb),.35);
  color: var(--primary);
  font-weight: 900;
  font-size: .82rem;
}

/* ===== Inline TOC داخل المقال (مفيد للجوال) ===== */
.gdy-inline-toc{border:1px solid rgba(148,163,184,.25);border-radius:16px;background:rgba(255,255,255,.96);padding:12px 14px;margin:14px 0}
.gdy-inline-toc-h{font-weight:900;display:flex;align-items:center;gap:8px;margin-bottom:8px;color:#0f172a}
.gdy-inline-toc-b{display:grid;gap:6px}
.gdy-inline-toc-b .lv2 a{font-weight:800;color:#0f172a;text-decoration:none}
.gdy-inline-toc-b .lv3{padding-right:10px;border-right:2px solid rgba(148,163,184,.35)}
.gdy-inline-toc-b .lv3 a{font-weight:700;color:#334155;text-decoration:none}
.gdy-inline-toc-b a:hover{text-decoration:underline}
@media (min-width: 1201px){
  /* على الديسكتوب يوجد فهرس في الشريط الجانبي، نخفي النسخة داخل المقال لتجنب التكرار */
  .gdy-inline-toc{display:none}
}

/* ===== Poll داخل المقال ===== */
.gdy-inline-poll{border:1px solid rgba(148,163,184,.25);border-radius:16px;background:rgba(255,255,255,.96);padding:12px 14px;margin:14px 0}
.gdy-inline-poll-h{font-weight:900;display:flex;align-items:center;gap:8px;margin-bottom:8px;color:#0f172a}
</style>

<div class="gdy-progress" id="gdyProgress"></div>

<main class="gdy-report-page">
  <div class="container">
    <?php $printSiteName = (string)($siteName ?? 'Godyar News'); ?>
    <div class="gdy-print-head" aria-hidden="true">
      <div class="gdy-print-head-inner">
        <div class="gdy-print-brand">
          <div class="name"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg><?= h($printSiteName) ?></div>
          <div class="gdy-print-right">
            <a class="url" href="<?= h($newsUrl) ?>"><?= h($newsUrl) ?></a>
            <div class="gdy-print-qr" title="QR">
              </div>
          </div>
        </div>
        <div class="gdy-print-sub">
          <span><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>رابط المقال</span>
        </div>
      </div>
    </div>

    <div class="gdy-report-hero">
  <?php if ($newsUrl !== ''): ?>
    <div class="gdy-hero-qr" id="gdyHeroQr" title="رمز QR للمقال">
      <img class="gdy-qr-image" alt="QR" loading="lazy" src="<?= h($qrApi) ?>size=160x160&data=<?= rawurlencode($newsUrl) ?>" />
    </div>
  <?php endif; ?>

      <nav class="gdy-breadcrumbs" aria-label="مسار التنقل">
        <a href="<?= h($baseUrl) ?>/">الرئيسية</a>
        <span>›</span>
        <a href="<?= h($baseUrl) ?>/category/<?= rawurlencode($categorySlug) ?>"><?= h($categoryName) ?></a>
        <span>›</span>
        <span>تقرير</span>
      </nav>

      <?php if (!empty($showOpinionAuthorCard)): ?>
  <div class="gdy-opinion-author-card" aria-label="<?= h(__('كاتب المقال')) ?>">
    <div class="gdy-opinion-author-avatar">
      <img src="<?= h($oaAvatar) ?>" alt="<?= h($oaName ?: 'Author') ?>" loading="lazy"
           onerror="this.onerror=null;this.src='<?= h(rtrim($baseUrl,'/').'/assets/images/avatar.png') ?>';">
    </div>

    <div class="gdy-opinion-author-name">
      <?php if ($authorUrl !== ''): ?>
        <a href="<?= h($authorUrl) ?>" style="color:inherit;text-decoration:none;">
          <?= h($oaName) ?>
        </a>
      <?php else: ?>
        <?= h($oaName) ?>
      <?php endif; ?>
    </div>

    <?php if ($oaPageTitle !== ''): ?>
      <div class="gdy-opinion-author-pill">
        <span><?= h($oaPageTitle) ?></span>
      </div>
    <?php endif; ?>

    <div class="gdy-opinion-social" aria-label="<?= h(__('تواصل مع الكاتب')) ?>">
      <?php if ($oaFacebook !== ''): ?>
        <a href="<?= h($oaFacebook) ?>" target="_blank" rel="noopener" aria-label="Facebook"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg></a>
      <?php endif; ?>
      <?php if ($oaTwitter !== ''): ?>
        <a href="<?= h($oaTwitter) ?>" target="_blank" rel="noopener" aria-label="X"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#x"></use></svg></a>
      <?php endif; ?>

      <!-- واتساب: مشاركة رابط المقال -->
      <a href="<?= h($shareWhatsapp) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#whatsapp"></use></svg></a>

      <!-- بريد: إن كان بريد الكاتب موجوداً استخدمه، وإلا مشاركة عبر البريد -->
      <?php if ($oaEmail !== ''): ?>
        <a href="mailto:<?= h($oaEmail) ?>" aria-label="Email"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></a>
      <?php else: ?>
        <a href="<?= h($shareEmail) ?>" aria-label="Email"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></a>
      <?php endif; ?>
    </div>
  </div>

  <div class="gdy-opinion-divider" aria-hidden="true"></div>
  <div class="gdy-opinion-article-badge"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('مقال مميز')) ?></div>
<?php endif; ?>


<h1 class="gdy-report-title"><?= h($title) ?></h1>


      <div class="gdy-meta-row">
        <?php if ($date !== ''): ?>
          <span class="gdy-pill"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h(date('Y/m/d', strtotime($date))) ?></span>
        <?php endif; ?>
        <?php if ($sourceLabel !== ''): ?>
          <span class="gdy-pill"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($sourceLabel) ?></span>
        <?php endif; ?>
        <?php if (!$showOpinionAuthorCard && $authorName !== ''): ?>
          <span class="gdy-pill"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg><?= h($authorName) ?></span>
        <?php endif; ?>
        <span class="gdy-pill"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= (int)$readMinutes ?> د</span>
        <?php if ($views > 0): ?>
          <span class="gdy-pill"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= (int)$views ?></span>
        <?php endif; ?>
        <?php if (!empty($membersOnly)): ?>
          <span class="gdy-pill"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> للأعضاء</span>
        <?php endif; ?>

      </div>

      <div class="gdy-actions" role="toolbar" aria-label="أدوات التقرير">
        <button class="gdy-act" type="button" id="gdyCopyLink">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>نسخ الرابط
        </button>
        <button class="gdy-act" type="button" id="gdyShare">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>مشاركة
        </button>
        <button class="gdy-act" type="button" id="gdyBookmark"
                data-news-id="<?= (int)($post['id'] ?? 0) ?>"
                data-title="<?= h((string)($post['title'] ?? '')) ?>"
                data-image="<?= h((string)($coverUrl ?? '')) ?>"
                data-url="<?= h((string)($newsUrl ?? '')) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><span class="gdy-bm-text">حفظ</span>
        </button>

        <button class="gdy-act" type="button" id="gdyPrint">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>طباعة
        </button>
        <button class="gdy-act" type="button" id="gdyPdf">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>PDF
        </button>
        <button class="gdy-act" type="button" id="gdyReadingMode">
  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>وضع قراءة
</button><button class="gdy-act" type="button" id="gdyFontInc" title="تكبير الخط">+</button>
<button class="gdy-act" type="button" id="gdyFontDec" title="تصغير الخط">−</button>
        <button class="gdy-act" type="button" id="gdyQrToggle" aria-label="QR" title="QR">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
        </button>

        <a class="gdy-act" target="_blank" rel="noopener"
           href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($newsUrl) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg>فيسبوك
        </a>
        <a class="gdy-act" target="_blank" rel="noopener"
           href="https://x.com/intent/post?url=<?= urlencode($newsUrl) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#x"></use></svg>X
        </a>
        <a class="gdy-act" target="_blank" rel="noopener"
           href="https://wa.me/?text=<?= urlencode($newsUrl) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#whatsapp"></use></svg>واتساب
        </a>

        <?php if ($aiSummary !== ''): ?>
          <button class="gdy-act secondary gdy-ai-toggle" type="button" id="gdyAiToggle" aria-expanded="false" aria-controls="gdyAiBox" data-mode="<?= h($aiSummaryMode) ?>">
            <?= h($aiBtnLabel) ?>
            <?php if ($aiBtnNote !== ''): ?><span class="gdy-badge"><?= h($aiBtnNote) ?></span><?php endif; ?>
          </button>
        <?php endif; ?>
      </div>

      <?php if ($aiSummary !== ''): ?>
        <div class="gdy-ai-box" id="gdyAiBox" hidden>
          <strong><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> ملخص المحتوى</strong>
          <div style="margin-top:10px; line-height:1.85;"><?= $aiSummary ?></div>
          <div class="gdy-ai-note">ملاحظة: هذا الملخص تم إنشاؤه آلياً، يُفضّل مراجعة النص الأصلي للتفاصيل.</div>
        </div>
      <?php endif; ?>
    </div>
<div class="gdy-report-shell gdy-shell" style="margin-top:16px;">
<section>
        <article class="gdy-card">
          <div class="gdy-article">
            <div class="gdy-article-cover">
              <?php if (!empty($coverUrl)): ?>
                <img src="<?= h($coverUrl) ?>" alt="<?= h($title) ?>" loading="eager" fetchpriority="high" decoding="async"
                     onerror="this.style.display='none'; this.parentElement.classList.add('gdy-cover-empty');">
              <?php else: ?>
                <div class="gdy-cover-placeholder" aria-hidden="true"></div>
              <?php endif; ?>
            </div>

            <?php if (!empty($paywallBoxHtml)): ?>
              <?= $paywallBoxHtml ?>
            <?php endif; ?>

            <div class="gdy-article-body" id="gdyArticleBody">
              <?= $body ?>
            </div>

            <?php if (!empty($tags) && is_array($tags)): ?>
              <div style="margin-top:16px; display:flex; flex-wrap:wrap; gap:8px;">
                <?php foreach ($tags as $t): ?>
                  <?php $tn = (string)($t['name'] ?? $t['title'] ?? ''); $ts = (string)($t['slug'] ?? ''); ?>
                  <?php if ($tn !== ''): ?>
                    <a class="gdy-pill" style="text-decoration:none; color:#0f172a; background:#fff;"
                       href="<?= h($baseUrl) ?>/tag/<?= rawurlencode($ts !== '' ? $ts : $tn) ?>">
                      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><?= h($tn) ?>
                    </a>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </article>


<?php
  $newsId = (int)($post['id'] ?? 0);
  $ttsSource = ($gdyBodyForTts !== null) ? $gdyBodyForTts : $body;
  $ttsText = trim(preg_replace('~\s+~u',' ', html_entity_decode(strip_tags((string)$ttsSource), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
?>
<section class="gdy-extras-wrap" aria-label="ميزات المقال الإضافية">
  <div class="gdy-extras-grid">
    <div class="gdy-extras-card">
      <div class="gdy-extras-head">
        <div class="gdy-extras-title"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('الاستماع للمقال')) ?></div>
      </div>

      <div id="gdy-tts" class="gdy-tts" data-news-id="<?= (int)$newsId ?>">
        <button type="button" id="gdy-tts-play" class="gdy-tts-btn"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('استماع')) ?></button>
        <button type="button" id="gdy-tts-stop" class="gdy-tts-btn"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('إيقاف')) ?></button>

        <label class="gdy-tts-rate">
          <span><?= h(__('السرعة')) ?></span>
          <input id="gdy-tts-rate" type="range" min="0.7" max="1.3" step="0.1" value="1">
        </label>

        <button type="button" id="gdy-tts-download" class="gdy-tts-btn" title="<?= h(__('تحميل الصوت')) ?>">
          <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('تحميل')) ?>
        </button>

        <div id="gdy-tts-text" style="display:none;"><?= h($ttsText) ?></div>
      </div>
    </div>

    <div class="gdy-extras-card">
      <div id="gdy-reactions" data-news-id="<?= (int)$newsId ?>"></div>
</div>
  </div>

  <div class="gdy-extras-grid2">
    <div class="gdy-extras-card" id="gdy-qa" data-news-id="<?= (int)$newsId ?>">
      <div class="gdy-extras-title"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('اسأل الكاتب')) ?></div>

      <form id="gdy-ask-form" class="gdy-ask-form">
        <div class="row g-2">
          <div class="col-12 col-md-4">
            <input class="form-control" name="name" placeholder="<?= h(__('الاسم')) ?>" autocomplete="name">
          </div>
          <div class="col-12 col-md-4">
            <input class="form-control" name="email" placeholder="<?= h(__('البريد (اختياري)')) ?>" autocomplete="email">
          </div>
          <div class="col-12 col-md-4">
            <button type="submit" class="btn btn-primary w-100"><?= h(__('إرسال السؤال')) ?></button>
          </div>
        </div>
        <div class="mt-2">
          <textarea class="form-control" name="question" rows="3" placeholder="<?= h(__('اكتب سؤالك هنا…')) ?>"></textarea>
        </div>
        <div id="gdy-ask-msg" class="gdy-ask-msg"></div>
      </form>

      <div class="gdy-qa-list" id="gdy-qa-list"></div>
      <div class="text-muted small mt-2"><?= h(__('الأسئلة تُعرض بعد المراجعة.')) ?></div>
    </div>
  </div>
</section>



        <?php
          // Comments section (internal + optional GitHub giscus)
          $csrf = function_exists('csrf_token') ? (string)csrf_token() : (string)($_SESSION['csrf_token'] ?? '');
          $isMember = (!empty($_SESSION['is_member_logged'])) || (!empty($_SESSION['user']) && is_array($_SESSION['user']) && (!empty($_SESSION['user']['id']) || !empty($_SESSION['user']['email']))) || (!empty($_SESSION['user_id']));
          $memberName  = (string)($_SESSION['user_name'] ?? ($_SESSION['user']['display_name'] ?? ($_SESSION['user']['username'] ?? '')));
          $memberEmail = (string)($_SESSION['user_email'] ?? ($_SESSION['email'] ?? ($_SESSION['user']['email'] ?? '')));
          if (($memberName ?? '') === '' && ($memberEmail ?? '') !== '' && strpos((string)$memberEmail, '@') !== false) { $memberName = substr((string)$memberEmail, 0, strpos((string)$memberEmail, '@')); }
// Optional giscus (GitHub discussions) — enable by setting env vars in .env
          $giscusRepo = (string)(function_exists('env') ? env('GISCUS_REPO', '') : '');
          $giscusRepoId = (string)(function_exists('env') ? env('GISCUS_REPO_ID', '') : '');
          $giscusCategory = (string)(function_exists('env') ? env('GISCUS_CATEGORY', '') : '');
          $giscusCategoryId = (string)(function_exists('env') ? env('GISCUS_CATEGORY_ID', '') : '');
          $giscusEnabled = ($giscusRepo !== '' && $giscusRepoId !== '' && $giscusCategoryId !== '');
          $giscusLang = (string)(function_exists('current_lang') ? current_lang() : (string)($_SESSION['lang'] ?? 'ar'));
          if (!in_array($giscusLang, ['ar','en','fr'], true)) $giscusLang = 'en';
        ?>

        <article class="gdy-card" id="gdyComments" style="margin-top:14px;">
          <div class="gdy-card-h">
            <strong><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h(__('التعليقات')) ?></strong>
            <span style="color:#64748b;font-size:.82rem;" id="gdyCommentsCount"></span>
          </div>
          <div class="gdy-card-b">
            <style>
              .gdy-cmt-wrap{display:grid;gap:14px}
              .gdy-cmt-item{display:grid;grid-template-columns:46px 1fr;gap:10px;padding:12px;border:1px solid rgba(148,163,184,.25);border-radius:16px;background:rgba(255,255,255,.96)}
              .gdy-cmt-vote{display:flex;flex-direction:column;align-items:center;gap:6px;padding:8px 6px;border:1px solid rgba(148,163,184,.25);border-radius:14px;background:rgba(2,6,23,.02)}
              .gdy-cmt-vote button{border:0;background:transparent;color:#334155;cursor:pointer;font-size:18px;line-height:1}
              .gdy-cmt-score{font-weight:800;color:#0f172a}
              .gdy-cmt-head{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center}
              .gdy-cmt-author{display:flex;align-items:center;gap:8px;font-weight:800}
              .gdy-cmt-avatar{width:26px;height:26px;border-radius:50%;object-fit:cover;border:1px solid rgba(148,163,184,.55)}
              .gdy-cmt-time{color:#64748b;font-size:.82rem}
              .gdy-cmt-body{margin-top:6px;white-space:pre-wrap;line-height:1.85;color:#0f172a}
              .gdy-cmt-actions{margin-top:10px;display:flex;gap:10px;flex-wrap:wrap}
              .gdy-cmt-actions button{border:1px solid rgba(148,163,184,.35);background:#fff;border-radius:12px;padding:6px 10px;cursor:pointer;font-weight:700}
              .gdy-cmt-children{margin-top:12px;display:grid;gap:10px;padding-right:10px;border-right:2px solid rgba(148,163,184,.25)}
              .gdy-cmt-form{display:grid;gap:10px;padding:12px;border:1px dashed rgba(148,163,184,.45);border-radius:16px;background:rgba(2,6,23,.02)}
              .gdy-cmt-form textarea{width:100%;min-height:110px;resize:vertical;border:1px solid rgba(148,163,184,.55);border-radius:14px;padding:10px;font:inherit}
              .gdy-cmt-row{display:flex;gap:10px;flex-wrap:wrap}
              .gdy-cmt-row input{flex:1;min-width:220px;border:1px solid rgba(148,163,184,.55);border-radius:14px;padding:10px;font:inherit}
              .gdy-cmt-submit{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}
              .gdy-cmt-submit button{border:0;border-radius:14px;padding:10px 14px;background:var(--primary);color:#fff;font-weight:800;cursor:pointer}
              .gdy-cmt-note{color:#64748b;font-size:.85rem;line-height:1.7}
              .gdy-cmt-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
              .gdy-cmt-tab{border:1px solid rgba(148,163,184,.35);background:#fff;border-radius:999px;padding:7px 12px;cursor:pointer;font-weight:800}
              .gdy-cmt-tab.active{background:var(--primary-dark);color:#fff;border-color:rgba(var(--primary-rgb),.35)}
            </style>

            <div class="gdy-cmt-tabs">
              <button type="button" class="gdy-cmt-tab active" data-tab="internal"><?= h(__('تعليقات الموقع')) ?></button>
              <?php if ($giscusEnabled): ?>
                <button type="button" class="gdy-cmt-tab" data-tab="giscus">GitHub</button>
              <?php endif; ?>
            </div>

            <div id="gdyCommentsInternal">
              <?php if (!$isMember): ?>
                <div class="gdy-cmt-note" style="margin-bottom:10px;">
                  <?= h(__('يمكنك التعليق بإدخال الاسم والبريد، أو تسجيل الدخول للتعليق كعضو.')) ?>
                  <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="gdy-act" href="<?= h($baseUrl) ?>/login?next=<?= urlencode('/news/id/' . (int)$postId) ?>"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#login"></use></svg> <?= h(__('تسجيل الدخول')) ?></a>
                    <a class="gdy-act" href="<?= h($baseUrl) ?>/oauth/github?next=<?= urlencode('/news/id/' . (int)$postId) ?>"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> GitHub</a>
                    <a class="gdy-act" href="<?= h($baseUrl) ?>/oauth/google?next=<?= urlencode('/news/id/' . (int)$postId) ?>"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> Google</a>
                    <a class="gdy-act" href="<?= h($baseUrl) ?>/oauth/facebook?next=<?= urlencode('/news/id/' . (int)$postId) ?>"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#facebook"></use></svg> Facebook</a>
                  </div>
                </div>
              <?php endif; ?>

              <div class="gdy-cmt-form" id="gdyCommentForm">
                <input type="hidden" id="gdyCmtParent" value="0">
                <?php if (!$isMember || $memberName==='' || $memberEmail===''): ?>
                  <div class="gdy-cmt-row">
                    <input id="gdyCmtName" placeholder="<?= h(__('الاسم')) ?>" value="<?= h($memberName ?? '') ?>">
                    <input id="gdyCmtEmail" placeholder="<?= h(__('البريد الإلكتروني')) ?>" type="email" value="<?= h($memberEmail ?? '') ?>">
                  </div>
                <?php endif; ?>
                <textarea id="gdyCmtBody" placeholder="<?= h(__('اكتب تعليقك هنا...')) ?>"></textarea>
                <div class="gdy-cmt-submit">
                  <button type="button" id="gdyCmtSend"><?= h(__('إرسال')) ?></button>
                  <div class="gdy-cmt-note" id="gdyCmtHint"></div>
                </div>
              </div>

              <div class="gdy-cmt-wrap" id="gdyCmtList" style="margin-top:14px;"></div>
            </div>

            <?php if ($giscusEnabled): ?>
              <div id="gdyCommentsGiscus" style="display:none;">
                <div class="gdy-cmt-note" style="margin-bottom:12px;">
                  <?= h(__('تعليقات GitHub تحتاج تسجيل الدخول بحساب GitHub.')) ?>
                </div>
                <div class="giscus"></div>
                <script
                  src="https://giscus.app/client.js"
                  data-repo="<?= h($giscusRepo) ?>"
                  data-repo-id="<?= h($giscusRepoId) ?>"
                  data-category="<?= h($giscusCategory) ?>"
                  data-category-id="<?= h($giscusCategoryId) ?>"
                  data-mapping="url"
                  data-strict="0"
                  data-reactions-enabled="1"
                  data-emit-metadata="0"
                  data-input-position="bottom"
                  data-theme="preferred_color_scheme"
                  data-lang="<?= h($giscusLang) ?>"
                  crossorigin="anonymous"
                  async>
                </script>
              </div>
            <?php endif; ?>

            <script>
            (function(){
              const newsId = <?= (int)$postId ?>;
              const api = '/ajax/comments.php';
              const csrfEndpoint = '/ajax/csrf.php';

              const listEl  = document.getElementById('gdyCmtList');
              const countEl = document.getElementById('gdyCommentsCount');
              const nameEl  = document.getElementById('gdyCmtName');
              const emailEl = document.getElementById('gdyCmtEmail');
              const bodyEl  = document.getElementById('gdyCmtBody');
              const sendBtn = document.getElementById('gdyCmtSend');
              const hintEl  = document.getElementById('gdyCmtHint');

              let me = null;
              let csrf = '';
              let replyTo = 0;

              function esc(s){
                return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'}[m]));
              }
              function fmtTime(s){
                try{ const d=new Date(String(s).replace(' ', 'T')); return d.toLocaleString(); }catch(e){ return s||''; }
              }

              async function getCSRF(){
                try{
                  const r = await fetch(csrfEndpoint, {credentials:'include'});
                  const j = await r.json();
                  if (j && j.ok && j.csrf_token) csrf = j.csrf_token;
                }catch(e){}
              }

              function setHint(msg, ok){
                if(!hintEl) return;
                hintEl.textContent = msg || '';
                hintEl.style.color = ok ? '#16a34a' : '#ef4444';
              }

              function showGuestFields(show){
                if(!nameEl || !emailEl) return;
                const row1 = nameEl.closest('.gdy-cmt-row') || nameEl.parentElement;
                const row2 = emailEl.closest('.gdy-cmt-row') || emailEl.parentElement;
                if(row1) row1.style.display = show ? '' : 'none';
                if(row2) row2.style.display = show ? '' : 'none';
              }

              function renderList(rows){
                // Build tree by parent_id
                const byParent = {};
                rows.forEach(c => {
                  const pid = Number(c.parent_id||0);
                  (byParent[pid] ||= []).push(c);
                });

                function renderNode(c, depth){
                  const author = esc(c.name || '—');
                  const body = esc(c.body || '');
                  const time = fmtTime(c.created_at);
                  const indent = depth ? ' style="margin-top:10px;margin-right:'+(depth*18)+'px;border-right:2px solid rgba(148,163,184,.45);padding-right:12px;"' : '';
                  const replyBtn = `<button type="button" class="gdy-cmt-reply" data-id="${c.id}" style="border:1px solid rgba(148,163,184,.45);background:#fff;border-radius:12px;padding:6px 10px;cursor:pointer;font-weight:700;"><?= h(__('رد')) ?></button>`;
                  let html = `<div class="gdy-cmt-item"${indent}>
                      <div class="gdy-cmt-head">
                        <div class="gdy-cmt-author">👤 ${author}</div>
                        <div class="gdy-cmt-time">${esc(time)}</div>
                      </div>
                      <div class="gdy-cmt-body">${body}</div>
                      <div class="gdy-cmt-actions">${replyBtn}</div>
                    </div>`;
                  const kids = byParent[Number(c.id)] || [];
                  kids.forEach(k => { html += renderNode(k, depth+1); });
                  return html;
                }

                const roots = byParent[0] || [];
                listEl.innerHTML = roots.length ? roots.map(c => renderNode(c, 0)).join('') :
                  `<div class="gdy-cmt-note"><?= h(__('لا توجد تعليقات بعد.')) ?></div>`;
              }

              async function load(){
                try{
                  const r = await fetch(`${api}?action=list&news_id=${encodeURIComponent(newsId)}`, {credentials:'include'});
                  const j = await r.json();
                  if(!j || !j.ok){ throw new Error((j && (j.msg||j.error)) || 'error'); }
                  me = j.me || null;
                  // Show guest fields only if NOT logged
                  showGuestFields(!me);
                  const rows = Array.isArray(j.comments) ? j.comments : [];
                  if(countEl) countEl.textContent = rows.length ? `(${rows.length})` : '';
                  renderList(rows);
                }catch(e){
                  if(listEl) listEl.innerHTML = `<div class="gdy-cmt-note" style="color:#ef4444;"><?= h(__('تعذر تحميل التعليقات.')) ?></div>`;
                }
              }

              async function send(){
                setHint('', true);
                const body = (bodyEl && bodyEl.value ? bodyEl.value.trim() : '');
                if(!body){ setHint('<?= h(__('نص التعليق مطلوب.')) ?>', false); return; }

                const payload = new FormData();
                payload.append('action','add');
                payload.append('news_id', String(newsId));
                payload.append('body', body);
                payload.append('parent_id', String(replyTo||0));
                if(csrf) payload.append('csrf_token', csrf);

                // guest
                if(!me){
                  const n = (nameEl && nameEl.value ? nameEl.value.trim() : '');
                  const em = (emailEl && emailEl.value ? emailEl.value.trim() : '');
                  if(!n || !em){ setHint('<?= h(__('الاسم والبريد الإلكتروني مطلوبان.')) ?>', false); return; }
                  payload.append('name', n);
                  payload.append('email', em);
                }

                sendBtn && (sendBtn.disabled = true);

                try{
                  const r = await fetch(api, {method:'POST', body: payload, credentials:'include'});
                  const j = await r.json().catch(()=>null);

                  if(!r.ok || !j || !j.ok){
                    // Retry once if CSRF
                    if(j && j.error === 'csrf'){
                      await getCSRF();
                      if(csrf){ payload.set('csrf_token', csrf); }
                      const r2 = await fetch(api, {method:'POST', body: payload, credentials:'include'});
                      const j2 = await r2.json().catch(()=>null);
                      if(r2.ok && j2 && j2.ok){
                        bodyEl.value = '';
                        replyTo = 0;
                        setHint('<?= h(__('تم نشر التعليق.')) ?>', true);
                        await load();
                        return;
                      }
                    }
                    const msg = (j && (j.msg||j.detail)) ? (j.msg||j.detail) : '<?= h(__('حدث خطأ.')) ?>';
                    setHint(msg, false);
                    return;
                  }

                  bodyEl.value = '';
                  replyTo = 0;
                  setHint('<?= h(__('تم نشر التعليق.')) ?>', true);
                  await load();
                }catch(e){
                  setHint('<?= h(__('تعذر الإرسال (مشكلة اتصال).')) ?>', false);
                }finally{
                  sendBtn && (sendBtn.disabled = false);
                }
              }

              document.addEventListener('click', function(ev){
                const btn = ev.target && ev.target.closest ? ev.target.closest('.gdy-cmt-reply') : null;
                if(!btn) return;
                const id = Number(btn.getAttribute('data-id')||0);
                if(!id) return;
                replyTo = id;
                setHint('<?= h(__('أنت ترد على تعليق. اكتب ردك ثم اضغط إرسال.')) ?>', true);
                if(bodyEl) bodyEl.focus();
              });

              if(sendBtn) sendBtn.addEventListener('click', send);

              (async function init(){
                await getCSRF();
                await load();
              })();
            })();
            </script>
          </div>
        </article>


      </section>

      <aside class="gdy-right gdy-sidebar">
        
        <div class="gdy-card">
          <div class="gdy-card-h">
            <strong><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> فهرس المحتوى</strong>
            <span style="color:#64748b;font-size:.78rem;">قفز سريع</span>
          </div>
          <div class="gdy-card-b gdy-toc">
            <?php if (!empty($toc)): ?>
              <?php foreach ($toc as $item): ?>
                <div class="<?= $item['level'] === 3 ? 'lv3' : 'lv2' ?>">
                  <a href="#<?= h($item['id']) ?>"><?= h($item['text']) ?></a>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="color:#64748b;font-size:.9rem;">لا توجد عناوين داخل المحتوى لعرض فهرس.</div>
            <?php endif; ?>
          </div>
        </div>

<?php if (!empty($related) && is_array($related)): ?>
          <div class="gdy-card" style="margin-bottom:14px;">
            <div class="gdy-card-h"><strong><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#news"></use></svg> تقارير ذات صلة</strong></div>
            <div class="gdy-card-b gdy-side-list">
              <?php foreach ($related as $r): ?>
                <?php
                  $rid = (int)($r['id'] ?? 0);
                  $rt  = (string)($r['title'] ?? '');
                  $rd  = (string)($r['published_at'] ?? ($r['created_at'] ?? ''));
                  $rurl = $rid > 0 ? ($baseUrl . '/news/id/' . $rid) : '#';
                ?>
                <a class="gdy-side-item" href="<?= h($rurl) ?>">
                  <div class="t"><?= h($rt) ?></div>
                  <div class="m">
                    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                    <span><?= $rd ? h(date('Y/m/d', strtotime($rd))) : '' ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($mostReadNews) && is_array($mostReadNews)): ?>
          <div class="gdy-card">
            <div class="gdy-card-h"><strong><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> الأكثر قراءة</strong></div>
            <div class="gdy-card-b gdy-side-list">
              <?php foreach ($mostReadNews as $r): ?>
                <?php
                  $rid = (int)($r['id'] ?? 0);
                  $rt  = (string)($r['title'] ?? '');
                  $rurl = $rid > 0 ? ($baseUrl . '/news/id/' . $rid) : '#';
                ?>
                <a class="gdy-side-item" href="<?= h($rurl) ?>">
                  <div class="t"><?= h($rt) ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</main>

<script>
(function(){
  const pageUrl = <?= json_encode((string)($pageUrl ?? $newsUrl ?? '')) ?>;

  const byId = (id) => document.getElementById(id);
  const toast = (msg) => {
    if(!msg) return;
    let t = byId('gdyToast');
    if(!t){
      t = document.createElement('div');
      t.id = 'gdyToast';
      t.style.position = 'fixed';
      t.style.left = '16px';
      t.style.bottom = '16px';
      t.style.zIndex = '99999';
      t.style.background = 'rgba(15,23,42,.92)';
      t.style.color = '#fff';
      t.style.padding = '10px 12px';
      t.style.borderRadius = '12px';
      t.style.boxShadow = '0 12px 28px rgba(0,0,0,.25)';
      t.style.fontSize = '14px';
      t.style.maxWidth = 'calc(100vw - 32px)';
      t.style.opacity = '0';
      t.style.transition = 'opacity .18s ease, transform .18s ease';
      t.style.transform = 'translateY(6px)';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = '1';
    t.style.transform = 'translateY(0)';
    clearTimeout(t._hideTimer);
    t._hideTimer = setTimeout(()=>{
      t.style.opacity = '0';
      t.style.transform = 'translateY(6px)';
    }, 2200);
  };

  const btnCopy  = byId('gdyCopyLink');
  const btnPrint = byId('gdyPrint');
  const btnPdf   = byId('gdyPdf');
  const btnRead  = byId('gdyReadingMode');
  const btnFont  = null;
  const btnInc   = byId('gdyFontInc');
  const btnDec   = byId('gdyFontDec');
  const btnQr    = byId('gdyQrToggle');
  const btnAi    = byId('gdyAiToggle');

  const articleBody = byId('gdyArticleBody');
  const heroQr = byId('gdyHeroQr');
  const heroQrTip = byId('gdyHeroQrTip');
  const aiBox  = byId('gdyAiBox');

  // ===== Copy link =====
  async function copyText(txt){
    try{
      if(navigator.clipboard && navigator.clipboard.writeText){
        await navigator.clipboard.writeText(txt);
        return true;
      }
    }catch(e){}
    try{
      const ta = document.createElement('textarea');
      ta.value = txt;
      ta.setAttribute('readonly','');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      return true;
    }catch(e){}
    return false;
  }

  if(btnCopy){
    btnCopy.addEventListener('click', async function(){
      if(!pageUrl){ toast('تعذّر نسخ الرابط.'); return; }
      const ok = await copyText(pageUrl);
      toast(ok ? 'تم نسخ الرابط.' : 'تعذّر النسخ. انسخ يدويًا: ' + pageUrl);
    });
  }

  // ===== Print / PDF =====
  if(btnPrint){
    btnPrint.addEventListener('click', function(){
      window.print();
    });
  }
  if(btnPdf){
    btnPdf.addEventListener('click', function(e){
      e.preventDefault();
      // متصفحات المستخدم تسمح بالحفظ كـ PDF من نافذة الطباعة
      window.print();
    });
  }

  // ===== Reading mode =====
  const LS_READ = 'gdy_reading_mode';
  function setReadingMode(on){
    document.body.classList.toggle('gdy-reading-mode', !!on);
    if(btnRead) btnRead.classList.toggle('is-active', !!on);
    try{ localStorage.setItem(LS_READ, on ? '1' : '0'); }catch(e){}
  }
  try{
    const saved = localStorage.getItem(LS_READ);
    if(saved === '1') setReadingMode(true);
  }catch(e){}
  if(btnRead){
    btnRead.addEventListener('click', function(){
      setReadingMode(!document.body.classList.contains('gdy-reading-mode'));
    });
  }

  // ===== Font size controls =====
  const LS_SCALE = 'gdy_font_scale';
  let baseSize = null;
  function getBaseSize(){
    if(baseSize !== null) return baseSize;
    if(!articleBody) return 18;
    const cs = getComputedStyle(articleBody);
    baseSize = parseFloat(cs.fontSize) || 18;
    return baseSize;
  }
  function setScale(scale){
    if(!articleBody) return;
    scale = Math.max(0.75, Math.min(1.6, scale));
    const sz = getBaseSize() * scale;
    articleBody.style.fontSize = sz.toFixed(2) + 'px';
    try{ localStorage.setItem(LS_SCALE, String(scale)); }catch(e){}
  }
  function getScale(){
    try{
      const s = parseFloat(localStorage.getItem(LS_SCALE) || '1');
      if(!isNaN(s)) return s;
    }catch(e){}
    return 1;
  }
  // apply saved scale
  setScale(getScale());

  if(btnInc) btnInc.addEventListener('click', ()=> setScale(getScale() + 0.08));
  if(btnDec) btnDec.addEventListener('click', ()=> setScale(getScale() - 0.08));

  // ===== Font family toggle (TT) =====
  let alt = false;
  try{ alt = (localStorage.getItem(LS_FONT) === '1'); }catch(e){}
  setAltFont(alt);
  if(btnFont){
    btnFont.addEventListener('click', function(){
      alt = !alt;
      setAltFont(alt);
      toast(alt ? 'تم تفعيل خط بديل.' : 'تم الرجوع للخط الافتراضي.');
    });
  }

  // ===== QR toggle =====
  const LS_QR = 'gdy_qr_hidden';
  function setQrHidden(hidden){
    const h = !!hidden;
    if(heroQr) heroQr.style.display = h ? 'none' : '';
    if(heroQrTip) heroQrTip.style.display = h ? 'none' : '';
    if(btnQr) btnQr.classList.toggle('is-active', !h);
    try{ localStorage.setItem(LS_QR, h ? '1' : '0'); }catch(e){}
  }
  try{
    if(localStorage.getItem(LS_QR) === '1') setQrHidden(true);
  }catch(e){}
  if(btnQr){
    btnQr.addEventListener('click', function(){
      const isHidden = heroQr && getComputedStyle(heroQr).display === 'none';
      setQrHidden(!isHidden);
    });
  }
  // click QR to copy link
  const qrImg = document.querySelector('.gdy-qr-image');
  if(qrImg){
    qrImg.addEventListener('click', async function(){
      if(!pageUrl) return;
      const ok = await copyText(pageUrl);
      toast(ok ? 'تم نسخ الرابط من QR.' : 'تعذّر النسخ.');
    });
  }

  // ===== AI box toggle =====
  const LS_AI = 'gdy_ai_open';
  function setAiOpen(on){
    if(!aiBox) return;
    aiBox.hidden = !on;
    if(btnAi){
      btnAi.classList.toggle('is-active', !!on);
      btnAi.setAttribute('aria-expanded', on ? 'true' : 'false');
    }
    try{ localStorage.setItem(LS_AI, on ? '1' : '0'); }catch(e){}
  }
  // default: closed unless previously opened
  try{
    const saved = localStorage.getItem(LS_AI);
    if(saved === '1') setAiOpen(true);
    else setAiOpen(false);
  }catch(e){
    setAiOpen(false);
  }
  if(btnAi){
    btnAi.addEventListener('click', function(){
      if(!aiBox) return;
      const isOpen = !aiBox.hidden;
      setAiOpen(!isOpen);
    });
  }
})();
</script>
<!-- News extras (TTS / Poll / Translate / Reactions / Q&A) -->
<script>
  window.GDY_BASE = <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
  // Metered Paywall: سجل قراءة المقال للزائر (٣ مقالات/أسبوع)
  (function(){
    try{
      var isGuest = <?= json_encode($isGuest) ?>;
      var membersOnly = <?= json_encode($membersOnly) ?>;
      var meteredLocked = <?= json_encode($meteredLocked) ?>;
      var postId = <?= (int)$postId ?>;
      if(!isGuest || membersOnly || meteredLocked || !postId) return;

      var limit = <?= (int)$meterLimit ?>;
      var windowSec = <?= (int)$meterWindowSeconds ?>;

      function getCookie(name){
        var m = document.cookie.match('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/\+^])/g,'\\$1') + '=([^;]*)');
        return m ? decodeURIComponent(m[1]) : '';
      }
      function setCookie(name, value, maxAge){
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + (maxAge||0) + '; samesite=lax';
      }

      var raw = getCookie('gdy_meter');
      var items = [];
      if(raw){
        try{ items = JSON.parse(raw); }catch(e){ items = []; }
      }
      if(!Array.isArray(items)) items = [];

      var now = Math.floor(Date.now()/1000);
      // فلترة حسب نافذة الأسبوع
      var fresh = [];
      var seen = {};
      for(var i=0;i<items.length;i++){
        var it = items[i] || {};
        var id = parseInt(it.id||0,10);
        var t  = parseInt(it.t||0,10);
        if(id>0 && t>0 && (now - t) <= windowSec){
          if(!seen[id]){
            fresh.push({id:id,t:t});
            seen[id]=true;
          }
        }
      }

      // إذا لم يكن المقال مُسجلاً في النافذة الحالية، أضفه
      if(!seen[postId]){
        fresh.push({id:postId,t:now});
      }

      // ترتيب بالأحدث وتقليل الحجم
      fresh.sort(function(a,b){ return (b.t||0) - (a.t||0); });
      if(fresh.length > 50) fresh = fresh.slice(0,50);

      setCookie('gdy_meter', JSON.stringify(fresh), 60*60*24*30);
    }catch(e){}
  })();
</script>

<script defer src="<?= h($baseUrl) ?>/assets/js/news-extras.js?v=20260102"></script>

<?php
if (!defined('GDY_TPL_WRAPPED') && is_file($footer)) {
    require $footer;
}