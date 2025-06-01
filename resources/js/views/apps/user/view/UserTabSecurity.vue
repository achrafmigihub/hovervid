<script setup>
import axios from 'axios'
import { ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const userId = ref(route.params.id)

const isNewPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const smsVerificationNumber = ref('+1(968) 819-2547')
const isTwoFactorDialogOpen = ref(false)

// Form data
const formData = ref({
  password: '',
  password_confirmation: ''
})

// Form state
const isSubmitting = ref(false)
const formErrors = ref({})
const successMessage = ref('')
const errorMessage = ref('')
const showAlert = ref(false)
const alertType = ref('success')

// Change user password
const changePassword = async () => {
  try {
    // Reset states
    isSubmitting.value = true
    formErrors.value = {}
    errorMessage.value = ''
    successMessage.value = ''
    showAlert.value = false
    
    let response
    let usedFallback = false
    
    try {
      // First try the API endpoint
      response = await axios.post(`/api/users/${userId.value}/change-password`, formData.value, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
      })
    } catch (apiError) {
      console.warn('API endpoint failed, using direct PHP script as fallback', apiError)
      
      // If API fails, try the direct PHP script
      response = await axios.post(`/direct-change-password.php?id=${userId.value}`, formData.value, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
      })
      usedFallback = true
    }
    
    console.log(`Password change response (${usedFallback ? 'fallback' : 'API'}):`, response.data)
    
    // Handle success
    if (response.data.success) {
      successMessage.value = response.data.message || 'Password changed successfully'
      alertType.value = 'success'
      showAlert.value = true
      
      // Reset form
      formData.value.password = ''
      formData.value.password_confirmation = ''
    } else {
      // Handle unexpected response structure
      throw new Error(response.data.message || 'An error occurred')
    }
  } catch (error) {
    console.error('Password change error:', error)
    
    // Handle validation errors
    if (error.response && error.response.status === 422) {
      formErrors.value = error.response.data.errors || {}
      alertType.value = 'error'
      errorMessage.value = 'Please correct the errors below'
      showAlert.value = true
    } else {
      // Handle other errors
      alertType.value = 'error'
      errorMessage.value = error.response?.data?.message || error.message || 'Failed to change password'
      showAlert.value = true
    }
  } finally {
    isSubmitting.value = false
  }
}

const recentDeviceHeader = [
  {
    title: 'BROWSER',
    key: 'browser',
  },
  {
    title: 'DEVICE',
    key: 'device',
  },
  {
    title: 'LOCATION',
    key: 'location',
  },
  {
    title: 'RECENT ACTIVITY',
    key: 'activity',
  },
]

const recentDevices = [
  {
    browser: ' Chrome on Windows',
    icon: 'bx-bxl-windows',
    color: 'info',
    device: 'HP Spectre 360',
    location: 'Switzerland',
    activity: '10, July 2021 20:07',
  },
  {
    browser: 'Chrome on Android',
    icon: 'bx-bxl-android',
    color: 'success',
    device: 'Oneplus 9 Pro',
    location: 'Dubai',
    activity: '14, July 2021 15:15',
  },
  {
    browser: 'Chrome on macOS',
    icon: 'bx-bxl-apple',
    color: 'secondary',
    device: 'Apple iMac',
    location: 'India',
    activity: '16, July 2021 16:17',
  },
  {
    browser: 'Chrome on iPhone',
    icon: 'bx-mobile-alt',
    color: 'error',
    device: 'iPhone 12x',
    location: 'Australia',
    activity: '13, July 2021 10:10',
  },
]
</script>

<template>
  <VRow>
    <VCol cols="12">
      <!-- 👉 Change password -->
      <VCard title="Change Password">
        <VCardText>
          <VAlert
            v-if="showAlert"
            :type="alertType"
            variant="tonal"
            closable
            class="mb-4"
            @click:close="showAlert = false"
          >
            <template v-if="alertType === 'success'">
              {{ successMessage }}
            </template>
            <template v-else>
              {{ errorMessage }}
            </template>
          </VAlert>

          <VAlert
            closable
            variant="tonal"
            color="warning"
            class="mb-4"
            title="Ensure that these requirements are met"
            text="Minimum 8 characters long, uppercase & symbol"
          />

          <VForm @submit.prevent="changePassword">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="formData.password"
                  label="New Password"
                  placeholder="············"
                  :type="isNewPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isNewPasswordVisible ? 'bx-hide' : 'bx-show'"
                  :error-messages="formErrors.password"
                  @click:append-inner="isNewPasswordVisible = !isNewPasswordVisible"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="formData.password_confirmation"
                  label="Confirm Password"
                  autocomplete="new-password"
                  placeholder="············"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isConfirmPasswordVisible ? 'bx-hide' : 'bx-show'"
                  :error-messages="formErrors.password_confirmation"
                  @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                />
              </VCol>
            </VRow>

            <VBtn
              type="submit"
              class="mt-4"
              :loading="isSubmitting"
              :disabled="isSubmitting"
            >
              Change Password
            </VBtn>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <!-- 👉 Two step verification -->
      <VCard
        title="Two-steps verification"
        subtitle="Keep your account secure with authentication step."
      >
        <VCardText>
          <div class="text-h6 mb-1">
            SMS
          </div>
          <AppTextField placeholder="+1(968) 819-2547">
            <template #append>
              <IconBtn color="secondary">
                <VIcon
                  icon="bx-edit"
                  size="22"
                />
              </IconBtn>
              <IconBtn color="secondary">
                <VIcon
                  icon="bx-user-plus"
                  size="22"
                />
              </IconBtn>
            </template>
          </AppTextField>

          <p class="mb-0 mt-4">
            Two-factor authentication adds an additional layer of security to your account by requiring more than just a password to log in. <a
              href="javascript:void(0)"
              class="text-decoration-none"
            >Learn more</a>.
          </p>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <!-- 👉 Recent devices -->

      <VCard title="Recent devices">
        <VDivider />
        <VDataTable
          :items="recentDevices"
          :headers="recentDeviceHeader"
          hide-default-footer
          class="text-no-wrap"
        >
          <template #item.browser="{ item }">
            <div class="d-flex align-center gap-x-4">
              <VIcon
                :icon="item.icon"
                :color="item.color"
                :size="22"
              />
              <div class="text-body-1 text-high-emphasis">
                {{ item.browser }}
              </div>
            </div>
          </template>
          <!-- TODO Refactor this after vuetify provides proper solution for removing default footer -->
          <template #bottom />
        </VDataTable>
      </VCard>
    </VCol>
  </VRow>

  <!-- 👉 Enable One Time Password Dialog -->
  <TwoFactorAuthDialog
    v-model:is-dialog-visible="isTwoFactorDialogOpen"
    :sms-code="smsVerificationNumber"
  />
</template>
