<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import axios from 'axios'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

// Component state
const showDomainPopup = ref(false)
const domainInput = ref('')
const isSubmittingDomain = ref(false)
const domainError = ref('')
const isLoggingOut = ref(false)
const checkInterval = ref(null)
const lastCheckTime = ref(null)
const checkCount = ref(0)
const userHasDomain = ref(false) // Track if user currently has domain

// Create custom event for domain status changes
const DOMAIN_STATUS_EVENT = 'domain-status-changed'

// User data
const user = computed(() => authStore.user)

// Check if user is a client
const isClient = computed(() => {
  return user.value?.role?.toLowerCase() === 'client'
})

// Watch for changes in auth store user data
watch(() => authStore.user, (newUserData, oldUserData) => {
  if (newUserData && oldUserData && isClient.value) {
    // Check if user went from needing domain to having domain
    const hadDomain = oldUserData.domain_id || (oldUserData.domain && oldUserData.domain.id)
    const hasDomain = newUserData.domain_id || (newUserData.domain && newUserData.domain.id)
    
    if (!hadDomain && hasDomain) {
      // User just got a domain - hide popup
      showDomainPopup.value = false
      
      // Dispatch event that can be listened for elsewhere
      window.dispatchEvent(new CustomEvent(DOMAIN_STATUS_EVENT, { 
        detail: { user: newUserData, event: 'domain_added' }
      }))
    } else if (hadDomain && !hasDomain) {
      // User lost their domain - show popup
      showDomainPopup.value = true
      
      // Dispatch event that can be listened for elsewhere
      window.dispatchEvent(new CustomEvent(DOMAIN_STATUS_EVENT, { 
        detail: { user: newUserData, event: 'domain_removed' }
      }))
    }
  }
}, { deep: true })

// Force refresh user data from auth store
const refreshUserData = async () => {
  try {
    await authStore.fetchUser()
    return true
  } catch (error) {
    console.error('Error refreshing user data:', error)
    return false
  }
}

