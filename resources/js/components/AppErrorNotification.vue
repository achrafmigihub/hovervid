<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import AppErrorAlert from './AppErrorAlert.vue'

const error = ref(null)
const showNotification = ref(false)

// Handle global API errors
const handleApiError = (event) => {
  error.value = event.detail
  showNotification.value = true
  
  // Auto-hide after 5 seconds for non-validation errors
  if (!error.value.isValidationError) {
    setTimeout(() => {
      showNotification.value = false
    }, 5000)
  }
}

// Set up and clean up event listeners
onMounted(() => {
  window.addEventListener('api-error', handleApiError)
})

onUnmounted(() => {
  window.removeEventListener('api-error', handleApiError)
})

// Method to close the notification
const closeNotification = () => {
  showNotification.value = false
  error.value = null
}
</script>

<template>
  <VSnackbar
    v-model="showNotification"
    :timeout="-1"
    location="top"
    class="error-notification"
    variant="flat"
  >
    <template #default>
      <AppErrorAlert :error="error" :dismissible="false" />
    </template>
    
    <template #actions>
      <VBtn
        color="white"
        icon="bx-x"
        @click="closeNotification"
      />
    </template>
  </VSnackbar>
</template>

<style scoped>
.error-notification {
  max-width: 600px;
  width: 100%;
}
</style> 