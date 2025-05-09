<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { apiCall } from '@/utils/errorHandler'
import FormInput from '@/components/FormInput.vue'
import AppErrorAlert from '@/components/AppErrorAlert.vue'

const router = useRouter()

// Form data
const formData = reactive({
  email: '',
  password: ''
})

// Loading state
const isLoading = ref(false)

// Error state
const error = ref(null)

// Submit login form
const handleLogin = async () => {
  // Reset error state
  error.value = null
  
  // Validate form
  if (!formData.email || !formData.password) {
    error.value = {
      message: 'Please fill in all required fields',
      validationErrors: {}
    }
    
    if (!formData.email) {
      error.value.validationErrors.email = 'Email is required'
    }
    
    if (!formData.password) {
      error.value.validationErrors.password = 'Password is required'
    }
    
    return
  }
  
  try {
    // Set loading state
    isLoading.value = true
    
    // Call login API endpoint
    const response = await apiCall('post', '/api/auth/login', {
      email: formData.email,
      password: formData.password
    })
    
    // Handle successful login
    if (response && response.access_token) {
      // Store token in localStorage or a store
      localStorage.setItem('token', response.access_token)
      
      // Redirect based on user role
      if (response.user && response.user.role === 'admin') {
        router.push('/admin/dashboard')
      } else {
        router.push('/client/dashboard')
      }
    }
  } catch (err) {
    // Store error for display
    error.value = err
    
    // You can handle specific error types differently
    if (err.isAuthError) {
      console.log('Authentication failed')
    }
    
    if (err.isNetworkError) {
      console.log('Network error, check connection')
    }
  } finally {
    // Reset loading state
    isLoading.value = false
  }
}
</script>

<template>
  <div class="login-form">
    <h2 class="text-h4 mb-6">Login</h2>
    
    <!-- Display general error message -->
    <AppErrorAlert 
      v-if="error" 
      :error="error" 
      :show-details="false"
      class="mb-4"
    />
    
    <form @submit.prevent="handleLogin">
      <!-- Email input with field-specific error -->
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
      
      <!-- Password input with field-specific error -->
      <FormInput
        v-model="formData.password"
        type="password"
        label="Password"
        name="password"
        placeholder="Enter your password"
        autocomplete="current-password"
        :error="error"
        required
        class="mb-6"
      />
      
      <!-- Submit button -->
      <VBtn
        type="submit"
        color="primary"
        :loading="isLoading"
        block
        size="large"
      >
        Login
      </VBtn>
      
      <!-- Forgot password -->
      <div class="text-center mt-4">
        <router-link to="/forgot-password" class="text-decoration-none">
          Forgot Password?
        </router-link>
      </div>
    </form>
  </div>
</template>

<style scoped>
.login-form {
  max-width: 450px;
  width: 100%;
  padding: 2rem;
}
</style> 