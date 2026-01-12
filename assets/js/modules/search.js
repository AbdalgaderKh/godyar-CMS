/* Smart search suggestions for the header search box */
(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  const form = qs('.header-search');
  const input = form ? qs('input[name="q"]', form) : null;
  if(!input) return;

  const box = document.createElement('div');
  box.className = 'gdy-suggest-box';
  box.style.display = 'none';
  form.style.position = 'relative';
  form.appendChild(box);

  let timer = null;
  let lastQ = '';

  function hide(){ box.style.display='none'; box.innerHTML=''; }
  function show(html){ box.innerHTML=html; box.style.display='block'; }

  function render(res){
    if(!res || !res.ok){ hide(); return; }
    const items = res.suggestions || [];
    const corrected = res.corrected;
    if(!items.length && !corrected){ hide(); return; }

    const parts = [];
    if(corrected){
      parts.push(`<div class="gdy-suggest-correct">هل تقصد: <a href="/search?q=${encodeURIComponent(corrected)}">${escapeHtml(corrected)}</a>؟</div>`);
    }
    parts.push('<div class="gdy-suggest-list">');
    items.slice(0,10).forEach(it => {
      parts.push(`<a class="gdy-suggest-item" href="${it.url}"><span class="t">${escapeHtml(it.title||'')}</span><span class="k">${escapeHtml(it.type||'')}</span></a>`);
    });
    parts.push('</div>');
    show(parts.join(''));
  }

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  async function fetchSuggest(q){
    try{
      const r = await fetch(`/api/search/suggest?q=${encodeURIComponent(q)}`, {credentials:'same-origin'});
      const j = await r.json();
      render(j);
    }catch(e){
      hide();
    }
  }

  input.addEventListener('input', () => {
    const q = (input.value||'').trim();
    lastQ = q;
    if(timer) clearTimeout(timer);
    if(q.length < 2){ hide(); return; }
    timer = setTimeout(() => {
      if(lastQ === q) fetchSuggest(q);
    }, 180);
  });

  document.addEventListener('click', (e) => {
    if(!form.contains(e.target)) hide();
  });
})();