// Direct API call to check domain status
const checkDirectApiDomainStatus = async () => {
  try {
    // Call direct PHP script instead of regular API endpoint
    const response = await axios.get('/check-domain-status.php', {
      params: { 
        _t: new Date().getTime()
      },
      headers: {
        'Cache-Control': 'no-cache, no-store, must-revalidate',
        'Pragma': 'no-cache',
        'Expires': '0',
        'X-Session-Check': 'true',
        'X-Client-Fingerprint': getClientFingerprint()
      }
    })
    
    console.log('Domain status check response:', response.data)
    
    if (response.data && response.data.needs_domain) {
      return { 
        needsDomain: true, 
        message: response.data.message || 'Please set up your domain to continue using the dashboard.'
      }
    }
    
    return { needsDomain: false }
  } catch (error) {
    console.error('Error checking domain status:', error)
    return { needsDomain: false, error }
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

// Check if user needs domain - comprehensive check
const checkDomainStatus = async () => {
  try {
    checkCount.value++
    lastCheckTime.value = new Date().toLocaleTimeString()
    
    // Only check if the user is authenticated and is a client
    if (!authStore.isAuthenticated || !isClient.value) {
      showDomainPopup.value = false
      userHasDomain.value = false
      return false
    }
    
    // 1. First refresh user data from the server
    await refreshUserData()
    
    // 2. Check auth store data
    const userNeedsDomainInStore = authStore.user && 
      isClient.value && 
      !authStore.user.domain_id && 
      !(authStore.user.domain && authStore.user.domain.id)
    
    // 3. Check via direct API call for absolute certainty
    const apiCheck = await checkDirectApiDomainStatus()
    
    console.log('Domain status check results:', {
      userNeedsDomainInStore,
      apiNeedsDomain: apiCheck.needsDomain,
      currentPopupState: showDomainPopup.value,
      userHasDomain: userHasDomain.value,
      checkCount: checkCount.value
    })
    
    // Determine if user needs domain
    const needsDomain = userNeedsDomainInStore || apiCheck.needsDomain
    
    // Track domain status changes
    const previousHasDomain = userHasDomain.value
    userHasDomain.value = !needsDomain
    
    // Handle domain status changes
    if (needsDomain) {
      // User needs domain
      if (!showDomainPopup.value) {
        showDomainPopup.value = true
        console.log('🔴 Showing domain popup - user needs domain')
        
        // If user previously had domain but now needs one, they lost their domain
        if (previousHasDomain) {
          console.log('⚠️ DOMAIN REMOVED - User lost their domain!')
          
          // Dispatch event for domain removal
          window.dispatchEvent(new CustomEvent(DOMAIN_STATUS_EVENT, { 
            detail: { 
              message: 'Domain was removed - please set up a new domain',
              event: 'domain_removed',
              user: authStore.user
            }
          }))
        }
      }
      
      // Set fast checking interval (every 10 seconds) when user needs domain
      setCheckingInterval(10000)
      return true
      
    } else {
      // User has domain
      if (showDomainPopup.value) {
        showDomainPopup.value = false
        console.log('🟢 Hiding domain popup - user has domain')
        
        // If user previously needed domain but now has one, they added a domain
        if (!previousHasDomain) {
          console.log('✅ DOMAIN ADDED - User got their domain!')
          
          // Dispatch event for domain addition
          window.dispatchEvent(new CustomEvent(DOMAIN_STATUS_EVENT, { 
            detail: { 
              message: 'Domain setup complete',
              event: 'domain_added',
              user: authStore.user
            }
          }))
        }
      }
      
      // Set fast checking interval (every 5 seconds) when user has domain  
      // This allows very fast detection of admin domain deletions
      setCheckingInterval(5000)
      return false
    }
  } catch (error) {
    console.error('Error checking domain status:', error)
    return false
  }
}

// Helper function to manage checking intervals
const setCheckingInterval = (intervalMs) => {
  // Clear existing interval
  if (checkInterval.value) {
    clearInterval(checkInterval.value)
  }
  
  // Set new interval
  checkInterval.value = setInterval(checkDomainStatus, intervalMs)
  
  const intervalType = intervalMs === 10000 ? 'NEEDS DOMAIN (10s)' : 
                      intervalMs === 5000 ? 'HAS DOMAIN (5s - monitoring deletions)' : 'OTHER'
  console.log(`🔄 Set domain checking interval to ${intervalMs/1000} seconds - ${intervalType}`)
}

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
    // Submit domain to backend using session authentication only (no bearer token)
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

    console.log('Domain submission successful:', data)

    if (data.success) {
      // IMMEDIATELY hide the popup - don't wait for any checks
      showDomainPopup.value = false
      userHasDomain.value = true // Mark that user now has domain
      
      // Clear form data immediately
      domainInput.value = ''
      domainError.value = ''
      
      // Update auth store user data immediately with the response data
      if (authStore.user && data.data?.user?.domain_id) {
        authStore.user.domain_id = data.data.user.domain_id
        if (data.data.domain) {
          authStore.user.domain = data.data.domain
        }
        
        // Ensure role is normalized to lowercase for consistent comparisons
        if (authStore.user.role) {
          authStore.user.role = authStore.user.role.toLowerCase()
        }
        
        // Update localStorage immediately
        localStorage.setItem('userData', JSON.stringify(authStore.user))
        console.log('Auth store updated immediately with domain:', authStore.user.domain_id)
      }
      
      // Show success message
      console.log('Domain set successfully - popup hidden immediately:', data.message)
      
      // Refresh user data in the background for consistency (but don't wait for it)
      refreshUserData().then(() => {
        console.log('Background user data refresh completed')
      }).catch(error => {
        console.error('Background refresh failed:', error)
      })
      
      // Switch to faster checking interval since user now has domain
      setCheckingInterval(5000)
      
      // Dispatch success event
      window.dispatchEvent(new CustomEvent(DOMAIN_STATUS_EVENT, { 
        detail: { 
          message: 'Domain successfully added',
          event: 'domain_added',
          user: authStore.user
        }
      }))
      
    } else {
      domainError.value = data.message || 'Failed to set domain'
    }
  } catch (error) {
    console.error('Error submitting domain:', error)
    
    if (error.response?.data?.message) {
      domainError.value = error.response.data.message
    } else {
      domainError.value = 'An error occurred while setting the domain'
    }
  } finally {
    isSubmittingDomain.value = false
  }
}

// Start periodic checks when the component is mounted
onMounted(async () => {
  console.log('🚀 DomainSetupPopup mounted, initializing domain monitoring...')
  
  // Check immediately on mount to determine initial state
  const needsDomain = await checkDomainStatus()
  
  // The checkDomainStatus function now automatically sets the appropriate interval
  console.log(`📊 Initial domain status: ${needsDomain ? 'NEEDS DOMAIN' : 'HAS DOMAIN'}`)
  
  // Also add event listener for focus/visibility changes to check when the tab becomes active
  window.addEventListener('focus', checkDomainStatus)
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      checkDomainStatus()
    }
  })
  
  // Listen for custom event from other components
  window.addEventListener(DOMAIN_STATUS_EVENT, (event) => {
    console.log('🔔 Domain status event received:', event.detail)
  })
})

// Clean up when component is unmounted
onUnmounted(() => {
  if (checkInterval.value) {
    clearInterval(checkInterval.value)
    checkInterval.value = null
  }
  
  window.removeEventListener('focus', checkDomainStatus)
  document.removeEventListener('visibilitychange', checkDomainStatus)
  window.removeEventListener(DOMAIN_STATUS_EVENT, () => {})
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
