<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import AddNewUserDrawer from '@/views/apps/user/list/AddNewUserDrawer.vue'

// 👉 Store
const searchQuery = ref('')
const selectedRole = ref()
const selectedStatus = ref()
const fetchError = ref(null)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()
const selectedRows = ref([])

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

// Headers
const headers = [
  {
    title: 'User',
    key: 'user',
  },
  {
    title: 'Role',
    key: 'role',
  },
  {
    title: 'Plan',
    key: 'plan',
  },
  {
    title: 'Billing',
    key: 'billing',
  },
  {
    title: 'Status',
    key: 'status',
  },
  {
    title: 'Actions',
    key: 'actions',
    sortable: false,
  },
]

// Initialize users data
const usersData = ref({
  users: [],
  totalUsers: 0,
  page: 1,
  totalPages: 1,
});

// Store user session status
const userSessionStatus = ref({});

const isLoading = ref(false);

const fetchPostgresUsers = async () => {
  try {
    isLoading.value = true
    console.log('Fetching users...')
    
    // Build query parameters
    const params = new URLSearchParams()
    if (searchQuery.value) params.append('q', searchQuery.value)
    if (selectedRole.value) params.append('role', selectedRole.value)
    if (selectedStatus.value) params.append('status', selectedStatus.value)
    if (itemsPerPage.value) params.append('itemsPerPage', itemsPerPage.value.toString())
    if (page.value) params.append('page', page.value.toString())
    if (sortBy.value) params.append('sortBy', sortBy.value)
    if (orderBy.value) params.append('orderBy', orderBy.value)
    
    // Make the API call using our endpoint
    const response = await fetch(`/user-management.php?${params.toString()}`)
    const data = await response.json()
    
    console.log('Received user data:', data)
    
    if (data.success) {
      // Update users data with only essential fields
      usersData.value = {
        users: data.users.map(user => ({
          id: user.id,
          fullName: user.name,
          email: user.email,
          role: user.role || 'client',
          currentPlan: 'basic',
          status: user.status || 'active',
          avatar: null,
          billing: 'Auto Debit'
        })),
        totalUsers: data.totalUsers,
        page: data.page,
        lastPage: data.lastPage
      }
    } else {
      console.error('Error fetching users:', data.message)
      fetchError.value = data.message
    }
  } catch (error) {
    console.error('Error fetching users:', error)
    fetchError.value = error.message
  } finally {
    isLoading.value = false
  }
}

// Fallback method with mock data
const useMockUsers = () => {
  console.log('Using mock user data since API failed')
  usersData.value = {
    users: [
      {
        id: 1,
        fullName: 'John Doe',
        email: 'john@example.com',
        role: 'admin',
        status: 'active',
        currentPlan: 'basic',
        avatar: null,
        billing: 'monthly'
      },
      {
        id: 2,
        fullName: 'Jane Smith',
        email: 'jane@example.com',
        role: 'client',
        status: 'active',
        currentPlan: 'basic',
        avatar: null,
        billing: 'monthly'
      }
    ],
    totalUsers: 2
  }
}

// Watch for API errors
watch(fetchError, (newError) => {
  if (newError) {
    console.error('Error fetching users:', newError)
    console.error('Error response:', newError.data)
  }
}, { immediate: true })

// Computed properties for users and totalUsers
const users = computed(() => {
  // Handle different possible response formats
  if (!usersData.value) return []
  
  // If users is directly available at the top level
  if (Array.isArray(usersData.value)) {
    return usersData.value
  }
  
  // If users is in a users property
  if (usersData.value.users && Array.isArray(usersData.value.users)) {
    return usersData.value.users
  }
  
  // If data is in a data property (some APIs use this pattern)
  if (usersData.value.data && Array.isArray(usersData.value.data)) {
    return usersData.value.data
  }
  
  return []
})

const totalUsers = computed(() => {
  // Handle different possible response formats
  if (!usersData.value) return 0
  
  // If totalUsers is directly available
  if (typeof usersData.value.totalUsers === 'number') {
    return usersData.value.totalUsers
  }
  
  // If it's in a meta object (Laravel pagination format)
  if (usersData.value.meta && typeof usersData.value.meta.total === 'number') {
    return usersData.value.meta.total
  }
  
  // If we have just the users array, use its length
  if (Array.isArray(usersData.value.users)) {
    return usersData.value.users.length
  }
  
  // If we just have an array at the top level
  if (Array.isArray(usersData.value)) {
    return usersData.value.length
  }
  
  // Last resort, use 0 to avoid NaN in pagination
  return 0
})

