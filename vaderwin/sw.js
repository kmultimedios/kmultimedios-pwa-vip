/**
 * Service Worker – KMultimedios VIP Windows (vaderwin)
 */

const CACHE_VERSION = 'km-vip-win-v1.0.0';
const CACHE_SHELL   = `${CACHE_VERSION}-shell`;
const CACHE_CONTENT = `${CACHE_VERSION}-content`;
const CACHE_IMAGES  = `${CACHE_VERSION}-images`;

const SHELL_ASSETS = [
  '/vaderwin/',
  '/vaderwin/index.html',
  '/vaderwin/js/auth.js',
  '/vaderwin/js/app.js',
  '/vader/css/app.css',
  '/vader/js/fingerprint.js',
  '/vader/js/webauthn.js',
  '/vader/icons/icon-192.png',
  '/vader/icons/icon-512.png',
  '/vaderwin/offline.html',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_SHELL)
      .then(cache => cache.addAll(SHELL_ASSETS).catch(err => console.warn('[SW] Shell cache error:', err)))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  const valid = [CACHE_SHELL, CACHE_CONTENT, CACHE_IMAGES];
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => !valid.includes(k)).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (!request.url.startsWith('http') || request.method !== 'GET') return;
  if (url.hostname === 'proxy.kmultimedios.com') return;

  if (url.pathname.startsWith('/wp-json/vader/') || url.pathname.startsWith('/wp-admin/admin-ajax.php')) {
    event.respondWith(networkFirst(request, CACHE_CONTENT, 5000));
    return;
  }

  if (/\.(png|jpg|jpeg|gif|webp|svg|ico)$/i.test(url.pathname)) {
    event.respondWith(staleWhileRevalidate(request, CACHE_IMAGES));
    return;
  }

  if (SHELL_ASSETS.some(a => url.pathname === a)) {
    event.respondWith(cacheFirst(request, CACHE_SHELL));
    return;
  }

  event.respondWith(networkFirst(request, CACHE_CONTENT, 8000));
});

async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response.ok) (await caches.open(cacheName)).put(request, response.clone());
    return response;
  } catch { return offlinePage(); }
}

async function networkFirst(request, cacheName, timeout = 5000) {
  const ctrl  = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeout);
  try {
    const response = await fetch(request, { signal: ctrl.signal });
    clearTimeout(timer);
    if (response.ok) (await caches.open(cacheName)).put(request, response.clone());
    return response;
  } catch {
    clearTimeout(timer);
    return (await caches.match(request)) || offlinePage();
  }
}

async function staleWhileRevalidate(request, cacheName) {
  const cache  = await caches.open(cacheName);
  const cached = await cache.match(request);
  const netReq = fetch(request).then(r => { if (r.ok) cache.put(request, r.clone()); return r; }).catch(() => null);
  return cached || await netReq || offlinePage();
}

function offlinePage() {
  return caches.match('/vaderwin/offline.html').then(
    r => r || new Response('<h1>Sin conexión</h1>', { headers: { 'Content-Type': 'text/html' } })
  );
}

self.addEventListener('push', (event) => {
  const data    = event.data?.json() ?? {};
  const title   = data.title ?? 'KMultimedios VIP';
  const options = {
    body:    data.body    ?? 'Nuevo contenido disponible.',
    icon:    '/vader/icons/icon-192.png',
    badge:   '/vader/icons/icon-96.png',
    data:    { url: data.url ?? '/vaderwin/' },
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data?.url ?? '/vaderwin/'));
});
