<script setup>
const shortcuts = [
  {
    icon: 'bx-calendar',
    title: 'Calendar',
    subtitle: 'Appointments',
    to: { name: 'apps-calendar' },
  },
  {
    icon: 'bx-food-menu',
    title: 'Invoice App',
    subtitle: 'Manage Accounts',
    to: { name: 'apps-invoice-list' },
  }
]

// Only include admin-specific shortcuts for admin users
const userData = JSON.parse(localStorage.getItem('userData') || '{}')
const isAdmin = userData?.role?.toLowerCase() === 'admin'

// Admin-only shortcuts
const adminShortcuts = [
  {
    icon: 'bx-user',
    title: 'Users',
    subtitle: 'Manage Users',
    to: { name: 'apps-user-list' },
  },
  {
    icon: 'bx-group',
    title: 'Role Management',
    subtitle: 'Permission',
    to: { name: 'apps-roles' },
  }
]

// Client-only shortcuts
const clientShortcuts = [
  {
    icon: 'bx-pie-chart-alt-2',
    title: 'My Dashboard',
    subtitle: 'Client Overview',
    to: { name: 'client-dashboard' },
  },
  {
    icon: 'bx-cog',
    title: 'Settings',
    subtitle: 'Account Settings',
    to: {
      name: 'pages-account-settings-tab',
      params: { tab: 'account' },
    },
  }
]

// Common shortcuts for all users
const commonShortcuts = [
  {
    icon: 'bx-calendar',
    title: 'Calendar',
    subtitle: 'Appointments',
    to: { name: 'apps-calendar' },
  },
  {
    icon: 'bx-food-menu',
    title: 'Invoice App',
    subtitle: 'Manage Accounts',
    to: { name: 'apps-invoice-list' },
  }
]

// Combine shortcuts based on user role
const allShortcuts = isAdmin 
  ? [...commonShortcuts, ...adminShortcuts] 
  : [...commonShortcuts, ...clientShortcuts]
</script>

<template>
  <Shortcuts :shortcuts="allShortcuts" />
</template>
