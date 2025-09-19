const CACHE_NAME = 'simad-v1.0.0';
const STATIC_CACHE = 'simad-static-v1.0.0';
const DYNAMIC_CACHE = 'simad-dynamic-v1.0.0';

// Files to cache immediately
const STATIC_FILES = [
  '/',
  '/index.php',
  '/dashboard.php',
  '/login.php',
  '/css/bootstrap.css',
  '/css/style.css',
  '/css/responsive.css',
  '/js/jquery.min.js',
  '/js/bootstrap/bootstrap.min.js',
  '/js/plugins.js',
  '/img/kode-icon.png',
  '/favicon.svg',
  '/manifest.json'
];

// Install event - cache static files
self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => {
        console.log('Service Worker: Caching static files');
        return cache.addAll(STATIC_FILES);
      })
      .then(() => {
        console.log('Service Worker: Static files cached');
        return self.skipWaiting();
      })
      .catch(err => {
        console.error('Service Worker: Error caching static files', err);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating...');
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
              console.log('Service Worker: Deleting old cache', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Activated');
        return self.clients.claim();
      })
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }

  // Skip external requests
  if (url.origin !== location.origin) {
    return;
  }

  // Handle API requests differently
  if (url.pathname.includes('/api/')) {
    event.respondWith(handleApiRequest(request));
    return;
  }

  // Handle static files
  if (isStaticFile(url.pathname)) {
    event.respondWith(handleStaticRequest(request));
    return;
  }

  // Handle dynamic pages
  event.respondWith(handleDynamicRequest(request));
});

// Handle static file requests
function handleStaticRequest(request) {
  return caches.match(request)
    .then(response => {
      if (response) {
        return response;
      }
      return fetch(request)
        .then(fetchResponse => {
          if (fetchResponse.ok) {
            const responseClone = fetchResponse.clone();
            caches.open(STATIC_CACHE)
              .then(cache => {
                cache.put(request, responseClone);
              });
          }
          return fetchResponse;
        });
    })
    .catch(() => {
      // Return offline fallback for images
      if (request.destination === 'image') {
        return new Response(
          '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f0f0f0"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999">Offline</text></svg>',
          { headers: { 'Content-Type': 'image/svg+xml' } }
        );
      }
    });
}

// Handle dynamic page requests
function handleDynamicRequest(request) {
  return caches.match(request)
    .then(response => {
      if (response) {
        // Serve from cache and update in background
        fetch(request)
          .then(fetchResponse => {
            if (fetchResponse.ok) {
              const responseClone = fetchResponse.clone();
              caches.open(DYNAMIC_CACHE)
                .then(cache => {
                  cache.put(request, responseClone);
                });
            }
          })
          .catch(() => {});
        return response;
      }
      
      return fetch(request)
        .then(fetchResponse => {
          if (fetchResponse.ok) {
            const responseClone = fetchResponse.clone();
            caches.open(DYNAMIC_CACHE)
              .then(cache => {
                cache.put(request, responseClone);
              });
          }
          return fetchResponse;
        });
    })
    .catch(() => {
      // Return offline page
      return caches.match('/offline.html')
        .then(response => {
          if (response) {
            return response;
          }
          return new Response(
            '<!DOCTYPE html><html><head><title>Offline - SIMAD</title><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div style="text-align:center;padding:50px;font-family:Arial,sans-serif"><h1>Anda Sedang Offline</h1><p>Silakan periksa koneksi internet Anda dan coba lagi.</p><button onclick="window.location.reload()">Coba Lagi</button></div></body></html>',
            { headers: { 'Content-Type': 'text/html' } }
          );
        });
    });
}

// Handle API requests
function handleApiRequest(request) {
  return fetch(request)
    .then(response => {
      if (response.ok) {
        const responseClone = response.clone();
        caches.open(DYNAMIC_CACHE)
          .then(cache => {
            cache.put(request, responseClone);
          });
      }
      return response;
    })
    .catch(() => {
      return caches.match(request)
        .then(response => {
          if (response) {
            return response;
          }
          return new Response(
            JSON.stringify({ error: 'Offline', message: 'Data tidak tersedia saat offline' }),
            { headers: { 'Content-Type': 'application/json' } }
          );
        });
    });
}

// Check if file is static
function isStaticFile(pathname) {
  const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot'];
  return staticExtensions.some(ext => pathname.endsWith(ext));
}

// Background sync for form submissions
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  }
});

function doBackgroundSync() {
  return new Promise((resolve, reject) => {
    // Handle background sync for offline form submissions
    console.log('Service Worker: Background sync triggered');
    resolve();
  });
}

// Push notification handler
self.addEventListener('push', event => {
  if (event.data) {
    const data = event.data.json();
    const options = {
      body: data.body,
      icon: '/img/icon-192x192.png',
      badge: '/img/icon-72x72.png',
      vibrate: [100, 50, 100],
      data: {
        dateOfArrival: Date.now(),
        primaryKey: data.primaryKey
      },
      actions: [
        {
          action: 'explore',
          title: 'Lihat Detail',
          icon: '/img/icon-96x96.png'
        },
        {
          action: 'close',
          title: 'Tutup',
          icon: '/img/icon-96x96.png'
        }
      ]
    };
    
    event.waitUntil(
      self.registration.showNotification(data.title, options)
    );
  }
});

// Notification click handler
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('/dashboard.php')
    );
  }
});