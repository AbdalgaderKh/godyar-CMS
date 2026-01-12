/* Godyar Mobile App helpers:
 * - Share (Web Share API)
 * - Bookmarks (guest localStorage + logged-in sync via /api/bookmarks/*)
 * - Reader mode + font size + progress + continue reading (already in template; we wire it)
 */
(function () {
  'use strict';

  const BASE = (window.GDY_BASE || '').replace(/\/$/, '');
  const api = (path) => (BASE ? (BASE + path) : path);

  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  function toast(msg){
    try{
      let t = document.getElementById('gdyToast');
      if(!t){
        t = document.createElement('div');
        t.id = 'gdyToast';
        t.style.position = 'fixed';
        t.style.left = '50%';
        t.style.bottom = '84px';
        t.style.transform = 'translateX(-50%)';
        t.style.padding = '10px 14px';
        t.style.borderRadius = '999px';
        t.style.background = 'rgba(15,23,42,.92)';
        t.style.color = '#fff';
        t.style.fontSize = '14px';
        t.style.zIndex = '99999';
        t.style.maxWidth = '92vw';
        t.style.textAlign = 'center';
        t.style.boxShadow = '0 10px 30px rgba(0,0,0,.25)';
        t.style.opacity = '0';
        t.style.transition = 'opacity .2s ease';
        document.body.appendChild(t);
      }
      t.textContent = msg;
      t.style.opacity = '1';
      clearTimeout(t.__hide);
      t.__hide = setTimeout(()=>{ t.style.opacity = '0'; }, 1600);
    }catch(e){}
  }

  async function postForm(url, data){
    const fd = new FormData();
    Object.keys(data||{}).forEach(k => fd.append(k, data[k]));
    const res = await fetch(url, { method:'POST', body: fd, credentials:'same-origin' });
    return res.json();
  }

  async function getJson(url){
    const res = await fetch(url, { credentials:'same-origin' });
    return res.json();
  }

  // -----------------------------
  // Bookmarks (local + sync)
  // -----------------------------
  const LS_KEY = 'gdy_bookmarks_v1';

  function readLocal(){
    try{
      const s = localStorage.getItem(LS_KEY);
      const v = s ? JSON.parse(s) : [];
      return Array.isArray(v) ? v : [];
    }catch(e){ return []; }
  }
  function writeLocal(arr){
    try{ localStorage.setItem(LS_KEY, JSON.stringify(arr||[])); }catch(e){}
  }
  function hasLocal(newsId){
    const arr = readLocal();
    return arr.some(x => String(x.news_id) === String(newsId));
  }
  function upsertLocal(item){
    const arr = readLocal();
    const id = String(item.news_id);
    const next = arr.filter(x => String(x.news_id) !== id);
    next.unshift(item);
    writeLocal(next.slice(0, 300));
  }
  function removeLocal(newsId){
    const id = String(newsId);
    const arr = readLocal().filter(x => String(x.news_id) !== id);
    writeLocal(arr);
  }

  function authInfo(){
    const b = document.body;
    const auth = b && b.dataset ? (b.dataset.auth === '1') : false;
    const uid = b && b.dataset ? parseInt(b.dataset.userId || '0', 10) : 0;
    return {auth, uid};
  }

  function setBookmarkBtnState(btn, saved){
    if(!btn) return;
    btn.dataset.saved = saved ? '1' : '0';
    const icon = btn.querySelector('i');
    const text = btn.querySelector('.gdy-bm-text');
    if(icon){
      icon.classList.remove('fa-regular','fa-solid');
      icon.classList.add(saved ? 'fa-solid' : 'fa-regular');
      icon.classList.add('fa-bookmark');
    }
    if(text) text.textContent = saved ? 'محفوظ' : 'حفظ';
    btn.classList.toggle('active', !!saved);
  }

  async function syncLocalToServer(force){
    const {auth, uid} = authInfo();
    if(!auth || uid <= 0) return;

    const local = readLocal();
    if(!local.length) return;

    const lastKey = 'gdy_bookmarks_last_sync_user';
    const last = parseInt(localStorage.getItem(lastKey) || '0', 10);
    if(!force && last === uid) return;

    const ids = local.map(x => parseInt(x.news_id,10)).filter(Boolean);
    if(!ids.length) return;

    try{
      const r = await fetch(api('/api/bookmarks/import'), {
        method:'POST',
        credentials:'same-origin',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({news_ids: ids})
      });
      const j = await r.json();
      if(j && j.ok){
        localStorage.setItem(lastKey, String(uid));
        toast('تمت مزامنة المحفوظات');
      }
    }catch(e){}
  }

  function renderLocalBookmarks(){
    const wrap = qs('#gdyLocalBookmarksWrap');
    const grid = qs('#gdyLocalBookmarks');
    const empty = qs('#gdyLocalBookmarksEmpty');
    if(!wrap || !grid) return;

    const items = readLocal();
    grid.innerHTML = '';
    if(!items.length){
      if(empty) empty.style.display = '';
      return;
    }
    if(empty) empty.style.display = 'none';

    items.forEach(it => {
      const col = document.createElement('div');
      col.className = 'col-12 col-md-6 col-lg-4';
      const url = it.url || ('/news/id/' + it.news_id);
      const img = it.image || '';
      const title = it.title || '';
      const date = it.published_at || '';
      col.innerHTML = `
        <div class="card h-100">
          ${img ? `<a href="${url}"><img src="${img}" class="card-img-top" alt="${escapeHtml(title)}" onerror="this.style.display='none'"></a>` : ``}
          <div class="card-body">
            <h3 class="h6 card-title"><a href="${url}" class="text-decoration-none">${escapeHtml(title)}</a></h3>
            ${date ? `<div class="text-muted small mb-1">${escapeHtml(date)}</div>` : ``}
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2 gdy-remove-local" data-id="${it.news_id}">إزالة</button>
          </div>
        </div>
      `;
      grid.appendChild(col);
    });

    qsa('.gdy-remove-local', grid).forEach(btn => {
      btn.addEventListener('click', function(){
        const id = parseInt(this.dataset.id || '0', 10);
        if(!id) return;
        removeLocal(id);
        toast('تمت الإزالة');
        renderLocalBookmarks();
      });
    });
  }

  function escapeHtml(s){
    return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // -----------------------------
  // Share button
  // -----------------------------
  function initShare(){
    const btn = qs('#gdyShare');
    if(!btn) return;

    btn.addEventListener('click', async function(){
      const url = (location && location.href) ? location.href : '';
      const titleEl = qs('h1') || qs('title');
      const title = titleEl ? (titleEl.textContent || document.title || '') : (document.title || '');
      if(navigator.share){
        try{
          await navigator.share({ title: title, text: title, url: url });
          return;
        }catch(e){}
      }
      // fallback: copy
      try{
        await navigator.clipboard.writeText(url);
        toast('تم نسخ الرابط');
      }catch(e){
        toast('انسخ الرابط من شريط العنوان');
      }
    });
  }

  // -----------------------------
  // Bookmark button in article
  // -----------------------------
  function initBookmarkBtn(){
    const btn = qs('#gdyBookmark');
    if(!btn) return;

    const newsId = parseInt(btn.dataset.newsId || '0', 10);
    if(!newsId) return;

    // initial state
    const {auth} = authInfo();
    if(auth){
      getJson(api('/api/bookmarks/status?news_id=' + encodeURIComponent(newsId)))
        .then(j => { if(j && j.ok) setBookmarkBtnState(btn, !!j.saved); })
        .catch(()=>{ setBookmarkBtnState(btn, hasLocal(newsId)); });
    } else {
      setBookmarkBtnState(btn, hasLocal(newsId));
    }

    btn.addEventListener('click', async function(){
      const {auth} = authInfo();
      const current = (btn.dataset.saved === '1');

      // Build local item (always keep local for offline / cross)
      const item = {
        news_id: newsId,
        title: btn.dataset.title || document.title || '',
        image: btn.dataset.image || '',
        url: btn.dataset.url || ('/news/id/' + newsId),
        published_at: btn.dataset.publishedAt || ''
      };

      if(auth){
        try{
          const res = await postForm(api('/api/bookmarks/toggle'), { news_id: String(newsId), action: 'toggle' });
          if(res && res.ok){
            const saved = (res.status === 'added');
            setBookmarkBtnState(btn, saved);
            if(saved) upsertLocal(item); else removeLocal(newsId);
            toast(saved ? 'تم الحفظ' : 'تمت الإزالة');
          } else {
            // fallback to local
            if(current){ removeLocal(newsId); setBookmarkBtnState(btn,false); toast('تمت الإزالة'); }
            else { upsertLocal(item); setBookmarkBtnState(btn,true); toast('تم الحفظ'); }
          }
        }catch(e){
          if(current){ removeLocal(newsId); setBookmarkBtnState(btn,false); toast('تمت الإزالة'); }
          else { upsertLocal(item); setBookmarkBtnState(btn,true); toast('تم الحفظ'); }
        }
      } else {
        if(current){
          removeLocal(newsId);
          setBookmarkBtnState(btn, false);
          toast('تمت الإزالة');
        } else {
          upsertLocal(item);
          setBookmarkBtnState(btn, true);
          toast('تم الحفظ');
        }
      }
    });
  }

  // -----------------------------
  // Reader mode + Font size + Progress + Continue
  // (wire existing buttons/elements if present)
  // -----------------------------
  function initReaderTools(){
    const btnRead = qs('#gdyReadingMode');
    const btnInc = qs('#gdyFontInc');
    const btnDec = qs('#gdyFontDec');
    const progress = qs('#gdyProgress');

    const LS_THEME = 'gdy_reading_mode';
    const LS_FONT  = 'gdy_font_scale';
    const LS_POS_PREFIX = 'gdy_read_pos_';

    function setReadingMode(on){
      document.body.classList.toggle('gdy-reading-mode', !!on);
      try{ localStorage.setItem(LS_THEME, on ? '1' : '0'); }catch(e){}
    }
    function getReadingMode(){
      try{ return localStorage.getItem(LS_THEME) === '1'; }catch(e){ return false; }
    }
    function setFontScale(v){
      const vv = Math.max(85, Math.min(130, v|0));
      var base = 1.05; // rem
      var rem = (base * (vv/100));
      document.documentElement.style.setProperty('--gdy-font-size', rem.toFixed(3) + 'rem');
      try{ localStorage.setItem(LS_FONT, String(vv)); }catch(e){}
      const badge = qs('#gdyFontBadge');
      if(badge) badge.textContent = vv + '%';
    }
    function getFontScale(){
      try{ return parseInt(localStorage.getItem(LS_FONT) || '100', 10) || 100; }catch(e){ return 100; }
    }

    // apply persisted settings
    if(btnRead) setReadingMode(getReadingMode());
    if(btnInc || btnDec) setFontScale(getFontScale());

    if(btnRead){
      btnRead.addEventListener('click', ()=> setReadingMode(!document.body.classList.contains('gdy-reading-mode')));
    }
    if(btnInc){
      btnInc.addEventListener('click', ()=> setFontScale(getFontScale() + 5));
    }
    if(btnDec){
      btnDec.addEventListener('click', ()=> setFontScale(getFontScale() - 5));
    }

    // Progress + save position
    if(progress){
      const article = qs('article') || qs('.gdy-article-body') || document.body;
      function updateProgress(){
        const doc = document.documentElement;
        const scrollTop = (window.pageYOffset || doc.scrollTop || 0);
        const height = Math.max(1, doc.scrollHeight - window.innerHeight);
        const pct = Math.max(0, Math.min(100, (scrollTop/height)*100));
        progress.style.width = pct.toFixed(2) + '%';
        // save position for article pages
        const nid = findNewsIdFromPage();
        if(nid){
          try{ localStorage.setItem(LS_POS_PREFIX + nid, String(scrollTop)); }catch(e){}
        }
      }
      window.addEventListener('scroll', ()=>{ requestAnimationFrame(updateProgress); }, {passive:true});
      window.addEventListener('resize', ()=>{ requestAnimationFrame(updateProgress); }, {passive:true});
      updateProgress();

      // restore pos
      const nid = findNewsIdFromPage();
      if(nid){
        try{
          const saved = parseInt(localStorage.getItem(LS_POS_PREFIX + nid) || '0', 10);
          if(saved > 200){
            // show small continue chip
            let chip = document.getElementById('gdyContinueChip');
            if(!chip){
              chip = document.createElement('button');
              chip.id = 'gdyContinueChip';
              chip.type = 'button';
              chip.textContent = 'تابع القراءة';
              chip.style.position = 'fixed';
              chip.style.right = '12px';
              chip.style.bottom = '140px';
              chip.style.zIndex = '99998';
              chip.style.border = '1px solid rgba(148,163,184,.35)';
              chip.style.background = 'rgba(15,23,42,.90)';
              chip.style.color = '#fff';
              chip.style.borderRadius = '999px';
              chip.style.padding = '10px 12px';
              chip.style.fontSize = '13px';
              chip.style.boxShadow = '0 10px 30px rgba(0,0,0,.25)';
              document.body.appendChild(chip);
            }
            chip.onclick = ()=>{ window.scrollTo({top: saved-40, behavior:'smooth'}); chip.remove(); };
          }
        }catch(e){}
      }
    }
  }

  function findNewsIdFromPage(){
    const btn = qs('#gdyBookmark');
    if(btn && btn.dataset.newsId) return parseInt(btn.dataset.newsId, 10) || 0;
    const any = qs('[data-news-id]');
    if(any && any.getAttribute('data-news-id')) return parseInt(any.getAttribute('data-news-id'), 10) || 0;
    return 0;
  }

  function initSavedPage(){
    // render local
    renderLocalBookmarks();

    const syncBtn = qs('#gdySyncBookmarks');
    if(syncBtn){
      syncBtn.addEventListener('click', async function(){
        await syncLocalToServer(true);
        // reload to reflect server list
        try{ location.reload(); }catch(e){}
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    initShare();
    initBookmarkBtn();
    initReaderTools();
    initSavedPage();
    // auto sync once per user
    syncLocalToServer(false);
  });

})();
