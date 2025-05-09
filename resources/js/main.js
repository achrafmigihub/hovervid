import { createApp } from 'vue'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { registerServiceWorker } from '@/utils/service-worker-setup'

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

// Create vue app
const app = createApp(App)

// Register plugins
registerPlugins(app)

// Mount vue app
app.mount('#app')
