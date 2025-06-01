<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import UserBioPanel from '@/views/apps/user/view/UserBioPanel.vue'
import UserTabAccount from '@/views/apps/user/view/UserTabAccount.vue'
import UserTabBillingsPlans from '@/views/apps/user/view/UserTabBillingsPlans.vue'
import UserTabConnections from '@/views/apps/user/view/UserTabConnections.vue'
import UserTabNotifications from '@/views/apps/user/view/UserTabNotifications.vue'
import UserTabSecurity from '@/views/apps/user/view/UserTabSecurity.vue'
import { inject, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

// Get API client from injection
const api = inject('api')

// Get auth store to access logged-in user
const authStore = useAuthStore()

// Route and router instances
const route = useRoute()
const router = useRouter()

// User ID from route parameters or current user
const routeUserId = route.params.id
const userId = ref(routeUserId || 'me') // Default to 'me' if no ID provided

// Component state
const userData = ref(null)
const isLoading = ref(true)
const error = ref(null)
const activeTab = ref('account')

// Function to normalize user data
const normalizeUserData = (data) => {
  return {
    id: data.id,
    name: data.name || data.fullName,
    email: data.email,
    role: data.role,
    status: data.status,
    created_at: data.created_at,
    // Provide default avatar with bx user icon if avatar is null
    avatar: data.avatar || data.avatar_url || 'bx-user',
    avatarIcon: !data.avatar && !data.avatar_url ? 'bx-user' : null,
    plan: data.plan || { name: 'Basic', price: 0, duration: 'month' }
  }
}

// Get the current logged-in user's data
const getCurrentUserData = () => {
  if (authStore.user) {
    console.log('Auth store user data:', authStore.user);
    
    // Create a direct API request to get the most up-to-date user data
    // This ensures we have the same data format as when fetching other users
    fetch(`/direct-user.php?id=${authStore.user.id}`)
      .then(response => response.json())
      .then(data => {
        if (data && data.success) {
          userData.value = normalizeUserData(data.user);
          console.log('Updated user data from API:', userData.value);
        } else {
          // Fallback to auth store data if API request fails
          userData.value = normalizeUserData(authStore.user);
          console.log('Using auth store data as fallback');
        }
        isLoading.value = false;
      })
      .catch(err => {
        console.error('Error fetching current user data:', err);
        // Fallback to auth store data
        userData.value = normalizeUserData(authStore.user);
        isLoading.value = false;
      });
    
    return true;
  }
  return false;
}

// Function to fetch user data
const fetchUserData = async () => {
  isLoading.value = true
  error.value = null
  
  // If user ID is 'me' or missing, show current user profile
  if (userId.value === 'me' || !userId.value) {
    if (getCurrentUserData()) {
      console.log('Using current user data from auth store')
      return
    }
  }
  
  try {
    console.log(`Fetching user profile for ID: ${userId.value}`)
    
    // Use the direct API endpoint that bypasses Laravel routing
    const response = await fetch(`/direct-user.php?id=${userId.value}`, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    })
    
    // Check if response is JSON
    const contentType = response.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      throw new Error('Server returned non-JSON response')
    }
    
    // Parse the JSON response
    const data = await response.json()
    console.log('API response:', data)
    
    if (data && data.success) {
      // Normalize the user data
      userData.value = normalizeUserData(data.user)
      console.log('User data loaded successfully')
    } else {
      // If user not found and we aren't already using current user, fall back to current user
      if (data?.message === 'User not found' && authStore.user) {
        getCurrentUserData()
        console.log('User not found, using current user data instead')
      } else {
        throw new Error(data?.message || 'Failed to fetch user data')
      }
    }
  } catch (err) {
    console.error('Error fetching user profile:', err)
    
    // If there's an error, try to fall back to current user
    if (authStore.user && userId.value !== authStore.user.id) {
      getCurrentUserData()
      console.log('Error occurred, using current user data instead')
    } else {
      if (err.name === 'SyntaxError') {
        error.value = 'Server returned invalid data format. Please try again later.'
      } else {
        error.value = `Failed to load user profile: ${err.message}`
      }
    }
  } finally {
    // Only set isLoading to false if we're not getting current user data
    // (getCurrentUserData will set isLoading to false when it's done)
    if (!(userId.value === 'me' || !userId.value || 
         (userData.value && userData.value.id === authStore.user?.id))) {
      isLoading.value = false
    }
  }
}

// Update userId when route param changes
watch(() => route.params.id, (newId) => {
  if (newId) {
    userId.value = newId
  } else {
    // If no ID in route, use current user
    userId.value = 'me'
  }
  fetchUserData()
})

// Load data when component mounts
onMounted(() => {
  fetchUserData()
})
</script>

<template>
  <section>
    <!-- Loading indicator -->
    <VRow v-if="isLoading">
      <VCol cols="12" class="d-flex justify-center">
        <VProgressCircular 
          indeterminate 
          color="primary"
          size="50"
        />
      </VCol>
    </VRow>
    
    <!-- Error message -->
    <VAlert
      v-else-if="error"
      type="error"
      class="mb-6"
    >
      {{ error }}
      <div class="mt-2">
        <VBtn 
          variant="tonal" 
          @click="fetchUserData"
        >
          Retry
        </VBtn>
      </div>
    </VAlert>
    
    <!-- User profile view -->
    <VRow v-else-if="userData">
      <VCol
        cols="12"
        md="5"
        lg="4"
      >
        <UserBioPanel :user-data="userData" />
      </VCol>

      <VCol
        cols="12"
        md="7"
        lg="8"
      >
        <VTabs
          v-model="activeTab"
          show-arrows
        >
          <VTab value="account">Account</VTab>
          <VTab value="security">Security</VTab>
          <VTab value="billing">Billing & Plans</VTab>
          <VTab value="notifications">Notifications</VTab>
          <VTab value="connections">Connections</VTab>
        </VTabs>
        
        <VDivider />

        <VWindow
          v-model="activeTab"
          class="disable-tab-transition"
        >
          <VWindowItem value="account">
            <UserTabAccount :user-data="userData" />
          </VWindowItem>

          <VWindowItem value="security">
            <UserTabSecurity />
          </VWindowItem>

          <VWindowItem value="billing">
            <UserTabBillingsPlans />
          </VWindowItem>

          <VWindowItem value="notifications">
            <UserTabNotifications />
          </VWindowItem>

          <VWindowItem value="connections">
            <UserTabConnections />
          </VWindowItem>
        </VWindow>
      </VCol>
    </VRow>
  </section>
</template>

<style lang="scss">
.disable-tab-transition {
  .v-window__container {
    transition: none !important;
  }
}
</style>
