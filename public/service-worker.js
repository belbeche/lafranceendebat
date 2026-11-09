// La France en Débat : service worker.
// Sécurité : on ne met JAMAIS en cache les pages (elles contiennent session, jetons CSRF, données perso).
// Seuls les fichiers statiques publics et la page hors ligne sont mis en cache.
const VERSION = 'lfed-v2';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/css/lfed.css', '/pwa/icon-192.png', '/pwa/icon-512.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(VERSION).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Pages : réseau d'abord, page hors ligne si le réseau échoue. Jamais de cache de page.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  // Statique public uniquement : cache d'abord, mise à jour en arrière-plan.
  if (/^\/(css|js|pwa|image|build|assets)\//.test(url.pathname)) {
    event.respondWith(
      caches.open(VERSION).then((cache) =>
        cache.match(req).then((hit) => {
          const net = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
          return hit || net;
        })
      )
    );
  }
});

// Clic sur une notification système : ouvre la page concernée.
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
    return self.clients.openWindow(url);
  }));
});
