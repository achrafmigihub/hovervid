import { useAuthStore } from '@/stores/useAuthStore'

export default [
  {
    title: 'User Management',
    icon: { icon: 'bx-user-plus' },
    children: [
      { title: 'List', to: 'apps-user-list' },
      { 
        title: 'View', 
        to: { 
          name: 'apps-user-view-id', 
          params: { id: '21' }  // Ensure ID is a string
        } 
      },
    ],
    action: 'read',
    subject: 'AclDemo',
    meta: {
      navActiveLink: 'apps-user-list',
    },
    conditionalVisible: () => {
      try {
        const authStore = useAuthStore()
        
        // Ensure auth store is properly initialized before checking conditions
        if (!authStore.sessionInitialized) {
          return false
        }
        
        // Check if user exists and has admin role
        return authStore.isAdmin
      } catch (error) {
        console.error('Error checking user management visibility:', error)
        return false
      }
    },
  },
] 
