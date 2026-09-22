/* ============================================================
   FITZONE GYM POS — PRODUCTION SERVICE WORKER v3
   Full Offline Support: Cache-First Static + Network-First API
   ============================================================ */

const CACHE_VERSION = 'gym-v8-offline-fast';
const STATIC_CACHE  = `${CACHE_VERSION}-static`;
const PAGES_CACHE   = `${CACHE_VERSION}-pages`;
const API_CACHE     = `${CACHE_VERSION}-api`;

/* All static assets to pre-cache on install */
const STATIC_ASSETS = [
  'assets/css/variables.css',
  'assets/css/main.css',
  'assets/css/pos.css',
  'assets/css/print.css',
  'assets/css/enhanced.css',
  'assets/js/app.js',
  'assets/js/pos.js',
  'assets/js/offline.js',
  'assets/js/scanner.js',
  'assets/js/chart.umd.min.js',
  'manifest.json'
];

/* Pages to cache for offline navigation */
const PAGES_TO_CACHE = [
  'index.php',
  'index.php?page=dashboard',
  'index.php?page=pos',
  'index.php?page=members',
  'index.php?page=attendance',
  'index.php?page=memberships',
];

/* API endpoints to cache (GET only) */
const API_CACHE_PATTERNS = [
  /api\/memberships\.php/,
  /api\/members\.php\?action=list/,
];

/* ─── INSTALL: Pre-cache all static assets ─────────────────── */
self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    Promise.all([
      caches.open(STATIC_CACHE).then(cache =>
        cache.addAll(STATIC_ASSETS).catch(() => {/* ignore offline install fails */})
      ),
      caches.open(PAGES_CACHE).then(cache =>
        cache.addAll(PAGES_TO_CACHE).catch(() => {})
      )
    ])
  );
});

/* ─── ACTIVATE: Clean up old caches ────────────────────────── */
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(k => !k.startsWith(CACHE_VERSION))
          .map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

/* ─── FETCH: Smart routing strategy ────────────────────────── */
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET & cross-origin requests
  if (request.method !== 'GET') return;
  if (url.origin !== location.origin) return;

  // CSS / JS / Fonts — Network First with Cache Fallback for instant live updates
  if (url.pathname.startsWith('/GYM/assets/') || url.pathname.includes('fonts.googleapis') || url.pathname.includes('fonts.gstatic')) {
    event.respondWith(networkFirstWithCache(request, STATIC_CACHE));
    return;
  }

  // API GET requests for memberships & member lists — Network First with API cache fallback
  if (API_CACHE_PATTERNS.some(p => p.test(url.pathname))) {
    event.respondWith(networkFirstWithCache(request, API_CACHE));
    return;
  }

  // HTML page navigation — Network First, fall back to cached page, then offline page
  if (request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(networkFirstHtml(request));
    return;
  }

  // Everything else — Network with cache fallback
  event.respondWith(networkFirstWithCache(request, PAGES_CACHE));
});

/* ─── STRATEGY: Cache First ────────────────────────────────── */
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
    return new Response('Asset unavailable offline', { status: 503 });
  }
}

/* ─── STRATEGY: Network First with cache ───────────────────── */
async function networkFirstWithCache(request, cacheName) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await caches.match(request);
    return cached || new Response('{"success":false,"message":"Offline — data unavailable","data":[]}', {
      status: 200,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

/* ─── STRATEGY: Network First for HTML pages ───────────────── */
async function networkFirstHtml(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(PAGES_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    // Try cached version of the exact page
    const cached = await caches.match(request);
    if (cached) return cached;

    // Fall back to cached dashboard
    const dashboard = await caches.match('index.php?page=dashboard');
    if (dashboard) return dashboard;

    // Ultimate offline fallback page
    return offlineFallbackPage();
  }
}

/* ─── Offline Fallback HTML Page ───────────────────────────── */
function offlineFallbackPage() {
  return new Response(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FITZONE GYM — Offline Mode</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;800&display=swap');
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Outfit', sans-serif;
      background: #0F172A;
      color: #F8FAFC;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1.5rem;
    }
    .container {
      text-align: center;
      max-width: 480px;
      animation: fadeIn 0.4s ease;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
    .icon { font-size: 4rem; margin-bottom: 1.5rem; }
    h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem; }
    p  { font-size: 0.95rem; color: #94A3B8; line-height: 1.6; margin-bottom: 1.5rem; }
    .badge {
      display: inline-flex; align-items: center; gap: 0.4rem;
      background: rgba(245, 158, 11, 0.15); color: #FCD34D;
      border: 1px solid rgba(245, 158, 11, 0.35);
      padding: 0.45rem 1rem; border-radius: 999px;
      font-size: 0.82rem; font-weight: 700; margin-bottom: 2rem;
    }
    .actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
    .btn {
      padding: 0.65rem 1.4rem; border-radius: 10px; border: none;
      font-size: 0.875rem; font-weight: 700; font-family: inherit;
      cursor: pointer; text-decoration: none; transition: all 0.18s ease;
      display: inline-flex; align-items: center; gap: 0.4rem;
    }
    .btn-primary { background: #2563EB; color: #fff; }
    .btn-primary:hover { background: #1D4ED8; transform: translateY(-1px); }
    .btn-outline {
      background: transparent; color: #94A3B8;
      border: 1px solid rgba(148, 163, 184, 0.3);
    }
    .btn-outline:hover { background: rgba(148, 163, 184, 0.1); color: #F8FAFC; }
    .tips {
      margin-top: 2.5rem;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 14px;
      padding: 1.25rem;
      text-align: left;
    }
    .tips h3 { font-size: 0.82rem; color: #64748B; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.85rem; }
    .tip-item { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.84rem; color: #94A3B8; margin-bottom: 0.5rem; }
    .tip-dot { color: #10B981; font-size: 1rem; flex-shrink: 0; }
  </style>
</head>
<body>
  <div class="container">
    <div class="icon">📡</div>
    <h1>You're Offline</h1>
    <p>No internet connection detected. FITZONE Gym ERP is running in offline mode — your locally cached data is still available.</p>
    <div class="badge">⚡ Offline Mode Active — IndexedDB transactions saved locally</div>

    <div class="actions">
      <button class="btn btn-primary" onclick="window.location.reload()">🔄 Try Reconnecting</button>
      <a href="index.php" class="btn btn-outline">🏋️ Open Cached App</a>
    </div>

    <div class="tips">
      <h3>While Offline You Can Still:</h3>
      <div class="tip-item"><span class="tip-dot">✅</span> View cached member records & membership plans</div>
      <div class="tip-item"><span class="tip-dot">✅</span> Process POS sales (saved to IndexedDB locally)</div>
      <div class="tip-item"><span class="tip-dot">✅</span> Mark attendance for members</div>
      <div class="tip-item"><span class="tip-dot">✅</span> View today's dashboard & analytics cache</div>
      <div class="tip-item"><span class="tip-dot">🔄</span> All pending transactions auto-sync when online</div>
    </div>
  </div>
</body>
</html>`, {
    status: 200,
    headers: { 'Content-Type': 'text/html; charset=utf-8' }
  });
}
