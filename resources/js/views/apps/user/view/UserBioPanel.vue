<script setup>
import axios from 'axios'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  userData: {
    type: Object,
    required: true,
  },
})

// Dialog state
const isDialogVisible = ref(false)

// Form state
const isSubmitting = ref(false)
const formErrors = ref({})
const alertMessage = ref('')
const alertType = ref('success')
const showAlert = ref(false)

// Form data
const formData = ref({
  name: '',
  email: '',
  role: '',
  status: '',
  plan: null
})

// Add suspend confirmation dialog state
const isSuspendDialogVisible = ref(false)
const isSuspending = ref(false)
const suspendAlertMessage = ref('')
const suspendAlertType = ref('success')
const showSuspendAlert = ref(false)

// Initialize form data when dialog opens
const openEditDialog = () => {
  formData.value = {
    name: userName.value,
    email: props.userData.email || '',
    role: props.userData.role || 'client',
    status: props.userData.status || 'inactive',
    plan: props.userData.plan?.name || 'Basic'
  }
  
  // Reset form state
  formErrors.value = {}
  showAlert.value = false
  
  isDialogVisible.value = true
}

// Handle form submission
const handleSubmit = async () => {
  try {
    // Set submitting state
    isSubmitting.value = true
    formErrors.value = {}
    
    console.log('Submitting form data:', formData.value)
    
    // Make API request to update user using the direct PHP endpoint
    const response = await axios.post(`/direct-update-user.php?id=${props.userData.id}`, formData.value, {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      }
    })
    
    console.log('API response:', response.data)
    
    // Check if the response indicates success
    if (response.data && response.data.success) {
      // Handle success
      alertType.value = 'success'
      alertMessage.value = response.data.message || 'User information updated successfully'
      showAlert.value = true
      
      // Emit update event to parent component to refresh user data
      setTimeout(() => {
        isDialogVisible.value = false
        
        // Force reload the page to show updated information
        window.location.reload()
      }, 1500)
    } else {
      // Handle unexpected success response format
      console.error('Unexpected response format:', response.data)
      throw new Error(response.data?.message || 'Unexpected response format')
    }
  } catch (error) {
    console.error('Full error object:', error)
    
    // Handle validation errors
    if (error.response && error.response.status === 422) {
      formErrors.value = error.response.data.errors || {}
      alertType.value = 'error'
      alertMessage.value = 'Please correct the errors below'
      showAlert.value = true
    } else if (error.response && error.response.data && !error.response.data.success) {
      // Handle direct API errors
      console.error('API error response:', error.response.data)
      alertType.value = 'error'
      alertMessage.value = error.response.data.message || 'An error occurred while updating user information'
      formErrors.value = error.response.data.errors || {}
      showAlert.value = true
      
      if (error.response.data.debug) {
        console.error('Debug info:', error.response.data.debug)
      }
    } else {
      // Handle other errors
      alertType.value = 'error'
      const errorMsg = error.response?.data?.message || error.message || 'An error occurred while updating user information'
      alertMessage.value = errorMsg
      showAlert.value = true
      console.error('Error updating user:', errorMsg)
      
      if (error.stack) {
        console.error('Error stack:', error.stack)
      }
    }
  } finally {
    isSubmitting.value = false
  }
}

// Debug output to console
console.log('UserBioPanel received userData:', props.userData)

// Function to generate avatar text from user name
const avatarText = name => {
  if (!name || typeof name !== 'string')
    return 'UN'
  
  const nameArray = name.split(' ')
  const initials = nameArray.map(word => word.charAt(0).toUpperCase()).join('')
  return initials || 'UN'
}

// Function to format price
const formatPrice = (price) => {
  if (!price && price !== 0)
    return 'N/A'
  return `$${parseFloat(price).toFixed(2)}`
}

// Determine status color
const getStatusColor = (status) => {
  const colors = {
    'active': 'success',
    'inactive': 'error',
    'pending': 'warning',
    'banned': 'error',
    'suspended': 'error',
  }
  
  return colors[status] || 'primary'
}

// Compute if user is suspended (based on both status or is_suspended flag)
const isUserSuspended = computed(() => {
  return props.userData.is_suspended || props.userData.status === 'suspended'
})

