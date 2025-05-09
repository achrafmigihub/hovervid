import { setupLayouts } from 'virtual:generated-layouts'
import { createRouter, createWebHistory } from 'vue-router/auto'
import { redirects, routes } from './additional-routes'
import { setupGuards } from './guards'

function recursiveLayouts(route) {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])
    
    return route
  }
  
  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }
    
    return { top: 0 }
  },
  extendRoutes: pages => [
    ...redirects,
    ...[
      ...pages,
      ...routes,
    ].map(route => recursiveLayouts(route)),
  ],
})

export { router }
export default function (app) {
  app.use(router)
  
  // Defer auth store initialization until after app is mounted
  // This ensures Pinia is fully initialized
  setTimeout(() => {
    import('@/stores/useAuthStore').then(module => {
      try {
        const authStore = module.useAuthStore()
        console.log('Initializing auth store...')
        authStore.init()
        
        // Setup navigation guards after auth store is initialized
        setupGuards(router)
        
        console.log('Auth store initialized, user:', authStore.user?.role || 'none')
      } catch (error) {
        console.error('Failed to initialize auth store:', error)
      }
    })
  }, 0)
}
