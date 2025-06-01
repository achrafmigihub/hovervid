// Navigation testing utility
import { useAuthStore } from '@/stores/useAuthStore'

export const testNavigationLoading = () => {
  const authStore = useAuthStore()
  
  console.group('🔍 Navigation Loading Test')
  
  console.log('Auth Store State:', {
    isAuthenticated: authStore.isAuthenticated,
    sessionInitialized: authStore.sessionInitialized,
    userRole: authStore.user?.role,
    hasUser: !!authStore.user,
    hasToken: !!authStore.token,
    hasSessionId: !!authStore.sessionId
  })
  
  // Test navigation visibility conditions
  const testNavConditions = () => {
    try {
      // Test admin condition
      const adminVisible = authStore.sessionInitialized && authStore.user?.role === 'admin'
      console.log('Admin nav visible:', adminVisible)
      
      // Test client condition
      const clientVisible = authStore.sessionInitialized && authStore.user?.role === 'client'
      console.log('Client nav visible:', clientVisible)
      
      // Test authenticated condition
      const authVisible = authStore.sessionInitialized && authStore.isAuthenticated
      console.log('Auth nav visible:', authVisible)
      
      return { adminVisible, clientVisible, authVisible }
    } catch (error) {
      console.error('Error testing nav conditions:', error)
      return { error: error.message }
    }
  }
  
  const navConditions = testNavConditions()
  console.log('Navigation Conditions:', navConditions)
  
  console.groupEnd()
  
  return {
    authState: {
      isAuthenticated: authStore.isAuthenticated,
      sessionInitialized: authStore.sessionInitialized,
      userRole: authStore.user?.role,
    },
    navConditions
  }
}

// Monitor navigation state changes
export const monitorNavigationState = () => {
  const authStore = useAuthStore()
  let lastState = null
  
  const checkState = () => {
    const currentState = {
      isAuthenticated: authStore.isAuthenticated,
      sessionInitialized: authStore.sessionInitialized,
      userRole: authStore.user?.role,
      timestamp: new Date().toISOString()
    }
    
    if (JSON.stringify(currentState) !== JSON.stringify(lastState)) {
      console.log('🔄 Navigation state changed:', currentState)
      lastState = currentState
      
      // Dispatch custom event that can be listened to
      window.dispatchEvent(new CustomEvent('navigation-state-changed', {
        detail: currentState
      }))
    }
  }
  
  // Check immediately
  checkState()
  
  // Set up interval to monitor changes
  const interval = setInterval(checkState, 1000)
  
  // Return cleanup function
  return () => clearInterval(interval)
}

// Development helper - only available in dev mode
if (import.meta.env.DEV) {
  window.testNavigation = testNavigationLoading
  window.monitorNavigation = monitorNavigationState
} 
