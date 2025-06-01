<!-- ❗Errors in the form are set on line 60 -->
<script setup>
import AppErrorAlert from '@/components/AppErrorAlert.vue'
import { ability } from '@/plugins/casl/ability'
import { useAuthStore } from '@/stores/useAuthStore'
import { checkServiceWorkerAuth } from '@/utils/service-worker-setup'
import AuthProvider from '@/views/pages/authentication/AuthProvider.vue'
import authV2LoginIllustration from '@images/pages/auth-v2-login-illustration.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { onMounted } from 'vue'
import { VForm } from 'vuetify/components/VForm'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const isPasswordVisible = ref(false)
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const refVForm = ref()

const credentials = ref({
  email: '',
  password: '',
})

// Display token expiration message if redirected with expired=true
const tokenExpired = computed(() => route.query.expired === 'true')
// Display logout message if redirected with logout=true
const loggedOut = computed(() => route.query.logout === 'true')
// Display registration success message if redirected with registered=success
const registrationSuccess = computed(() => route.query.registered === 'success')
// Track service worker status
const serviceWorkerStatus = ref('checking')

// Track form validation errors
const validationErrors = ref({})

const rememberMe = ref(true) // Default to true for better UX with persistent login

// Function to initialize CASL abilities based on user role
const initializeAbilities = () => {
  if (!authStore.user) return
  
  // Clear existing abilities
  ability.update([])
  
  const role = authStore.user.role
  
  // Set up basic abilities for all users
  const abilities = [
    { action: 'read', subject: 'Auth' },
  ]
  
  // Add admin-specific abilities
  if (role === 'admin') {
    abilities.push(
      { action: 'read', subject: 'AclDemo' },
      { action: 'read', subject: 'all' },
      { action: 'manage', subject: 'all' }
    )
  }
  
  // Add client-specific abilities
  if (role === 'client') {
    abilities.push(
      { action: 'read', subject: 'AclDemo' },
      { action: 'read', subject: 'ClientPages' }
    )
  }
  
  // Update CASL ability instance
  ability.update(abilities)
  
  // Save to cookie for persistence
  const abilityStringified = JSON.stringify(abilities)
  document.cookie = `userAbilityRules=${encodeURIComponent(abilityStringified)}; path=/; max-age=86400`
  
  console.log('CASL abilities initialized for role:', role)
}

// Check service worker authentication on component mount
onMounted(async () => {
  try {
    const isAuthenticated = await checkServiceWorkerAuth()
    serviceWorkerStatus.value = isAuthenticated ? 'authenticated' : 'unauthenticated'
    console.log('Service worker authentication status:', serviceWorkerStatus.value)
    
    // If already authenticated in service worker but not in store, try to restore session
    if (isAuthenticated && !authStore.isAuthenticated) {
      console.log('Service worker has authentication data but auth store does not - attempting to restore session')
      await authStore.init()
      
      // If restoration was successful, redirect to appropriate dashboard
      if (authStore.isAuthenticated) {
        initializeAbilities() // Initialize CASL abilities
        handleSuccessfulLogin()
      }
    }
  } catch (error) {
    console.error('Error checking service worker auth:', error)
    serviceWorkerStatus.value = 'error'
  }
})

const login = async () => {
  try {
    validationErrors.value = {}
    
    // Basic client-side validation before sending to server
    if (!credentials.value.email) {
      validationErrors.value.email = 'Email is required'
      return
    }

    if (!credentials.value.password) {
      validationErrors.value.password = 'Password is required'
      return
    }
    
    // Email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(credentials.value.email)) {
      validationErrors.value.email = 'Please enter a valid email address'
      return
    }
    
    // Password length validation
    if (credentials.value.password.length < 6) {
      validationErrors.value.password = 'Password must be at least 6 characters'
      return
    }
    
    const response = await authStore.login({
      email: credentials.value.email,
      password: credentials.value.password,
      remember: rememberMe.value
    })
    
    // Initialize CASL abilities after login
    initializeAbilities()
    
    handleSuccessfulLogin()
  } catch (error) {
    // Convert technical errors to user-friendly messages
    if (error.isValidationError && error.validationErrors) {
      validationErrors.value = error.validationErrors
    } else if (error.response?.status === 403 && error.response?.data?.message?.includes('suspended')) {
      // Handle account suspension with appropriate message
      authStore.error = {
        message: 'Your account has been suspended. Please contact administration for assistance.',
        stack: null
      }
    } else if (error.response?.status === 401) {
      // Handle authentication failure with clear message
      authStore.error = {
        message: 'The email or password you entered is incorrect. Please try again.',
        stack: null
      }
    } else if (error.message?.includes('Network Error')) {
      // Handle connection issues
      authStore.error = {
        message: 'Connection problem. Please check your internet connection and try again.',
        stack: null
      }
    } else {
      // Generic user-friendly error message
      authStore.error = {
        message: 'We couldn\'t log you in. Please try again or contact support if the problem persists.',
        stack: null
      }
    }
  }
}

