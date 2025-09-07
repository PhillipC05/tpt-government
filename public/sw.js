// TPT Government Platform - Service Worker
// Handles caching, offline functionality, and background sync

const CACHE_NAME = 'tpt-gov-v1.0.0';
const STATIC_CACHE = 'tpt-gov-static-v1.0.0';
const DYNAMIC_CACHE = 'tpt-gov-dynamic-v1.0.0';

// Files to cache immediately
const STATIC_ASSETS = [
  '/',
  '/manifest.json',
  '/css/main.css',
  '/js/app.js',
  '/js/components.js',
  '/js/api.js',
  '/js/utils.js',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png'
];

// API endpoints that should be cached
const API_CACHE_PATTERNS = [
  /\/api\/health/,
  /\/api\/services/,
  /\/api\/user\/profile/
];

// Files that should not be cached
const EXCLUDE_FROM_CACHE = [
  /\/api\/auth\//,
  /\/admin\//,
  /\/logout/
];

// Install event - cache static assets
self.addEventListener('install', event => {
  console.log('[SW] Installing service worker');

  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => {
        console.log('[SW] Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        console.log('[SW] Service worker installed');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('[SW] Installation failed:', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('[SW] Activating service worker');

  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('[SW] Service worker activated');
        return self.clients.claim();
      })
  );
});

// Fetch event - handle requests
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // Skip external requests
  if (!url.origin.includes(self.location.origin)) {
    return;
  }

  // Skip excluded patterns
  if (EXCLUDE_FROM_CACHE.some(pattern => pattern.test(url.pathname))) {
    return;
  }

  // Handle API requests with network-first strategy
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(request)
        .then(response => {
          // Cache successful API responses
          if (response.ok && API_CACHE_PATTERNS.some(pattern => pattern.test(url.pathname))) {
            const responseClone = response.clone();
            caches.open(DYNAMIC_CACHE)
              .then(cache => cache.put(request, responseClone));
          }
          return response;
        })
        .catch(() => {
          // Try cache if network fails
          return caches.match(request)
            .then(cachedResponse => {
              if (cachedResponse) {
                return cachedResponse;
              }
              // Return offline page for API failures
              return new Response(
                JSON.stringify({
                  error: 'Offline',
                  message: 'You are currently offline. Please check your connection.'
                }),
                {
                  status: 503,
                  headers: { 'Content-Type': 'application/json' }
                }
              );
            });
        })
    );
    return;
  }

  // Handle static assets with cache-first strategy
  event.respondWith(
    caches.match(request)
      .then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }

        // Fetch from network
        return fetch(request)
          .then(response => {
            // Don't cache non-successful responses
            if (!response.ok) {
              return response;
            }

            // Cache the response
            const responseClone = response.clone();
            caches.open(DYNAMIC_CACHE)
              .then(cache => cache.put(request, responseClone));

            return response;
          })
          .catch(() => {
            // Return offline page for navigation requests
            if (request.mode === 'navigate') {
              return caches.match('/offline.html')
                .then(cachedResponse => {
                  return cachedResponse || new Response(
                    '<h1>Offline</h1><p>You are currently offline. Please check your connection.</p>',
                    {
                      headers: { 'Content-Type': 'text/html' }
                    }
                  );
                });
            }

            // Return error for other requests
            return new Response('Offline', { status: 503 });
          });
      })
  );
});

// Background sync for offline form submissions
self.addEventListener('sync', event => {
  console.log('[SW] Background sync triggered:', event.tag);

  if (event.tag === 'background-sync-forms') {
    event.waitUntil(syncOfflineForms());
  }

  if (event.tag === 'background-sync-api') {
    event.waitUntil(syncOfflineApiCalls());
  }
});

// Push notifications
self.addEventListener('push', event => {
  console.log('[SW] Push notification received');

  if (!event.data) {
    return;
  }

  const data = event.data.json();

  const options = {
    body: data.body,
    icon: '/icons/icon-192x192.png',
    badge: '/icons/icon-96x96.png',
    vibrate: [100, 50, 100],
    data: data.data || {},
    actions: data.actions || []
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
  console.log('[SW] Notification clicked:', event.action);

  event.notification.close();

  const action = event.action || 'default';
  const data = event.notification.data || {};

  let url = '/';

  switch (action) {
    case 'view':
      url = data.url || '/dashboard';
      break;
    case 'dismiss':
      return;
    default:
      url = data.url || '/';
  }

  event.waitUntil(
    clients.openWindow(url)
  );
});

// Message handler for communication with main thread
self.addEventListener('message', event => {
  console.log('[SW] Message received:', event.data);

  const { type, data } = event.data;

  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;

    case 'CACHE_URL':
      event.waitUntil(
        caches.open(DYNAMIC_CACHE)
          .then(cache => cache.add(data.url))
      );
      break;

    case 'DELETE_CACHE':
      event.waitUntil(
        caches.delete(data.cacheName)
      );
      break;
  }
});

// Periodic cleanup
self.addEventListener('periodicsync', event => {
  if (event.tag === 'cleanup-cache') {
    event.waitUntil(cleanupCache());
  }
});

// Helper functions

async function syncOfflineForms() {
  try {
    const cache = await caches.open('offline-forms');
    const keys = await cache.keys();

    for (const request of keys) {
      try {
        const response = await fetch(request);
        if (response.ok) {
          await cache.delete(request);
          console.log('[SW] Synced offline form:', request.url);
        }
      } catch (error) {
        console.error('[SW] Failed to sync form:', request.url, error);
      }
    }
  } catch (error) {
    console.error('[SW] Background sync failed:', error);
  }
}

async function syncOfflineApiCalls() {
  try {
    const cache = await caches.open('offline-api');
    const keys = await cache.keys();

    for (const request of keys) {
      try {
        const response = await fetch(request);
        if (response.ok) {
          await cache.delete(request);
          console.log('[SW] Synced offline API call:', request.url);
        }
      } catch (error) {
        console.error('[SW] Failed to sync API call:', request.url, error);
      }
    }
  } catch (error) {
    console.error('[SW] API sync failed:', error);
  }
}

async function cleanupCache() {
  try {
    const cache = await caches.open(DYNAMIC_CACHE);
    const keys = await cache.keys();

    // Remove old entries (older than 24 hours)
    const oneDayAgo = Date.now() - (24 * 60 * 60 * 1000);

    for (const request of keys) {
      const response = await cache.match(request);
      if (response) {
        const date = new Date(response.headers.get('date') || 0);
        if (date.getTime() < oneDayAgo) {
          await cache.delete(request);
          console.log('[SW] Cleaned up old cache entry:', request.url);
        }
      }
    }
  } catch (error) {
    console.error('[SW] Cache cleanup failed:', error);
  }
}

// Utility function to check if request is cacheable
function isCacheable(request) {
  const url = new URL(request.url);

  // Don't cache API auth endpoints
  if (url.pathname.startsWith('/api/auth/')) {
    return false;
  }

  // Don't cache admin pages
  if (url.pathname.startsWith('/admin/')) {
    return false;
  }

  // Don't cache logout
  if (url.pathname.includes('/logout')) {
    return false;
  }

  return true;
}
