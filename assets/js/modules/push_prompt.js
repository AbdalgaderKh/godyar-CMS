/* Mobile push enable prompt */
(function(){
  const pubKey = window.GDY_VAPID_PUBLIC_KEY || '';
  if(!pubKey) return;

  const isMobile = () => {
    try{
      return window.matchMedia('(max-width: 768px)').matches &&
             window.matchMedia('(pointer: coarse)').matches;
    }catch(e){ return (window.innerWidth||0) <= 768; }
  };

  const toast = document.getElementById('gdy-push-toast');
  if(!toast) return;

  const btnEnable = toast.querySelector('[data-gdy-push-enable]');
  const btnLater  = toast.querySelector('[data-gdy-push-later]');

  const dismissKey = 'gdy_push_prompt_dismissed_at';
  const coolDownMs = 1000 * 60 * 60 * 24 * 7; // 7 days

  function show(){
    toast.style.display = 'block';
  }
  function hide(){
    toast.style.display = 'none';
  }
  function setDismiss(){
    try{ localStorage.setItem(dismissKey, String(Date.now())); }catch(e){}
  }
  function recentlyDismissed(){
    try{
      const v = parseInt(localStorage.getItem(dismissKey)||'0',10);
      return v && (Date.now()-v) < coolDownMs;
    }catch(e){ return false; }
  }

  async function hasSubscription(){
    if(!('serviceWorker' in navigator)) return false;
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    return !!sub;
  }

  async function init(){
    if(!isMobile()) return;
    if(recentlyDismissed()) return;
    if(Notification && Notification.permission === 'denied') return;
    try{
      if(await hasSubscription()) return;
    }catch(e){ /* ignore */ }

    // show after short delay so it doesn't feel intrusive
    setTimeout(show, 1200);
  }

  btnLater && btnLater.addEventListener('click', function(){
    setDismiss(); hide();
  });

  btnEnable && btnEnable.addEventListener('click', async function(){
    btnEnable.disabled = true;
    try{
      if(!window.GodyarPush || !window.GodyarPush.subscribe) throw new Error('push-api-missing');
      await window.GodyarPush.subscribe({});
      hide();
    }catch(e){
      // If user dismisses permission prompt, don't nag.
      setDismiss();
      hide();
      // Optional: could show a small toast, but keep silent.
    }finally{
      btnEnable.disabled = false;
    }
  });

  document.addEventListener('DOMContentLoaded', init);
})();
