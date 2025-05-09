<script setup>
import { ref } from 'vue'
import { useErrorHandler } from '@/composables/useErrorHandler'
import { apiCall } from '@/utils/errorHandler'
import AppErrorAlert from './AppErrorAlert.vue'

const props = defineProps({
  userId: {
    type: [Number, String],
    required: true
  }
})

const emit = defineEmits(['update:success'])

// Form data
const userData = ref({
  name: '',
  email: '',
  phone: '',
  bio: ''
})

// Use our error handling composable
const { 
  error, 
  validationErrors, 
  isLoading, 
  executeWithErrorHandling 
} = useErrorHandler()

// Load user data
const loadUserData = async () => {
  await executeWithErrorHandling(async () => {
    const data = await apiCall('get', `/api/users/${props.userId}`)
    
    if (data) {
      userData.value = {
        name: data.name || '',
        email: data.email || '',
        phone: data.phone || '',
        bio: data.bio || ''
      }
    }
  })
}

// Update user profile
const updateProfile = async () => {
  const success = await executeWithErrorHandling(async () => {
    const result = await apiCall(
      'put', 
      `/api/users/${props.userId}`, 
      userData.value
    )
    
    if (result) {
      emit('update:success', result)
      return true
    }
    return false
  })
  
  return success
}

// Load user data when component is mounted
loadUserData()
</script>

<template>
  <div class="user-profile-form">
    <!-- Show error alert if there's an error -->
    <AppErrorAlert
      v-if="error"
      :error="error"
      class="mb-6"
    />
    
    <VForm @submit.prevent="updateProfile">
      <VRow>
        <!-- Name field -->
        <VCol cols="12" md="6">
          <VTextField
            v-model="userData.name"
            label="Name"
            :error-messages="validationErrors.name"
            placeholder="John Doe"
            required
          />
        </VCol>
        
        <!-- Email field -->
        <VCol cols="12" md="6">
          <VTextField
            v-model="userData.email"
            label="Email"
            :error-messages="validationErrors.email"
            placeholder="john@example.com"
            type="email"
            required
          />
        </VCol>
        
        <!-- Phone field -->
        <VCol cols="12" md="6">
          <VTextField
            v-model="userData.phone"
            label="Phone"
            :error-messages="validationErrors.phone"
            placeholder="+1 123 456 7890"
          />
        </VCol>
        
        <!-- Bio field -->
        <VCol cols="12">
          <VTextarea
            v-model="userData.bio"
            label="Bio"
            :error-messages="validationErrors.bio"
            placeholder="Tell us about yourself"
            rows="4"
          />
        </VCol>
        
        <!-- Submit button -->
        <VCol cols="12" class="d-flex justify-end">
          <VBtn
            type="submit"
            :loading="isLoading"
            :disabled="isLoading"
          >
            {{ isLoading ? 'Saving...' : 'Save Changes' }}
          </VBtn>
        </VCol>
      </VRow>
    </VForm>
  </div>
</template> 