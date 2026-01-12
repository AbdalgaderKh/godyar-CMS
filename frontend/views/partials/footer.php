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
        'label' => __('about_us'),
        'url'   => $siteSettings['link_about'] ?? '/page/about',
    ],
    [
        'label' => __('contact_us'),
        'url'   => $siteSettings['link_contact'] ?? '/contact.php',
    ],
    [
        'label' => __('privacy_policy'),
        'url'   => $siteSettings['link_privacy'] ?? '/page/privacy',
    ],
];

// سنة (قد تُفقد إذا تم include جزئي) — نعرّفها هنا
$year = (int)date('Y');

// Base URLs (root + language prefix)
$_gdy_baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
$_gdy_lang = function_exists('gdy_lang') ? (string)gdy_lang() : 'ar';
$_gdy_navBaseUrl = rtrim($_gdy_baseUrl, '/') . '/' . trim($_gdy_lang, '/');

?>

      </div><!-- /.container من الهيدر -->
    </div><!-- /.page-shell -->
  </main>

  <style>
    /* Footer (Light, theme-aware) */
    .gdy-footer{
      position: relative;
      overflow: hidden;
      background: var(--footer-bg, linear-gradient(180deg, rgba(var(--primary-rgb), .12) 0%, rgba(var(--primary-rgb), .06) 100%));
      color: var(--footer-text, #0f172a);
      border-top: 1px solid rgba(var(--primary-rgb), .18);
      padding: 28px 0 18px;
      margin-top: 36px;
      font-size: .9rem;
    }
    .gdy-footer::before{
      content:"";
      position:absolute;
      inset:0;
      pointer-events:none;
      background: radial-gradient(1200px 220px at 20% 0%, rgba(255,255,255,.75), rgba(255,255,255,0));
      opacity:.85;
    }

    .gdy-footer-inner{
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .gdy-footer-main{
      display: grid;
      grid-template-columns: 1.2fr 1fr 1.2fr;
      gap: 1.25rem;
      align-items: start;
      padding-bottom: 14px;
      border-bottom: 1px solid rgba(var(--primary-rgb), .14);
    }

    .gdy-footer-brand{ min-width: 200px; }
    .gdy-footer-title{ font-weight: 800; font-size: 1.05rem; letter-spacing: .2px; }
    .gdy-footer-tagline{ margin-top: .25rem; font-size: .85rem; color: var(--footer-muted, rgba(15,23,42,.72)); }

    .gdy-footer-contact{ font-size: .86rem; }
    .gdy-footer-contact div{ color: var(--footer-text, #0f172a); line-height: 1.85; }
    .gdy-footer-contact a{ color: inherit; text-decoration: none; border-bottom: 1px dashed rgba(var(--primary-rgb), .35); }
    .gdy-footer-contact a:hover{ color: var(--primary); border-bottom-color: rgba(var(--primary-rgb), .55); }

    .gdy-footer-extra{ display:flex; flex-direction:column; gap:.75rem; }

    .gdy-footer-links{
      display:flex;
      flex-wrap:wrap;
      gap:.65rem 1rem;
      align-items:center;
    }
    .gdy-footer-links a{
      color: var(--footer-muted, rgba(15,23,42,.72));
      text-decoration:none;
      padding:.2rem .1rem;
      border-radius:.5rem;
      transition: color .15s ease, background-color .15s ease;
    }
    .gdy-footer-links a:hover{
      color: var(--footer-text, #0f172a);
      background: rgba(var(--primary-rgb), .08);
    }

    .gdy-footer-social{
      display:flex;
      gap:.65rem;
      align-items:center;
      flex-wrap:wrap;
    }
    .gdy-footer-social-label{ color: var(--footer-muted, rgba(15,23,42,.72)); font-size:.85rem; }

    .gdy-footer-social-links{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }

    .gdy-social-icon{
      position: relative;
      width: 42px;
      height: 42px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      border-radius: 999px;
      background: rgba(255,255,255,.92);
      border: 1px solid rgba(var(--primary-rgb), .22);
      color: var(--primary);
      box-shadow: 0 10px 24px rgba(15,23,42,.10);
      transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease, border-color .15s ease;
      text-decoration:none;
    }
    .gdy-social-icon .gdy-icon{ color: var(--primary); font-size: 1.05rem; }
    .gdy-social-icon:hover{
      transform: translateY(-2px);
      background: rgba(var(--primary-rgb), .10);
      border-color: rgba(var(--primary-rgb), .40);
      box-shadow: 0 16px 34px rgba(15,23,42,.14);
    }

    .gdy-footer-social-tooltip{
      position:absolute;
      bottom: 52px;
      right: 50%;
      transform: translateX(50%);
      background: rgba(255,255,255,.96);
      color: var(--footer-text, #0f172a);
      padding: .4rem .55rem;
      border-radius: .6rem;
      font-size: .78rem;
      white-space: nowrap;
      opacity: 0;
      pointer-events:none;
      border: 1px solid rgba(var(--primary-rgb), .22);
      box-shadow: 0 16px 34px rgba(15,23,42,.12);
      transition: opacity .15s ease, transform .15s ease;
    }
    .gdy-social-icon:hover .gdy-footer-social-tooltip{
      opacity: 1;
      transform: translateX(50%) translateY(-2px);
    }

    .gdy-footer-apps{
      display:flex;
      flex-wrap:wrap;
      gap:.5rem 1rem;
      align-items:center;
    }
    .gdy-footer-apps a{
      display:inline-flex;
      align-items:center;
      gap:.45rem;
      padding:.35rem .55rem;
      border-radius: .75rem;
      background: rgba(255,255,255,.80);
      border: 1px solid rgba(var(--primary-rgb), .18);
      color: var(--footer-text, #0f172a);
      text-decoration:none;
      transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
    }
    .gdy-footer-apps a:hover{
      background: rgba(var(--primary-rgb), .08);
      border-color: rgba(var(--primary-rgb), .35);
      transform: translateY(-1px);
    }

    .gdy-footer-bottom{
      display:flex;
      justify-content: space-between;
      align-items:center;
      gap: .75rem;
      flex-wrap: wrap;
      color: var(--footer-muted, rgba(15,23,42,.72));
      font-size: .82rem;
      padding-top: 6px;
    }

    .gdy-footer-team-link{
      position: relative;
      display:inline-flex;
      align-items:center;
      gap:.5rem;
      padding:.35rem .55rem;
      border-radius: 999px;
      background: rgba(255,255,255,.78);
      border: 1px solid rgba(var(--primary-rgb), .18);
      color: var(--footer-text, #0f172a);
      text-decoration:none;
    }
    .gdy-footer-team-link:hover{ border-color: rgba(var(--primary-rgb), .35); background: rgba(var(--primary-rgb), .08); }

    @media (max-width: 992px){
      .gdy-footer-main{ grid-template-columns: 1fr; }
      .gdy-footer-brand{ min-width: unset; }
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
            <div><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#mail"></use></svg> <?= h($siteEmail) ?></div>
          <?php endif; ?>
          <?php if ($sitePhone): ?>
            <div><svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#phone"></use></svg> <?= h($sitePhone) ?></div>
          <?php endif; ?>
          <?php if ($siteAddr): ?>
            <div>
              <svg class="gdy-icon ms-1" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <strong><?= h(__("address")) ?>:</strong>
              <?= h($siteAddr) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- روابط سريعة + تواصل اجتماعي + تطبيقات -->
        <div class="gdy-footer-extra">
          <?php if (!empty($footerLinks)): ?>
            <nav class="gdy-footer-links" aria-label="<?= h(__("footer_links")) ?>">
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
            <span class="gdy-footer-social-label"><?= h(__("follow_us")) ?>:</span>
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

              // ✅ تم استبدال النصوص العربية الثابتة بمفاتيح ترجمة
              render_social_icon($socialFacebook,  (string)__("facebook"),   'is-facebook',  'fa-brands fa-facebook-f');
              render_social_icon($socialTwitter,   (string)__("twitter_x"),  'is-twitter',   'fa-brands fa-x-twitter');
              render_social_icon($socialYoutube,   (string)__("youtube"),    'is-youtube',   'fa-brands fa-youtube');
              render_social_icon($socialTelegram,  (string)__("telegram"),   'is-telegram',  'fa-brands fa-telegram');
              render_social_icon($socialInstagram, (string)__("instagram"),  'is-instagram', 'fa-brands fa-instagram');
              render_social_icon($socialWhatsApp,  (string)__("whatsapp"),   'is-whatsapp',  'fa-brands fa-whatsapp');
              ?>
            </div>
          </div>

          <?php if ($hasApps): ?>
            <div class="gdy-footer-apps">
              <?php if ($appAndroid): ?>
                <a href="<?= h($appAndroid) ?>" target="_blank" rel="noopener noreferrer">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <span><?= h(__("android_app")) ?></span>
                </a>
              <?php endif; ?>
              <?php if ($appIos): ?>
                <a href="<?= h($appIos) ?>" target="_blank" rel="noopener noreferrer">
                  <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
                  <span><?= h(__("ios_app")) ?></span>
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="gdy-footer-bottom">
        <?php if (!isset($year) || !$year) { $year = (int)date('Y'); } // ✅ حارس نهائي لمنع أي Warning ?>
        <span>© <?= $year ?> <?= h($siteName) ?>. <?= h(__("all_rights_reserved")) ?>.</span>

        <?php if (!empty($teamUrl)): ?>
          <a href="<?= h($teamUrl) ?>" class="gdy-footer-team-link">
            <span class="gdy-social-icon is-team">
              <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
              <span class="gdy-footer-social-tooltip"><?= h(__("team")) ?></span>
            </span>
            <span><?= h(__("team")) ?></span>
          </a>
        <?php endif; ?>
</div>
    </div>
  </footer>

  <!-- Mobile App Bar (Facebook-like) -->
  <?php $gdyIsUser = (!empty($_SESSION['user']) || !empty($_SESSION['user_id']) || !empty($_SESSION['user_email'])); ?>
  <nav class="gdy-mobile-bar" id="gdyMobileBar" aria-label="التنقل">
    <a class="mb-item" href="<?= h(($_gdy_baseUrl ?: '') . '/') ?>" data-tab="home" aria-label="الرئيسية">
      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg><span>الرئيسية</span>
    </a>
    <button class="mb-item" type="button" data-action="cats" data-tab="cats" aria-label="الأقسام">
      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><span>الأقسام</span>
    </button>
    <a class="mb-item" href="<?= h(($_gdy_baseUrl ?: '') . '/saved') ?>" data-tab="saved" aria-label="محفوظاتي">
      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg><span>محفوظاتي</span>
    </a>
    <?php if ($gdyIsUser): ?>
      <a class="mb-item" href="<?= h(($_gdy_baseUrl ?: '') . '/profile.php') ?>" data-tab="profile" aria-label="حسابي">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg><span>حسابي</span>
      </a>
    <?php else: ?>
      <a class="mb-item" href="<?= h(($_gdy_baseUrl ?: '') . '/login.php') ?>" data-tab="login" aria-label="دخول">
        <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg><span>دخول</span>
      </a>
    <?php endif; ?>
    <button class="mb-item" type="button" data-action="theme" aria-label="الوضع الليلي">
      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#moon"></use></svg><span>ليلي</span>
    </button>
  </nav>

  <style>
    .gdy-mobile-bar{ display:none; }
    @media (max-width: 992px){
      body{ padding-bottom: 72px; }
      .gdy-mobile-bar{
        position: fixed;
        inset-inline: 0;
        bottom: 0;
        z-index: 2000;
        height: 64px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        gap: 6px;
        padding: 8px 10px;
        background: rgba(2,6,23,.96);
        border-top: 1px solid rgba(148,163,184,.25);
        -webkit-backdrop-filter: blur(10px);
        backdrop-filter: blur(10px);
      }
      .gdy-mobile-bar .mb-item{
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        border: 0;
        background: transparent;
        color: var(--footer-text, #0f172a);
        font-size: 12px;
        line-height: 1;
        padding: 6px 2px;
        border-radius: 14px;
      }
      .gdy-mobile-bar .mb-item i{ font-size: 18px; }
      .gdy-mobile-bar .mb-item.active{ background: rgba(var(--primary-rgb),.12); color: var(--primary); }
      .gdy-mobile-bar .mb-item:active{ transform: scale(.98); }
    }
  </style>

  <script>
    (function(){
      function toggleThemeFallback(){
        var KEY = 'gdy_theme';
        var isDark = document.documentElement.classList.contains('theme-dark');
        var next = !isDark;
        document.documentElement.classList.toggle('theme-dark', next);
        try{ localStorage.setItem(KEY, next ? 'dark' : 'light'); }catch(e){}
      }

      function syncThemeIcon(){
        var btn = document.querySelector('#gdyMobileBar [data-action="theme"]');
        if(!btn) return;
        var ico = btn.querySelector('i');
        var dark = document.documentElement.classList.contains('theme-dark');
        if(ico){
          ico.classList.remove('fa-moon','fa-sun','fa-regular','fa-solid');
          ico.classList.add(dark ? 'fa-solid' : 'fa-regular');
          ico.classList.add(dark ? 'fa-sun' : 'fa-moon');
        }
        btn.querySelector('span').textContent = dark ? 'نهاري' : 'ليلي';
      }

      function setActive(){
        var path = (location.pathname || '/');
        var items = document.querySelectorAll('#gdyMobileBar .mb-item');
        items.forEach(function(a){ a.classList.remove('active'); });

        function actByTab(tab){
          var el = document.querySelector('#gdyMobileBar [data-tab="' + tab + '"]');
          if(el) el.classList.add('active');
        }

        if(/\/saved/.test(path)) actByTab('saved');
        else if(/\/category\//.test(path) || /\/categories/.test(path)) actByTab('cats');
        else if(/\/profile\.php/.test(path)) actByTab('profile');
        else if(/\/login\.php/.test(path) || /\/register\.php/.test(path)) actByTab('login');
        else actByTab('home');
      }

      document.addEventListener('DOMContentLoaded', function(){
        setActive();
        syncThemeIcon();

        // Categories quick toggle (opens header categories drawer if present)
        var catsBtn = document.querySelector('#gdyMobileBar [data-action="cats"]');
        if(catsBtn){
          catsBtn.addEventListener('click', function(){
            var t = document.getElementById('gdyCatsToggle');
            if(t){ t.click(); }
            var nav = document.getElementById('gdyCatsNav');
            if(nav){ nav.scrollIntoView({behavior:'smooth', block:'nearest'}); }
          });
        }

        // Theme toggle
        var themeBtn = document.querySelector('#gdyMobileBar [data-action="theme"]');
        if(themeBtn){
          themeBtn.addEventListener('click', function(){
            var hdr = document.getElementById('gdyThemeToggle');
            if(hdr){ hdr.click(); }
            else { toggleThemeFallback(); }
            setTimeout(syncThemeIcon, 40);
          });
        }

        // Keep icon synced when header toggles
        document.addEventListener('gdy:theme', function(){ syncThemeIcon(); });
      });
    })();
  </script>

  <?php if (!empty($extraBodyCode)): ?>
    <?= $extraBodyCode . "\n" ?>
  <?php endif; ?>

  <!-- Glossary tooltip styles & behavior -->
  <style>
    .gdy-glossary-term{
      border-bottom:1px dashed rgba(var(--primary-rgb), .55);
      cursor:help;
      position:relative;
      transition:background-color .15s ease, color .15s ease;
    }
    .gdy-glossary-term:hover{
      background: rgba(var(--primary-rgb), .08);
    }
    .gdy-glossary-tooltip{
      position:absolute;
      z-index:9999;
      background: rgba(255,255,255,.96);
      color: var(--text-strong, #0f172a);
      padding:.6rem .75rem;
      border-radius:.5rem;
      font-size:.8rem;
      max-width:260px;
      line-height:1.6;
      box-shadow:0 16px 40px rgba(15,23,42,.14);
      border:1px solid rgba(var(--primary-rgb), .22);
    }
    .gdy-glossary-tooltip::after{
      content:"";
      position:absolute;
      bottom:-6px;
      right:1.5rem;
      border-width:6px 6px 0 6px;
      border-style:solid;
      border-color: rgba(255,255,255,.96) transparent transparent transparent;
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

	  <!-- Godyar Analytics (Client Beacon) -->
	  <script>
	  (function(){
	    try {
	      var path = (window.location && window.location.pathname) ? window.location.pathname : '/';
	      if (!path) path = '/';
	      // تجاهل لوحة التحكم و endpoint التتبع
	      if (path.indexOf('/admin') === 0 || path.indexOf('/ar/admin') === 0 || path.indexOf('/en/admin') === 0 || path.indexOf('/fr/admin') === 0) return;
	      if (path.indexOf('/track.php') !== -1) return;

	      var trim = ('/' + path.replace(/^\/+/, ''));
	      var page = 'other';
	      var newsId = null;
	
	      if (trim === '/' || /^\/(ar|en|fr)\/?$/.test(trim)) {
	        page = 'home';
	      } else if (trim === '/search' || /^\/(ar|en|fr)\/search$/.test(trim)) {
	        page = 'search';
	      } else if (/^\/(ar|en|fr)\/category\//.test(trim) || trim.indexOf('/category/') === 0) {
	        page = 'category';
	      } else if (/^\/(ar|en|fr)\/tag\//.test(trim) || trim.indexOf('/tag/') === 0) {
	        page = 'tag';
	      } else if (/^\/(ar|en|fr)\/page\//.test(trim) || trim.indexOf('/page/') === 0) {
	        page = 'page';
	      } else {
	        var m = trim.match(/\/news\/id\/(\d+)/);
	        if (m && m[1]) {
	          page = 'article';
	          newsId = parseInt(m[1], 10) || null;
	        } else if (/\/opinion_author\.php$/.test(trim) || trim === '/opinion_author.php') {
	          page = 'opinion_author';
	        }
	      }

	      var payload = {
	        page: page,
	        news_id: newsId,
	        referrer: document.referrer || ''
	      };

	      var url = (typeof BASE_URL !== 'undefined' ? (BASE_URL + 'track.php') : '/track.php');
	      // BASE_URL قد يكون "/" أو "https://.../" لذلك نضمن عدم تكرار /
	      url = url.replace(/\/\/track\.php$/, '/track.php');

	      if (navigator.sendBeacon) {
	        var blob = new Blob([JSON.stringify(payload)], {type: 'application/json'});
	        navigator.sendBeacon(url, blob);
	      } else {
	        fetch(url, {
	          method: 'POST',
	          headers: {'Content-Type': 'application/json'},
	          body: JSON.stringify(payload),
	          keepalive: true,
	          credentials: 'same-origin'
	        }).catch(function(){});
	      }
	    } catch (e) {}
	  })();
	  </script>


<?php
  $baseUrl = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';

  // VAPID public key: prefer ENV, fallback to DB settings (push.vapid_public)
  $vapidPublic = (string)($_ENV['GDY_VAPID_PUBLIC_KEY'] ?? '');
  if ($vapidPublic === '' && function_exists('gdy_pdo_safe')) {
    try {
      $pdo2 = gdy_pdo_safe();
      if ($pdo2 instanceof PDO) {
        $st = $pdo2->prepare("SELECT `value` FROM `settings` WHERE `key` = :k LIMIT 1");
        $st->execute([':k' => 'push.vapid_public']);
        $val = $st->fetchColumn();
        if ($val !== false) { $vapidPublic = (string)$val; }
      }
    } catch (Throwable $e) {}
  }
?>
<script>
  window.GDY_VAPID_PUBLIC_KEY = <?= json_encode($vapidPublic, JSON_UNESCAPED_UNICODE) ?>;
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= h($baseUrl) ?>/sw.js').catch(function(){});
  }
</script>

<?php
  // Mobile Tab Bar (PWA-style navigation)
  $_gdyLang = function_exists('gdy_lang') ? gdy_lang() : (isset($_SESSION['lang']) ? (string)$_SESSION['lang'] : 'ar');
  $rootUrl2 = function_exists('base_url') ? rtrim((string)base_url(), '/') : '';
  $navBaseUrl2 = ($rootUrl2 !== '' ? $rootUrl2 : '') . '/' . trim($_gdyLang, '/');
  if ($rootUrl2 === '') { $navBaseUrl2 = '/' . trim($_gdyLang, '/'); }

  $currentUser2 = $_SESSION['user'] ?? null;
  $isLoggedIn2  = is_array($currentUser2) && !empty($currentUser2['id']);

  $tabNewest = rtrim($navBaseUrl2, '/') . '/';
  $tabSaved   = rtrim($navBaseUrl2, '/') . '/saved';
  $tabMost    = rtrim($navBaseUrl2, '/') . '/trending';
  $tabMy     = rtrim($navBaseUrl2, '/') . '/my';
  $tabArchive = rtrim($navBaseUrl2, '/') . '/archive';
?>
<nav class="gdy-tabbar" aria-label="<?= h(__('navigation')) ?>">
  <a class="gdy-tab" href="<?= h($tabNewest) ?>" data-tab="home" aria-label="<?= h(__('home')) ?>">
    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#home"></use></svg>
    <span><?= h(__('home')) ?></span>
  </a>

  <a class="gdy-tab" href="<?= h($tabArchive) ?>" data-tab="archive" aria-label="<?= h(__('latest_news')) ?>">
    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
    <span><?= h(__('latest_news')) ?></span>
  </a>

  <a class="gdy-tab" href="<?= h($tabSaved) ?>" data-tab="saved" aria-label="<?= h(__('saved')) ?>">
    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
    <span><?= h(__('saved')) ?></span>
  </a>

  <a class="gdy-tab" href="<?= h($tabMost) ?>" data-tab="most" aria-label="<?= h(__('most_read')) ?>">
    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
    <span><?= h(__('most_read')) ?></span>
  </a>

  <a class="gdy-tab" href="<?= h($tabMy) ?>" data-tab="my" aria-label="<?= h(__('my_news')) ?>">
    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#user"></use></svg>
    <span><?= h(__('my_news')) ?></span>
  </a>

  <button class="gdy-tab gdy-tab--btn" type="button" data-tab="theme" id="gdyTabTheme" aria-label="<?= h(__('dark_mode')) ?>">
    <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#moon"></use></svg>
    <span><?= h(__('dark_mode')) ?></span>
  </button>
</nav>


<!-- Mobile Search Overlay -->
<div class="gdy-search" id="gdyMobileSearch" hidden>
  <div class="gdy-search__top">
    <button class="gdy-search__close" type="button" id="gdyMobileSearchClose" aria-label="<?= h(__('close')) ?>">
      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg>
    </button>
    <div class="gdy-search__field">
      <svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#search"></use></svg>
      <input id="gdyMobileSearchInput" type="search" autocomplete="off" placeholder="<?= h(__('search_placeholder')) ?>">
    </div>
  </div>
  <div class="gdy-search__body">
    <div class="gdy-search__title"><?= h(__('smart_search')) ?></div>
    <div class="gdy-search__list" id="gdyMobileSearchList"></div>
  </div>
</div>

<script>
  window.GDY_NAV_BASE = "<?= h($navBaseUrl2) ?>";
</script>
  
<button id="gdyBackTop" class="gdy-backtop" type="button" aria-label="<?= h(__('العودة للأعلى')) ?>"><svg class="gdy-icon" aria-hidden="true" focusable="false"><use href="/assets/icons/gdy-icons.svg#dot"></use></svg></button>

<script src="<?= h($baseUrl) ?>/assets/js/modules/search.js" defer></script>
<script src="<?= h($baseUrl) ?>/assets/js/modules/notifications.js" defer></script>
<script src="<?= h($baseUrl) ?>/assets/js/modules/push_prompt.js?v=20260107_1" defer></script>
<script src="<?= h($baseUrl) ?>/assets/js/modules/mobile_app.js" defer></script>
<script src="<?= h($baseUrl) ?>/assets/js/pwa.js" defer></script>
<script src="<?= h($baseUrl) ?>/assets/js/modules/mobile_tabbar.js?v=20260107_5" defer></script>
<script src="<?= h($baseUrl) ?>/assets/js/modules/mobile_search_overlay.js" defer></script>

<script src="<?= h($baseUrl) ?>/assets/js/ui-enhancements.js" defer></script>
<script src="<?= h($baseUrl) ?>/assets/js/lazy-images.js" defer></script>
<script src="<?= h($baseUrl) ?>/assets/js/modules/newsletter_subscribe.js" defer></script>


<!-- Mobile push enable prompt (shows only if push enabled + no subscription) -->
<div id="gdy-push-toast" class="gdy-push-toast" role="dialog" aria-live="polite" aria-label="Push Prompt">
  <div class="gdy-push-toast__title">تفعيل إشعارات الأخبار</div>
  <div class="gdy-push-toast__desc">وصل تنبيه لأهم الأخبار على جهازك. يمكنك إيقافها في أي وقت.</div>
  <div class="gdy-push-toast__actions">
    <button type="button" class="gdy-btn gdy-btn-primary" data-gdy-push-enable>تفعيل</button>
    <button type="button" class="gdy-btn gdy-btn-ghost" data-gdy-push-later>لاحقاً</button>
  </div>
</div>

  <script src="/assets/js/public-interactions.js" defer></script>
</body>
</html>