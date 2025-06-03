<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import axios from 'axios'
import { computed, inject, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

// Get the properly configured API instance
const api = inject('api')

// Get auth store to access logged-in user
const authStore = useAuthStore()

// Get router for navigation
const router = useRouter()

// Component state
const showDomainPopup = ref(false)
const domainInput = ref('')
const isSubmittingDomain = ref(false)
const domainError = ref('')
const isLoggingOut = ref(false)
const periodicCheckInterval = ref(null)
const isInitialLoadComplete = ref(false)
const isRefreshing = ref(false)

// User data
const user = computed(() => authStore.user)

// Check if user has a domain
const hasDomain = computed(() => {
  const userValue = user.value
  if (!userValue) return false
  
  // Check for domain_id or domain relationship object
  const hasDomainId = !!(userValue.domain_id)
  const hasDomainObject = !!(userValue.domain && userValue.domain.id)
  
  return hasDomainId || hasDomainObject
})

// Check if user is a client
const isClient = computed(() => {
  return user.value?.role?.toLowerCase() === 'client'
})

// Force refresh user data and check domain status
const refreshUserAndCheckDomain = async (forceRefresh = false) => {
  if (isRefreshing.value) return // Prevent multiple simultaneous refreshes
  
  isRefreshing.value = true
  try {
    console.log('Refreshing user data...', forceRefresh ? '(forced)' : '(normal)')
    
    // If forcing refresh, clear any cached data first
    if (forceRefresh) {
      // Clear localStorage cache to force fresh data
      localStorage.removeItem('userData')
      authStore.user = null
    }
    
    // Fetch fresh user data from the backend
    await authStore.fetchUser()
    
    // Mark initial load as complete after first successful fetch
    isInitialLoadComplete.value = true
    
    // Wait a bit longer for the computed property to update when forcing refresh
    await new Promise(resolve => setTimeout(resolve, forceRefresh ? 500 : 100))
    
    // Check again after refresh
    checkAndShowPopup()
    
    console.log('User data refreshed successfully')
  } catch (error) {
    console.error('Error refreshing user data:', error)
    // Even on error, mark as complete to prevent indefinite loading
    isInitialLoadComplete.value = true
  } finally {
    isRefreshing.value = false
  }
}

// Manual refresh function for the refresh button
const manualRefresh = async () => {
  console.log('Manual refresh triggered')
  await refreshUserAndCheckDomain(true)
}

// Check if popup should be shown
const checkAndShowPopup = () => {
  // Don't show popup until initial data load is complete
  if (!isInitialLoadComplete.value) {
    console.log('Domain popup check: Initial load not complete, skipping check')
    return
  }
  
  // Don't show popup if auth store is still initializing
  if (!authStore.sessionInitialized) {
    console.log('Domain popup check: Auth store not initialized, skipping check')
    return
  }
  
  const domainCheck = hasDomain.value
  const clientCheck = isClient.value
  const authCheck = authStore.isAuthenticated
  
  // Debug logging to help track domain status
  console.log('Domain popup check:', {
    user: user.value ? {
      id: user.value.id,
      email: user.value.email,
      role: user.value.role,
      domain_id: user.value.domain_id,
      domain: user.value.domain ? {
        id: user.value.domain.id,
        domain: user.value.domain.domain
      } : null
    } : null,
    hasDomain: domainCheck,
    isClient: clientCheck,
    isAuthenticated: authCheck,
    isInitialLoadComplete: isInitialLoadComplete.value,
    sessionInitialized: authStore.sessionInitialized,
    shouldShowPopup: clientCheck && !domainCheck && authCheck
  })
  
  if (isClient.value && !hasDomain.value && authStore.isAuthenticated) {
    showDomainPopup.value = true
  } else {
    showDomainPopup.value = false
  }
}

// Watch for user changes and show popup if needed
watch([user, isClient, isInitialLoadComplete], () => {
  checkAndShowPopup()
}, { immediate: true })

// Setup periodic check when popup is visible
const setupPeriodicCheck = () => {
  // Clear any existing interval
  if (periodicCheckInterval.value) {
    clearInterval(periodicCheckInterval.value)
  }
  
  // Set up new interval to check every 10 seconds when popup is visible (reduced from 30)
  periodicCheckInterval.value = setInterval(async () => {
    if (showDomainPopup.value) {
      console.log('Periodic domain check triggered')
      await refreshUserAndCheckDomain()
    }
  }, 10000) // 10 seconds for faster detection
}

// Clear periodic check
const clearPeriodicCheck = () => {
  if (periodicCheckInterval.value) {
    clearInterval(periodicCheckInterval.value)
    periodicCheckInterval.value = null
  }
}

// Watch for popup visibility changes
watch(showDomainPopup, (newValue) => {
  if (newValue) {
    setupPeriodicCheck()
  } else {
    clearPeriodicCheck()
  }
})

// Initialize on mount
onMounted(async () => {
  // Add a small delay to ensure auth store and router are fully initialized
  await new Promise(resolve => setTimeout(resolve, 500))
  
  // Force refresh user data on mount to ensure we have the latest domain info
  await refreshUserAndCheckDomain()
})

// Handle logout
const handleLogout = async () => {
  isLoggingOut.value = true
  
  try {
    await authStore.logout()
    // Redirect to login page after successful logout
    await router.push({ name: 'login' })
  } catch (error) {
    console.error('Logout error:', error)
  } finally {
    isLoggingOut.value = false
  }
}

// Handle domain submission
const submitDomain = async () => {
  if (!domainInput.value.trim()) {
    domainError.value = 'Please enter a domain name'
    return
  }

  isSubmittingDomain.value = true
  domainError.value = ''

  try {
    // Debug auth state
    console.log('Auth Debug Info:', {
      isAuthenticated: authStore.isAuthenticated,
      hasToken: !!authStore.token,
      token: authStore.token ? `${authStore.token.substring(0, 10)}...` : 'No token',
      user: authStore.user?.email || 'No user',
      userRole: authStore.user?.role || 'No role'
    })

    // Debug the exact URL and request
    console.log('Making API call to:', '/client/set-domain')
    console.log('Request payload:', { domain: domainInput.value.trim() })
    console.log('Request method:', 'post')
    console.log('Base URL from axios:', (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api')
    console.log('Full URL should be:', ((import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api') + '/client/set-domain')

    // Submit domain to backend using session authentication only (no bearer token)
    // Create a new axios instance without auth token for this specific request
    const sessionApi = axios.create({
      baseURL: (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      withCredentials: true // Use session cookies
    })
    
    const response = await sessionApi.post('/client/set-domain', {
      domain: domainInput.value.trim()
    })
    
    const data = response.data

    console.log('API call successful:', data)

    if (data.success) {
      // Force a complete refresh with cache clearing
      await refreshUserAndCheckDomain(true)
      
      // Wait a bit more to ensure data propagates
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      // Clear form data
      showDomainPopup.value = false
      domainInput.value = ''
      
      // Show success message
      console.log('Domain set successfully:', data.message)
      
      // Force one more check after a delay to be absolutely sure
      setTimeout(async () => {
        await refreshUserAndCheckDomain(true)
      }, 2000)
    } else {
      domainError.value = data.message || 'Failed to set domain'
    }
  } catch (error) {
    console.error('Full error object:', error)
    console.error('Error details:', {
      message: error.message,
      status: error.status,
      statusText: error.statusText,
      originalError: error.originalError,
      rawResponseData: error.rawResponseData
    })
    
    // The apiCall utility provides better error messages
    domainError.value = error.message || 'An error occurred while setting the domain'
  } finally {
    isSubmittingDomain.value = false
  }
}

// Close popup (only if user already has a domain)
const closeDomainPopup = () => {
  if (hasDomain.value) {
    showDomainPopup.value = false
  }
}

// Cleanup logic when component is unmounted
onUnmounted(() => {
  clearPeriodicCheck()
})
</script>

<template>
  <!-- Domain Selection Popup -->
  <VDialog
    v-model="showDomainPopup"
    max-width="500"
    persistent
  >
    <VCard>
      <VCardItem class="position-relative">
        <VCardTitle class="text-h5">
          <VIcon
            icon="bx-globe"
            class="me-2"
          />
          Set Your Domain
        </VCardTitle>
        
        <!-- Logout Button -->
        <VBtn
          icon
          size="small"
          variant="text"
          color="error"
          class="position-absolute logout-btn"
          :loading="isLoggingOut"
          @click="handleLogout"
        >
          <VIcon icon="bx-log-out" />
          <VTooltip activator="parent" location="bottom">
            Logout
          </VTooltip>
        </VBtn>
      </VCardItem>

      <VCardText>
        <p class="text-body-1 mb-4">
          To continue using your dashboard, please enter your domain name. This will help us provide you with personalized analytics and plugin management.
        </p>
        
        <p class="text-body-2 text-medium-emphasis mb-4">
          <VIcon icon="bx-info-circle" size="16" class="me-1" />
          If you prefer not to set a domain right now, you can logout using the button above.
        </p>
        
        <!-- Refresh button for manual data refresh -->
        <div class="d-flex justify-end mb-3">
          <VBtn
            size="small"
            variant="outlined"
            color="primary"
            :loading="isRefreshing"
            @click="manualRefresh"
          >
            <VIcon icon="bx-refresh" size="16" class="me-1" />
            Refresh
          </VBtn>
        </div>

        <VTextField
          v-model="domainInput"
          label="Domain Name"
          placeholder="example.com"
          prepend-inner-icon="bx-globe"
          :error-messages="domainError"
          :disabled="isSubmittingDomain"
          @keyup.enter="submitDomain"
        />
      </VCardText>

      <VCardActions class="px-6 pb-6">
        <VSpacer />
        <VBtn
          color="primary"
          :loading="isSubmittingDomain"
          @click="submitDomain"
        >
          Continue
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.v-card {
  transition: transform 0.2s ease-in-out;
}

.v-card:hover {
  transform: translateY(-2px);
}

.logout-btn {
  top: 16px;
  right: 16px;
}
</style> 
