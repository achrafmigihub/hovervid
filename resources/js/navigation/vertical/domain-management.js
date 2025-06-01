import { useAuthStore } from '@/stores/useAuthStore'

export default [
  {
    title: 'Domain Management',
    icon: { icon: 'bx-globe' },
    to: { name: 'admin-domain-management' },
    action: 'read',
    subject: 'AclDemo',
    meta: {
      navActiveLink: 'admin-domain-management',
    },
    conditionalVisible: () => {
      const authStore = useAuthStore()
      return authStore.isAdmin
    },
  },
] 