// Compute the effective status to display
const effectiveStatus = computed(() => {
  if (isUserSuspended.value) {
    return 'suspended'
  }
  return props.userData.status || 'inactive'
})

// Determine role icon
const getRoleIcon = (role) => {
  const icons = {
    'admin': 'bx-crown',
    'client': 'bx-user',
  }
  
  return icons[role] || 'bx-user'
}

// Compute the user's name
const userName = computed(() => {
  // Try different formats that could be in the data
  if (props.userData.name) return props.userData.name
  if (props.userData.fullName) return props.userData.fullName
  
  // If we have first name and last name
  if (props.userData.first_name && props.userData.last_name) {
    return `${props.userData.first_name} ${props.userData.last_name}`
  }
  
  // If we have firstName and lastName
  if (props.userData.firstName && props.userData.lastName) {
    return `${props.userData.firstName} ${props.userData.lastName}`
  }
  
  return 'Unknown User'
})

// Debug logs for watching props changes
watch(() => props.userData, (newVal) => {
  console.log('UserBioPanel userData changed:', newVal)
}, { deep: true })

const profileSections = [
  {
    title: 'Account Details',
    icon: 'bx-user',
  },
  {
    title: 'Personal Info',
    icon: 'bx-bookmark',
  },
  {
    title: 'Language and Region',
    icon: 'bx-globe',
  },
  {
    title: 'Email',
    icon: 'bx-envelope',
  },
]

// Available plans
const availablePlans = ref([
  'Basic',
  'Essential',
  'Premium',
  'Enterprise'
])

// Function to suspend/unsuspend a user
const toggleUserSuspension = async () => {
  const action = isUserSuspended.value ? 'unsuspend' : 'suspend'
  
  try {
    isSuspending.value = true
    showSuspendAlert.value = false
    
    console.log(`${action}ing user:`, props.userData.id)
    
    // Try multiple endpoints in order of preference
    let response
    let endpoint
    
    try {
      // First attempt: Use general Laravel API endpoints (less restrictive middleware)
      endpoint = action === 'suspend' 
        ? `/api/users/${props.userData.id}/suspend`
        : `/api/users/${props.userData.id}/unsuspend`
        
      response = await axios.post(endpoint, {}, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        withCredentials: true // Use session cookies for auth
      })
      
      console.log('General API endpoint succeeded')
    } catch (apiError) {
      console.log('General API failed, trying admin endpoint...', apiError.message)
      
      try {
        // Second attempt: Use admin Laravel API endpoints
        endpoint = action === 'suspend' 
          ? `/api/admin/users/${props.userData.id}/suspend`
          : `/api/admin/users/${props.userData.id}/unsuspend`
          
        response = await axios.post(endpoint, {}, {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          withCredentials: true // Use session cookies for auth
        })
        
        console.log('Admin API endpoint succeeded')
      } catch (adminApiError) {
        console.log('Admin API also failed, trying direct script...', adminApiError.message)
        
        // Third attempt: Use direct PHP script with GET request
        endpoint = `/direct-suspend-user.php?id=${props.userData.id}&action=${action}`
        
        // Create a new axios instance without the /api prefix for direct scripts
        const directAxios = axios.create({
          baseURL: window.location.origin,
          withCredentials: true
        })
        
        response = await directAxios.get(endpoint, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        
        console.log('Direct script succeeded')
      }
    }
    
    console.log(`${action} response:`, response.data)
    
    if (response.data && response.data.success) {
      suspendAlertType.value = 'success'
      suspendAlertMessage.value = response.data.message || `User has been ${action}ed successfully`
      showSuspendAlert.value = true
      
      // Close dialog and reload page after short delay
      setTimeout(() => {
        isSuspendDialogVisible.value = false
        window.location.reload()
      }, 1500)
    } else {
      throw new Error(response.data?.message || `Failed to ${action} user`)
    }
  } catch (error) {
    console.error(`Error ${action}ing user:`, error)
    suspendAlertType.value = 'error'
    suspendAlertMessage.value = error.response?.data?.message || error.message || `An error occurred while ${action}ing user`
    showSuspendAlert.value = true
  } finally {
    isSuspending.value = false
  }
}
</script>

