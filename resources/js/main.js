import App from '@/App.vue'
import { registerServiceWorker } from '@/utils/service-worker-setup'
import { registerPlugins } from '@core/utils/plugins'
import { createApp } from 'vue'

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'

// Register service worker for offline support and authentication persistence
window.addEventListener('load', () => {
  registerServiceWorker().then(registration => {
    if (registration) {
      console.log('Service Worker registered from main.js');
    }
  });
});

// Development navigation testing utility
if (import.meta.env.DEV) {
  import('@/utils/navigationTest.js').then(({ exposeTestingFunctions }) => {
    exposeTestingFunctions?.()
  }).catch(() => {
    // Silently fail if navigation test file doesn't exist
  })
}

// Create vue app
const app = createApp(App)

// Register plugins
registerPlugins(app)

// Mount vue app
app.mount('#app')
