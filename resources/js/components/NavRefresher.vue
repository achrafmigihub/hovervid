<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import { watch, ref } from 'vue'

// Create a reactive reference to track auth changes
const authStore = useAuthStore()
const refreshKey = ref(0)

// Watch for changes in the authentication state
watch(() => authStore.isAuthenticated, (newVal, oldVal) => {
  if (newVal !== oldVal) {
    // Force navigation components to re-render by changing the key
    refreshKey.value++
    console.log('Auth state changed, refreshing navigation')
  }
})

// Also watch for user role changes
watch(() => authStore.user?.role, (newVal, oldVal) => {
  if (newVal !== oldVal) {
    // Force navigation components to re-render
    refreshKey.value++
    console.log('User role changed, refreshing navigation')
  }
})

// Provide the refresh key to be used in navigation components
</script>

<template>
  <div class="nav-refresher" style="display: none;">
    <!-- This is an invisible component that triggers navigation refresh -->
  </div>
</template> 