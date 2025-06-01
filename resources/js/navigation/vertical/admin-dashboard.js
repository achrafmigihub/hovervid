import { useAuthStore } from '@/stores/useAuthStore'

export default [
  {
    title: 'Admin Dashboard',
    icon: { icon: 'bx-desktop' },
    to: { name: 'admin-dashboard' },
    action: 'read',
    subject: 'AclDemo',
    meta: {
      navActiveLink: 'admin-dashboard',
    },
    conditionalVisible: () => {
      try {
        const authStore = useAuthStore()
        
        // Ensure auth store is properly initialized before checking conditions
        if (!authStore.sessionInitialized) {
          return false
        }
        
        // Check if user exists and has admin role
        return authStore.user?.role === 'admin'
      } catch (error) {
        console.error('Error checking admin dashboard visibility:', error)
        return false
      }
    },
  },
] 
