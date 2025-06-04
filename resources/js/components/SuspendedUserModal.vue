<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import axios from 'axios'
import { onMounted, onUnmounted, ref, watch } from 'vue'

const authStore = useAuthStore()
const showModal = ref(false)
const checkInterval = ref(null)
const lastCheckTime = ref(null)
const checkCount = ref(0)
const suspendMessage = ref('Your account has been suspended. Please contact administration for assistance.')

// Create custom event for suspension
const SUSPENSION_EVENT = 'user-suspended'

// Watch for changes in auth store user data
watch(() => authStore.user, (newUserData, oldUserData) => {
  if (newUserData && oldUserData) {
    // Check if user went from not suspended to suspended
    const wasSuspended = oldUserData.is_suspended || oldUserData.status === 'suspended'
    const isSuspended = newUserData.is_suspended || newUserData.status === 'suspended'
    
    if (!wasSuspended && isSuspended) {
      showModal.value = true
      suspendMessage.value = 'Your account has been suspended. Please contact administration for assistance.'
      
      // Dispatch event that can be listened for elsewhere
      window.dispatchEvent(new CustomEvent(SUSPENSION_EVENT, { 
        detail: { user: newUserData }
      }))
    }
  }
}, { deep: true })

// Force refresh auth store user data
const refreshUserData = async () => {
  try {
    await authStore.fetchUser()
    return true
  } catch (error) {
    return false
  }
}

// Direct API call to check suspension status
const checkDirectApiSuspension = async () => {
  try {
    // Create a new axios instance without the /api prefix for direct scripts
    const directAxios = axios.create({
      baseURL: window.location.origin,
      withCredentials: true
    })
    
    // Call direct PHP script instead of regular API endpoint with user info
    const response = await directAxios.get('/check-suspension.php', {
      params: { 
        _t: new Date().getTime(),
        updateStatus: true // Signal intent to update user status
      },
      headers: {
        'Cache-Control': 'no-cache, no-store, must-revalidate',
        'Pragma': 'no-cache',
        'Expires': '0',
        'X-Session-Check': 'true', // Add custom header for session verification
        'X-Client-Fingerprint': getClientFingerprint() // Add fingerprint for session tracking
      }
    })
    
    console.log('Suspension check response:', response.data)
    
    if (response.data && response.data.is_suspended) {
      return { 
        suspended: true, 
        message: response.data.message || 'Your account has been suspended. Please contact administration for assistance.'
      }
    } else if (response.data && response.data.status_updated) {
      // If server indicates it updated user status, refresh user data
      await refreshUserData()
    }
    
    return { suspended: false }
  } catch (error) {
    console.error('Error checking suspension status:', error)
    return { suspended: false, error }
  }
}

// Generate a simple browser fingerprint for session tracking
const getClientFingerprint = () => {
  try {
    const components = [
      navigator.userAgent,
      navigator.language,
      new Date().getTimezoneOffset(),
      screen.width + 'x' + screen.height,
      navigator.platform
    ]
    
    return btoa(components.join('|')).substring(0, 32)
  } catch (error) {
    console.error('Error generating fingerprint:', error)
    return 'fingerprint-error'
  }
}

// Check if user is suspended - comprehensive check
const checkSuspendStatus = async () => {
  try {
    checkCount.value++
    lastCheckTime.value = new Date().toLocaleTimeString()
    
    // Only check if the user is authenticated
    if (!authStore.isAuthenticated) {
      return
    }
    
    // 1. First refresh user data from the server
    await refreshUserData()
    
    // 2. Check auth store data
    const userIsSuspendedInStore = authStore.user && 
      (authStore.user.is_suspended || authStore.user.status === 'suspended')
    
    // 3. Check via direct API call
    const apiCheck = await checkDirectApiSuspension()
    
    // Show modal if EITHER check indicates suspension
    if (userIsSuspendedInStore || apiCheck.suspended) {
      // Set message from API if available, otherwise use default
      if (apiCheck.suspended && apiCheck.message) {
        suspendMessage.value = apiCheck.message
      }
      
      showModal.value = true
      
      // Clear interval when suspended to stop further checks
      if (checkInterval.value) {
        clearInterval(checkInterval.value)
        checkInterval.value = null
      }
      
      // Dispatch event that can be listened for elsewhere
      window.dispatchEvent(new CustomEvent(SUSPENSION_EVENT, { 
        detail: { message: suspendMessage.value }
      }))
      
      return true
    }
    
    return false
  } catch (error) {
    return false
  }
}

// Handle logout
const handleLogout = async () => {
  try {
    await authStore.logout()
    // Redirect to login page after logout
    window.location.href = '/login'
  } catch (error) {
    // Force redirect even if logout fails
    window.location.href = '/login'
  }
}

// Start periodic checks when the component is mounted
onMounted(() => {
  // Check immediately on mount
  checkSuspendStatus()
  
  // Then set up interval to check every 15 seconds
  checkInterval.value = setInterval(checkSuspendStatus, 15000)
  
  // Also add event listener for focus/visibility changes to check when the tab becomes active
  window.addEventListener('focus', checkSuspendStatus)
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      checkSuspendStatus()
    }
  })
  
  // Listen for custom event from other components
  window.addEventListener(SUSPENSION_EVENT, (event) => {
    showModal.value = true
    if (event.detail?.message) {
      suspendMessage.value = event.detail.message
    }
  })
})

// Clean up when component is unmounted
onUnmounted(() => {
  if (checkInterval.value) {
    clearInterval(checkInterval.value)
    checkInterval.value = null
  }
  
  window.removeEventListener('focus', checkSuspendStatus)
  document.removeEventListener('visibilitychange', checkSuspendStatus)
  window.removeEventListener(SUSPENSION_EVENT, () => {})
})
</script>

<template>
  <VDialog
    v-model="showModal"
    persistent
    max-width="400px"
  >
    <VCard>
      <VCardTitle class="text-h5 py-4 bg-error text-white">
        Account Suspended
        <!-- No close button since it's persistent -->
      </VCardTitle>
      
      <VCardText class="pa-6">
        <div class="d-flex justify-center mb-4">
          <VIcon
            icon="bx-lock-alt"
            color="error"
            size="36"
          />
        </div>
        
        <p class="text-body-1 mb-6 text-center">
          {{ suspendMessage }}
        </p>
        
        <div class="d-flex justify-center">
          <VBtn
            color="error"
            @click="handleLogout"
          >
            Logout
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