// 👉 search filters
const roles = [
  {
    title: 'Admin',
    value: 'admin',
  },
  {
    title: 'Client',
    value: 'client',
  },
]

const status = [
  {
    title: 'Active',
    value: 'active',
  },
  {
    title: 'Inactive',
    value: 'inactive',
  },
]

const resolveUserRoleVariant = role => {
  const roleLowerCase = role.toLowerCase()
  if (roleLowerCase === 'client')
    return {
      color: 'success',
      icon: 'bx-user',
    }
  if (roleLowerCase === 'admin')
    return {
      color: 'primary',
      icon: 'bx-crown',
    }
  
  return {
    color: 'primary',
    icon: 'bx-user',
  }
}

const resolveUserStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  
  return 'primary'
}

const isAddNewUserDrawerVisible = ref(false)

// Add notification states
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

const addNewUser = async (userData) => {
  try {
    isLoading.value = true
    console.log('Adding new user:', userData)
    
    // Format the data to match our simplified backend API
    const formattedData = {
      name: userData.name,
      email: userData.email,
      password: userData.password,
      role: userData.role,
      status: userData.status
    }
    
    console.log('Sending user data:', formattedData)
    
    // Call our backend API endpoint
    const response = await fetch('/user-management.php?action=create', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(formattedData)
    })
    
    const result = await response.json()
    
    if (result.success) {
      // Show success message
      console.log('User created successfully:', result.user)
      snackbarColor.value = 'success'
      snackbarText.value = `User ${formattedData.name} created successfully!`
      snackbar.value = true
      
      // Refetch users list
      fetchPostgresUsers()
    } else {
      console.error('Error adding user:', result.message)
      fetchError.value = result.message
      snackbarColor.value = 'error'
      snackbarText.value = `Failed to create user: ${result.message}`
      snackbar.value = true
    }
  } catch (error) {
    console.error('Error adding user:', error)
    fetchError.value = error.message
    snackbarColor.value = 'error'
    snackbarText.value = `Error: ${error.message}`
    snackbar.value = true
  } finally {
    isLoading.value = false
  }
}

// Add variables for delete confirmation dialog
const deleteDialog = ref(false)
const userToDelete = ref(null)

// Modified deleteUser function to handle confirmation
const confirmDeleteUser = (userId) => {
  // First find the user to show their name in the confirmation
  const user = users.value.find(u => u.id === userId)
  if (user) {
    userToDelete.value = {
      id: userId,
      name: user.fullName
    }
    deleteDialog.value = true
  }
}

const deleteUser = async () => {
  // Make sure we have a user to delete
  if (!userToDelete.value) return
  
  const id = userToDelete.value.id
  
  try {
    isLoading.value = true
    console.log('Permanently deleting user:', id)
    
    // Call our backend API endpoint with hard delete type
    const response = await fetch(`/user-management.php?action=delete&id=${id}&delete_type=hard`, {
      method: 'DELETE'
    })
    
    const result = await response.json()
    
    if (result.success) {
      console.log('User permanently deleted', result)
      snackbarColor.value = 'success'
      snackbarText.value = 'User permanently deleted from database'
      snackbar.value = true
      
      // Delete from selectedRows
      const index = selectedRows.value.findIndex(row => row === id)
      if (index !== -1)
        selectedRows.value.splice(index, 1)
      
      // Refetch users list
      fetchPostgresUsers()
    } else {
      console.error('Error deleting user:', result.message)
      snackbarColor.value = 'error'
      snackbarText.value = `Failed to delete user: ${result.message}`
      snackbar.value = true
    }
  } catch (error) {
    console.error('Error deleting user:', error)
    snackbarColor.value = 'error'
    snackbarText.value = `Error: ${error.message}`
    snackbar.value = true
  } finally {
    isLoading.value = false
    // Close the dialog and reset userToDelete
    deleteDialog.value = false
    userToDelete.value = null
  }
}

// Add watchers to refetch when filters change
watch([searchQuery, selectedRole, selectedStatus, page, itemsPerPage], () => {
  console.log('Filter changed, refetching users...')
  fetchPostgresUsers()
}, { deep: true })

