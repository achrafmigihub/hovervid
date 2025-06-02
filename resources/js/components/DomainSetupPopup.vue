<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import axios from 'axios'
import { computed, inject, onMounted, ref, watch } from 'vue'
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

// User data
const user = computed(() => authStore.user)

// Check if user has a domain
const hasDomain = computed(() => {
  return user.value?.domain_id || user.value?.domain
})

// Check if user is a client
const isClient = computed(() => {
  return user.value?.role?.toLowerCase() === 'client'
})

// Check if popup should be shown
const checkAndShowPopup = () => {
  if (isClient.value && !hasDomain.value && authStore.isAuthenticated) {
    showDomainPopup.value = true
  } else {
    showDomainPopup.value = false
  }
}

// Watch for user changes and show popup if needed
watch([user, isClient], () => {
  checkAndShowPopup()
}, { immediate: true })

// Initialize on mount
onMounted(() => {
  checkAndShowPopup()
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
      // Update user data
      await authStore.fetchUser()
      showDomainPopup.value = false
      domainInput.value = ''
      
      // Show success message
      console.log('Domain set successfully:', data.message)
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
