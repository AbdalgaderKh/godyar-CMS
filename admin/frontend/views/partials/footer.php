<?php
// /frontend/views/partials/footer.php

if (!function_exists('h')) {
    function h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$siteSettings = [];
if (class_exists('HomeController')) {
    try {
        $siteSettings = HomeController::getSiteSettings();
        if (!is_array($siteSettings)) {
            $siteSettings = [];
        }
    } catch (Throwable $e) {
        $siteSettings = [];
    }
}

// بيانات الموقع الأساسية
$siteName    = $siteSettings['site_name']    ?? 'Godyar News';
$siteTagline = $siteSettings['site_tagline'] ?? 'منصة إخبارية متكاملة';
$siteEmail   = $siteSettings['site_email']   ?? '';
$sitePhone   = $siteSettings['site_phone']   ?? '';
$siteAddr    = $siteSettings['site_address'] ?? '';

// كود نهاية الـ body من الإعدادات
$extraBodyCode = $siteSettings['extra_body_code'] ?? '';

// روابط التواصل الاجتماعي (كما تُرجع من HomeController)
$socialFacebook  = trim((string)($siteSettings['social_facebook']  ?? ''));
$socialTwitter   = trim((string)($siteSettings['social_twitter']   ?? ''));
$socialYoutube   = trim((string)($siteSettings['social_youtube']   ?? ''));
$socialTelegram  = trim((string)($siteSettings['social_telegram']  ?? ''));
$socialInstagram = trim((string)($siteSettings['social_instagram'] ?? ''));
$socialWhatsApp  = trim((string)($siteSettings['social_whatsapp']  ?? ''));

// روابط تطبيقات الجوال (إن وجدت)
$appAndroid = trim((string)($siteSettings['app_android_url'] ?? ''));
$appIos     = trim((string)($siteSettings['app_ios_url']     ?? ''));
$hasApps    = ($appAndroid || $appIos);

// رابط صفحة فريق العمل (من الإعدادات أو افتراضي)
$teamUrl = trim((string)($siteSettings['link_team'] ?? '/team.php'));

// روابط سريعة في التذييل
$footerLinks = [
    [
        'label' => 'من نحن',
        'url'   => $siteSettings['link_about'] ?? '/page/about',
    ],
    [
        'label' => 'اتصل بنا',
        'url'   => $siteSettings['link_contact'] ?? '/contact.php',
    ],
    [
        'label' => 'سياسة الخصوصية',
        'url'   => $siteSettings['link_privacy'] ?? '/page/privacy',
    ],
];

$year = (int)date('Y');
?>

      </div><!-- /.container من الهيدر -->
    </div><!-- /.page-shell -->
  </main>

  <style>
    .gdy-footer {
      background: #020617;
      color: #e5e7eb;
      border-top: 1px solid #0b1120;
      padding: 18px 0 14px;
      margin-top: 32px;
      font-size: .85rem;
    }
    .gdy-footer-inner {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .gdy-footer-main {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      flex-wrap: wrap;
      border-bottom: 1px solid rgba(55,65,81,.9);
      padding-bottom: .5rem;
      margin-bottom: .4rem;
    }
    .gdy-footer-brand {
      min-width: 180px;
    }
    .gdy-footer-title {
      font-weight: 700;
      font-size: 1rem;
    }
    .gdy-footer-tagline {
      font-size: .8rem;
      color: #9ca3af;
      margin-top: .2rem;
    }
    .gdy-footer-contact div {
      font-size: .8rem;
      color: #e5e7eb;
      white-space: nowrap;
    }
    .gdy-footer-contact div + div {
      margin-top: .15rem;
    }

    .gdy-footer-extra {
      display: flex;
      flex-direction: column;
      gap: .35rem;
      align-items: flex-start;
      min-width: 220px;
    }

    /* روابط التذييل السريعة */
    .gdy-footer-links {
      display: flex;
      flex-wrap: wrap;
      gap: .75rem;
      font-size: .78rem;
    }
    .gdy-footer-links a {
      color: #9ca3af;
      text-decoration: none;
    }
    .gdy-footer-links a:hover {
      color: #e5e7eb;
      text-decoration: underline;
    }

    .gdy-footer-social {
      display: flex;
      flex-direction: column;
      gap: .25rem;
      font-size: .8rem;
    }
    .gdy-footer-social-label {
      color: #9ca3af;
    }

    /* حاوية أيقونات السوشال */
    .gdy-footer-social-links {
      display: flex;
      flex-wrap: wrap;
      gap: .45rem;
    }

    /* أيقونة دائرية متوهجة (تُستخدم للسوشال وفريق العمل) */
    .gdy-social-icon {
      position: relative;
      width: 38px;
      height: 38px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      overflow: hidden;
      color: #e5e7eb;
      isolation: isolate;
      background: radial-gradient(circle at 30% 0%, #1f2937 0, #020617 60%);
      box-shadow:
        0 0 0 1px rgba(148,163,184,.4),
        0 8px 20px rgba(15,23,42,.7);
      transition:
        transform .18s ease,
        box-shadow .18s ease,
        background .18s ease,
        color .18s ease;
      animation: socialPulse 4s ease-in-out infinite;
    }

    /* حالة الأيقونة المعطلة (لا يوجد رابط) */
    .gdy-social-icon.is-disabled {
      opacity: .55;
      cursor: default;
      animation: none;
    }
    .gdy-social-icon.is-disabled:hover {
      transform: none;
      box-shadow:
        0 0 0 1px rgba(148,163,184,.4),
        0 8px 20px rgba(15,23,42,.7);
    }

    /* الطبقة المضيئة */
    .gdy-social-icon::before {
      content: "";
      position: absolute;
      inset: -40%;
      background: conic-gradient(
        from 180deg,
        rgba(96,165,250,.0),
        rgba(96,165,250,.6),
        rgba(244,114,182,.7),
        rgba(96,165,250,.0)
      );
      opacity: 0;
      transform: scale(.6);
      filter: blur(8px);
      transition:
        opacity .22s ease,
        transform .22s ease,
        filter .22s ease;
      z-index: -1;
    }

    /* الطبقة الداخلية */
    .gdy-social-icon::after {
      content: "";
      position: absolute;
      inset: 2px;
      border-radius: inherit;
      background: radial-gradient(circle at 30% 0%, #0f172a 0, #020617 65%);
      z-index: -1;
    }

    .gdy-social-icon .gdy-icon{
      position: relative;
      z-index: 1;
      font-size: .95rem;
    }

    /* توهج وحركة عند المرور (للأيقونات غير المعطلة) */
    .gdy-social-icon:hover:not(.is-disabled) {
      transform: translateY(-2px) scale(1.05);
      box-shadow:
        0 0 0 1px rgba(248,250,252,.5),
        0 12px 30px rgba(15,23,42,.9);
      color: #f9fafb;
    }

    .gdy-social-icon:hover:not(.is-disabled)::before {
      opacity: 1;
      transform: scale(1);
      filter: blur(3px);
    }

    /* نص صغير باسم الشبكة (tooltip تحت الأيقونة عند المرور) */
    .gdy-footer-social-tooltip {
      position: absolute;
      bottom: -1.5rem;
      left: 50%;
      transform: translateX(-50%) translateY(4px);
      padding: 1px 8px;
      border-radius: 999px;
      background: rgba(15,23,42,.96);
      color: #e5e7eb;
      font-size: .65rem;
      white-space: nowrap;
      opacity: 0;
      pointer-events: none;
      box-shadow: 0 4px 10px rgba(15,23,42,.75);
      transition: opacity .18s ease, transform .18s ease;
      z-index: 3;
    }

    .gdy-social-icon:hover .gdy-footer-social-tooltip {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }

    /* تفاوت ألوان التوهج حسب نوع الشبكة */
    .gdy-social-icon.is-facebook::before {
      background: conic-gradient(
        from 180deg,
        rgba(59,130,246,0),
        rgba(59,130,246,.85),
        rgba(37,99,235,.9),
        rgba(59,130,246,0)
      );
    }
    .gdy-social-icon.is-twitter::before {
      background: conic-gradient(
        from 180deg,
        rgba(56,189,248,0),
        rgba(56,189,248,.9),
        rgba(8,47,73,.9),
        rgba(56,189,248,0)
      );
    }
    .gdy-social-icon.is-youtube::before {
      background: conic-gradient(
        from 180deg,
        rgba(248,113,113,0),
        rgba(248,113,113,.95),
        rgba(127,29,29,.9),
        rgba(248,113,113,0)
      );
    }
    .gdy-social-icon.is-telegram::before {
      background: conic-gradient(
        from 180deg,
        rgba(96,165,250,0),
        rgba(96,165,250,.95),
        rgba(15,118,110,.9),
        rgba(96,165,250,0)
      );
    }
    .gdy-social-icon.is-instagram::before {
      background: conic-gradient(
        from 180deg,
        rgba(249,115,22,0),
        rgba(249,115,22,.95),
        rgba(219,39,119,.95),
        rgba(96,165,250,.9),
        rgba(249,115,22,0)
      );
    }
    .gdy-social-icon.is-whatsapp::before {
      background: conic-gradient(
        from 180deg,
        rgba(34,197,94,0),
        rgba(34,197,94,.95),
        rgba(21,128,61,.9),
        rgba(34,197,94,0)
      );
    }
    /* توهج خاص لأيقونة فريق العمل */
    .gdy-social-icon.is-team::before {
      background: conic-gradient(
        from 180deg,
        rgba(129,140,248,0),
        rgba(129,140,248,.95),
        rgba(59,130,246,.9),
        rgba(16,185,129,.9),
        rgba(129,140,248,0)
      );
    }

    /* نبضة خفيفة دورية للأيقونات داخل شريط السوشال */
    @keyframes socialPulse {
      0%   { transform: translateY(0) scale(1);   box-shadow: 0 8px 20px rgba(15,23,42,.7); }
      50%  { transform: translateY(-1px) scale(1.02); box-shadow: 0 10px 26px rgba(15,23,42,.85); }
      100% { transform: translateY(0) scale(1);   box-shadow: 0 8px 20px rgba(15,23,42,.7); }
    }

    .gdy-footer-social-links .gdy-social-icon:nth-child(2) { animation-delay: .15s; }
    .gdy-footer-social-links .gdy-social-icon:nth-child(3) { animation-delay: .3s; }
    .gdy-footer-social-links .gdy-social-icon:nth-child(4) { animation-delay: .45s; }
    .gdy-footer-social-links .gdy-social-icon:nth-child(5) { animation-delay: .6s; }
    .gdy-footer-social-links .gdy-social-icon:nth-child(6) { animation-delay: .75s; }

    /* روابط التطبيقات */
    .gdy-footer-apps {
      display: flex;
      flex-wrap: wrap;
      gap: .3rem;
      font-size: .75rem;
    }
    .gdy-footer-apps a {
      padding: .2rem .5rem;
      border-radius: 999px;
      border: 1px solid rgba(156,163,175,.5);
      color: #e5e7eb;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .25rem;
      transition: background-color .15s ease, color .15s ease, border-color .15s ease;
    }
    .gdy-footer-apps a:hover {
      background-color: #0f172a;
      color: #fff;
      border-color: #22c55e;
    }

    .gdy-footer-bottom {
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      align-items: center;
      gap: .5rem;
      color: #9ca3af;
      font-size: .78rem;
    }
    .gdy-footer-made strong {
      color: #e5e7eb;
    }

    /* رابط فريق العمل في التذييل */
    .gdy-footer-team-link {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      text-decoration: none;
      color: #e5e7eb;
      font-size: .8rem;
    }
    .gdy-footer-team-link span:last-child {
      white-space: nowrap;
    }
    .gdy-footer-team-link:hover span:last-child {
      text-decoration: underline;
      color: #fbbf24;
    }

    /* زر العودة للأعلى */
    .gdy-scroll-top {
      position: fixed;
      bottom: 1.5rem;
      left: 1.5rem;
      width: 38px;
      height: 38px;
      border-radius: 999px;
      border: none;
      background: #0f172a;
      color: #e5e7eb;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 20px rgba(15,23,42,.55);
      cursor: pointer;
      opacity: 0;
      pointer-events: none;
      transition: opacity .18s ease, transform .18s ease;
      z-index: 999;
    }
    .gdy-scroll-top.is-visible {
      opacity: 1;
      pointer-events: auto;
      transform: translateY(0);
    }
    .gdy-scroll-top:hover {
      background: #1f2937;
    }
    .gdy-scroll-top i {
      font-size: .9rem;
    }

    @media (max-width: 768px) {
      .gdy-footer-main {
        flex-direction: column;
        align-items: flex-start;
      }
      .gdy-footer-contact div {
        white-space: normal;
      }
    }
  </style>

  <footer class="gdy-footer">
    <div class="container gdy-footer-inner">
      <div class="gdy-footer-main">
        <!-- هوية الموقع -->
        <div class="gdy-footer-brand">
          <div class="gdy-footer-title"><?= h($siteName) ?></div>
          <div class="gdy-footer-tagline">
            <?= h($siteTagline) ?>
          </div>
        </div>

        <!-- بيانات التواصل + العنوان -->
        <div class="gdy-footer-contact">
          <?php if ($siteEmail): ?>
            <div><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($siteEmail) ?></div>
          <?php endif; ?>
          <?php if ($sitePhone): ?>
            <div><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg> <?= h($sitePhone) ?></div>
          <?php endif; ?>
          <?php if ($siteAddr): ?>
            <div>
              <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <strong>العنوان:</strong>
              <?= h($siteAddr) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- روابط سريعة + تواصل اجتماعي + تطبيقات -->
        <div class="gdy-footer-extra">
          <?php if (!empty($footerLinks)): ?>
            <nav class="gdy-footer-links" aria-label="روابط تذييل">
              <?php foreach ($footerLinks as $link):
                  $url   = trim((string)($link['url'] ?? ''));
                  $label = trim((string)($link['label'] ?? ''));
                  if ($url === '' || $label === '') continue;
              ?>
                <a href="<?= h($url) ?>"><?= h($label) ?></a>
              <?php endforeach; ?>
            </nav>
          <?php endif; ?>

          <div class="gdy-footer-social">
            <span class="gdy-footer-social-label">تابعنا على:</span>
            <div class="gdy-footer-social-links">
              <?php
              if (!function_exists('render_social_icon')) {
                  function render_social_icon(string $url, string $label, string $baseClass, string $iconClass): void {
                      $hasUrl = ($url !== '');
                      $tag    = $hasUrl ? 'a' : 'span';
                      $href   = $hasUrl ? ' href="' . h($url) . '"' : '';
                      $extra  = $hasUrl ? ' target="_blank" rel="noopener noreferrer"' : '';
                      $aria   = ' aria-label="' . h($label) . '"';
                      $class  = 'gdy-social-icon ' . $baseClass . ($hasUrl ? '' : ' is-disabled');
                      echo '<' . $tag . $href . ' class="' . $class . '"' . $extra . $aria . '>';
                      $iconId = 'dot';
                      $lc = strtolower($iconClass);
                      if (strpos($lc, 'facebook') !== false) { $iconId = 'facebook'; }
                      elseif (strpos($lc, 'x-twitter') !== false || strpos($lc, 'twitter') !== false) { $iconId = 'x'; }
                      elseif (strpos($lc, 'youtube') !== false) { $iconId = 'youtube'; }
                      elseif (strpos($lc, 'telegram') !== false) { $iconId = 'telegram'; }
                      elseif (strpos($lc, 'instagram') !== false) { $iconId = 'instagram'; }
                      elseif (strpos($lc, 'whatsapp') !== false) { $iconId = 'whatsapp'; }
                      echo '<svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#' . $iconId . '"></use></svg>';
                      echo '<span class="gdy-footer-social-tooltip">' . h($label) . '</span>';
                      echo '</' . $tag . '>';
                  }
              }

              render_social_icon($socialFacebook,  'فيسبوك',     'is-facebook',  'fa-brands fa-facebook-f');
              render_social_icon($socialTwitter,   'تويتر / X',  'is-twitter',   'fa-brands fa-x-twitter');
              render_social_icon($socialYoutube,   'يوتيوب',     'is-youtube',   'fa-brands fa-youtube');
              render_social_icon($socialTelegram,  'تليجرام',    'is-telegram',  'fa-brands fa-telegram');
              render_social_icon($socialInstagram, 'إنستغرام',   'is-instagram', 'fa-brands fa-instagram');
              render_social_icon($socialWhatsApp,  'واتساب',     'is-whatsapp',  'fa-brands fa-whatsapp');
              ?>
            </div>
          </div>

          <?php if ($hasApps): ?>
            <div class="gdy-footer-apps">
              <?php if ($appAndroid): ?>
                <a href="<?= h($appAndroid) ?>" target="_blank" rel="noopener noreferrer">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <span>تطبيق أندرويد</span>
                </a>
              <?php endif; ?>
              <?php if ($appIos): ?>
                <a href="<?= h($appIos) ?>" target="_blank" rel="noopener noreferrer">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <span>تطبيق iOS</span>
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="gdy-footer-bottom">
        <span>© <?= date('Y') ?> <?= h($siteName) ?>. جميع الحقوق محفوظة.</span>

        <?php if (!empty($teamUrl)): ?>
          <a href="<?= h($teamUrl) ?>" class="gdy-footer-team-link">
            <span class="gdy-social-icon is-team">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <span class="gdy-footer-social-tooltip">فريق العمل</span>
            </span>
            <span>فريق العمل</span>
          </a>
        <?php endif; ?>

        <span class="gdy-footer-made">
          مُدار بواسطة نظام <strong>Godyar</strong>
        </span>
      </div>
    </div>
  </footer>

  <!-- زر العودة للأعلى -->
  <button type="button" class="gdy-scroll-top" aria-label="العودة للأعلى">
    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
  </button>

  <?php if (!empty($extraBodyCode)): ?>
    <?= $extraBodyCode . "\n" ?>
  <?php endif; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var scrollBtn = document.querySelector('.gdy-scroll-top');
      if (!scrollBtn) return;

      function toggleScrollBtn() {
        if (window.scrollY > 250) {
          scrollBtn.classList.add('is-visible');
        } else {
          scrollBtn.classList.remove('is-visible');
        }
      }

      window.addEventListener('scroll', toggleScrollBtn);
      toggleScrollBtn();

      scrollBtn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  </script>


  <!-- Glossary tooltip styles & behavior -->
  <style>
    .gdy-glossary-term{
      border-bottom:1px dashed rgba(55,65,81,.9);
      cursor:help;
      position:relative;
      transition:background-color .15s ease, color .15s ease;
    }
    .gdy-glossary-term:hover{
      background:rgba(59,130,246,.06);
    }
    .gdy-glossary-tooltip{
      position:absolute;
      z-index:9999;
      background:#020617;
      color:#e5e7eb;
      padding:.6rem .75rem;
      border-radius:.5rem;
      font-size:.8rem;
      max-width:260px;
      line-height:1.6;
      box-shadow:0 10px 30px rgba(15,23,42,.45);
      border:1px solid rgba(148,163,184,.6);
    }
    .gdy-glossary-tooltip::after{
      content:"";
      position:absolute;
      bottom:-6px;
      right:1.5rem;
      border-width:6px 6px 0 6px;
      border-style:solid;
      border-color:#020617 transparent transparent transparent;
    }
    @media (max-width: 768px){
      .gdy-glossary-tooltip{
        max-width:80vw;
      }
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      let tooltip = null;

      function ensureTooltip(){
        if(!tooltip){
          tooltip = document.createElement('div');
          tooltip.className = 'gdy-glossary-tooltip';
          tooltip.style.display = 'none';
          document.body.appendChild(tooltip);
        }
        return tooltip;
      }

      function showTooltip(el){
        const text = el.getAttribute('data-definition');
        if(!text) return;
        const tip = ensureTooltip();
        tip.textContent = text;

        tip.style.display = 'block';
        tip.style.visibility = 'hidden';

        const rect = el.getBoundingClientRect();
        const scrollY = window.scrollY || window.pageYOffset;
        const scrollX = window.scrollX || window.pageXOffset;

        const padding = 10;
        let top = rect.top + scrollY - tip.offsetHeight - 10;
        if(top < scrollY + 10){
          top = rect.bottom + scrollY + 10;
          tip.style.transformOrigin = 'top right';
        } else {
          tip.style.transformOrigin = 'bottom right';
        }

        let left = rect.right + scrollX - tip.offsetWidth;
        if(left < scrollX + padding){
          left = scrollX + padding;
        }

        tip.style.top = top + 'px';
        tip.style.left = left + 'px';
        tip.style.visibility = 'visible';
      }

      function hideTooltip(){
        if(tooltip){
          tooltip.style.display = 'none';
        }
      }

      document.body.addEventListener('mouseenter', function(e){
        if(e.target.classList && e.target.classList.contains('gdy-glossary-term')){
          showTooltip(e.target);
        }
      }, true);

      document.body.addEventListener('mouseleave', function(e){
        if(e.target.classList && e.target.classList.contains('gdy-glossary-term')){
          hideTooltip();
        }
      }, true);

      document.body.addEventListener('click', function(e){
        if(e.target.classList && e.target.classList.contains('gdy-glossary-term')){
          e.preventDefault();
          showTooltip(e.target);
        } else if(tooltip && !tooltip.contains(e.target)){
          hideTooltip();
        }
      }, true);
    });
  </script>

</body>
</html>
