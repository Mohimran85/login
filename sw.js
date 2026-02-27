// Service Worker for Event Management System
// Handles offline caching and PWA support
// Push notifications are handled by OneSignal via Median.co integration
// Compatible with Median.co web-to-app conversion

const CACHE_VERSION = "v3";
const CACHE_NAME = "event-management-" + CACHE_VERSION;

// Assets to cache for offline functionality
const ASSETS_TO_CACHE = [
  "/event_management_system/login/student/index.php",
  "/event_management_system/login/student/hackathons.php",
  "/event_management_system/login/student/my_hackathons.php",
  "/event_management_system/login/manifest.json",
];

// Install event - cache assets
self.addEventListener("install", (event) => {
  console.log("[Service Worker] Installing...");

  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => {
        console.log("[Service Worker] Caching assets");
        // Cache assets one by one to avoid failure on missing files
        return Promise.all(
          ASSETS_TO_CACHE.map((url) => {
            return cache.add(url).catch((error) => {
              console.warn(`[Service Worker] Failed to cache ${url}:`, error);
              // Continue even if one fails
              return Promise.resolve();
            });
          }),
        );
      })
      .then(() => {
        console.log("[Service Worker] Assets cached successfully");
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error("[Service Worker] Cache installation failed:", error);
      }),
  );
});

// Activate event - clean up old caches
self.addEventListener("activate", (event) => {
  console.log("[Service Worker] Activating...");

  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((cacheName) => {
              return (
                cacheName.startsWith("event-management-") &&
                cacheName !== CACHE_NAME
              );
            })
            .map((cacheName) => {
              console.log("[Service Worker] Deleting old cache:", cacheName);
              return caches.delete(cacheName);
            }),
        );
      })
      .then(() => self.clients.claim()),
  );
});

// Fetch event - serve from cache when offline
self.addEventListener("fetch", (event) => {
  // Skip non-GET requests
  if (event.request.method !== "GET") return;

  // Skip chrome-extension and other non-http requests
  if (!event.request.url.startsWith("http")) return;

  event.respondWith(
    caches
      .match(event.request)
      .then((cachedResponse) => {
        // Return cached version if available
        if (cachedResponse) {
          return cachedResponse;
        }

        // Otherwise fetch from network
        return fetch(event.request).then((response) => {
          // Don't cache if not a success response
          if (
            !response ||
            response.status !== 200 ||
            response.type !== "basic"
          ) {
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
        return caches.match("/offline.html");
      }),
  );
});

// Push event handling is delegated to OneSignal via Median.co integration
// Service Worker push listeners are not needed as OneSignal manages notifications
// Keep offline caching and message handling for client communication

// Notification click event - handle user interaction
self.addEventListener("notificationclick", (event) => {
  console.log("[Service Worker] Notification clicked");

  event.notification.close();

  // Handle action buttons
  if (event.action === "close") {
    return;
  }

  // Get the URL to open
  const urlToOpen = event.notification.data?.url || "/student/index.php";

  // Open or focus the URL
  event.waitUntil(
    clients
      .matchAll({ type: "window", includeUncontrolled: true })
      .then((clientList) => {
        // Check if there's already a window open with this URL
        for (let i = 0; i < clientList.length; i++) {
          const client = clientList[i];
          if (client.url === urlToOpen && "focus" in client) {
            return client.focus();
          }
        }

        // If no window is open, open a new one
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      }),
  );
});

// Notification close event - handle notification dismissal
self.addEventListener("notificationclose", (event) => {
  console.log("[Service Worker] Notification closed", event.notification.tag);

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
self.addEventListener("message", (event) => {
  console.log("[Service Worker] Message received:", event.data);

  if (event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }

  if (event.data.type === "CLEAR_CACHE") {
    event.waitUntil(
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => caches.delete(cacheName)),
        );
      }),
    );
  }
});

// Sync event - handle background sync (optional)
self.addEventListener("sync", (event) => {
  console.log("[Service Worker] Background sync:", event.tag);

  if (event.tag === "sync-notifications") {
    event.waitUntil(
      // Sync notifications with server
      fetch("/student/ajax/notifications.php?action=sync")
        .then((response) => response.json())
        .then((data) => {
          console.log("[Service Worker] Notifications synced:", data);
        })
        .catch((error) => {
          console.error("[Service Worker] Sync failed:", error);
        }),
    );
  }
});

console.log("[Service Worker] Loaded successfully");
