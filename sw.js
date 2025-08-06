// Service Worker for HRMS PWA
// Version 1.0 - Advanced Features Implementation

const CACHE_NAME = 'hrms-cache-v1.0';
const STATIC_CACHE = 'hrms-static-v1.0';
const DYNAMIC_CACHE = 'hrms-dynamic-v1.0';

// Files to cache for offline functionality
const STATIC_FILES = [
    '/billbook/',
    '/billbook/HRMS/',
    '/billbook/HRMS/index.php',
    '/billbook/HRMS/advanced_features.php',
    '/billbook/layouts/header.php',
    '/billbook/layouts/sidebar.php',
    '/billbook/layouts/footer.php',
    '/billbook/manifest.json',
    // Add essential CSS and JS files
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
];

// Files that should always be fetched from network
const NETWORK_FIRST = [
    '/billbook/db.php',
    '/billbook/HRMS/api/',
    '/billbook/includes/'
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
                console.log('Service Worker: Static files cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker: Failed to cache static files', error);
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
                console.log('Service Worker: Activated successfully');
                return self.clients.claim();
            })
    );
});

// Fetch event - handle requests with caching strategies
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-HTTP requests
    if (!request.url.startsWith('http')) {
        return;
    }
    
    // Handle different types of requests
    if (request.method === 'GET') {
        event.respondWith(handleGetRequest(request));
    } else if (request.method === 'POST') {
        event.respondWith(handlePostRequest(request));
    }
});

// Handle GET requests with appropriate caching strategy
async function handleGetRequest(request) {
    const url = new URL(request.url);
    
    try {
        // Network first for dynamic content
        if (shouldUseNetworkFirst(url.pathname)) {
            return await networkFirstStrategy(request);
        }
        
        // Cache first for static content
        if (shouldUseCacheFirst(url.pathname)) {
            return await cacheFirstStrategy(request);
        }
        
        // Stale while revalidate for regular pages
        return await staleWhileRevalidateStrategy(request);
        
    } catch (error) {
        console.error('Service Worker: Fetch error', error);
        return await getOfflineFallback(request);
    }
}

// Handle POST requests (for forms and API calls)
async function handlePostRequest(request) {
    try {
        // Always try network first for POST requests
        const response = await fetch(request);
        
        // If successful, cache the response if appropriate
        if (response.ok && shouldCacheResponse(request.url)) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
        }
        
        return response;
        
    } catch (error) {
        console.error('Service Worker: POST request failed', error);
        
        // Store failed requests for retry when online
        await storeFailedRequest(request);
        
        // Return offline fallback
        return new Response(
            JSON.stringify({
                error: 'Offline',
                message: 'Request saved for retry when online'
            }),
            {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

// Caching Strategies

// Network First - for dynamic content
async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
        
    } catch (error) {
        const cachedResponse = await caches.match(request);
        return cachedResponse || getOfflineFallback(request);
    }
}

// Cache First - for static assets
async function cacheFirstStrategy(request) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
        
    } catch (error) {
        return getOfflineFallback(request);
    }
}

// Stale While Revalidate - for regular pages
async function staleWhileRevalidateStrategy(request) {
    const cachedResponse = await caches.match(request);
    
    const networkResponsePromise = fetch(request)
        .then(response => {
            if (response.ok) {
                const cache = caches.open(DYNAMIC_CACHE);
                cache.then(c => c.put(request, response.clone()));
            }
            return response;
        })
        .catch(() => null);
    
    return cachedResponse || await networkResponsePromise || getOfflineFallback(request);
}

// Utility Functions

function shouldUseNetworkFirst(pathname) {
    return NETWORK_FIRST.some(pattern => pathname.includes(pattern)) ||
           pathname.includes('.php?') ||
           pathname.includes('api/');
}

function shouldUseCacheFirst(pathname) {
    return pathname.includes('.css') ||
           pathname.includes('.js') ||
           pathname.includes('.png') ||
           pathname.includes('.jpg') ||
           pathname.includes('.svg') ||
           pathname.includes('.ico');
}

function shouldCacheResponse(url) {
    // Don't cache error responses or very large responses
    return !url.includes('error') && !url.includes('logout');
}

