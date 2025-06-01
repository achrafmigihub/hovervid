<template>
  <VSnackbar
    v-model="showWarning"
    :timeout="-1"
    location="top"
    color="warning"
    variant="tonal"
  >
    <VRow align="center">
      <VCol cols="auto">
        <VIcon icon="tabler-clock" />
      </VCol>
      <VCol>
        <div>
          <strong>Your session is about to expire</strong>
          <div v-if="timeLeft">{{ formattedTimeLeft }} remaining</div>
        </div>
      </VCol>
      <VCol cols="auto">
        <VBtn
          color="primary"
          :loading="isRefreshing"
          @click="refreshSession"
        >
          Extend Session
        </VBtn>
        <VBtn
          color="default"
          variant="text"
          :disabled="isRefreshing"
          @click="dismiss"
        >
          Dismiss
        </VBtn>
      </VCol>
    </VRow>
  </VSnackbar>
</template>

<script setup>
import sessionService from '@/services/sessionService'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useToast } from 'vue-toast-notification'

const $toast = useToast()
const sessionStore = sessionService.getStore()

// Component state
const showWarning = ref(false)
const timeLeft = ref(0)
const isRefreshing = ref(false)
const checkIntervalId = ref(null)

// Computed values
const formattedTimeLeft = computed(() => {
  const minutes = Math.floor(timeLeft.value / 60000)
  const seconds = Math.floor((timeLeft.value % 60000) / 1000)
  
  if (minutes > 0) {
    return `${minutes}m ${seconds}s`
  }
  
  return `${seconds}s`
})

// Methods
const refreshSession = async () => {
  try {
    isRefreshing.ref = true
    await sessionService.refreshSession()
    dismiss()
    $toast.success('Session extended successfully')
  } catch (error) {
    $toast.error('Failed to extend session')
    console.error('Failed to refresh session:', error)
  } finally {
    isRefreshing.value = false
  }
}

const dismiss = () => {
  showWarning.value = false
  sessionService.dismissTimeoutWarning()
}

const checkSession = () => {
  const session = sessionStore.currentSession
  if (!session || !session.expires_at) return
  
  const expiryTime = new Date(session.expires_at).getTime()
  const now = Date.now()
  timeLeft.value = Math.max(0, expiryTime - now)
  
  // Show warning if session is about to expire
  showWarning.value = sessionStore.sessionTimeoutWarning
}

// Watch for timeout warning events
const handleTimeoutWarning = (event) => {
  showWarning.value = true
  timeLeft.value = event.detail.timeUntilExpiry
  
  // Start a countdown timer
  if (checkIntervalId.value) {
    clearInterval(checkIntervalId.value)
  }
  
  checkIntervalId.value = setInterval(() => {
    checkSession()
  }, 1000) // Check every second when the warning is active
}

// Lifecycle hooks
onMounted(() => {
  window.addEventListener('session-timeout-warning', handleTimeoutWarning)
  
  // Initial check
  checkSession()
})

onUnmounted(() => {
  window.removeEventListener('session-timeout-warning', handleTimeoutWarning)
  
  if (checkIntervalId.value) {
    clearInterval(checkIntervalId.value)
  }
})
</script> 
