import { useAuthStore } from '@/stores/useAuthStore'
import { canNavigate } from '@layouts/plugins/casl'
import axios from 'axios'

export const setupGuards = router => {
  console.log('Setting up router guards...')
  
  // Check for JWT token expiration
  axios.interceptors.response.use(
    response => response,
    async error => {
      // Handle 401 responses (unauthorized - expired token)
      if (error.response?.status === 401) {
        const authStore = useAuthStore()
        
        // Check if we have a token (user was logged in)
        if (authStore.token) {
          console.log('Received 401 with existing token - attempting refresh')
          try {
            // Try to refresh the token
            await authStore.refreshToken()
            console.log('Token refresh successful')
            
            // If successful, retry the original request
            const config = error.config
            return axios(config)
          } catch (refreshError) {
            console.error('Token refresh failed:', refreshError.message)
            // Token refresh failed, redirect to login
            authStore.clearAuthData()
            
            // Only redirect if we're not already on the login page
            const currentRoute = router.currentRoute.value
            if (currentRoute.name !== 'login') {
              console.log('Redirecting to login page after failed token refresh')
              router.push({ 
                name: 'login', 
                query: { 
                  to: currentRoute.fullPath !== '/' ? currentRoute.path : undefined,
                  expired: 'true'
                } 
              })
            }
          }
        } else {
          // Only log this for non-auth related endpoints
          // Auth endpoints like /session-user are expected to return 401 for guests
          const isAuthEndpoint = error.config?.url?.includes('/auth/') || 
                                 error.config?.url?.includes('/sanctum/')
          
          if (!isAuthEndpoint) {
            console.log('Received 401 without token - unauthorized request to:', error.config?.url)
          }
        }
      }
      
      return Promise.reject(error)
    }
  )

  // Add history state changes listener to handle back/forward navigation
  window.addEventListener('popstate', (event) => {
    const authStore = useAuthStore()
    const currentRoute = router.currentRoute.value
    
    // Check if history was recently cleared (indicates recent logout)
    const historyCleared = localStorage.getItem('historyCleared')
    const historyClearTime = localStorage.getItem('historyClearTime')
    
    if (historyCleared === 'true') {
      const clearTime = historyClearTime ? parseInt(historyClearTime) : 0
      const oneHourAgo = Date.now() - (60 * 60 * 1000)
      
      if (clearTime > oneHourAgo) {
        // History was cleared recently due to logout
        console.log('Detected navigation after history clear (logout), forcing redirect to login')
        event.preventDefault()
        window.location.href = '/login'
        return
      } else {
        // Clear time was more than an hour ago, remove the flags
        localStorage.removeItem('historyCleared')
        localStorage.removeItem('historyClearTime')
      }
    }
    
    // Check if user has explicitly logged out recently
    const userLoggedOut = localStorage.getItem('userLoggedOut')
    const logoutTimestamp = localStorage.getItem('logoutTimestamp')
    
    if (userLoggedOut === 'true') {
      const logoutTime = logoutTimestamp ? parseInt(logoutTimestamp) : 0
      const oneHourAgo = Date.now() - (60 * 60 * 1000)
      
      if (logoutTime > oneHourAgo) {
        // User logged out recently, force them to login page
        console.log('Detected navigation after recent logout, redirecting to login')
        event.preventDefault()
        window.location.href = '/login'
        return
      }
    }
    
    // If user is not authenticated and trying to access a protected route
    if (!authStore.isAuthenticated && currentRoute && 
        (currentRoute.meta.requiresAuth || currentRoute.meta.requiredRole)) {
      console.log('Detected popstate navigation to protected route while logged out')
      event.preventDefault()
      router.push({ name: 'login' })
    }
    
    // Additional security check: if we're accessing any dashboard route without authentication
    if (!authStore.isAuthenticated && currentRoute && 
        (currentRoute.path.includes('/dashboard') || 
         currentRoute.path.includes('/admin') || 
         currentRoute.path.includes('/client'))) {
      console.log('Detected unauthorized access to dashboard route via popstate')
      event.preventDefault()
      window.location.href = '/login'
      return
    }
    
    // If user is authenticated and trying to access login page via back button, redirect to dashboard
    if (authStore.isAuthenticated && currentRoute && currentRoute.name === 'login') {
      console.log('Detected popstate navigation to login while logged in, redirecting to dashboard')
      // Redirect based on role
      redirectAuthenticatedUserToDashboard(authStore)
    }
  })

  // Utility function to redirect authenticated users to their dashboard
  const redirectAuthenticatedUserToDashboard = (authStore) => {
    const userRole = (authStore.user?.role || '').toLowerCase()
    
    if (userRole === 'admin') {
      console.log('Redirecting admin to admin dashboard')
      router.replace({ path: '/admin/dashboard' })
      return true
    } else if (userRole === 'client') {
      console.log('Redirecting client to client dashboard')
      router.replace({ path: '/client/dashboard' })
      return true
    } else {
      console.log(`Unknown role "${userRole}" - redirecting to default route`)
      router.replace({ path: '/' })
      return true
    }
  }

  // Global navigation guards
  router.beforeEach(async (to, from) => {
    const authStore = useAuthStore()
    
    // Debug current auth state
    console.log(`Route guard for: ${from.path} → ${to.path}`, {
      authStatus: authStore.isAuthenticated ? 'authenticated' : 'unauthenticated',
      userRole: authStore.user?.role || 'none',
      isAdmin: authStore.isAdmin,
      isClient: authStore.isClient,
      toMeta: to.meta
    })
    
    // Check if authentication was recently cleared (indicates recent logout)
    const authCleared = sessionStorage.getItem('authenticationCleared')
    const authClearTime = sessionStorage.getItem('authClearTime')
    
    if (authCleared === 'true') {
      const clearTime = authClearTime ? parseInt(authClearTime) : 0
      const fiveMinutesAgo = Date.now() - (5 * 60 * 1000) // 5 minutes
      
      if (clearTime > fiveMinutesAgo) {
        // Authentication was cleared recently due to logout, only allow auth pages
        if (to.name !== 'login' && to.name !== 'register' && to.name !== 'forgot-password') {
          console.log('Authentication was cleared recently (logout), redirecting to login')
          return { name: 'login' }
        }
      } else {
        // Clear time was more than 5 minutes ago, remove the flags
        sessionStorage.removeItem('authenticationCleared')
        sessionStorage.removeItem('authClearTime')
      }
    }
    
    // Check if history was recently cleared (indicates recent logout)
    const historyCleared = localStorage.getItem('historyCleared')
    const historyClearTime = localStorage.getItem('historyClearTime')
    
    if (historyCleared === 'true') {
      const clearTime = historyClearTime ? parseInt(historyClearTime) : 0
      const oneHourAgo = Date.now() - (60 * 60 * 1000)
      
      if (clearTime > oneHourAgo) {
        // History was cleared recently due to logout, only allow auth pages
        if (to.name !== 'login' && to.name !== 'register' && to.name !== 'forgot-password') {
          console.log('History was cleared recently (logout), redirecting to login')
          return { name: 'login' }
        }
      } else {
        // Clear time was more than an hour ago, remove the flags
        localStorage.removeItem('historyCleared')
        localStorage.removeItem('historyClearTime')
      }
    }
    
    // Check if user has explicitly logged out recently
    const userLoggedOut = localStorage.getItem('userLoggedOut')
    const logoutTimestamp = localStorage.getItem('logoutTimestamp')
    
    if (userLoggedOut === 'true') {
      const logoutTime = logoutTimestamp ? parseInt(logoutTimestamp) : 0
      const oneHourAgo = Date.now() - (60 * 60 * 1000)
      
      if (logoutTime > oneHourAgo) {
        // User logged out recently, only allow access to auth pages
        if (to.name !== 'login' && to.name !== 'register' && to.name !== 'forgot-password') {
          console.log('User logged out recently, redirecting to login')
          return { name: 'login' }
        }
      } else {
        // Logout was more than an hour ago, clear the flags
        localStorage.removeItem('userLoggedOut')
        localStorage.removeItem('logoutTimestamp')
      }
    }
    
    // Additional security check: prevent access to any dashboard/admin/client routes if not authenticated
    if (!authStore.isAuthenticated && 
        (to.path.includes('/dashboard') || 
         to.path.includes('/admin') || 
         to.path.includes('/client'))) {
      console.log('Unauthorized access attempt to protected dashboard route')
      return { name: 'login' }
    }
    
    // Additional check for session validity on each navigation
    // This ensures browser back button won't restore invalidated sessions
    if (authStore.token && !authStore.sessionInitialized) {
      console.log('Token exists but session not initialized, attempting to reinitialize')
      authStore.init()
      
      // If still not initialized after init, it means the session is invalid
      if (!authStore.sessionInitialized && (to.meta.requiresAuth || to.meta.requiredRole)) {
        console.log('Session reinitialization failed, redirecting to login')
        return { 
          name: 'login', 
          query: { 
            to: to.fullPath !== '/' ? to.path : undefined,
            expired: 'true'
          } 
        }
      }
    }
    
    // Prevent authenticated users from accessing login page
    // Important: this needs to come before other checks
    if ((to.name === 'login' || to.name === 'register' || to.name === 'forgot-password') && authStore.isAuthenticated) {
      console.log('Authenticated user attempted to access login page, redirecting to dashboard')
      // Redirect to appropriate dashboard based on role
      return redirectAuthenticatedUserToDashboard(authStore)
    }
    
    // Allow access to authentication pages (login, register) if not authenticated
    if ((to.name === 'login' || to.name === 'register' || to.name === 'forgot-password') && !authStore.isAuthenticated) {
      console.log('Allowing unauthenticated access to auth page:', to.name)
      return true
    }
    
    // If route requires auth and user isn't authenticated
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
      console.log('Auth required but user not authenticated - redirecting to login')
      return { 
        name: 'login', 
        query: { 
          to: to.fullPath !== '/' ? to.path : undefined
        } 
      }
    }
    
    // Handle role-based access control
    if (to.meta.requiredRole) {
      // If user is not authenticated at all
      if (!authStore.isAuthenticated) {
        console.log('Role-based route requires auth - redirecting to login')
        return { 
          name: 'login', 
          query: { 
            to: to.fullPath !== '/' ? to.path : undefined
          } 
        }
      }
      
      // Normalize the required role for case-insensitive comparison
      const requiredRole = (to.meta.requiredRole || '').toLowerCase()
      const userRole = (authStore.user?.role || '').toLowerCase()
      
      console.log(`Role check for ${to.path}:`, {
        requiredRole,
        userRole,
        hasRole: userRole === requiredRole
      })
      
      // Check if user has the required role
      if (userRole !== requiredRole) {
        console.log(`User role "${userRole}" doesn't match required "${requiredRole}" - access denied`)
        return { name: 'not-authorized' }
      }
    }
    
    // Check if route is public (accessible without authorization)
    if (to.meta.public) {
      console.log('Allowing access to public route:', to.path)
      return true
    }
    
    // Use existing CASL ability check for fine-grained permissions
    if (!canNavigate(to) && to.matched.length) {
      console.log('CASL ability check failed for route:', to.path)
      
      // Check if it's a role-based route that was already validated
      if (to.meta.requiredRole) {
        const userRole = (authStore.user?.role || '').toLowerCase()
        const requiredRole = (to.meta.requiredRole || '').toLowerCase()
        
        // If roles match, allow navigation despite CASL check
        if (userRole === requiredRole) {
          console.log('Allowing access to role-based route despite CASL check failure')
          return true
        }
      }
      
      return authStore.isAuthenticated
        ? { name: 'not-authorized' }
        : {
            name: 'login',
            query: {
              ...to.query,
              to: to.fullPath !== '/' ? to.path : undefined,
            },
          }
    }
  })
}

// Helper function to enable dynamic imports with route meta handling
export const defineAsyncRouteComponent = (importFn, meta = {}) => {
  const component = () => importFn()
  component.meta = meta
  return component
}
