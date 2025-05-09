import { useAuthStore } from '../stores/useAuthStore'

export default function(app) {
  // Auth store initialization is already handled in the router plugin
  // Initializing here as well causes duplicate initialization
  // const authStore = useAuthStore()
  // authStore.init()
  
  console.log('Auth plugin loaded (initialization handled by router plugin)')
}