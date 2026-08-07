var staticCacheName = "pwa-v" + new Date().getTime();
// (captura fluida · Paso A3) Cache de RUNTIME para que las FORMAS DE CAPTURA abran sin red.
// Nombre FIJO (no se borra en cada activate) → sobrevive a las actualizaciones del SW.
var runtimeCache = "pwa-runtime-v1";
var filesToCache = [
    '/offline',
    '/css/form-register.css',
    '/js/app.js',
    '/images/icons/icon-72x72.png',
    '/images/icons/icon-96x96.png',
    '/images/icons/icon-128x128.png',
    '/images/icons/icon-144x144.png',
    '/images/icons/icon-152x152.png',
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-384x384.png',
    '/images/icons/icon-512x512.png',
];

// Cache on install
self.addEventListener("install", event => {
    this.skipWaiting();
    event.waitUntil(
        caches.open(staticCacheName)
            .then(cache => {
                return cache.addAll(filesToCache);
            })
    )
});

// Clear cache on activate — PERO conserva el runtimeCache (nombre fijo) además del static actual.
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(cacheName => (cacheName.startsWith("pwa-")))
                    .filter(cacheName => (cacheName !== staticCacheName && cacheName !== runtimeCache))
                    .map(cacheName => caches.delete(cacheName))
            );
        })
    );
});

// (captura fluida · Paso A3) Solo las FORMAS DE CAPTURA (rutas .../create|crear) se guardan en
// runtime con estrategia NETWORK-FIRST: online siempre trae la versión fresca (token CSRF al día);
// solo si NO hay red se sirve la copia cacheada, para poder ABRIR la forma y retomar el borrador
// (que vive en IndexedDB). Se limita a estas rutas a propósito: son formas EN BLANCO (no filtran
// datos de nadie), a diferencia de un dashboard con información. Todo lo demás conserva el
// comportamiento previo (cache-first de los estáticos precacheados; fallback a /offline).
function isCaptureForm(req) {
    if (req.method !== 'GET') { return false; }
    var url;
    try { url = new URL(req.url); } catch (e) { return false; }
    if (url.origin !== self.location.origin) { return false; }
    var isNav = req.mode === 'navigate' || (req.headers.get('accept') || '').indexOf('text/html') !== -1;
    return isNav && /(create|crear)(\/|$)/i.test(url.pathname);
}

self.addEventListener("fetch", event => {
    var req = event.request;

    if (isCaptureForm(req)) {
        event.respondWith(
            fetch(req)
                .then(function (res) {
                    var copy = res.clone();
                    caches.open(runtimeCache).then(function (c) { c.put(req, copy); });
                    return res;
                })
                .catch(function () {
                    return caches.match(req).then(function (r) { return r || caches.match('offline'); });
                })
        );
        return;
    }

    // Comportamiento previo (cache-first de estáticos; red si no está en cache; /offline si falla).
    event.respondWith(
        caches.match(req)
            .then(response => {
                return response || fetch(req);
            })
            .catch(() => {
                return caches.match('offline');
            })
    );
});
