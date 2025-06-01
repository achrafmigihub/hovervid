import { useAuthStore } from '@/stores/useAuthStore'
import adminDashboard from './admin-dashboard'
import appsAndPages from './apps-and-pages'
import charts from './charts'
import dashboard from './dashboard'
import domainManagement from './domain-management'
import forms from './forms'
import others from './others'
import uiElements from './ui-elements'
import userManagement from './user-management'

// Utility function for safe auth checking in navigation
export const checkAuthCondition = (condition) => {
  try {
    const authStore = useAuthStore()
    
    // Always check if session is initialized first
    if (!authStore.sessionInitialized) {
      return false
    }
    
    // Execute the condition function with the auth store
    return condition(authStore)
  } catch (error) {
    console.error('Error checking auth condition:', error)
    return false
  }
}

// Helper functions for common role checks
export const isAdmin = () => checkAuthCondition(auth => auth.isAdmin)
export const isClient = () => checkAuthCondition(auth => auth.isClient)
export const isAuthenticated = () => checkAuthCondition(auth => auth.isAuthenticated)
export const hasRole = (role) => checkAuthCondition(auth => auth.user?.role === role)

// Export raw navigation items - filtering will be handled by the layout
export default [...adminDashboard, ...domainManagement, ...userManagement, ...dashboard, ...appsAndPages, ...uiElements, ...forms, ...charts, ...others]
