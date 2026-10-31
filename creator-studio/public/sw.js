// Service worker: makes the studio installable and opens the app shell when the network is
// down. Pages and files are fetched fresh first (network-first) and only fall back to the
// cache offline. The API, sign-ins and uploads are never cached: her data always comes live.
const CACHE = 'studio-shell-v2';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(['/', '/manifest.json', '/icons/icon.svg'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys()
    .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) return;
  if (/^\/(api|oauth|storage|media)\//.test(url.pathname)) return; // live only

  const isPage = req.mode === 'navigate';
  const isAsset = /^\/(css|js|icons)\//.test(url.pathname) || url.pathname === '/manifest.json';
  if (!isPage && !isAsset) return;

  event.respondWith(fetch(req).then((res) => {
    if (res.ok) {
      const copy = res.clone();
      // Every page is the same SPA shell, so pages are stored under "/".
      caches.open(CACHE).then((c) => c.put(isPage ? '/' : req, copy));
    }
    return res;
  }).catch(() => caches.match(isPage ? '/' : req).then((hit) => hit || Response.error())));
});
