/**
 * Service Worker – KMultimedios VIP PWA
 * Estrategia:
 *   - Shell (HTML/CSS/JS) → Cache First
 *   - API requests         → Network First con fallback a cache
 *   - Imágenes             → Stale While Revalidate
 */

const CACHE_VERSION  = 'km-vip-v1.0.2';
const CACHE_SHELL    = `${CACHE_VERSION}-shell`;
const CACHE_CONTENT  = `${CACHE_VERSION}-content`;
const CACHE_IMAGES   = `${CACHE_VERSION}-images`;

const SHELL_ASSETS = [
  '/pwa/',
  '/pwa/index.html',
  '/pwa/css/app.css',
  '/pwa/js/webauthn.js',
  '/pwa/js/auth.js',
  '/pwa/js/app.js',
  '/pwa/icons/icon-192.png',
  '/pwa/icons/icon-512.png',
  '/pwa/offline.html',
];

// ── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_SHELL).then((cache) => {
      return cache.addAll(SHELL_ASSETS).catch((err) => {
        console.warn('[SW] Error cacheando shell:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// ── Activate ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  const validCaches = [CACHE_SHELL, CACHE_CONTENT, CACHE_IMAGES];
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => !validCaches.includes(k))
          .map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ── Fetch ─────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Ignorar chrome-extension y non-GET para cachear
  if (!request.url.startsWith('http') || request.method !== 'GET') return;

  // API → Network First
  if (url.pathname.startsWith('/wp-json/pwa/')) {
    event.respondWith(networkFirst(request, CACHE_CONTENT, 5000));
    return;
  }

  // Imágenes → Stale While Revalidate
  if (/\.(png|jpg|jpeg|gif|webp|svg|ico)$/i.test(url.pathname)) {
    event.respondWith(staleWhileRevalidate(request, CACHE_IMAGES));
    return;
  }

  // Shell assets → Cache First
  if (SHELL_ASSETS.some((a) => url.pathname === a || url.pathname === a.replace('/pwa', ''))) {
    event.respondWith(cacheFirst(request, CACHE_SHELL));
    return;
  }

  // Resto → Network First
  event.respondWith(networkFirst(request, CACHE_CONTENT, 8000));
});

// ── Estrategias de caché ─────────────────────────────────────────────────────

async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    return offlinePage();
  }
}

async function networkFirst(request, cacheName, timeout = 5000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);

  try {
    const response = await fetch(request, { signal: controller.signal });
    clearTimeout(timer);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    clearTimeout(timer);
    const cached = await caches.match(request);
    return cached || offlinePage();
  }
}

async function staleWhileRevalidate(request, cacheName) {
  const cache  = await caches.open(cacheName);
  const cached = await cache.match(request);

  const networkFetch = fetch(request).then((response) => {
    if (response.ok) cache.put(request, response.clone());
    return response;
  }).catch(() => null);

  return cached || await networkFetch || offlinePage();
}

function offlinePage() {
  return caches.match('/pwa/offline.html').then(
    (r) => r || new Response('<h1>Sin conexión</h1>', { headers: { 'Content-Type': 'text/html' } })
  );
}

// ── Push Notifications ────────────────────────────────────────────────────────
self.addEventListener('push', (event) => {
  const data = event.data?.json() ?? {};
  const title   = data.title   ?? 'KMultimedios VIP';
  const options = {
    body:  data.body  ?? 'Nuevo contenido disponible.',
    icon:  '/pwa/icons/icon-192.png',
    badge: '/pwa/icons/icon-96.png',
    data:  { url: data.url ?? '/pwa/' },
    vibrate: [200, 100, 200],
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url ?? '/pwa/';
  event.waitUntil(clients.openWindow(url));
});