// Fetch session statistics from the API
const sessionStats = ref({
  total_sessions: 0,
  total_active_sessions: 0,
  sessions_last_24_hours: 0,
  guest_sessions: 0,
  sessions_by_role: {},
})

// Function to fetch session statistics
const fetchSessionStats = async () => {
  try {
    console.log('Fetching session statistics...')
    
    // Make the API call to our api endpoint
    const response = await fetch('/api-sessions.php')
    const data = await response.json()
    
    console.log('Session statistics response:', data)
    
    if (data && data.success) {
      // Update sessionStats with the response data
      sessionStats.value = {
        total_sessions: data.total_sessions || 0,
        total_active_sessions: data.total_active_sessions || 0,
        sessions_last_24_hours: data.sessions_last_24_hours || 0,
        guest_sessions: data.guest_sessions || 0,
        sessions_by_role: data.sessions_by_role || {}
      }
      
      // Fetch user session statuses
      await fetchUserSessionStatuses()
      
      console.log('Updated sessionStats:', sessionStats.value)
      return true
    }
    
    return false
  } catch (error) {
    console.error('Error fetching session statistics:', error)
    return false
  }
}

// Function to fetch session status for each user
const fetchUserSessionStatuses = async () => {
  try {
    console.log('Fetching user session statuses...')
    
    // Fetch session statuses for all users
    const response = await fetch('/session-maintenance.php?action=list')
    const data = await response.json()
    
    if (data && data.success && data.sessions) {
      // Create a map of user_id -> session status
      const statusMap = {}
      
      data.sessions.forEach(session => {
        if (session.user_id) {
          statusMap[session.user_id] = session.is_active
        }
      })
      
      userSessionStatus.value = statusMap
      console.log('Updated user session statuses:', userSessionStatus.value)
    }
  } catch (error) {
    console.error('Error fetching user session statuses:', error)
  }
}

// Helper function to get session status for a user
const getUserSessionStatus = userId => {
  // Check if we have session status for this user
  if (userSessionStatus.value[userId] !== undefined) {
    return userSessionStatus.value[userId] ? 'active' : 'inactive'
  }
  
  // If no session data, return 'inactive' instead of 'offline'
  return 'inactive'
}

// Create widgetData based on real session statistics
const widgetData = computed(() => {
  // Get actual values with fallbacks
  const totalSessions = sessionStats.value?.total_sessions || 0
  const activeSessions = sessionStats.value?.total_active_sessions || 0
  const recentSessions = sessionStats.value?.sessions_last_24_hours || 0
  const guestSessions = sessionStats.value?.guest_sessions || 0
  const adminSessions = sessionStats.value?.sessions_by_role?.admin || 0
  
  console.log('Building widget data using sessions:', {
    totalUsers: totalUsers.value,
    totalUsersType: typeof totalUsers.value,
    totalUsersDirectAccess: typeof totalUsers,
    sessionStats: sessionStats.value,
    adminSessions,
    totalSessions
  })
  
  return [
    {
      title: 'Total Sessions',
      value: totalSessions.toString(),
      change: totalSessions > 0 ? Math.round((activeSessions / totalSessions) * 100) : 0,
      desc: 'All Sessions',
      icon: 'bx-group',
      iconColor: 'primary',
    },
    {
      title: 'Active Sessions',
      value: activeSessions.toString(),
      change: totalSessions > 0 ? Math.round((activeSessions / totalSessions) * 100) : 0,
      desc: 'Currently Active',
      icon: 'bx-user-plus',
      iconColor: 'error',
    },
    {
      title: 'Active Users',
      value: String(totalUsers.value || 0),
      change: totalUsers.value > 0 ? Math.min(100, Math.round((adminSessions / totalUsers.value) * 100)) : 0,
      desc: `Admin Sessions: ${adminSessions}`,
      icon: 'bx-user-check',
      iconColor: 'success',
    },
    {
      title: 'Recent Sessions',
      value: recentSessions.toString(),
      change: totalSessions > 0 ? Math.round((guestSessions / totalSessions) * 100) : 0,
      desc: `Guest Sessions: ${guestSessions}`,
      icon: 'bx-user-voice',
      iconColor: 'warning',
    },
  ]
})

