export default [
  {
    path: '/cors-test',
    name: 'cors-test',
    component: () => import('@/components/CorsTest.vue'),
    meta: {
      layout: 'default',
      title: 'CORS Configuration Test',
      requiresAuth: false
    }
  }
] 