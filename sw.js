// Service Worker for Event Management System
// Handles push notifications and offline caching
// Compatible with Median.co web-to-app conversion

const CACHE_VERSION = 'v1';
const CACHE_NAME = 'event-management-' + CACHE_VERSION;

// Assets to cache for offline functionality
const ASSETS_TO_CACHE = [
    '/',
    '/student/index.php',
    '/student/hackathons.php',
    '/student/CSS/student_dashboard.css',
    '/student/js/dashboard-manager.js',
    '/asserts/images/logo.png'
];

// Install event - cache assets
self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[Service Worker] Caching assets');
                return cache.addAll(ASSETS_TO_CACHE);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[Service Worker] Activating...');
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((cacheName) => {
                        return cacheName.startsWith('event-management-') && cacheName !== CACHE_NAME;
                    })
                    .map((cacheName) => {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') return;
    
    // Skip chrome-extension and other non-http requests
    if (!event.request.url.startsWith('http')) return;
    
    event.respondWith(
        caches.match(event.request)
            .then((cachedResponse) => {
                // Return cached version if available
                if (cachedResponse) {
                    return cachedResponse;
                }
                
                // Otherwise fetch from network
                return fetch(event.request).then((response) => {
                    // Don't cache if not a success response
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }
                    
                    // Clone the response
                    const responseToCache = response.clone();
                    
                    // Cache images and static assets
                    if (event.request.url.match(/\.(jpg|jpeg|png|gif|svg|css|js)$/)) {
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseToCache);
                        });
                    }
                    
                    return response;
                });
            })
            .catch(() => {
                // Return offline page if available
                return caches.match('/offline.html');
            })
    );
});

// Push event - handle incoming push notifications
self.addEventListener('push', (event) => {
    console.log('[Service Worker] Push received');
    
    let notificationData = {
        title: 'New Notification',
        body: 'You have a new notification',
        icon: '/asserts/images/logo.png',
        badge: '/asserts/images/badge.png',
        data: {
            url: '/student/index.php',
            dateOfArrival: Date.now()
        },
        actions: [
            {
                action: 'view',
                title: 'View',
                icon: '/asserts/images/view-icon.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/asserts/images/close-icon.png'
            }
        ],
        tag: 'notification-' + Date.now(),
        requireInteraction: false,
        vibrate: [200, 100, 200],
        timestamp: Date.now()
    };
    
    // Parse push data if available
    if (event.data) {
        try {
            const data = event.data.json();
            
            notificationData.title = data.title || notificationData.title;
            notificationData.body = data.body || data.message || notificationData.body;
            notificationData.icon = data.icon || notificationData.icon;
            notificationData.badge = data.badge || notificationData.badge;
            notificationData.data.url = data.url || data.link || notificationData.data.url;
            notificationData.tag = data.tag || notificationData.tag;
            
            // Add custom data
            if (data.data) {
                notificationData.data = { ...notificationData.data, ...data.data };
            }
            
        } catch (error) {
            console.error('[Service Worker] Error parsing push data:', error);
        }
    }
    
    // Show notification
    event.waitUntil(
        self.registration.showNotification(notificationData.title, notificationData)
    );
});

// Notification click event - handle user interaction
self.addEventListener('notificationclick', (event) => {
    console.log('[Service Worker] Notification clicked');
    
    event.notification.close();
    
    // Handle action buttons
    if (event.action === 'close') {
        return;
    }
    
    // Get the URL to open
    const urlToOpen = event.notification.data?.url || '/student/index.php';
    
    // Open or focus the URL
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Check if there's already a window open with this URL
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }
                
                // If no window is open, open a new one
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// Notification close event - handle notification dismissal
self.addEventListener('notificationclose', (event) => {
    console.log('[Service Worker] Notification closed', event.notification.tag);
    
    // Optional: Send analytics about notification dismissal
    // fetch('/api/analytics/notification-closed', {
    //     method: 'POST',
    //     body: JSON.stringify({
    //         tag: event.notification.tag,
    //         timestamp: Date.now()
    //     })
    // });
});

// Message event - handle messages from clients
self.addEventListener('message', (event) => {
    console.log('[Service Worker] Message received:', event.data);
    
    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => caches.delete(cacheName))
                );
            })
        );
    }
});

// Sync event - handle background sync (optional)
self.addEventListener('sync', (event) => {
    console.log('[Service Worker] Background sync:', event.tag);
    
    if (event.tag === 'sync-notifications') {
        event.waitUntil(
            // Sync notifications with server
            fetch('/student/ajax/notifications.php?action=sync')
                .then((response) => response.json())
                .then((data) => {
                    console.log('[Service Worker] Notifications synced:', data);
                })
                .catch((error) => {
                    console.error('[Service Worker] Sync failed:', error);
                })
        );
    }
});

console.log('[Service Worker] Loaded successfully');