// Update onMounted to fetch session statistics as well
onMounted(async () => {
  try {
    console.log('Component mounted, fetching data...')
    
    // Fetch both users and session statistics
    await Promise.all([
      fetchPostgresUsers(),
      fetchSessionStats()
    ])
    
  } catch (error) {
    console.error('Error during initialization:', error)
    useMockUsers() // Fallback to mock data on error
  }
})

// Helper function for prefix with plus
const prefixWithPlus = value => {
  return value > 0 ? `+${value}` : value
}

// Function to refresh all data
const refreshData = async () => {
  console.log('Refreshing all data...')
  
  // Show loading spinner
  isLoading.value = true
  
  try {
    // Fetch both users and session statistics
    await Promise.all([
      fetchPostgresUsers(),
      fetchSessionStats()
    ])
  } catch (error) {
    console.error('Error refreshing data:', error)
  } finally {
    isLoading.value = false
  }
}

// Set up periodic refresh for session statuses (every 30 seconds)
let refreshInterval
onMounted(() => {
  refreshData()
  
  // Set up periodic refresh
  refreshInterval = setInterval(() => {
    fetchUserSessionStatuses()
  }, 30000) // 30 seconds
})

// Clean up interval when component is unmounted
onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})
</script>

<template>
  <section>
    <!-- 👉 Widgets -->
    <div class="d-flex mb-6">
      <VRow>
        <template
          v-for="(data, id) in widgetData"
          :key="id"
        >
          <VCol
            cols="12"
            md="3"
            sm="6"
          >
            <VCard>
              <VCardText>
                <div class="d-flex justify-space-between">
                  <div class="d-flex flex-column gap-y-1">
                    <div class="text-body-1 text-high-emphasis">
                      {{ data.title }}
                    </div>
                    <div class="d-flex gap-x-2 align-center">
                      <h4 class="text-h4">
                        {{ data.value }}
                      </h4>
                      <div
                        class="text-base"
                        :class="data.change > 0 ? 'text-success' : 'text-error'"
                      >
                        ({{ prefixWithPlus(data.change) }}%)
                      </div>
                    </div>
                    <div class="text-sm">
                      {{ data.desc }}
                    </div>
                  </div>
                  <VAvatar
                    :color="data.iconColor"
                    variant="tonal"
                    rounded
                    size="40"
                  >
                    <VIcon
                      :icon="data.icon"
                      size="24"
                    />
                  </VAvatar>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </template>
      </VRow>
    </div>
    
    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <VCardTitle>Filters</VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <!-- 👉 Select Role -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedRole"
              placeholder="Select Role"
              :items="roles"
              clearable
              clear-icon="bx-x"
            />
          </VCol>
          <!-- 👉 Select Status -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="Select Status"
              :items="status"
              clearable
              clear-icon="bx-x"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardText class="d-flex flex-wrap gap-4">
        <div class="me-3 d-flex gap-3">
          <AppSelect
            :model-value="itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' },
              { value: -1, title: 'All' },
            ]"
            style="inline-size: 6.25rem;"
            @update:model-value="itemsPerPage = parseInt($event, 10)"
          />
        </div>
        <VSpacer />

        <div class="app-user-search-filter d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search User"
            />
          </div>

          <!-- 👉 Export button -->
          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="bx-export"
          >
            Export
          </VBtn>

          <!-- 👉 Refresh button -->
          <VBtn
            variant="tonal"
            color="primary"
            prepend-icon="bx-refresh"
            @click="refreshData"
            :loading="isLoading"
          >
            Refresh
          </VBtn>

          <!-- 👉 Add user button -->
          <VBtn
            prepend-icon="bx-plus"
            @click="isAddNewUserDrawerVisible = true"
          >
            Add New User
          </VBtn>
        </div>
      </VCardText>
      <VDivider />

      <!-- SECTION PostgreSQL Users Table -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :items="users"
        item-value="id"
        :items-length="totalUsers"
        :headers="headers"
        :loading="isLoading"
        class="text-no-wrap"
        show-select
        @update:options="updateOptions"
      >
        <!-- No data message -->
        <template #no-data>
          <div class="d-flex align-center justify-center flex-column pa-5">
            <VIcon
              icon="bx-alert-circle"
              size="40"
              color="text-disabled"
              class="mb-2"
            />
            <p class="text-high-emphasis text-center text-body-1">
              {{ fetchError && fetchError.value ? 'Error loading users from PostgreSQL' : 'No users found in PostgreSQL' }}
            </p>
            <p v-if="fetchError && fetchError.value" class="text-disabled text-center">
              {{ fetchError.value?.message || 'Could not load user data from PostgreSQL. Please try again later.' }}
            </p>
          </div>
        </template>
        
        <!-- User -->
        <template #item.user="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              size="34"
              :variant="!item.avatar ? 'tonal' : undefined"
              :color="!item.avatar ? resolveUserRoleVariant(item.role).color : undefined"
            >
              <VImg
                v-if="item.avatar"
                :src="item.avatar"
              />
              <VIcon v-else icon="bx-user" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h6 class="text-base">
                <RouterLink
                  :to="{ path: `/admin/users/${item.id}` }"
                  class="font-weight-medium text-link"
                >
                  {{ item.fullName }}
                </RouterLink>
              </h6>
              <div class="text-sm">
                {{ item.email }}
              </div>
            </div>
          </div>
        </template>

        <!-- 👉 Role -->
        <template #item.role="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VIcon
              :size="20"
              :icon="resolveUserRoleVariant(item.role).icon"
              :color="resolveUserRoleVariant(item.role).color"
            />

            <div class="text-capitalize text-high-emphasis text-body-1">
              {{ item.role }}
            </div>
          </div>
        </template>

        <!-- Plan -->
        <template #item.plan="{ item }">
          <div class="text-body-1 text-high-emphasis text-capitalize">
            {{ item.currentPlan }}
          </div>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveUserStatusVariant(getUserSessionStatus(item.id))"
            size="small"
            label
            class="text-capitalize"
          >
            {{ getUserSessionStatus(item.id) }}
          </VChip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click="confirmDeleteUser(item.id)">
            <VIcon icon="bx-trash" />
          </IconBtn>

          <IconBtn>
            <VIcon icon="bx-show" />
          </IconBtn>

          <VBtn
            icon
            variant="text"
            color="medium-emphasis"
          >
            <VIcon icon="bx-dots-vertical-rounded" />
            <VMenu activator="parent">
              <VList>
                <VListItem :to="{ path: `/admin/users/${item.id}` }">
                  <template #prepend>
                    <VIcon icon="bx-show" />
                  </template>

                  <VListItemTitle>View</VListItemTitle>
                </VListItem>

                <VListItem link>
                  <template #prepend>
                    <VIcon icon="bx-pencil" />
                  </template>
                  <VListItemTitle>Edit</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </VBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalUsers"
          />
        </template>
      </VDataTableServer>
      <!-- SECTION -->
    </VCard>

    <!-- 👉 Add New User -->
    <AddNewUserDrawer
      v-model:is-drawer-open="isAddNewUserDrawerVisible"
      @user-data="addNewUser"
    />
    
    <!-- Delete User Confirmation Dialog -->
    <VDialog
      v-model="deleteDialog"
      persistent
      max-width="500"
    >
      <VCard>
        <VCardTitle class="text-h5">
          Delete User
        </VCardTitle>
        <VCardText>
          <p class="mb-2">Are you sure you want to delete this user?</p>
          <p class="font-weight-medium" v-if="userToDelete">{{ userToDelete.name }}</p>
          
          <VDivider class="my-4" />
          
          <div class="mt-4">
            <p class="text-caption text-error">
              <strong>Warning:</strong> Permanent delete will completely remove the user from the database. This action cannot be undone.
            </p>
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer></VSpacer>
          <VBtn
            color="secondary"
            variant="tonal"
            @click="deleteDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            @click="deleteUser"
            :loading="isLoading"
          >
            Permanently Delete
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
    
    <!-- Notification Snackbar -->
    <VSnackbar
      v-model="snackbar"
      :color="snackbarColor"
      location="top"
      :timeout="5000"
    >
      {{ snackbarText }}
      
      <template #actions>
        <VBtn
          icon
          variant="text"
          @click="snackbar = false"
        >
          <VIcon icon="bx-x" />
        </VBtn>
      </template>
    </VSnackbar>
  </section>
</template>

<style lang="scss">
.text-capitalize {
  text-transform: capitalize;
}

.user-list-name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}
</style>
