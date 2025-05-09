// Service Worker for session persistence
const CACHE_NAME = 'hovervid-cache-v1';
const urlsToCache = [
  '/',
  '/login',
  '/register',
  '/assets/images/logo.png',
  '/assets/css/main.css',
  '/assets/js/app.js'
];

// Session state
let isAuthenticated = false;

// Install event - cache critical assets
self.addEventListener('install', event => {
  console.log('[Service Worker] Installing...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[Service Worker] Caching app shell');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('[Service Worker] Install completed');
        return self.skipWaiting();
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('[Service Worker] Activating...');
  
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('[Service Worker] Clearing old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('[Service Worker] Activate completed');
      return self.clients.claim();
    })
  );
});

// Helper function to determine if a request should be cached
function shouldCache(url) {
  // Don't cache API requests
  if (url.includes('/api/')) return false;
  
  // Cache static assets
  if (url.endsWith('.css') || url.endsWith('.js') || url.endsWith('.png') 
      || url.endsWith('.jpg') || url.endsWith('.svg') || url.endsWith('.ico')) return true;
  
  // Default behavior
  return false;
}

// Fetch event - network first strategy for API, cache first for assets
self.addEventListener('fetch', event => {
  // Skip for non-GET requests or cross-origin requests
  if (event.request.method !== 'GET' || 
      !event.request.url.startsWith(self.location.origin)) {
    return;
  }
  
  const url = new URL(event.request.url);
  
  // For API requests, use network first strategy
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request.clone(), {
        credentials: 'include', // Include credentials for cross-origin requests
        cache: 'no-store' // Don't cache API requests
      })
        .then(response => {
          // Return the response directly to client
          return response;
        })
        .catch(error => {
          console.error('[Service Worker] API fetch failed:', error);
          return new Response(JSON.stringify({ 
            error: 'Network connection lost. Please check your internet connection.',
            details: error.message
          }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
          });
        })
    );
    return;
  }
  
  // For other requests, check cache first
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response; // Return cached response
        }
        
        // Not in cache, fetch from network
        return fetch(event.request.clone())
          .then(networkResponse => {
            // Check if response is valid
            if (!networkResponse || networkResponse.status !== 200) {
              console.log('[Service Worker] Non-200 response:', networkResponse?.status);
              return networkResponse;
            }
            
            try {
              // Clone the response before using it to avoid the "already used" error
              const responseToCache = networkResponse.clone();
              
              // Cache the response if appropriate
              if (shouldCache(url.pathname)) {
                caches.open(CACHE_NAME)
                  .then(cache => {
                    cache.put(event.request, responseToCache)
                      .catch(err => console.error('[Service Worker] Cache put error:', err));
                  })
                  .catch(err => console.error('[Service Worker] Cache open error:', err));
              }
              
              return networkResponse;
            } catch (error) {
              console.error('[Service Worker] Response processing error:', error);
              return networkResponse;
            }
          })
          .catch(error => {
            console.error('[Service Worker] Fetch failed:', error);
            // For navigation requests, return the offline page
            if (event.request.mode === 'navigate') {
              return caches.match('/offline.html');
            }
            
            return new Response('Network error occurred', { 
              status: 503,
              headers: { 'Content-Type': 'text/plain' }
            });
          });
      })
  );
});

// Handle messages from the main app
self.addEventListener('message', event => {
  console.log('[Service Worker] Received message:', event.data);
  
  if (!event.data || !event.data.type) {
    console.warn('[Service Worker] Received invalid message:', event.data);
    return;
  }
  
  if (event.data.type === 'LOGIN') {
    // Update authentication state
    isAuthenticated = true;
    console.log('[Service Worker] Login processed, isAuthenticated:', isAuthenticated);
    
    // Respond to confirm login processed
    if (event.data.clientId) {
      self.clients.get(event.data.clientId)
        .then(client => {
          if (client) {
            client.postMessage({
              type: 'LOGIN_CONFIRMED',
              timestamp: Date.now()
            });
          }
        })
        .catch(err => console.error('[Service Worker] Client get error:', err));
    }
  }
  
  else if (event.data.type === 'LOGOUT') {
    // Update authentication state
    isAuthenticated = false;
    console.log('[Service Worker] Logout processed, isAuthenticated:', isAuthenticated);
    
    // Notify all clients about logout
    self.clients.matchAll().then(clients => {
      clients.forEach(client => {
        client.postMessage({
          type: 'LOGOUT_CONFIRMED',
          timestamp: Date.now()
        });
      });
    });
  }
  
  else if (event.data.type === 'CHECK_AUTH') {
    console.log('[Service Worker] Processing auth check request');
    
    // Handle auth check request using the event.ports[0]
    if (event.ports && event.ports.length > 0) {
      const port = event.ports[0];
      console.log('[Service Worker] Sending auth status via port:', isAuthenticated);
      port.postMessage({
        type: 'AUTH_STATUS',
        isAuthenticated: isAuthenticated,
        timestamp: Date.now()
      });
    } else {
      // Fallback - respond to the client directly
      console.log('[Service Worker] Sending auth status directly:', isAuthenticated);
      event.source.postMessage({
        type: 'AUTH_STATUS',
        isAuthenticated: isAuthenticated,
        timestamp: Date.now()
      });
    }
  }
});

// Listen for push notifications
self.addEventListener('push', event => {
  console.log('[Service Worker] Push received:', event);
  
  const data = event.data.json();
  
  // Check if it's a session expiration notification
  if (data.type === 'SESSION_EXPIRED') {
    isAuthenticated = false;
    
    // Notify all clients about session expiration
    self.clients.matchAll().then(clients => {
      clients.forEach(client => {
        client.postMessage({
          type: 'SESSION_EXPIRED'
        });
      });
    });
  }
  
  // Show notification for other push events
  const notificationOptions = {
    body: data.body || 'New notification',
    icon: data.icon || '/assets/images/notification-icon.png',
    badge: data.badge || '/assets/images/badge-icon.png',
    data: data.data || {}
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'Notification', notificationOptions)
  );
});

console.log('[Service Worker] Script loaded'); 