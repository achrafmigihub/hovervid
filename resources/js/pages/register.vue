<script setup>
import { useErrorHandler } from '@/composables/useErrorHandler'
import { useAuthStore } from '@/stores/useAuthStore'
import AuthProvider from '@/views/pages/authentication/AuthProvider.vue'
import authV2RegisterIllustration from '@images/pages/auth-v2-register-illustration.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { VForm } from 'vuetify/components/VForm'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const authStore = useAuthStore()
const router = useRouter()
const { error, validationErrors, isLoading, executeWithErrorHandling } = useErrorHandler()

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  agree_terms: false,
  // Default role is 'client'
  role: 'client'
})

const refVForm = ref()
const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const registrationComplete = ref(false)
const requiresEmailVerification = ref(false)
const formSubmitted = ref(false)

// Handle form submission
const onSubmit = async () => {
  formSubmitted.value = true // Mark form as submitted
  const { valid } = await refVForm.value.validate()
  
  if (valid) {
    try {
      // Clear any previous errors first
      error.value = null
      validationErrors.value = {}
      
      // Register the user
      const result = await authStore.register(form.value)
      
      // Show registration success message
      registrationComplete.value = true
      
      // Check if email verification is required
      if (result?.requires_email_verification) {
        requiresEmailVerification.value = true
        return
      }
      
      // Logout the user since they should log in manually after registration
      await authStore.logout(true)
      
      // Redirect to login page with success message after a short delay
      setTimeout(() => {
        router.replace({ 
          name: 'login',
          query: { registered: 'success' }
        })
      }, 2000)
    } catch (error) {
      // Make sure we're not already showing success
      if (registrationComplete.value) return
      
      // Only process validation errors if we actually have errors
      let hasRealErrors = false
      
      // Handle validation errors - check multiple possible response formats
      if (error.validationErrors && Object.keys(error.validationErrors).length > 0) {
        // Format from errorHandler.js
        validationErrors.value = error.validationErrors
        hasRealErrors = Object.values(error.validationErrors).some(val => val && (val.length > 0 || typeof val === 'string'))
      } else if (error.response?.data?.errors) {
        // Direct response format
        validationErrors.value = error.response.data.errors
        hasRealErrors = Object.values(error.response.data.errors).some(val => val && (val.length > 0 || typeof val === 'string'))
      } else if (error.isValidationError && error.rawResponseData?.errors) {
        // Another possible format
        validationErrors.value = error.rawResponseData.errors
        hasRealErrors = Object.values(error.rawResponseData.errors).some(val => val && (val.length > 0 || typeof val === 'string'))
      }
      
      // Special case: if status is 422 but there are no real validation errors,
      // this may indicate a successful registration with a validation response
      if (error.response?.status === 422 && !hasRealErrors) {
        console.log('Received 422 without validation errors, treating as success')
        registrationComplete.value = true
        return
      }
      
      // Set a user-friendly error message
      if (hasRealErrors && Object.keys(validationErrors.value).length > 0) {
        // Extract only first error message to display at the top
        for (const field in validationErrors.value) {
          const fieldErrors = validationErrors.value[field]
          if (fieldErrors && (fieldErrors.length > 0 || typeof fieldErrors === 'string')) {
            error.value = Array.isArray(fieldErrors) ? fieldErrors[0] : fieldErrors
            break
          }
        }
      } else {
        // Use generic or server error message
        error.value = error.message || authStore.error || 'Registration failed. Please try again.'
      }
    }
  }
}

// Clear specific field validation error
const clearFieldError = (field) => {
  if (formSubmitted.value && validationErrors.value) {
    validationErrors.value[field] = ''
  }
}

// Reset form and error states
const resetForm = () => {
  formSubmitted.value = false
  validationErrors.value = {}
  error.value = null
}

// Reset when component is mounted
onMounted(() => {
  resetForm()
})

