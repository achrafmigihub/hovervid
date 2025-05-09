<script setup>
import { ref, reactive, onMounted } from 'vue'
import { apiCall } from '@/utils/errorHandler'
import FormInput from '@/components/FormInput.vue'
import AppErrorAlert from '@/components/AppErrorAlert.vue'
import useApiError from '@/composables/useApiError'

// Use the API error composable
const { 
  error, 
  setError, 
  resetError, 
  errorType, 
  isErrorType, 
  errorMessage, 
  validationErrors 
} = useApiError()

// Form data
const formData = reactive({
  name: '',
  email: '',
  phone: '',
  bio: ''
})

// Form state
const isLoading = ref(false)
const isSaved = ref(false)

// Fetch user profile
const fetchProfile = async () => {
  try {
    isLoading.value = true
    resetError()
    
    const response = await apiCall('get', '/api/auth/user-profile')
    
    // Update form data with user profile
    if (response && response.user) {
      const { name, email, phone, bio } = response.user
      
      formData.name = name || ''
      formData.email = email || ''
      formData.phone = phone || ''
      formData.bio = bio || ''
    }
  } catch (err) {
    setError(err)
    
    if (isErrorType('network')) {
      console.log('Network error while fetching profile')
    } else if (isErrorType('authentication')) {
      console.log('Authentication error, user not logged in')
    }
  } finally {
    isLoading.value = false
  }
}

// Update user profile
const updateProfile = async () => {
  try {
    isLoading.value = true
    resetError()
    isSaved.value = false
    
    await apiCall('post', '/api/auth/update-profile', formData)
    
    // Show success state
    isSaved.value = true
    
    // Reset success message after delay
    setTimeout(() => {
      isSaved.value = false
    }, 3000)
  } catch (err) {
    setError(err)
    
    // Log complete response data in development
    if (process.env.NODE_ENV !== 'production') {
      console.log(`Error type: ${errorType.value}`)
      console.log('Validation errors:', validationErrors.value)
    }
  } finally {
    isLoading.value = false
  }
}

// Load profile on mount
onMounted(fetchProfile)
</script>

<template>
  <div class="profile-form">
    <h2 class="text-h4 mb-6">Profile Settings</h2>
    
    <!-- Success message -->
    <VAlert
      v-if="isSaved"
      type="success"
      variant="tonal"
      class="mb-4"
    >
      Profile updated successfully!
    </VAlert>
    
    <!-- Error message -->
    <AppErrorAlert 
      v-if="error" 
      :error="error" 
      :show-details="false"
      class="mb-4"
    />
    
    <form @submit.prevent="updateProfile">
      <!-- Name input -->
      <FormInput
        v-model="formData.name"
        type="text"
        label="Full Name"
        name="name"
        placeholder="Enter your full name"
        :error="error"
        required
        class="mb-4"
      />
      
      <!-- Email input -->
      <FormInput
        v-model="formData.email"
        type="email"
        label="Email"
        name="email"
        placeholder="Enter your email"
        :error="error"
        required
        class="mb-4"
      />
      
      <!-- Phone input -->
      <FormInput
        v-model="formData.phone"
        type="tel"
        label="Phone Number"
        name="phone"
        placeholder="Enter your phone number"
        :error="error"
        class="mb-4"
      />
      
      <!-- Bio input -->
      <FormInput
        v-model="formData.bio"
        type="textarea"
        label="Bio"
        name="bio"
        placeholder="Tell us about yourself"
        :error="error"
        class="mb-6"
      />
      
      <!-- Submit button -->
      <VBtn
        type="submit"
        color="primary"
        :loading="isLoading"
        :disabled="isLoading"
      >
        Save Changes
      </VBtn>
      
      <!-- Reset button -->
      <VBtn
        class="ml-4"
        variant="outlined"
        :disabled="isLoading"
        @click="fetchProfile"
      >
        Reset
      </VBtn>
    </form>
  </div>
</template>

<style scoped>
.profile-form {
  max-width: 650px;
  width: 100%;
  padding: 2rem;
}
</style> 