<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { apiCall, getErrorType } from '@/utils/errorHandler'
import FormInput from '@/components/FormInput.vue'
import AppErrorAlert from '@/components/AppErrorAlert.vue'

const router = useRouter()

// Form data
const formData = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'client' // Default role
})

// Form state
const isLoading = ref(false)
const error = ref(null)
const successMessage = ref('')

// Submit registration form
const handleRegister = async () => {
  // Reset states
  error.value = null
  successMessage.value = ''
  
  try {
    // Set loading state
    isLoading.value = true
    
    // Call register API endpoint
    const response = await apiCall('post', '/api/auth/register', formData)
    
    // Handle successful registration
    successMessage.value = 'Registration successful! Redirecting to login...'
    
    // Redirect to login after a delay
    setTimeout(() => {
      router.push('/login')
    }, 2000)
    
  } catch (err) {
    // Store error for display
    error.value = err
    
    // Get error type for custom handling
    const errorType = getErrorType(err)
    
    // Handle different error types
    switch(errorType) {
      case 'validation':
        // Validation errors are already displayed in form inputs
        console.log('Validation error detected')
        break
        
      case 'network':
        // Show offline message or retry option
        console.log('Network error detected')
        break
        
      case 'server':
        // Show server error message
        console.log('Server error detected')
        break
        
      default:
        // Generic error handling
        console.log(`Error type: ${errorType}`)
    }
    
    // Log the raw server response in development
    if (process.env.NODE_ENV !== 'production' && err.rawResponseData) {
      console.log('Raw server response:', err.rawResponseData)
    }
    
  } finally {
    // Reset loading state
    isLoading.value = false
  }
}
</script>

<template>
  <div class="register-form">
    <h2 class="text-h4 mb-6">Create Account</h2>
    
    <!-- Success message -->
    <VAlert
      v-if="successMessage"
      type="success"
      variant="tonal"
      class="mb-4"
    >
      {{ successMessage }}
    </VAlert>
    
    <!-- General error message -->
    <AppErrorAlert 
      v-if="error" 
      :error="error" 
      :show-details="false"
      class="mb-4"
    />
    
    <form @submit.prevent="handleRegister">
      <!-- Name input -->
      <FormInput
        v-model="formData.name"
        type="text"
        label="Full Name"
        name="name"
        placeholder="Enter your full name"
        autocomplete="name"
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
        autocomplete="email"
        :error="error"
        required
        class="mb-4"
      />
      
      <!-- Password input -->
      <FormInput
        v-model="formData.password"
        type="password"
        label="Password"
        name="password"
        placeholder="Choose a password"
        autocomplete="new-password"
        :error="error"
        required
        class="mb-4"
      />
      
      <!-- Password confirmation input -->
      <FormInput
        v-model="formData.password_confirmation"
        type="password"
        label="Confirm Password"
        name="password_confirmation"
        placeholder="Confirm your password"
        autocomplete="new-password"
        :error="error"
        required
        class="mb-6"
      />
      
      <!-- Role selection -->
      <VRadioGroup
        v-model="formData.role"
        inline
        class="mb-4"
      >
        <VLabel class="mb-2">Account Type</VLabel>
        <VRadio value="client" label="Client" />
        <VRadio value="admin" label="Administrator" />
      </VRadioGroup>
      
      <!-- Submit button -->
      <VBtn
        type="submit"
        color="primary"
        :loading="isLoading"
        block
        size="large"
      >
        Register
      </VBtn>
      
      <!-- Login link -->
      <div class="text-center mt-4">
        Already have an account?
        <router-link to="/login" class="text-decoration-none ml-2">
          Login
        </router-link>
      </div>
    </form>
  </div>
</template>

<style scoped>
.register-form {
  max-width: 550px;
  width: 100%;
  padding: 2rem;
}
</style> 