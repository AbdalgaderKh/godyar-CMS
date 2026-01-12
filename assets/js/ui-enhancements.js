/* UI Enhancements (v1) - Theme + BackToTop + Reading progress + Reveal */
(function(){
  'use strict';

  const docEl = document.documentElement;
  const lang = (docEl.getAttribute('lang') || 'ar').toLowerCase();

  const t = {
    ar: {
      dark: 'الوضع الليلي',
      light: 'الوضع النهاري',
      backTop: 'العودة للأعلى',
      minRead: 'دقيقة قراءة',
      minsRead: 'دقائق قراءة',
    },
    en: {
      dark: 'Dark mode',
      light: 'Light mode',
      backTop: 'Back to top',
      minRead: 'min read',
      minsRead: 'min read',
    },
    fr: {
      dark: 'Mode sombre',
      light: 'Mode clair',
      backTop: 'Haut de page',
      minRead: 'min de lecture',
      minsRead: 'min de lecture',
    }
  }[(lang.startsWith('en') ? 'en' : (lang.startsWith('fr') ? 'fr' : 'ar'))];

  function setTheme(theme){
    docEl.setAttribute('data-theme', theme);
    try{ localStorage.setItem('gdy_theme', theme); }catch(e){}
    const btn = document.getElementById('gdyThemeToggle');
    if(btn){
      const isDark = theme === 'dark';
      btn.setAttribute('aria-pressed', String(isDark));
      btn.setAttribute('title', isDark ? t.light : t.dark);
      const useEl = btn.querySelector('use');
      if(useEl){
        const id = isDark ? 'sun' : 'moon';
        const href = '/assets/icons/gdy-icons.svg#' + id;
        useEl.setAttribute('href', href);
        useEl.setAttribute('xlink:href', href);
      }
    }
  }

  function initTheme(){
    let theme = 'light';
    try{
      const saved = localStorage.getItem('gdy_theme');
      if(saved === 'dark' || saved === 'light') theme = saved;
      /* prefers-color-scheme disabled: keep default light unless user chose */
    }catch(e){}
    setTheme(theme);

    const btn = document.getElementById('gdyThemeToggle');
    if(btn){
      btn.addEventListener('click', () => {
        const next = (docEl.getAttribute('data-theme') === 'dark') ? 'light' : 'dark';
        setTheme(next);
      });
    }
  }

  function ensureBackTop(){
    let btn = document.getElementById('gdyBackTop');
    if(!btn){
      btn = document.createElement('button');
      btn.id = 'gdyBackTop';
      btn.className = 'gdy-backtop';
      btn.type = 'button';
      btn.setAttribute('aria-label', t.backTop);
      btn.innerHTML = '<i class="fa-solid fa-arrow-up"></i>';
      document.body.appendChild(btn);
    }

    const onScroll = () => {
      const show = window.scrollY > 600;
      btn.classList.toggle('show', show);
    };
    window.addEventListener('scroll', onScroll, {passive:true});
    onScroll();

    btn.addEventListener('click', () => window.scrollTo({top:0, behavior:'smooth'}));
  }

  function readingProgress(){
    const article = document.querySelector('.gdy-article-body');
    if(!article) return;

    // progress bar (fixed overlay, no layout shift)
    const bar = document.createElement('div');
    bar.className = 'gdy-reading-progress';
    bar.innerHTML = '<span></span>';
    document.body.appendChild(bar);
    const fill = bar.firstElementChild;

    const calc = () => {
      const rect = article.getBoundingClientRect();
      const top = rect.top + window.scrollY;
      const height = article.scrollHeight;
      const y = window.scrollY;
      const progress = Math.min(1, Math.max(0, (y - top) / Math.max(1, (height - window.innerHeight))));
      fill.style.width = (progress * 100).toFixed(2) + '%';
    };
    window.addEventListener('scroll', calc, {passive:true});
    window.addEventListener('resize', calc);
    calc();

    // reading time pill
    const meta = document.querySelector('.gdy-meta-row');
    if(meta && !meta.querySelector('.gdy-pill--reading')){
      const text = article.innerText || '';
      const words = text.trim().split(/\s+/).filter(Boolean).length;
      const mins = Math.max(1, Math.round(words / 220)); // ~220 wpm
      const pill = document.createElement('span');
      pill.className = 'gdy-pill gdy-pill--reading';
      pill.innerHTML = '<i class="fa-regular fa-clock"></i>' + mins + ' ' + (lang.startsWith('ar') ? (mins === 1 ? t.minRead : t.minsRead) : t.minsRead);
      meta.appendChild(pill);
    }
  }

  function revealOnScroll(){
    const targets = document.querySelectorAll('.gdy-card, .gdy-ad-slot, .gdy-category-layout, .gdy-cat-page, .gdy-news-card, .gdy-report-side, .gdy-report-box');
    if(!targets.length) return;

    targets.forEach(el => el.classList.add('gdy-reveal'));

    if(!('IntersectionObserver' in window)){
      targets.forEach(el => el.classList.add('is-visible'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if(e.isIntersecting){
          e.target.classList.add('is-visible');
          io.unobserve(e.target);
        }
      });
    }, {rootMargin: '80px 0px', threshold: 0.05});

    targets.forEach(el => io.observe(el));
  }

  
function initSideTabs(){
  const containers = document.querySelectorAll('[data-gdy-tabs]');
  if(!containers.length) return;

  containers.forEach(root => {
    const btns = root.querySelectorAll('.gdy-tab-btn[data-tab]');
    const panels = root.querySelectorAll('.gdy-tab-panel[data-panel]');
    if(!btns.length || !panels.length) return;

    const activate = (tab) => {
      btns.forEach(b => {
        const on = b.getAttribute('data-tab') === tab;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach(p => p.classList.toggle('is-active', p.getAttribute('data-panel') === tab));
    };

    btns.forEach(b => {
      b.addEventListener('click', () => activate(b.getAttribute('data-tab') || 'mostread'));
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    ensureBackTop();
    readingProgress();
    revealOnScroll();
    initSideTabs();
  });
})();