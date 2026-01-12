/* Godyar Service Worker: offline + push notifications */
const CACHE_NAME = 'godyar-cache-v16';
const OFFLINE_URL = '/offline.html';
const CORE_ASSETS = [
  OFFLINE_URL,
  '/manifest.webmanifest?lang=ar',
  '/manifest.webmanifest',
  '/assets/css/ui-enhancements.css',
  '/assets/js/ui-enhancements.js',
  '/assets/js/modules/mobile_tabbar.js',
  '/assets/js/modules/mobile_search_overlay.js',
  '/assets/js/news-extras.js',
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await cache.addAll(CORE_ASSETS);
  })());
});


self.addEventListener('message', (event) => {
  if (!event.data) return;
  if (event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => (k !== CACHE_NAME) ? caches.delete(k) : Promise.resolve()));
    self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Only handle same-origin
  if (url.origin !== self.location.origin) return;

  // Navigation requests → network first, fallback offline
  if (req.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        const fresh = await fetch(req);
        const cache = await caches.open(CACHE_NAME);
        cache.put(req, fresh.clone()).catch(() => {});
        return fresh;
      } catch (e) {
        const cached = await caches.match(req);
        return cached || caches.match(OFFLINE_URL);
      }
    })());
    return;
  }

  // API/latest: stale-while-revalidate
  if (url.pathname === '/api/latest') {
    event.respondWith((async () => {
      const cache = await caches.open(CACHE_NAME);
      const cached = await cache.match(req);
      const fetchPromise = fetch(req).then((resp) => {
        cache.put(req, resp.clone()).catch(()=>{});
        return resp;
      }).catch(() => null);
      return cached || (await fetchPromise) || new Response(JSON.stringify({ok:false, offline:true}), {headers:{'Content-Type':'application/json'}});
    })());
    return;
  }

  // Runtime caching (GET only)
  if (req.method !== 'GET') return;

  event.respondWith((async () => {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(req);

    const isCSSJS = req.destination === 'style' || req.destination === 'script' || /\.(css|js)$/i.test(url.pathname);
    const isImage = req.destination === 'image' || /\.(png|jpe?g|gif|webp|svg|ico)$/i.test(url.pathname);
    const isFont  = req.destination === 'font'  || /\.(woff2?|ttf|otf|eot)$/i.test(url.pathname);

    // Images / fonts: cache-first
    if (isImage || isFont) {
      if (cached) return cached;
      try {
        const resp = await fetch(req);
        if (resp && resp.ok) cache.put(req, resp.clone()).catch(() => {});
        return resp;
      } catch (e) {
        return cached || new Response('', { status: 504 });
      }
    }

    // CSS/JS: stale-while-revalidate
    if (isCSSJS) {
      const fetchPromise = fetch(req).then((resp) => {
        if (resp && resp.ok) cache.put(req, resp.clone()).catch(() => {});
        return resp;
      }).catch(() => null);

      return cached || (await fetchPromise) || new Response('', { status: 504 });
    }

    // Everything else (GET): network-first, fallback cache/offline for documents
    try {
      const resp = await fetch(req);
      if (resp && resp.ok) cache.put(req, resp.clone()).catch(() => {});
      return resp;
    } catch (e) {
      if (cached) return cached;
      // If the browser is requesting an HTML page, show offline page
      const accept = req.headers.get('accept') || '';
      if (accept.includes('text/html')) {
        return cache.match(OFFLINE_URL);
      }
      return new Response('', { status: 504 });
    }
  })());
});


/* Push Notifications (payload should include title/body/icon/url) */
self.addEventListener('push', function (event) {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = {}; }

  const title = data.title || 'Godyar News';
  const options = {
    body: data.body || 'خبر جديد',
    icon: data.icon || '/icons/icon-192x192.png',
    badge: data.badge || '/icons/badge-72x72.png',
    data: {
      url: data.url || '/',
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  const urlToOpen = (event.notification && event.notification.data && event.notification.data.url) || '/';
  event.waitUntil((async () => {
    const allClients = await clients.matchAll({ includeUncontrolled: true, type: 'window' });
    for (const client of allClients) {
      if (client.url === urlToOpen && 'focus' in client) return client.focus();
    }
    if (clients.openWindow) return clients.openWindow(urlToOpen);
  })());
});