<template>
  <VCard>
    <VCardText class="text-center pt-15">
      <!-- 👉 Avatar -->
      <VAvatar
        rounded
        :size="120"
        :color="!props.userData.avatar && !props.userData.avatarIcon ? 'primary' : undefined"
        :variant="!props.userData.avatar && !props.userData.avatarIcon ? 'tonal' : undefined"
      >
        <VImg
          v-if="props.userData.avatar && props.userData.avatar !== 'bx-user'"
          :src="props.userData.avatar"
        />
        <VIcon
          v-else-if="props.userData.avatarIcon || props.userData.avatar === 'bx-user'"
          :icon="props.userData.avatarIcon || 'bx-user'"
          size="48"
          color="primary"
        />
        <span
          v-else
          class="text-5xl font-weight-medium"
        >
          {{ avatarText(userName) }}
        </span>
      </VAvatar>

      <!-- 👉 User Info -->
      <h6 class="text-h6 mt-4">
        {{ userName }}
      </h6>
      
      <!-- Role chip -->
      <VChip
        size="small"
        class="text-capitalize mt-2"
        :color="props.userData.role === 'admin' ? 'primary' : 'secondary'"
        variant="tonal"
      >
        <VIcon
          :icon="getRoleIcon(props.userData.role || 'client')"
          size="16"
          start
        />
        {{ props.userData.role || 'client' }}
      </VChip>
      
      <!-- Status badge -->
      <VChip
        v-if="props.userData.status"
        size="small"
        class="text-capitalize mt-2 ms-2"
        :color="getStatusColor(effectiveStatus)"
        variant="tonal"
      >
        {{ effectiveStatus }}
      </VChip>

      <VDivider class="my-4" />

      <!-- 👉 Plan Info (only for clients with a plan) -->
      <div v-if="props.userData.role === 'client' && props.userData.plan" class="mb-4">
        <h6 class="text-h6 mb-1">
          {{ props.userData.plan.name }} Plan
        </h6>
        <p class="text-body-1 mb-0">
          {{ formatPrice(props.userData.plan.price) }}/{{ props.userData.plan.duration || 'month' }}
        </p>
        
        <!-- Plan features list -->
        <div v-if="props.userData.plan.features && props.userData.plan.features.length > 0" class="mt-3">
          <div
            v-for="(feature, index) in props.userData.plan.features"
            :key="index"
            class="d-flex align-center mt-1"
          >
            <VIcon
              icon="bx-check"
              color="success"
              size="16"
              class="me-1"
            />
            <span class="text-body-2">{{ feature }}</span>
          </div>
        </div>
      </div>

      <!-- 👉 Upgrade Plan Button (only for clients) -->
      <VBtn
        v-if="props.userData.role === 'client'"
        block
        :variant="$vuetify.theme.current.dark ? 'tonal' : 'flat'"
        color="primary"
      >
        <VIcon
          size="20"
          start
          icon="bx-credit-card"
        />
        {{ props.userData.plan ? 'Manage Plan' : 'Upgrade Plan' }}
      </VBtn>
    </VCardText>

    <VCardText>
      <p class="text-h5 mb-4">
        User Information
      </p>

      <!-- 👉 User Details list -->
      <VList class="card-list">
        <VListItem>
          <VListItemTitle>
            <h6 class="text-h6">
              Username:
              <div class="d-inline-block text-body-1">
                {{ userName }}
              </div>
            </h6>
          </VListItemTitle>
        </VListItem>

        <VListItem>
          <VListItemTitle>
            <h6 class="text-h6">
              Email:
              <span class="text-body-1 d-inline-block">
                {{ props.userData.email || 'No Email' }}
              </span>
            </h6>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="props.userData.status">
          <VListItemTitle>
            <h6 class="text-h6">
              Status:
              <div class="d-inline-block text-body-1 text-capitalize">
                {{ effectiveStatus }}
              </div>
            </h6>
          </VListItemTitle>
        </VListItem>

        <VListItem>
          <VListItemTitle>
            <h6 class="text-h6">
              Role:
              <div class="d-inline-block text-capitalize text-body-1">
                {{ props.userData.role || 'client' }}
              </div>
            </h6>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="props.userData.role === 'client'">
          <VListItemTitle>
            <h6 class="text-h6">
              Plan:
              <div class="d-inline-block text-body-1">
                {{ props.userData.plan ? props.userData.plan.name : 'No Plan' }}
              </div>
            </h6>
          </VListItemTitle>
        </VListItem>
        
        <VListItem v-if="props.userData.created_at">
          <VListItemTitle>
            <h6 class="text-h6">
              Joined:
              <div class="d-inline-block text-body-1">
                {{ new Date(props.userData.created_at).toLocaleDateString() }}
              </div>
            </h6>
          </VListItemTitle>
        </VListItem>
      </VList>

      <VDivider class="my-4" />

      <!-- 👉 Edit and Suspend buttons -->
      <div class="d-flex flex-column">
        <VBtn
          variant="tonal"
          color="primary"
          class="me-3 mb-2"
          @click="openEditDialog"
        >
          <VIcon
            size="20"
            start
            icon="bx-edit"
          />
          Edit
        </VBtn>
        <VBtn
          :color="isUserSuspended ? 'success' : 'error'"
          variant="tonal"
          @click="isSuspendDialogVisible = true"
        >
          <VIcon
            size="20"
            start
            :icon="isUserSuspended ? 'bx-user-check' : 'bx-user-minus'"
          />
          {{ isUserSuspended ? 'Unsuspend' : 'Suspend' }}
        </VBtn>
      </div>
    </VCardText>
  </VCard>

  <!-- Edit User Dialog -->
  <VDialog
    v-model="isDialogVisible"
    persistent
    max-width="600px"
  >
    <VCard>
      <VCardTitle class="text-h5 py-4 px-6">
        Edit User Information
        <VBtn
          icon
          variant="text"
          position="absolute"
          color="default"
          style="right: 12px; top: 12px;"
          @click="isDialogVisible = false"
        >
          <VIcon>bx-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      
      <VCardText class="pa-6">
        <!-- Alert Messages -->
        <VAlert
          v-if="showAlert"
          :type="alertType"
          class="mb-4"
          variant="tonal"
          closable
          @click:close="showAlert = false"
        >
          {{ alertMessage }}
        </VAlert>
        
        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- 👉 Name -->
            <VCol cols="12">
              <VRow no-gutters>
                <VCol
                  cols="12"
                  md="3"
                  class="d-flex align-items-center"
                >
                  <label
                    class="v-label text-body-2 text-high-emphasis"
                    for="nameInput"
                  >Name</label>
                </VCol>

                <VCol
                  cols="12"
                  md="9"
                >
                  <VTextField
                    id="nameInput"
                    v-model="formData.name"
                    prepend-inner-icon="bx-user"
                    placeholder="John Doe"
                    persistent-placeholder
                    :error-messages="formErrors.name"
                  />
                </VCol>
              </VRow>
            </VCol>

            <!-- 👉 Email -->
            <VCol cols="12">
              <VRow no-gutters>
                <VCol
                  cols="12"
                  md="3"
                  class="d-flex align-items-center"
                >
                  <label
                    class="v-label text-body-2 text-high-emphasis"
                    for="emailInput"
                  >Email</label>
                </VCol>

                <VCol
                  cols="12"
                  md="9"
                >
                  <VTextField
                    id="emailInput"
                    v-model="formData.email"
                    type="email"
                    prepend-inner-icon="bx-envelope"
                    placeholder="johndoe@example.com"
                    persistent-placeholder
                    :error-messages="formErrors.email"
                  />
                </VCol>
              </VRow>
            </VCol>

            <!-- 👉 Role -->
            <VCol cols="12">
              <VRow no-gutters>
                <VCol
                  cols="12"
                  md="3"
                  class="d-flex align-items-center"
                >
                  <label
                    class="v-label text-body-2 text-high-emphasis"
                    for="roleSelect"
                  >Role</label>
                </VCol>

                <VCol
                  cols="12"
                  md="9"
                >
                  <VSelect
                    id="roleSelect"
                    v-model="formData.role"
                    :items="['admin', 'client']"
                    prepend-inner-icon="bx-shield"
                    :error-messages="formErrors.role"
                  />
                </VCol>
              </VRow>
            </VCol>

            <!-- 👉 Status for admin users / Plan for client users -->
            <VCol cols="12">
              <VRow no-gutters>
                <VCol
                  cols="12"
                  md="3"
                  class="d-flex align-items-center"
                >
                  <label
                    class="v-label text-body-2 text-high-emphasis"
                    :for="formData.role === 'client' ? 'planSelect' : 'statusSelect'"
                  >{{ formData.role === 'client' ? 'Plan' : 'Status' }}</label>
                </VCol>

                <VCol
                  cols="12"
                  md="9"
                >
                  <!-- Show plan selection for clients -->
                  <VSelect
                    v-if="formData.role === 'client'"
                    id="planSelect"
                    v-model="formData.plan"
                    :items="availablePlans"
                    prepend-inner-icon="bx-package"
                    :error-messages="formErrors.plan"
                  />
                  
                  <!-- Show status selection for admins -->
                  <VSelect
                    v-else
                    id="statusSelect"
                    v-model="formData.status"
                    :items="['active', 'inactive', 'pending', 'banned', 'suspended']"
                    prepend-inner-icon="bx-toggle-left"
                    :error-messages="formErrors.status"
                  />
                </VCol>
              </VRow>
            </VCol>
          </VRow>

          <!-- 👉 Submit and Cancel buttons -->
          <VRow class="mt-3">
            <VCol
              cols="12"
              md="3"
            />
            <VCol
              cols="12"
              md="9"
              class="d-flex gap-4"
            >
              <VBtn
                type="submit"
                color="primary"
                :loading="isSubmitting"
                :disabled="isSubmitting"
              >
                Save Changes
              </VBtn>
              <VBtn
                color="secondary"
                variant="tonal"
                @click="isDialogVisible = false"
                :disabled="isSubmitting"
              >
                Cancel
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>

  <!-- Suspend/Unsuspend User Confirmation Dialog -->
  <VDialog
    v-model="isSuspendDialogVisible"
    persistent
    max-width="500px"
  >
    <VCard>
      <VCardTitle 
        class="text-h5 py-4 px-6 text-white"
        :class="isUserSuspended ? 'bg-success' : 'bg-error'"
      >
        {{ isUserSuspended ? 'Unsuspend User' : 'Suspend User' }}
        <VBtn
          icon
          variant="text"
          position="absolute"
          color="white"
          style="right: 12px; top: 12px;"
          @click="isSuspendDialogVisible = false"
        >
          <VIcon>bx-x</VIcon>
        </VBtn>
      </VCardTitle>
      
      <VCardText class="pa-6">
        <!-- Alert Messages -->
        <VAlert
          v-if="showSuspendAlert"
          :type="suspendAlertType"
          class="mb-4"
          variant="tonal"
          closable
          @click:close="showSuspendAlert = false"
        >
          {{ suspendAlertMessage }}
        </VAlert>
        
        <p class="text-body-1 mb-6" v-if="isUserSuspended">
          Are you sure you want to unsuspend <strong>{{ userName }}</strong>? 
          This action will restore the user's access to their account.
        </p>
        <p class="text-body-1 mb-6" v-else>
          Are you sure you want to suspend <strong>{{ userName }}</strong>? 
          This action will prevent the user from accessing their account until they are unsuspended.
        </p>
        
        <div class="d-flex justify-end gap-3">
          <VBtn
            color="default"
            variant="tonal"
            @click="isSuspendDialogVisible = false"
            :disabled="isSuspending"
          >
            Cancel
          </VBtn>
          <VBtn
            :color="isUserSuspended ? 'success' : 'error'"
            :loading="isSuspending"
            :disabled="isSuspending"
            @click="toggleUserSuspension"
          >
            {{ isUserSuspended ? 'Unsuspend User' : 'Suspend User' }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.card-list {
  --v-card-list-gap: 1rem;

  .v-list-item {
    padding-inline: 0;
  }
}
</style>
