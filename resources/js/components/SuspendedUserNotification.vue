<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

// Component state
const isVisible = ref(false)
const isLoggingOut = ref(false)

// Check if user is suspended
const isUserSuspended = computed(() => {
  return authStore.user?.is_suspended || authStore.user?.status === 'suspended'
})

// Check suspension status on mount and when user data changes
onMounted(() => {
  checkSuspensionStatus()
})

// Watch for changes in user data
const checkSuspensionStatus = () => {
  if (isUserSuspended.value) {
    isVisible.value = true
  }
}

// Handle logout
const handleLogout = async () => {
  try {
    isLoggingOut.value = true
    await authStore.logout()
    router.push({ name: 'login' })
  } catch (error) {
    console.error('Error during logout:', error)
  } finally {
    isLoggingOut.value = false
  }
}

// Watch for user changes
authStore.$subscribe((mutation, state) => {
  if (state.user) {
    checkSuspensionStatus()
  }
})
</script>

<template>
  <VDialog
    v-model="isVisible"
    persistent
    max-width="500px"
    :scrim="true"
  >
    <VCard>
      <VCardTitle class="text-h5 py-4 px-6 text-white bg-error">
        <VIcon
          icon="bx-user-minus"
          size="24"
          class="me-2"
        />
        Account Suspended
      </VCardTitle>
      
      <VCardText class="pa-6">
        <div class="text-center">
          <VIcon
            icon="bx-error-circle"
            size="64"
            color="error"
            class="mb-4"
          />
          
          <h6 class="text-h6 mb-4">
            Your account has been suspended
          </h6>
          
          <p class="text-body-1 mb-6">
            Your account access has been temporarily suspended by an administrator. 
            You will not be able to access your dashboard or use any services until your account is reactivated.
          </p>
          
          <p class="text-body-2 mb-6 text-medium-emphasis">
            If you believe this is an error, please contact support for assistance.
          </p>
          
          <VBtn
            color="error"
            size="large"
            :loading="isLoggingOut"
            :disabled="isLoggingOut"
            @click="handleLogout"
          >
            <VIcon
              icon="bx-log-out"
              size="20"
              start
            />
            Logout
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.v-dialog {
  z-index: 9999;
}
</style> 