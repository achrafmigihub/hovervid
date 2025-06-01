import { useAuthStore } from '@/stores/useAuthStore'

export default [
  { heading: 'Others' },
  // Client Dashboard item - only visible to client users
  {
    title: 'Dashboard',
    icon: { icon: 'bx-home' },
    to: { name: 'client-dashboard' },
    action: 'read',
    subject: 'AclDemo',
    meta: {
      navActiveLink: 'client-dashboard',
    },
    conditionalVisible: () => {
      const authStore = useAuthStore()
      // Wait for auth store to be properly initialized
      return authStore.sessionInitialized && authStore.user?.role === 'client'
    },
  },
  // Contents item - only visible to client users
  {
    title: 'Contents',
    icon: { icon: 'bx-file-blank' },
    to: 'apps-contents',
    action: 'read',
    subject: 'AclDemo',
    conditionalVisible: () => {
      const authStore = useAuthStore()
      // Wait for auth store to be properly initialized
      return authStore.sessionInitialized && authStore.user?.role === 'client'
    },
  },
  {
    title: 'Nav Levels',
    icon: { icon: 'bx-menu' },
    children: [
      {
        title: 'Level 2.1',
        to: null,
      },
      {
        title: 'Level 2.2',
        children: [
          {
            title: 'Level 3.1',
            to: null,
          },
          {
            title: 'Level 3.2',
            to: null,
          },
        ],
      },
    ],
  },
  {
    title: 'Disabled Menu',
    to: null,
    icon: { icon: 'bx-hide' },
    disable: true,
  },
  {
    title: 'Raise Support',
    href: 'https://themeselection.com/support/',
    icon: { icon: 'bx-phone' },
    target: '_blank',
  },
  {
    title: 'Documentation',
    href: 'https://demos.themeselection.com/sneat-vuetify-vuejs-admin-template/documentation/guide/laravel-integration/folder-structure.html',
    icon: { icon: 'bx-file' },
    target: '_blank',
  },
]
