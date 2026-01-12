/* Mobile Search Overlay
 * Requires:
 * - Button: #gdyMobileSearchBtn (in header)
 * - Overlay: #gdyMobileSearch with input #gdyMobileSearchInput and list #gdyMobileSearchList
 * - window.GDY_NAV_BASE (e.g., /ar)
 *
 * Uses:
 * - {base}/api/latest  (default list)
 * - {base}/api/search/suggest?q=...
 */
(function(){
  'use strict';

  var btn = null, overlay = null, closeBtn = null, input = null, list = null;
  var base = '';

  function qs(id){ return document.getElementById(id); }

  function open(){
    if(!overlay) return;
    overlay.hidden = false;
    document.documentElement.classList.add('gdy-search-open');
    document.body.classList.add('gdy-search-open');
    setTimeout(function(){ try{ input && input.focus(); }catch(e){} }, 60);
    if(!list || list.childElementCount === 0){
      loadLatest();
    }
  }

  function close(){
    if(!overlay) return;
    overlay.hidden = true;
    document.documentElement.classList.remove('gdy-search-open');
    document.body.classList.remove('gdy-search-open');
  }

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
    });
  }

  function renderItems(items){
    if(!list) return;
    list.innerHTML = '';
    (items||[]).forEach(function(it){
      var title = escapeHtml(it.title || '');
      var url = (it.url || '#');
      // prefix language base if URL is site-relative (starts with /)
      if(url.charAt(0) === '/' && base){
        // avoid double prefix for already-prefixed urls
        if(!(url.indexOf('/ar/')===0 || url.indexOf('/en/')===0 || url.indexOf('/fr/')===0)){
          url = base.replace(/\/+$/,'') + url;
        }
      }
      var a = document.createElement('a');
      a.className = 'gdy-search__item';
      a.href = url;
      a.innerHTML = '<span class="gdy-search__itemTitle">'+title+'</span>' +
                    '<i class="fa-solid fa-arrow-up-right-from-square"></i>';
      list.appendChild(a);
    });

    if(list.childElementCount === 0){
      var empty = document.createElement('div');
      empty.className = 'gdy-search__empty';
      empty.textContent = 'لا توجد نتائج';
      list.appendChild(empty);
    }
  }

  function fetchJson(url){
    return fetch(url, { credentials: 'same-origin' }).then(function(r){ return r.json(); });
  }

  function loadLatest(){
    if(!base) return;
    fetchJson(base.replace(/\/+$/,'') + '/api/latest')
      .then(function(j){ if(j && j.ok) renderItems(j.items || []); })
      .catch(function(){});
  }

  var tmr = null;
  function onInput(){
    if(!base) return;
    var q = (input && input.value || '').trim();
    if(tmr) clearTimeout(tmr);
    tmr = setTimeout(function(){
      if(q === ''){
        loadLatest();
        return;
      }
      fetchJson(base.replace(/\/+$/,'') + '/api/search/suggest?q=' + encodeURIComponent(q))
        .then(function(j){
          if(j && j.ok){
            renderItems(j.suggestions || []);
          }
        })
        .catch(function(){});
    }, 220);
  }

  document.addEventListener('DOMContentLoaded', function(){
    btn = qs('gdyMobileSearchBtn');
    overlay = qs('gdyMobileSearch');
    closeBtn = qs('gdyMobileSearchClose');
    input = qs('gdyMobileSearchInput');
    list = qs('gdyMobileSearchList');
    base = (window.GDY_NAV_BASE || '').toString();

    if(btn) btn.addEventListener('click', open);
    if(closeBtn) closeBtn.addEventListener('click', close);
    if(overlay){
      overlay.addEventListener('click', function(e){
        if(e.target === overlay) close();
      });
      document.addEventListener('keydown', function(e){
        if(e.key === 'Escape') close();
      });
    }
    if(input) input.addEventListener('input', onInput);

    // If user taps a tab bar link, close overlay
    document.addEventListener('click', function(e){
      var t = e.target;
      if(!t) return;
      var a = t.closest ? t.closest('.gdy-tabbar a') : null;
      if(a) close();
    });
  });
})();
