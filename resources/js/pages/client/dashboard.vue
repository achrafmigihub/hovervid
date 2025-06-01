<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import { computed, inject, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

// Get API client from injection
const api = inject('api')

// Get auth store to access logged-in user
const authStore = useAuthStore()
const router = useRouter()

// Component state
const isLoading = ref(true)
const dashboardData = ref(null)
const lastFetchTime = ref(null)
const CACHE_DURATION = 5 * 60 * 1000 // 5 minutes cache

// User data
const user = computed(() => authStore.user)

// Dashboard stats
const dashboardStats = ref({
  totalViews: 0,
  activePlugins: 0,
  monthlyGrowth: 0,
  lastUpdate: new Date().toLocaleDateString()
})

// Initialize dashboard
onMounted(async () => {
  try {
    // Load dashboard data
    await loadDashboardData()
  } catch (error) {
    console.error('Error initializing dashboard:', error)
  } finally {
    isLoading.value = false
  }
})

// Load dashboard data with caching
const loadDashboardData = async () => {
  try {
    // Check if we have cached data that's still valid
    const now = Date.now()
    if (dashboardData.value && lastFetchTime.value && (now - lastFetchTime.value < CACHE_DURATION)) {
      return
    }

    // Fetch user's latest data to check for domain
    await authStore.fetchUser()
    
    // Fetch dashboard statistics - fixing the endpoint URL
    const response = await api.get('/api/client/dashboard-stats')
    if (response.data) {
      // Update dashboard stats with actual data structure from backend
      dashboardStats.value = {
        totalViews: response.data.totalViews || 0,
        activePlugins: response.data.activePlugins || 0,
        monthlyGrowth: response.data.monthlyGrowth || 0,
        lastUpdate: response.data.lastUpdate || new Date().toLocaleDateString()
      }
      dashboardData.value = response.data
      lastFetchTime.value = now
    }
  } catch (error) {
    console.error('Error loading dashboard data:', error)
    // Set default values to prevent undefined errors
    dashboardStats.value = {
      totalViews: 0,
      activePlugins: 0,
      monthlyGrowth: 0,
      lastUpdate: new Date().toLocaleDateString()
    }
  }
}

// Refresh dashboard data manually
const refreshDashboard = async () => {
  isLoading.value = true
  try {
    await loadDashboardData()
  } finally {
    isLoading.value = false
  }
}

definePage({
  meta: {
    requiresAuth: true,
    requiredRole: 'client',
  },
})
</script>

<template>
  <div>
    <!-- Loading State -->
    <div v-if="isLoading" class="d-flex justify-center align-center" style="min-height: 400px;">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
    </div>

    <!-- Dashboard Content -->
    <div v-else>
      <!-- Welcome Section -->
      <VRow class="mb-6">
        <VCol cols="12">
          <VCard>
            <VCardText class="d-flex align-center">
              <div class="flex-grow-1">
                <h4 class="text-h4 mb-2">
                  Welcome back, {{ user?.name || 'Client' }}! 👋
                </h4>
                <p class="text-body-1 mb-0">
                  Here's what's happening with your account today.
                </p>
              </div>
              <VAvatar
                size="80"
                color="primary"
                variant="tonal"
              >
                <VIcon
                  icon="bx-user"
                  size="40"
                />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Dashboard Stats -->
      <VRow class="mb-6">
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard>
            <VCardText class="text-center">
              <VAvatar
                size="56"
                color="primary"
                variant="tonal"
                class="mb-4"
              >
                <VIcon
                  icon="bx-show"
                  size="28"
                />
              </VAvatar>
              <h5 class="text-h5 mb-1">
                {{ dashboardStats.totalViews.toLocaleString() }}
              </h5>
              <p class="text-body-2 mb-0">
                Total Views
              </p>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard>
            <VCardText class="text-center">
              <VAvatar
                size="56"
                color="success"
                variant="tonal"
                class="mb-4"
              >
                <VIcon
                  icon="bx-plug"
                  size="28"
                />
              </VAvatar>
              <h5 class="text-h5 mb-1">
                {{ dashboardStats.activePlugins }}
              </h5>
              <p class="text-body-2 mb-0">
                Active Plugins
              </p>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard>
            <VCardText class="text-center">
              <VAvatar
                size="56"
                color="info"
                variant="tonal"
                class="mb-4"
              >
                <VIcon
                  icon="bx-trending-up"
                  size="28"
                />
              </VAvatar>
              <h5 class="text-h5 mb-1">
                +{{ dashboardStats.monthlyGrowth }}%
              </h5>
              <p class="text-body-2 mb-0">
                Monthly Growth
              </p>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard>
            <VCardText class="text-center">
              <VAvatar
                size="56"
                color="warning"
                variant="tonal"
                class="mb-4"
              >
                <VIcon
                  icon="bx-calendar"
                  size="28"
                />
              </VAvatar>
              <h5 class="text-h5 mb-1">
                {{ dashboardStats.lastUpdate }}
              </h5>
              <p class="text-body-2 mb-0">
                Last Update
              </p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Quick Actions -->
      <VRow>
        <VCol cols="12">
          <VCard>
            <VCardItem>
              <VCardTitle>Quick Actions</VCardTitle>
            </VCardItem>
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <VBtn
                    block
                    color="primary"
                    variant="tonal"
                    size="large"
                    prepend-icon="bx-cog"
                  >
                    Plugin Settings
                  </VBtn>
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <VBtn
                    block
                    color="success"
                    variant="tonal"
                    size="large"
                    prepend-icon="bx-bar-chart"
                  >
                    View Analytics
                  </VBtn>
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <VBtn
                    block
                    color="info"
                    variant="tonal"
                    size="large"
                    prepend-icon="bx-support"
                  >
                    Get Support
                  </VBtn>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.v-card {
  transition: transform 0.2s ease-in-out;
}

.v-card:hover {
  transform: translateY(-2px);
}
</style> 
