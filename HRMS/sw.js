// HRMS Service Worker
// Provides offline functionality, background sync, and push notifications

const CACHE_NAME = 'hrms-v1.0.0';
const OFFLINE_URL = '/HRMS/offline.html';

// Files to cache for offline functionality
const urlsToCache = [
  '/HRMS/',
  '/HRMS/index.php',
  '/HRMS/executive_dashboard.php',
  '/HRMS/employee_directory.php',
  '/HRMS/advanced_attendance_management.php',
  '/HRMS/advanced_leave_management.php',
  '/HRMS/advanced_performance_review.php',
  '/HRMS/mobile_pwa_manager.php',
  '/HRMS/offline.html',
  
  // CSS and JS files
  '/layouts/header.php',
  '/layouts/sidebar.php',
  '/layouts/footer.php',
  
  // External libraries (CDN fallbacks)
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/chart.js',
  
  // PWA assets
  '/HRMS/assets/icon-192x192.png',
  '/HRMS/assets/icon-512x512.png',
  '/HRMS/manifest.json'
];

// Install event - cache resources
self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Caching files');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('Service Worker: Installation complete');
        self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Installation failed', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating...');
  
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('Service Worker: Activation complete');
      self.clients.claim();
    })
  );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }
  
  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }
  
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version if available
        if (response) {
          console.log('Service Worker: Serving from cache:', event.request.url);
          return response;
        }
        
        // Otherwise fetch from network
        return fetch(event.request)
          .then(response => {
            // Don't cache if not a valid response
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            
            // Clone the response as it can only be consumed once
            const responseToCache = response.clone();
            
            // Add to cache for future offline use
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });
            
            return response;
          })
          .catch(() => {
            // If network fails, show offline page for navigation requests
            if (event.request.mode === 'navigate') {
              return caches.match(OFFLINE_URL);
            }
            
            // For other requests, return a simple offline response
            return new Response('Offline - Content not available', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: new Headers({
                'Content-Type': 'text/plain'
              })
            });
          });
      })
  );
});

// Background sync for offline actions
self.addEventListener('sync', event => {
  console.log('Service Worker: Background sync triggered:', event.tag);
  
  if (event.tag === 'attendance-sync') {
    event.waitUntil(syncAttendanceData());
  } else if (event.tag === 'notification-sync') {
    event.waitUntil(syncNotifications());
  }
});

// Push notification handling
self.addEventListener('push', event => {
  console.log('Service Worker: Push notification received');
  
  let data = {};
  
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { title: 'HRMS Notification', body: event.data.text() };
    }
  }
  
  const options = {
    title: data.title || 'HRMS Notification',
    body: data.body || 'You have a new notification',
    icon: '/HRMS/assets/icon-192x192.png',
    badge: '/HRMS/assets/badge-72x72.png',
    image: data.image,
    tag: data.tag || 'hrms-notification',
    renotify: true,
    requireInteraction: data.urgent || false,
    actions: [
      {
        action: 'view',
        title: 'View',
        icon: '/HRMS/assets/action-view.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss',
        icon: '/HRMS/assets/action-dismiss.png'
      }
    ],
    data: {
      url: data.url || '/HRMS/',
      timestamp: Date.now()
    }
  };
  
  event.waitUntil(
    self.registration.showNotification(options.title, options)
  );
});

// Notification click handling
self.addEventListener('notificationclick', event => {
  console.log('Service Worker: Notification clicked');
  
  const notification = event.notification;
  const action = event.action;
  
  if (action === 'dismiss') {
    notification.close();
    return;
  }
  
  // Default action or 'view' action
  const urlToOpen = notification.data.url || '/HRMS/';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        // Try to focus existing window
        for (let client of clientList) {
          if (client.url.includes('/HRMS/') && 'focus' in client) {
            client.navigate(urlToOpen);
            return client.focus();
          }
        }
        
        // Open new window if no existing one
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
      .then(() => {
        notification.close();
      })
  );
});

// Notification close handling
self.addEventListener('notificationclose', event => {
  console.log('Service Worker: Notification closed');
  
  // Track notification dismissal analytics
  event.waitUntil(
    fetch('/HRMS/api/notification-analytics.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        action: 'dismissed',
        tag: event.notification.tag,
        timestamp: Date.now()
      })
    }).catch(error => {
      console.log('Analytics tracking failed:', error);
    })
  );
});

