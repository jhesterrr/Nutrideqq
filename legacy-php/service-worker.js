// service-worker.js
// NutriDeq PWA Engine v3 - Offline Resilience & Dynamic Caching
const CACHE_NAME = 'nutrideq-v3';
const STATIC_ASSETS = [
    '/css/base.css',
    '/css/dashboard.css',
    '/css/user-premium.css',
    '/scripts/user-realtime.js',
    '/assets/img/logo.png',
    '/offline.html'
];

// 1. Install Event - Cache static assets only
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            console.log('NutriDeq - Static Assets & Fallback Primed');
            return cache.addAll(STATIC_ASSETS);
        })
    );
});

// 2. Fetch Event - Network-First for dynamic PHP, Cache-First for static
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    const isPhp = url.pathname.endsWith('.php');
    const isHtml = url.pathname.endsWith('.html');
    const isNav = event.request.mode === 'navigate';

    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    if (isPhp || isHtml || isNav) {
        // Dynamic routes: NETWORK-FIRST with OFFLINE FALLBACK
        event.respondWith(
            fetch(event.request).catch(() => {
                // Return custom offline page if the network fails
                return caches.match('/offline.html');
            })
        );
    } else {
        // Static Assets: CACHE-FIRST (Faster performance)
        event.respondWith(
            caches.match(event.request).then(response => {
                return response || fetch(event.request);
            })
        );
    }
});

// 3. Activate Event - Force clear old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
            );
        })
    );
    // Immediately take control of the page
    self.clients.claim();
});
