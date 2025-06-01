<script setup>
import { ability } from '@/plugins/casl/ability'
import { useAuthStore } from '@/stores/useAuthStore'
import { nextTick, onMounted, ref, watch } from 'vue'

// Create a reactive reference to track auth changes
const authStore = useAuthStore()
const refreshKey = ref(0)
const isInitializing = ref(false)

// Function to initialize CASL abilities based on user role
const initializeAbilities = async () => {
  if (!authStore.user || isInitializing.value) return
  
  try {
    isInitializing.value = true
    
    // Clear existing abilities
    ability.update([])
    
    const role = authStore.user.role
    
    // Set up basic abilities for all users
    const abilities = [
      { action: 'read', subject: 'Auth' },
    ]
    
    // Add admin-specific abilities
    if (role === 'admin') {
      abilities.push(
        { action: 'read', subject: 'AclDemo' },
        { action: 'read', subject: 'all' },
        { action: 'manage', subject: 'all' }
      )
    }
    
    // Add client-specific abilities
    if (role === 'client') {
      abilities.push(
        { action: 'read', subject: 'AclDemo' },
        { action: 'read', subject: 'ClientPages' }
      )
    }
    
    // Update CASL ability instance
    ability.update(abilities)
    
    // Save to cookie for persistence
    const abilityStringified = JSON.stringify(abilities)
    document.cookie = `userAbilityRules=${encodeURIComponent(abilityStringified)}; path=/; max-age=86400`
    
    console.log('CASL abilities initialized for role:', role)
    
    // Wait for next tick to ensure all Vue reactivity has processed
    await nextTick()
    
    // Force navigation to refresh
    refreshKey.value++
    
    // Emit a custom event that the layout can listen to
    window.dispatchEvent(new CustomEvent('nav-abilities-updated', {
      detail: { role, abilities }
    }))
    
  } catch (error) {
    console.error('Error initializing abilities:', error)
  } finally {
    isInitializing.value = false
  }
}

// Debounced version of initializeAbilities to prevent rapid successive calls
let initializationTimer = null
const debouncedInitializeAbilities = () => {
  if (initializationTimer) {
    clearTimeout(initializationTimer)
  }
  
  initializationTimer = setTimeout(async () => {
    await initializeAbilities()
  }, 100) // 100ms debounce
}

// Watch for changes in the authentication state
watch(() => authStore.isAuthenticated, (newVal, oldVal) => {
  if (newVal !== oldVal) {
    console.log('Auth state changed, refreshing navigation abilities')
    debouncedInitializeAbilities()
  }
})

// Watch for session initialization state changes
watch(() => authStore.sessionInitialized, (newVal, oldVal) => {
  if (newVal !== oldVal && newVal) {
    console.log('Session initialized, refreshing navigation abilities')
    debouncedInitializeAbilities()
  }
})

// Watch for user role changes
watch(() => authStore.user?.role, (newVal, oldVal) => {
  if (newVal !== oldVal && newVal) {
    console.log('User role changed, refreshing navigation abilities')
    debouncedInitializeAbilities()
  }
})

// Watch for user object changes (in case user data is updated)
watch(() => authStore.user, (newUser, oldUser) => {
  // Only trigger if we actually got a user (not cleared)
  if (newUser && newUser !== oldUser && authStore.sessionInitialized) {
    console.log('User data updated, refreshing navigation abilities')
    debouncedInitializeAbilities()
  }
})

// Initialize abilities when component is mounted
onMounted(async () => {
  console.log('NavRefresher mounted')
  
  // If auth is already initialized and we have a user, set up abilities
  if (authStore.isAuthenticated && authStore.sessionInitialized && authStore.user) {
    console.log('Auth already initialized, setting up abilities immediately')
    await initializeAbilities()
  } else {
    console.log('Waiting for auth initialization...')
  }
})

// Cleanup on unmount
onUnmounted(() => {
  if (initializationTimer) {
    clearTimeout(initializationTimer)
  }
})

// Provide the refresh key to be used in navigation components
defineExpose({
  refreshKey,
  initializeAbilities: debouncedInitializeAbilities
})
</script>

<template>
  <div class="nav-refresher" style="display: none;">
    <!-- This is an invisible component that triggers navigation refresh -->
    <!-- Debug info (only visible in development) -->
    <div v-if="$dev" class="dev-info" style="position: fixed; top: 0; right: 0; background: rgba(0,0,0,0.8); color: white; padding: 4px; font-size: 10px; z-index: 9999;">
      <div>Auth: {{ authStore.isAuthenticated ? 'Yes' : 'No' }}</div>
      <div>Session: {{ authStore.sessionInitialized ? 'Yes' : 'No' }}</div>
      <div>Role: {{ authStore.user?.role || 'None' }}</div>
      <div>RefreshKey: {{ refreshKey }}</div>
      <div>Initializing: {{ isInitializing ? 'Yes' : 'No' }}</div>
    </div>
  </div>
</template> 
