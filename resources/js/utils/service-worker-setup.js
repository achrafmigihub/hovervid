import { useAuthStore } from '@/stores/useAuthStore';

/**
 * Service Worker Registration Utility
 * Handles registration and communication with the service worker for authentication persistence
 */
export const registerServiceWorker = async () => {
  if ('serviceWorker' in navigator) {
    try {
      // Make sure to unregister any existing service workers first to prevent conflicts
      const registrations = await navigator.serviceWorker.getRegistrations();
      for (const registration of registrations) {
        await registration.unregister();
      }
      
      // Register a new service worker
      const registration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/',
        updateViaCache: 'none' // Don't use cached version
      });
      
      console.log('Service worker registered:', registration.scope);
      
      // Wait for the service worker to be activated
      if (registration.installing) {
        console.log('Service worker installing');
        const worker = registration.installing;
        
        // Create a promise that resolves when the service worker is activated
        await new Promise((resolve) => {
          worker.addEventListener('statechange', () => {
            if (worker.state === 'activated') {
              console.log('Service worker activated');
              resolve();
            }
          });
        });
      }
      
      setupMessaging();
      return registration;
    } catch (error) {
      console.error('Service worker registration failed:', error);
      return null;
    }
  } else {
    console.warn('Service workers are not supported in this browser');
    return null;
  }
};

/**
 * Set up messaging between the app and service worker
 */
export const setupMessaging = () => {
  if (!navigator.serviceWorker.controller) {
    console.warn('No service worker controller found');
    return;
  }

  // Listen for messages from the service worker
  navigator.serviceWorker.addEventListener('message', (event) => {
    const data = event.data;
    
    if (!data || !data.type) {
      console.warn('Received malformed message from service worker', data);
      return;
    }
    
    console.log('Received message from service worker:', data.type);
    
    if (data.type === 'LOGOUT_CONFIRMED' || data.type === 'SESSION_EXPIRED') {
      try {
        const authStore = useAuthStore();
        
        // If this is a session expiration, show a notification
        if (data.type === 'SESSION_EXPIRED') {
          // Show notification to user
          alert('Your session has expired. Please log in again.');
        }
        
        // Force logout on the client side
        if (authStore.isAuthenticated) {
          authStore.logout(true); // true = silent logout (no API call)
        }
      } catch (error) {
        console.error('Error handling auth message:', error);
      }
    }
    
    else if (data.type === 'LOGIN_CONFIRMED') {
      console.log('Login confirmed by service worker');
    }
  });
};

/**
 * Notify the service worker about login
 */
export const notifyLoginToServiceWorker = () => {
  if (!navigator.serviceWorker.controller) {
    console.warn('Cannot notify service worker about login: No controller');
    return;
  }
  
  try {
    navigator.serviceWorker.controller.postMessage({
      type: 'LOGIN',
      clientId: navigator.serviceWorker.controller.id,
      timestamp: Date.now()
    });
    console.log('Login notification sent to service worker');
  } catch (error) {
    console.error('Failed to notify service worker about login:', error);
  }
};

/**
 * Notify the service worker about logout
 */
export const notifyLogoutToServiceWorker = () => {
  if (!navigator.serviceWorker.controller) {
    console.warn('Cannot notify service worker about logout: No controller');
    return;
  }
  
  try {
    navigator.serviceWorker.controller.postMessage({
      type: 'LOGOUT',
      timestamp: Date.now()
    });
    console.log('Logout notification sent to service worker');
  } catch (error) {
    console.error('Failed to notify service worker about logout:', error);
  }
};

/**
 * Check authentication state in the service worker
 * @returns {Promise<boolean>} True if authenticated in the service worker
 */
export const checkServiceWorkerAuth = async () => {
  if (!navigator.serviceWorker.controller) {
    console.warn('Cannot check auth with service worker: No controller');
    return false;
  }
  
  return new Promise((resolve) => {
    try {
      const messageChannel = new MessageChannel();
      let timeoutId;
      
      // Set up the message handler before sending the message
      messageChannel.port1.onmessage = (event) => {
        if (timeoutId) {
          clearTimeout(timeoutId);
        }
        
        if (event.data && event.data.type === 'AUTH_STATUS') {
          console.log('Received auth status from service worker:', event.data.isAuthenticated);
          resolve(event.data.isAuthenticated);
        } else {
          console.warn('Received unexpected auth response:', event.data);
          resolve(false);
        }
      };
      
      // Send message and port2 to the service worker
      navigator.serviceWorker.controller.postMessage({
        type: 'CHECK_AUTH',
        timestamp: Date.now()
      }, [messageChannel.port2]);
      
      // Timeout after 2 seconds (increased from 1 second)
      timeoutId = setTimeout(() => {
        console.warn('Service worker auth check timed out');
        resolve(false);
      }, 2000);
    } catch (error) {
      console.error('Error checking service worker auth:', error);
      resolve(false);
    }
  });
}; 