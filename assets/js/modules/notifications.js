/* Optional Web Push subscription (requires VAPID public key). */
(function(){
  const pubKey = window.GDY_VAPID_PUBLIC_KEY || '';
  if(!pubKey) return;

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
  }

  async function ensureSw(){
    if(!('serviceWorker' in navigator)) throw new Error('no-sw');
    const reg = await navigator.serviceWorker.ready;
    return reg;
  }

  async function subscribe(prefs){
    const reg = await ensureSw();
    const perm = await Notification.requestPermission();
    if(perm !== 'granted') throw new Error('denied');

    const sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(pubKey)
    });

    await fetch('/api/push/subscribe', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ ...sub.toJSON(), prefs: prefs||{} })
    });

    return sub;
  }

  async function unsubscribe(){
    const reg = await ensureSw();
    const sub = await reg.pushManager.getSubscription();
    if(!sub) return;

    try{
      await fetch('/api/push/unsubscribe', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'same-origin',
        body: JSON.stringify({ endpoint: sub.endpoint })
      });
    }catch(e){}
    await sub.unsubscribe();
  }

  // Expose minimal API for profile page
  window.GodyarPush = { subscribe, unsubscribe };
})();
