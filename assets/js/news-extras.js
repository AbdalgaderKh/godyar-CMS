/* Godyar News Extras: reactions, polls, ask author, TTS (client), download via API */
(function () {
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.from((root||document).querySelectorAll(sel)); }

  const BASE = (window.GDY_BASE || '');
  function api(path){
    if(!BASE) return path;
    return BASE.replace(/\/$/,'') + path;
  }

  function safeJson(res){
    return res.text().then(txt => {
      const t = (txt || '').trim();
      if(!t) return {};
      try{ return JSON.parse(t); }
      catch(e){
        const err = new Error('Non-JSON response');
        err.status = res.status;
        err.responseText = txt;
        throw err;
      }
    });
  }

  function postForm(url, data){
    const params = new URLSearchParams();
    Object.keys(data||{}).forEach(k => params.append(k, (data[k] ?? '')));
    return fetch(url, {
      method:'POST',
      body: params.toString(),
      credentials:'same-origin',
      headers: {
        'Accept':'application/json',
        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'
      }
    }).then(safeJson);
  }
  function postJson(url, data){
    return fetch(url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify(data||{}), credentials:'same-origin' }).then(safeJson);
  }
  function getJson(url){
    return fetch(url, { credentials:'same-origin', headers:{'Accept':'application/json'} }).then(safeJson);
  }

  async function initReactions(){
    const wrap = qs('#gdy-reactions');
    if(!wrap) return;
    const newsId = wrap.getAttribute('data-news-id');
    if(!newsId) return;

    // Emoji-only reactions (no text labels inside the buttons).
    // Keep aria-label/title for accessibility and hover tooltips.
    const reactions = {
      like:     { label: 'إعجاب',  emoji: '👍' },
      useful:   { label: 'مفيد',   emoji: '✅' },
      disagree: { label: 'مختلف',  emoji: '🤔' },
      angry:    { label: 'غاضب',   emoji: '😡' },
      funny:    { label: 'مضحك',   emoji: '😂' }
    };

    function render(state){
      const counts = (state && state.counts) || {};
      const mine = new Set((state && state.mine) || []);
      wrap.innerHTML = '';
      const row = document.createElement('div');
      row.className = 'gdy-react-row';
      Object.keys(reactions).forEach(key => {
        const btn = document.createElement('button');
        btn.type='button';
        btn.className = 'gdy-react-btn' + (mine.has(key) ? ' active' : '');
        btn.setAttribute('data-reaction', key);
        btn.title = reactions[key].label;
        btn.setAttribute('aria-label', reactions[key].label);
        btn.innerHTML = `<span class="emo" aria-hidden="true">${reactions[key].emoji}</span><span class="cnt">${counts[key]||0}</span>`;
        btn.addEventListener('click', async () => {
          btn.disabled = true;
          try{
            const res = await postForm(api('/api/news/react'), {news_id: newsId, reaction: key});
            if(res && res.ok){
              render({counts: res.counts, mine: res.mine});
            }
          }catch(e){}
          btn.disabled = false;
        });
        row.appendChild(btn);
      });
      wrap.appendChild(row);
    }

    try{
      const res = await getJson(api(`/api/news/reactions?news_id=${encodeURIComponent(newsId)}`));
      if(res && res.ok) render(res);
    }catch(e){}
  }

  async function initPoll(){
    const el = qs('#gdy-poll');
    if(!el) return;
    const newsId = el.getAttribute('data-news-id');
    if(!newsId) return;

    function renderPoll(payload){
      const wrap = el.closest('.gdy-inline-poll');
      if(!payload || !payload.has_poll){
        el.innerHTML='';
        if(wrap) wrap.style.display = 'none';
        return;
      }
      if(wrap) wrap.style.display = '';
      const poll = payload.poll || {};
      const opts = (poll.options && poll.options.items) || [];
      const total = (poll.options && poll.options.total) || 0;
      const my = poll.my_vote;

      const html = [];
      html.push(`<div class="gdy-poll-box">`);
      html.push(`<div class="gdy-poll-q">${escapeHtml(poll.question||'')}</div>`);
      html.push(`<div class="gdy-poll-opts">`);
      opts.forEach(o => {
        const checked = (my && parseInt(my,10)===parseInt(o.id,10));
        const disabled = my ? 'disabled' : '';
        html.push(`
          <button type="button" class="gdy-poll-opt ${checked?'active':''}" data-option="${o.id}" ${disabled}>
            <div class="row1">
              <span class="lbl">${escapeHtml(o.label||'')}</span>
              <span class="pct">${o.pct||0}%</span>
            </div>
            <div class="bar"><div class="fill" style="width:${o.pct||0}%"></div></div>
            <div class="meta">${o.votes||0} صوت</div>
          </button>
        `);
      });
      html.push(`</div>`);
      html.push(`<div class="gdy-poll-foot text-muted small">إجمالي الأصوات: ${total}</div>`);
      html.push(`</div>`);
      el.innerHTML = html.join('');

      qsa('.gdy-poll-opt', el).forEach(btn => {
        btn.addEventListener('click', async () => {
          const oid = btn.getAttribute('data-option');
          if(!oid) return;
          qsa('.gdy-poll-opt', el).forEach(b => b.disabled=true);
          try{
            const res = await postForm(api('/api/news/poll/vote'), {news_id: newsId, option_id: oid});
            if(res && res.ok){
              const ref = await getJson(api(`/api/news/poll?news_id=${encodeURIComponent(newsId)}`));
              if(ref && ref.ok) renderPoll(ref);
            }
          }catch(e){}
        });
      });
    }

    try{
      const res = await getJson(api(`/api/news/poll?news_id=${encodeURIComponent(newsId)}`));
      if(res && res.ok) renderPoll(res);
    }catch(e){}
  }

  async function initQuestions(){
    const box = qs('#gdy-qa');
    if(!box) return;
    const newsId = box.getAttribute('data-news-id');
    if(!newsId) return;

    const listEl = qs('#gdy-qa-list');
    const form = qs('#gdy-ask-form');
    const msg = qs('#gdy-ask-msg');

    async function load(){
      if(!listEl) return;
      listEl.innerHTML = '<div class="text-muted small">جارٍ التحميل…</div>';
      try{
        const res = await getJson(api(`/api/news/questions?news_id=${encodeURIComponent(newsId)}`));
        if(!res || res.ok === false){
          listEl.innerHTML = '<div class="text-muted small">تعذر تحميل الأسئلة.</div>';
          return;
        }
        const items = (res.items) || [];
        if(!items.length){
          listEl.innerHTML = '<div class="text-muted small">لا توجد أسئلة منشورة بعد.</div>';
          return;
        }
        listEl.innerHTML = items.map((it) => {
          const q = escapeHtml(it.question||'');
          const a = escapeHtml(it.answer||'');
          return `
            <details class="gdy-qa-item">
              <summary>${q}</summary>
              <div class="ans">${a||'<span class="text-muted">لم يتم الرد بعد.</span>'}</div>
            </details>`;
        }).join('');
      }catch(e){
        listEl.innerHTML = '<div class="text-muted small">تعذر تحميل الأسئلة.</div>';
      }
    }

    if(form){
      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if(msg) msg.textContent='';
        const name = (qs('[name="name"]', form)?.value||'').trim();
        const email = (qs('[name="email"]', form)?.value||'').trim();
        const question = (qs('[name="question"]', form)?.value||'').trim();
        if(!question){
          if(msg) msg.textContent='اكتب سؤالك أولاً.';
          return;
        }
        try{
          const res = await postForm(api('/api/news/ask'), {news_id: newsId, name, email, question});
          if(res && res.ok){
            form.reset();
            if(msg) msg.textContent = res.message || 'تم الإرسال.';
            await load();
          }else{
            if(msg) msg.textContent = (res && res.error) ? res.error : 'تعذر الإرسال.';
          }
        }catch(e){
          if(msg) {
            const status = (e && e.status) ? (' (HTTP ' + e.status + ')') : '';
            msg.textContent = 'تعذر الإرسال.' + status;
          }
        }
      });
    }

    await load();
  }


  function initTts(){
  const playBtn = document.getElementById('gdy-tts-play');
  const stopBtn = document.getElementById('gdy-tts-stop');
  const rateEl  = document.getElementById('gdy-tts-rate');
  const langEl  = document.getElementById('gdy-tts-lang');
  const statusEl= document.getElementById('gdy-tts-status');

  if(!playBtn || !stopBtn) return;
  if(!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)){
    playBtn.disabled = true;
    stopBtn.disabled = true;
    return;
  }

  let queue = [];
  let idx = 0;
  let isPaused = false;
  let isSpeaking = false;

  const mergeSpelledArabic = (t) => {
    // يحوّل: "ا ل س و د ا ن" => "السودان"
    // نكرر عدة مرات لتجميع الكلمات الطويلة
    for(let k=0;k<6;k++){
      t = t.replace(/([ء-ي])\s+(?=[ء-ي])/g, '$1');
    }
    return t;
  };

  const normalizeText = (t) => {
    t = (t||'').replace(/\u00A0/g,' ').replace(/\s+/g,' ').trim();
    t = mergeSpelledArabic(t);
    // إزالة الرموز المتكررة التي تربك TTS
    t = t.replace(/[•·•]+/g,' ').replace(/\s+/g,' ').trim();
    return t;
  };

  const chunkText = (t) => {
    // تقسيم مريح: فقرات ثم جمل ثم مقاطع (حد أقصى ~180 حرف)
    const out = [];
    const paras = t.split(/\n+/).map(x=>x.trim()).filter(Boolean);

    const pushChunk = (s) => {
      s = s.trim();
      if(!s) return;
      const maxLen = 180;
      if(s.length <= maxLen){ out.push(s); return; }
      // قص ذكي على المسافات
      let cur = '';
      s.split(' ').forEach(w=>{
        if((cur + ' ' + w).trim().length > maxLen){
          if(cur.trim()) out.push(cur.trim());
          cur = w;
        }else{
          cur = (cur + ' ' + w).trim();
        }
      });
      if(cur.trim()) out.push(cur.trim());
    };

    paras.forEach(p=>{
      const parts = p.split(/(?<=[\.\!\؟\?])\s+/);
      parts.forEach(pushChunk);
    });

    return out;
  };

  const getLang = () => {
    const v = (langEl && langEl.value) ? langEl.value : (document.documentElement.lang || 'ar');
    // تحويل قيم بسيطة إلى قيم مناسبة للـ Speech API
    if(v === 'ar') return 'ar-SA';
    if(v === 'en') return 'en-US';
    return v;
  };

  const updateStatus = () => {
    if(!statusEl) return;
    if(!queue.length) { statusEl.textContent = ''; return; }
    statusEl.textContent = `${idx+1}/${queue.length}`;
  };

  const speakNext = () => {
    if(idx >= queue.length){
      isSpeaking = false;
      isPaused = false;
      playBtn.classList.remove('is-playing');
      playBtn.innerHTML = '<i class="fa-solid fa-play"></i> تشغيل';
      updateStatus();
      return;
    }

    const u = new SpeechSynthesisUtterance(queue[idx]);
    u.lang = getLang();
    u.rate = Math.max(0.6, Math.min(1.4, parseFloat(rateEl?.value || '1') || 1));

    u.onend = () => {
      if(isPaused) return;
      idx += 1;
      updateStatus();
      speakNext();
    };
    u.onerror = () => {
      idx += 1;
      updateStatus();
      speakNext();
    };

    isSpeaking = true;
    window.speechSynthesis.speak(u);
  };

  const stopAll = () => {
    window.speechSynthesis.cancel();
    queue = [];
    idx = 0;
    isPaused = false;
    isSpeaking = false;
    playBtn.classList.remove('is-playing');
    playBtn.innerHTML = '<i class="fa-solid fa-play"></i> تشغيل';
    updateStatus();
  };

  playBtn.addEventListener('click', function(){
    // Toggle: Play / Pause / Resume
    if(isSpeaking && !isPaused){
      isPaused = true;
      window.speechSynthesis.pause();
      playBtn.innerHTML = '<i class="fa-solid fa-play"></i> متابعة';
      return;
    }
    if(isSpeaking && isPaused){
      isPaused = false;
      window.speechSynthesis.resume();
      playBtn.innerHTML = '<i class="fa-solid fa-pause"></i> إيقاف مؤقت';
      return;
    }

    const raw = normalizeText(extractReadableText());
    if(!raw){
      alert('لا يوجد نص صالح للقراءة.');
      return;
    }

    queue = chunkText(raw);
    idx = 0;
    isPaused = false;

    playBtn.classList.add('is-playing');
    playBtn.innerHTML = '<i class="fa-solid fa-pause"></i> إيقاف مؤقت';
    updateStatus();

    window.speechSynthesis.cancel();
    speakNext();
  });

  stopBtn.addEventListener('click', stopAll);

  if(rateEl){
    rateEl.addEventListener('change', function(){
      // لا نغير أثناء التشغيل حتى لا تتقطع القراءة بشكل مزعج
    });
  }

  // إيقاف TTS عند تغيير الصفحة/المسار
  window.addEventListener('beforeunload', stopAll);
}



  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    initReactions();
    initPoll();
    initQuestions();
    initTts();
  });
})();
