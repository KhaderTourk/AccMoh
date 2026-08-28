/* AccMa CP Service Worker — offline shell for control panel */
const CACHE = 'accma-cp-v3';
const SHELL = [
  '/assets/css/cp.css',
  '/assets/js/cp-offline.js',
  '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then(async (cache) => {
      for (const url of SHELL) {
        try {
          await cache.add(url);
        } catch (_) {}
      }
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET') return;

  // Never cache API — always network (fresh financial data)
  if (url.pathname.startsWith('/cp/api/') || url.pathname.startsWith('/api/')) {
    return;
  }

  // HTML navigations: network-first, cached fallback for offline
  if (req.mode === 'navigate' && url.pathname.startsWith('/cp')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          if (res.ok) {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
          }
          return res;
        })
        .catch(async () => {
          const cachedPage = await caches.match(req);
          if (cachedPage) return cachedPage;
          const offline = await caches.match('/cp/offline');
          if (offline) return offline;
          return new Response(
            '<!DOCTYPE html><html dir="rtl" lang="ar"><body style="font-family:sans-serif;padding:2rem;text-align:center"><h1>AccMa Offline</h1><p>افتح /cp/offline بعد الاتصال مرة واحدة لتفعيل الكاش.</p></body></html>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
          );
        })
    );
    return;
  }

  // Static + CDN: stale-while-revalidate (instant paint on slow links)
  if (
    url.pathname.startsWith('/assets/') ||
    url.hostname.includes('cdn.') ||
    url.hostname.includes('jsdelivr') ||
    url.hostname.includes('fonts.googleapis') ||
    url.hostname.includes('fonts.gstatic') ||
    url.hostname.includes('cdn.tailwindcss.com')
  ) {
    event.respondWith(
      caches.open(CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        const network = fetch(req)
          .then((res) => {
            if (res.ok) cache.put(req, res.clone());
            return res;
          })
          .catch(() => cached);
        return cached || network;
      })
    );
  }
});