async function getOfflineFallback(request) {
    const url = new URL(request.url);
    
    // Return offline page for navigation requests
    if (request.mode === 'navigate') {
        return await caches.match('/billbook/offline.html') ||
               new Response(getOfflineHTML(), {
                   headers: { 'Content-Type': 'text/html' }
               });
    }
    
    // Return offline JSON for API requests
    if (url.pathname.includes('api/')) {
        return new Response(
            JSON.stringify({
                error: 'Offline',
                message: 'No internet connection'
            }),
            {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
    
    // Return generic offline response
    return new Response('Offline', { status: 503 });
}

function getOfflineHTML() {
    return `
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>HRMS - Offline</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-align: center;
            }
            .offline-container {
                max-width: 400px;
                padding: 2rem;
            }
            .offline-icon {
                font-size: 4rem;
                margin-bottom: 1rem;
            }
            .retry-btn {
                background: rgba(255,255,255,0.2);
                border: 2px solid white;
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 5px;
                cursor: pointer;
                margin-top: 1rem;
            }
            .retry-btn:hover {
                background: rgba(255,255,255,0.3);
            }
        </style>
    </head>
    <body>
        <div class="offline-container">
            <div class="offline-icon">📡</div>
            <h1>You're Offline</h1>
            <p>No internet connection detected. Some features may be limited.</p>
            <button class="retry-btn" onclick="window.location.reload()">
                Retry Connection
            </button>
        </div>
    </body>
    </html>
    `;
}

// Background Sync for failed requests
async function storeFailedRequest(request) {
    try {
        const requestData = {
            url: request.url,
            method: request.method,
            headers: Object.fromEntries(request.headers.entries()),
            body: await request.text(),
            timestamp: Date.now()
        };
        
        // Store in IndexedDB for retry when online
        const db = await openDB();
        const transaction = db.transaction(['failed_requests'], 'readwrite');
        const store = transaction.objectStore('failed_requests');
        await store.add(requestData);
        
    } catch (error) {
        console.error('Service Worker: Failed to store request', error);
    }
}

// IndexedDB helper
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('hrms-offline', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains('failed_requests')) {
                const store = db.createObjectStore('failed_requests', {
                    keyPath: 'id',
                    autoIncrement: true
                });
                store.createIndex('timestamp', 'timestamp');
            }
        };
    });
}

// Message handling for communication with main thread
self.addEventListener('message', event => {
    const { type, data } = event.data;
    
    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;
            
        case 'CACHE_UPDATE':
            updateCache(data);
            break;
            
        case 'CLEAR_CACHE':
            clearCache(data.cacheType);
            break;
            
        case 'GET_CACHE_SIZE':
            getCacheSize().then(size => {
                event.ports[0].postMessage({ type: 'CACHE_SIZE', size });
            });
            break;
    }
});

// Cache management functions
async function updateCache(urls) {
    try {
        const cache = await caches.open(DYNAMIC_CACHE);
        await cache.addAll(urls);
        console.log('Service Worker: Cache updated successfully');
    } catch (error) {
        console.error('Service Worker: Cache update failed', error);
    }
}

async function clearCache(cacheType) {
    try {
        if (cacheType === 'all') {
            const cacheNames = await caches.keys();
            await Promise.all(cacheNames.map(name => caches.delete(name)));
        } else {
            await caches.delete(cacheType);
        }
        console.log('Service Worker: Cache cleared successfully');
    } catch (error) {
        console.error('Service Worker: Cache clear failed', error);
    }
}

async function getCacheSize() {
    try {
        const cacheNames = await caches.keys();
        let totalSize = 0;
        
        for (const cacheName of cacheNames) {
            const cache = await caches.open(cacheName);
            const requests = await cache.keys();
            
            for (const request of requests) {
                const response = await cache.match(request);
                if (response) {
                    const blob = await response.blob();
                    totalSize += blob.size;
                }
            }
        }
        
        return totalSize;
    } catch (error) {
        console.error('Service Worker: Failed to calculate cache size', error);
        return 0;
    }
}

// Performance monitoring
self.addEventListener('fetch', event => {
    // Track performance metrics
    const startTime = performance.now();
    
    event.respondWith(
        handleRequest(event.request).then(response => {
            const endTime = performance.now();
            const duration = endTime - startTime;
            
            // Log slow requests for optimization
            if (duration > 1000) {
                console.warn(`Service Worker: Slow request detected: ${event.request.url} (${duration}ms)`);
            }
            
            return response;
        })
    );
});

// Generic request handler
async function handleRequest(request) {
    if (request.method === 'GET') {
        return handleGetRequest(request);
    } else if (request.method === 'POST') {
        return handlePostRequest(request);
    } else {
        return fetch(request);
    }
}

console.log('Service Worker: Loaded successfully');