// Password confirmation validation
const passwordConfirmationRule = (value) => {
  return value === form.value.password || 'Passwords must match'
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
            :src="authV2RegisterIllustration"
            class="auth-illustration"
          />
        </div>
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
      style="background-color: rgb(var(--v-theme-surface))"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-6"
      >
        <!-- Registration Success Message -->
        <template v-if="registrationComplete">
          <VCardText class="text-center pt-10 pb-10">
            <VIcon
              icon="bx-check-circle"
              color="success"
              size="112"
              class="mb-6"
            />
            <h3 class="text-h3 mb-4">Registration Successful!</h3>
            
            <p v-if="requiresEmailVerification" class="mb-6">
              Please check your email to verify your account before logging in.
            </p>
            <p v-else class="mb-6">
              Your account has been created successfully.
            </p>
            
            <VBtn
              variant="elevated"
              color="primary"
              class="mt-4"
              :to="{ name: 'login' }"
            >
              Go to Login
            </VBtn>
          </VCardText>
        </template>
        
        <!-- Registration Form -->
        <template v-else>
          <VCardText>
            <h4 class="text-h4 mb-1">
              Adventure starts here 🚀
            </h4>
            <p class="mb-0">
              Create your account and start your adventure!
            </p>
          </VCardText>

          <VCardText>
            <!-- Display general error if exists -->
            <VAlert
              v-if="error && formSubmitted && !registrationComplete"
              type="error"
              variant="tonal"
              class="mb-6"
            >
              {{ error }}
            </VAlert>
            
            <!-- Display validation errors -->
            <VAlert
              v-if="Object.keys(validationErrors).length > 0 && formSubmitted && !registrationComplete && Object.values(validationErrors).some(val => val && val.length)"
              type="error"
              variant="tonal"
              class="mb-6"
              closable
              border="start"
              density="compact"
            >
              <div class="d-flex align-center mb-2">
                <VIcon icon="bx-error-circle" class="me-2" />
                <span class="text-subtitle-1 font-weight-bold">Please correct the following issues:</span>
              </div>
              <ul class="mb-0 ps-6">
                <li
                  v-for="(errors, field) in validationErrors"
                  :key="field"
                  v-if="errors && errors.length"
                  class="py-1"
                >
                  <span class="font-weight-medium">{{ field.replace('_', ' ') }}: </span>
                  {{ Array.isArray(errors) ? errors[0] : errors }}
                </li>
              </ul>
            </VAlert>
            
            <VForm 
              ref="refVForm"
              @submit.prevent="onSubmit"
            >
              <VRow>
                <!-- Full Name -->
                <VCol cols="12">
                  <AppTextField
                    v-model="form.name"
                    label="Full Name"
                    placeholder="John Doe"
                    :rules="[requiredValidator]"
                    :error-messages="formSubmitted && validationErrors.name && validationErrors.name.length ? validationErrors.name : ''"
                    autofocus
                    @focus="clearFieldError('name')"
                    @input="clearFieldError('name')"
                  />
                </VCol>

                <!-- Email -->
                <VCol cols="12">
                  <AppTextField
                    v-model="form.email"
                    label="Email"
                    type="email"
                    placeholder="john@example.com"
                    :rules="[requiredValidator, emailValidator]"
                    :error-messages="formSubmitted && validationErrors.email && validationErrors.email.length ? (Array.isArray(validationErrors.email) ? validationErrors.email[0] : validationErrors.email) : ''"
                    @focus="clearFieldError('email')"
                    @input="clearFieldError('email')"
                    error-focused
                  />
                  <div v-if="formSubmitted && validationErrors.email && validationErrors.email.includes('already been taken')" class="text-caption mt-1">
                    <RouterLink
                      class="text-primary"
                      :to="{ name: 'login' }"
                    >
                      This email is already registered. Click here to log in instead.
                    </RouterLink>
                  </div>
                </VCol>

                <!-- Password -->
                <VCol cols="12">
                  <AppTextField
                    v-model="form.password"
                    label="Password"
                    placeholder="············"
                    :type="isPasswordVisible ? 'text' : 'password'"
                    :append-inner-icon="isPasswordVisible ? 'bx-hide' : 'bx-show'"
                    @click:append-inner="isPasswordVisible = !isPasswordVisible"
                    :rules="[requiredValidator, passwordValidator]"
                    :error-messages="formSubmitted && validationErrors.password && validationErrors.password.length ? validationErrors.password : ''"
                    autocomplete="new-password"
                    @focus="clearFieldError('password')"
                    @input="clearFieldError('password')"
                  />
                </VCol>
                
                <!-- Confirm Password -->
                <VCol cols="12">
                  <AppTextField
                    v-model="form.password_confirmation"
                    label="Confirm Password"
                    placeholder="············"
                    :type="isConfirmPasswordVisible ? 'text' : 'password'"
                    :append-inner-icon="isConfirmPasswordVisible ? 'bx-hide' : 'bx-show'"
                    @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                    :rules="[requiredValidator, passwordConfirmationRule]"
                    autocomplete="new-password"
                    :error-messages="formSubmitted && validationErrors.password_confirmation && validationErrors.password_confirmation.length ? validationErrors.password_confirmation : ''"
                    @focus="clearFieldError('password_confirmation')"
                    @input="clearFieldError('password_confirmation')"
                  />
                </VCol>

                <!-- Terms and Conditions -->
                <VCol cols="12">
                  <div class="d-flex align-center">
                    <VCheckbox
                      id="privacy-policy"
                      v-model="form.agree_terms"
                      :rules="[v => !!v || 'You must agree to continue']"
                      :error-messages="formSubmitted ? validationErrors.agree_terms : ''"
                      inline
                      @change="clearFieldError('agree_terms')"
                    />
                    <VLabel
                      for="privacy-policy"
                      style="opacity: 1"
                    >
                      <span class="me-1 text-high-emphasis">I agree to</span>
                      <a
                        href="javascript:void(0)"
                        class="text-primary"
                      >privacy policy & terms</a>
                    </VLabel>
                  </div>
                </VCol>

                <!-- Submit Button -->
                <VCol cols="12">
                  <VBtn
                    block
                    type="submit"
                    :loading="isLoading"
                    :disabled="isLoading"
                  >
                    {{ isLoading ? 'Creating Account...' : 'Sign Up' }}
                  </VBtn>
                </VCol>

                <!-- Login Link -->
                <VCol
                  cols="12"
                  class="text-center text-base"
                >
                  <span class="d-inline-block">Already have an account?</span>
                  <RouterLink
                    class="text-primary ms-1 d-inline-block"
                    :to="{ name: 'login' }"
                  >
                    Sign in instead
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

                <!-- Auth Providers -->
                <VCol
                  cols="12"
                  class="text-center"
                >
                  <AuthProvider />
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </template>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
