import { defineAsyncRouteComponent } from './guards'

// 👉 Redirects
export const redirects = [
  // ℹ️ We are redirecting to different pages based on role.
  // NOTE: Role is just for UI purposes. ACL is based on abilities.
  {
    path: '/',
    name: 'index',
    redirect: to => {
      // Get user role from auth store
      const userData = JSON.parse(localStorage.getItem('userData'))
      const userRole = userData?.role
      
      if (userRole === 'admin')
        return { path: '/admin/dashboard' }
      if (userRole === 'client')
        return { path: '/client/dashboard' }
      
      // Redirect to login without a 'to' parameter to avoid infinite loops
      return { name: 'login' }
    },
  },
  {
    path: '/pages/user-profile',
    name: 'pages-user-profile',
    redirect: () => ({ name: 'pages-user-profile-tab', params: { tab: 'profile' } }),
  },
  {
    path: '/pages/account-settings',
    name: 'pages-account-settings',
    redirect: () => ({ name: 'pages-account-settings-tab', params: { tab: 'account' } }),
  },
]

export const routes = [
  // CORS Test Route - public route for testing CORS configuration
  {
    path: '/cors-test',
    name: 'cors-test',
    component: defineAsyncRouteComponent(() => import('@/components/CorsTest.vue')),
    meta: {
      public: true,
      title: 'CORS Configuration Test',
    },
  },
  
  // Admin Routes
  {
    path: '/admin/dashboard',
    name: 'admin-dashboard',
    component: defineAsyncRouteComponent(() => import('@/pages/dashboards/crm.vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'admin',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },
  
  // Domain Management Route
  {
    path: '/admin/domain-management',
    name: 'admin-domain-management',
    component: defineAsyncRouteComponent(() => import('@/pages/admin/domain-management.vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'admin',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },
  
  // Admin User Management Routes - Explicitly for admin role only
  {
    path: '/admin/users',
    name: 'admin-users-list',
    component: defineAsyncRouteComponent(() => import('@/pages/apps/user/list/index.vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'admin',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },
  {
    path: '/admin/users/:id',
    name: 'admin-users-view',
    component: defineAsyncRouteComponent(() => import('@/pages/apps/user/view/[id].vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'admin',
      navActiveLink: 'admin-users-list',
    },
  },
  // Block access to general user management routes for non-admin users
  {
    path: '/apps/user/list',
    name: 'apps-user-list',
    component: defineAsyncRouteComponent(() => import('@/pages/apps/user/list/index.vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'admin',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },
  {
    path: '/apps/user/view/:id',
    name: 'apps-user-view-id',
    component: defineAsyncRouteComponent(() => import('@/pages/apps/user/view/[id].vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'admin',
      navActiveLink: 'apps-user-list',
    },
  },
  // Block access to roles management for non-admin users
  {
    path: '/apps/roles',
    name: 'apps-roles',
    component: defineAsyncRouteComponent(() => import('@/pages/apps/roles/index.vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'admin',
    },
  },
  {
    path: '/apps/permissions',
    name: 'apps-permissions',
    component: defineAsyncRouteComponent(() => import('@/pages/apps/permissions/index.vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'admin',
    },
  },
  
  // Client Routes
  {
    path: '/client/dashboard',
    name: 'client-dashboard',
    component: defineAsyncRouteComponent(() => import('@/pages/client/dashboard.vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'client',
      action: 'read',
      subject: 'ClientPages',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },
  
  // Client Contents Page
  {
    path: '/apps/contents',
    name: 'apps-contents',
    component: defineAsyncRouteComponent(() => import('@/pages/apps/contents.vue')),
    meta: {
      requiresAuth: true,
      requiredRole: 'client',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },
  
  // Email filter - example of a route that requires authentication but not a specific role
  {
    path: '/apps/email/filter/:filter',
    name: 'apps-email-filter',
    component: () => import('@/pages/apps/email/index.vue'),
    meta: {
      requiresAuth: true,
      navActiveLink: 'apps-email',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },

  // Email label
  {
    path: '/apps/email/label/:label',
    name: 'apps-email-label',
    component: () => import('@/pages/apps/email/index.vue'),
    meta: {
      requiresAuth: true,
      navActiveLink: 'apps-email',
      layoutWrapperClasses: 'layout-content-height-fixed',
    },
  },
  
  // Dashboards - require authentication but accessible to both admins and clients
  {
    path: '/dashboards/logistics',
    name: 'dashboards-logistics',
    component: defineAsyncRouteComponent(() => import('@/pages/apps/logistics/dashboard.vue')),
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/dashboards/academy',
    name: 'dashboards-academy',
    component: defineAsyncRouteComponent(() => import('@/pages/apps/academy/dashboard.vue')),
    meta: {
      requiresAuth: true,
    },
  },
  {
    path: '/apps/ecommerce/dashboard',
    name: 'apps-ecommerce-dashboard',
    component: defineAsyncRouteComponent(() => import('@/pages/dashboards/ecommerce.vue')),
    meta: {
      requiresAuth: true,
    },
  },
  
  // Public route example
  {
    path: '/about',
    name: 'about',
    component: defineAsyncRouteComponent(() => import('@/pages/[...error].vue')),
    meta: {
      public: true,
    },
  },
  
  // Sessions Management - accessible to authenticated users
  {
    path: '/account/sessions',
    name: 'account-sessions',
    component: defineAsyncRouteComponent(() => import('@/views/account/SessionsManagement.vue')),
    meta: {
      requiresAuth: true,
      title: 'Manage Sessions',
      contentClass: 'account-settings-tab',
    },
  },
]
