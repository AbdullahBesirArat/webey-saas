/* ============================================================
   Webey Service Worker  –  v1.0.0
   Strateji: Cache-first statik varlıklar, Network-first API
   ============================================================ */

const CACHE_NAME    = 'webey-v1';
const OFFLINE_URL   = '/404.html';

/* Kurulumda önbelleğe alınacak statik varlıklar */
const PRECACHE_URLS = [
  '/',
  '/index.html',
  '/kuafor.html',
  '/hakkimizda.html',
  '/iletisim.html',
  '/sss.html',
  '/fiyat.html',
  '/404.html',
  '/css/index.css',
  '/css/kuafor.css',
  '/css/auth-modal.css',
  '/css/wb-transitions.css',
  '/css/wb-bottom-nav.css',
  '/css/cookie-consent.css',
  '/js/index.js',
  '/js/kuafor.js',
  '/js/auth.js',
  '/js/wb-transitions.js',
  '/js/wb-bottom-nav.js',
  '/js/cookie-consent.js',
  '/manifest.json'
];

/* ── INSTALL ── */
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

/* ── ACTIVATE ── */
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

/* ── FETCH ── */
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  /* API isteklerini her zaman ağdan al, cache'e alma */
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(fetch(request).catch(() => new Response('', { status: 503 })));
    return;
  }

  /* Navigasyon isteği: Network-first, çevrimdışıysa offline sayfası */
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then(res => {
          const clone = res.clone();
          caches.open(CACHE_NAME).then(c => c.put(request, clone));
          return res;
        })
        .catch(() => caches.match(request).then(r => r || caches.match(OFFLINE_URL)))
    );
    return;
  }

  /* Statik varlıklar: Cache-first */
  event.respondWith(
    caches.match(request).then(cached => {
      if (cached) return cached;
      return fetch(request).then(res => {
        if (res.ok) {
          const clone = res.clone();
          caches.open(CACHE_NAME).then(c => c.put(request, clone));
        }
        return res;
      });
    })
  );
});

/* ── PUSH BİLDİRİMLERİ ── */
self.addEventListener('push', event => {
  let data = { title: 'Webey', body: 'Yeni bildiriminiz var.' };
  try { data = event.data.json(); } catch (e) {}

  event.waitUntil(
    self.registration.showNotification(data.title, {
      body:    data.body,
      icon:    '/img/icon-192.png',
      badge:   '/img/icon-192.png',
      vibrate: [200, 100, 200],
      data:    { url: data.url || '/' },
      actions: [
        { action: 'open',    title: 'Görüntüle' },
        { action: 'dismiss', title: 'Kapat'     }
      ]
    })
  );
});

/* Bildirime tıklayınca ilgili sayfayı aç */
self.addEventListener('notificationclick', event => {
  event.notification.close();
  if (event.action === 'dismiss') return;

  const targetUrl = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const client of list) {
        if (client.url === targetUrl && 'focus' in client) return client.focus();
      }
      return clients.openWindow(targetUrl);
    })
  );
});