const handleSuccessfulLogin = () => {
  // Get normalized role from user object
  const userRole = (authStore.user?.role || '').toLowerCase()
  console.log('User authenticated with role:', userRole)
  
  // Determine redirect path
  let redirectPath
  
  // First check for a 'to' parameter in the URL (where the user was trying to go)
  if (route.query.to) {
    redirectPath = route.query.to
    console.log('Redirecting to requested page:', redirectPath)
  } 
  // Otherwise, redirect based on role
  else if (userRole === 'admin') {
    redirectPath = '/admin/dashboard'
    console.log('Redirecting to admin dashboard')
  } else if (userRole === 'client') {
    redirectPath = '/client/dashboard'
    console.log('Redirecting to client dashboard')
  } else {
    // Fallback for unknown roles
    redirectPath = '/'
    console.log('Unknown role, redirecting to home page')
  }
  
  // Use router.push instead of replace for better history handling
  router.push(redirectPath)
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      login()
  })
}
</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-2">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 pa-8">
        <div class="d-flex align-center justify-center w-100 h-100">
          <VImg
            max-width="700"
            :src="authV2LoginIllustration"
            class="auth-illustration"
          />
        </div>
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-6"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            Welcome to <span class="text-capitalize">{{ themeConfig.app.title }}</span>! 👋🏻
          </h4>
          <p class="mb-0">
            Please sign-in to your account and start the adventure
          </p>
        </VCardText>
        <VCardText>
          <VForm
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <!-- auth store error alert -->
              <VCol 
                v-if="authStore.error" 
                cols="12"
              >
                <AppErrorAlert :error="authStore.error" />
              </VCol>
              
              <!-- token expired alert -->
              <VCol 
                v-if="tokenExpired" 
                cols="12"
              >
                <VAlert
                  color="warning"
                  variant="tonal"
                  closable
                >
                  Your session has expired. Please log in again.
                </VAlert>
              </VCol>

              <!-- logged out alert -->
              <VCol 
                v-if="loggedOut" 
                cols="12"
              >
                <VAlert
                  color="success"
                  variant="tonal"
                  closable
                >
                  You have been successfully logged out.
                </VAlert>
              </VCol>

              <!-- registration success alert -->
              <VCol 
                v-if="registrationSuccess" 
                cols="12"
              >
                <VAlert
                  color="success"
                  variant="tonal"
                  closable
                >
                  Your registration was successful! You can now log in.
                </VAlert>
              </VCol>

              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="credentials.email"
                  label="Email"
                  placeholder="johndoe@email.com"
                  type="email"
                  autofocus
                  :rules="[requiredValidator, emailValidator]"
                  :error-messages="validationErrors.email"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="credentials.password"
                  label="Password"
                  placeholder="············"
                  :rules="[requiredValidator]"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="current-password"
                  :append-inner-icon="isPasswordVisible ? 'bx-hide' : 'bx-show'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                  :error-messages="validationErrors.password"
                />

                <div class="d-flex align-center flex-wrap justify-space-between my-6">
                  <VCheckbox
                    v-model="rememberMe"
                    label="Remember me"
                  />
                  <RouterLink
                    class="text-primary"
                    :to="{ name: 'forgot-password' }"
                  >
                    Forgot Password?
                  </RouterLink>
                </div>

                <VBtn
                  block
                  type="submit"
                  :loading="authStore.isLoading"
                  :disabled="authStore.isLoading"
                >
                  {{ authStore.isLoading ? 'Logging in...' : 'Login' }}
                </VBtn>
              </VCol>

              <!-- create account -->
              <VCol
                cols="12"
                class="text-body-1 text-center"
              >
                <span class="d-inline-block">
                  New on our platform?
                </span>
                <RouterLink
                  class="text-primary ms-1 d-inline-block text-body-1"
                  :to="{ name: 'register' }"
                >
                  Create an account
                </RouterLink>
              </VCol>
              <VCol
                cols="12"
                class="d-flex align-center"
              >
                <VDivider />
                <span class="mx-4">or</span>
                <VDivider />
              </VCol>

              <!-- auth providers -->
              <VCol
                cols="12"
                class="text-center"
              >
                <AuthProvider />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