// Message handling for communication with main thread
self.addEventListener('message', event => {
  console.log('Service Worker: Message received:', event.data);
  
  if (event.data && event.data.type) {
    switch (event.data.type) {
      case 'SKIP_WAITING':
        self.skipWaiting();
        break;
        
      case 'CACHE_URLS':
        cacheUrls(event.data.urls);
        break;
        
      case 'CLEAR_CACHE':
        clearCache();
        break;
        
      case 'GET_CACHE_STATUS':
        getCacheStatus().then(status => {
          event.ports[0].postMessage(status);
        });
        break;
    }
  }
});

// Helper function to sync attendance data
async function syncAttendanceData() {
  try {
    console.log('Service Worker: Syncing attendance data...');
    
    // Get pending attendance data from IndexedDB
    const pendingData = await getPendingAttendanceData();
    
    if (pendingData.length === 0) {
      console.log('Service Worker: No pending attendance data to sync');
      return;
    }
    
    // Sync each pending record
    for (const record of pendingData) {
      try {
        const response = await fetch('/HRMS/api/sync-attendance.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(record)
        });
        
        if (response.ok) {
          await removePendingAttendanceData(record.id);
          console.log('Service Worker: Synced attendance record:', record.id);
        }
      } catch (error) {
        console.error('Service Worker: Failed to sync attendance record:', error);
      }
    }
    
    console.log('Service Worker: Attendance sync complete');
    
  } catch (error) {
    console.error('Service Worker: Attendance sync failed:', error);
  }
}

// Helper function to sync notifications
async function syncNotifications() {
  try {
    console.log('Service Worker: Syncing notifications...');
    
    const response = await fetch('/HRMS/api/get-notifications.php');
    if (response.ok) {
      const notifications = await response.json();
      
      // Store notifications in IndexedDB for offline access
      await storeNotifications(notifications);
      
      console.log('Service Worker: Notifications synced');
    }
  } catch (error) {
    console.error('Service Worker: Notification sync failed:', error);
  }
}

// Helper function to cache additional URLs
async function cacheUrls(urls) {
  try {
    const cache = await caches.open(CACHE_NAME);
    await cache.addAll(urls);
    console.log('Service Worker: Additional URLs cached');
  } catch (error) {
    console.error('Service Worker: Failed to cache additional URLs:', error);
  }
}

// Helper function to clear cache
async function clearCache() {
  try {
    const cacheNames = await caches.keys();
    await Promise.all(
      cacheNames.map(cacheName => caches.delete(cacheName))
    );
    console.log('Service Worker: Cache cleared');
  } catch (error) {
    console.error('Service Worker: Failed to clear cache:', error);
  }
}

// Helper function to get cache status
async function getCacheStatus() {
  try {
    const cache = await caches.open(CACHE_NAME);
    const keys = await cache.keys();
    
    return {
      cacheName: CACHE_NAME,
      cachedUrls: keys.length,
      lastUpdated: Date.now()
    };
  } catch (error) {
    console.error('Service Worker: Failed to get cache status:', error);
    return null;
  }
}

// IndexedDB helpers for offline data storage
async function getPendingAttendanceData() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('HRMS-OfflineDB', 1);
    
    request.onerror = () => reject(request.error);
    
    request.onsuccess = () => {
      const db = request.result;
      const transaction = db.transaction(['pendingAttendance'], 'readonly');
      const store = transaction.objectStore('pendingAttendance');
      const getAllRequest = store.getAll();
      
      getAllRequest.onsuccess = () => resolve(getAllRequest.result);
      getAllRequest.onerror = () => reject(getAllRequest.error);
    };
    
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains('pendingAttendance')) {
        db.createObjectStore('pendingAttendance', { keyPath: 'id', autoIncrement: true });
      }
      if (!db.objectStoreNames.contains('notifications')) {
        db.createObjectStore('notifications', { keyPath: 'id' });
      }
    };
  });
}

async function removePendingAttendanceData(id) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('HRMS-OfflineDB', 1);
    
    request.onsuccess = () => {
      const db = request.result;
      const transaction = db.transaction(['pendingAttendance'], 'readwrite');
      const store = transaction.objectStore('pendingAttendance');
      const deleteRequest = store.delete(id);
      
      deleteRequest.onsuccess = () => resolve();
      deleteRequest.onerror = () => reject(deleteRequest.error);
    };
  });
}

async function storeNotifications(notifications) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('HRMS-OfflineDB', 1);
    
    request.onsuccess = () => {
      const db = request.result;
      const transaction = db.transaction(['notifications'], 'readwrite');
      const store = transaction.objectStore('notifications');
      
      // Clear existing notifications
      store.clear();
      
      // Add new notifications
      notifications.forEach(notification => {
        store.add(notification);
      });
      
      transaction.oncomplete = () => resolve();
      transaction.onerror = () => reject(transaction.error);
    };
  });
}

console.log('Service Worker: Script loaded successfully');
