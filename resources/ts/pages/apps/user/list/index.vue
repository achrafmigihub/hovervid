<script setup>
import AddNewUserDrawer from './AddNewUserDrawer.vue'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useApi } from '@/composables/useApi'

// 👉 Store
const searchQuery = ref('')
const selectedRole = ref()
const selectedPlan = ref()
const selectedStatus = ref()
const fetchError = ref(null)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()
const selectedRows = ref([])

// Update data table options
const updateOptions = (options) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

// Headers
const headers = [
  { title: 'User', key: 'user' },
  { title: 'Role', key: 'role' },
  { title: 'Plan', key: 'plan' },
  { title: 'Billing', key: 'billing' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
]

// 👉 Initialize users data
const usersData = ref({
  users: [],
  totalUsers: 0,
  page: 1,
  lastPage: 1
})

// Computed properties for data table
const users = computed(() => usersData.value.users)
const totalUsers = computed(() => usersData.value.totalUsers)

// Loading state for data table
const isLoading = ref(false)

// Fetch session statistics from the API
const sessionStats = ref({
  total_sessions: 0,
  total_active_sessions: 0,
  sessions_last_24_hours: 0,
  guest_sessions: 0,
  sessions_by_role: {
    admin: 0,
    client: 0
  }
})

// Function to fetch session statistics
const fetchSessionStats = async () => {
  try {
    console.log('Fetching session statistics...')
    
    // Make the API call to our test endpoint
    const response = await fetch('/test-sessions.php')
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
      console.log('Updated sessionStats:', sessionStats.value)
      return true
    }
    
    return false
  } catch (error) {
    console.error('Error fetching session statistics:', error)
    return false
  }
}

// Create widgetData based on real session statistics
const widgetData = computed(() => {
  // Get actual values with fallbacks
  const totalSessions = sessionStats.value?.total_sessions || 0
  const activeSessions = sessionStats.value?.total_active_sessions || 0
  const recentSessions = sessionStats.value?.sessions_last_24_hours || 0
  const guestSessions = sessionStats.value?.guest_sessions || 0
  const adminSessions = sessionStats.value?.sessions_by_role?.admin || 0
  
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
      value: totalUsers.value.toString(),
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

// Function to fetch users from the backend
const fetchPostgresUsers = async () => {
  try {
    isLoading.value = true
    console.log('Fetching users from PostgreSQL database...')
    
    // Build query parameters
    const params = new URLSearchParams()
    if (searchQuery.value) params.append('q', searchQuery.value)
    if (selectedRole.value) params.append('role', selectedRole.value)
    if (selectedPlan.value) params.append('plan', selectedPlan.value)
    if (selectedStatus.value) params.append('status', selectedStatus.value)
    if (itemsPerPage.value) params.append('itemsPerPage', itemsPerPage.value.toString())
    if (page.value) params.append('page', page.value.toString())
    if (sortBy.value) params.append('sortBy', sortBy.value)
    if (orderBy.value) params.append('orderBy', orderBy.value)
    
    // Make the API call - using test-api.php for now until we fix auth issues
    const response = await fetch(`/test-api.php?${params.toString()}`)
    const data = await response.json()
    
    console.log('Received user data:', data)
    
    // Update users data
    usersData.value = {
      users: data.users,
      totalUsers: data.totalUsers,
      page: data.page,
      lastPage: data.lastPage
    }
  } catch (error) {
    console.error('Error fetching users:', error)
  } finally {
    isLoading.value = false
  }
}

// 👉 search filters
const roles = [
  { title: 'Admin', value: 'admin' },
  { title: 'Author', value: 'author' },
  { title: 'Editor', value: 'editor' },
  { title: 'Maintainer', value: 'maintainer' },
  { title: 'Subscriber', value: 'subscriber' },
  { title: 'Client', value: 'client' },
]

const plans = [
  { title: 'Basic', value: 'basic' },
  { title: 'Company', value: 'company' },
  { title: 'Enterprise', value: 'enterprise' },
  { title: 'Team', value: 'team' },
]

const status = [
  { title: 'Pending', value: 'pending' },
  { title: 'Active', value: 'active' },
  { title: 'Inactive', value: 'inactive' },
  { title: 'Suspended', value: 'suspended' },
]

const resolveUserRoleVariant = (role) => {
  const roleLowerCase = role.toLowerCase()

  if (roleLowerCase === 'subscriber')
    return { color: 'success', icon: 'bx-user' }
  if (roleLowerCase === 'admin')
    return { color: 'primary', icon: 'bx-crown' }
  if (roleLowerCase === 'maintainer')
    return { color: 'info', icon: 'bx-pie-chart-alt' }
  if (roleLowerCase === 'editor')
    return { color: 'warning', icon: 'bx-edit' }
  if (roleLowerCase === 'author')
    return { color: 'error', icon: 'bx-desktop' }
  if (roleLowerCase === 'client')
    return { color: 'secondary', icon: 'bx-user' }

  return { color: 'success', icon: 'bx-user' }
}

const resolveUserStatusVariant = (stat) => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  if (statLowerCase === 'suspended')
    return 'error'

  return 'primary'
}

const isAddNewUserDrawerVisible = ref(false)

// 👉 Add new user
const addNewUser = async (userData) => {
  try {
    const { $api } = useApi()
    await $api('/api/admin/users', {
      method: 'POST',
      body: userData,
    })
    
    // Refetch User
    fetchPostgresUsers()
  } catch (error) {
    console.error('Error adding user:', error)
  }
}

// 👉 Delete user
const deleteUser = async (id) => {
  try {
    const { $api } = useApi()
    await $api(`/api/admin/users/${id}`, {
      method: 'DELETE',
    })

    // Delete from selectedRows
    const index = selectedRows.value.findIndex(row => row === id)
    if (index !== -1)
      selectedRows.value.splice(index, 1)

    // refetch User
    fetchPostgresUsers()
  } catch (error) {
    console.error('Error deleting user:', error)
  }
}

// Add watchers to refetch when filters change
watch([searchQuery, selectedRole, selectedPlan, selectedStatus, page, itemsPerPage], () => {
  console.log('Filter changed, refetching users...')
  fetchPostgresUsers()
}, { deep: true })

// Fetch PostgreSQL users on component mount
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
  }
})

// Helper function for prefix with plus
const prefixWithPlus = (value) => {
  return value > 0 ? `+${value}` : value
}
</script>

<template>
  <section>
    <VCard>
      <VCardText class="d-flex flex-wrap justify-space-between gap-4">
        <VTabs v-model="selectedStatus">
          <VTab value="">
            All
          </VTab>
          <VTab value="active">
            Active
          </VTab>
          <VTab value="inactive">
            Inactive
          </VTab>
          <VTab value="pending">
            Pending
          </VTab>
          <VTab value="suspended">
            Suspended
          </VTab>
        </VTabs>

        <!-- 👉 Export button -->
        <div class="me-3">
          <VBtn
            variant="outlined"
            color="secondary"
            prepend-icon="bx-export"
          >
            Export
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <VCardText class="d-flex flex-md-row flex-column justify-space-between gap-4">
        <AppSelect
          :model-value="itemsPerPage"
          :items="[
            { value: 10, title: '10' },
            { value: 25, title: '25' },
            { value: 50, title: '50' },
            { value: 100, title: '100' },
            { value: -1, title: 'All' },
          ]"
          style="inline-size: 5.5rem;"
          @update:model-value="itemsPerPage = parseInt($event, 10)"
        />

        <div class="d-flex align-start flex-column flex-sm-row flex-wrap gap-4">
          <!-- 👉 Search  -->
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search User"
            />
          </div>

          <!-- 👉 Select role -->
          <div style="inline-size: 9.375rem;">
            <AppSelect
              v-model="selectedRole"
              placeholder="Select Role"
              :items="roles"
              clearable
              clear-icon="bx-x"
            />
          </div>

          <!-- 👉 Select Plan -->
          <div style="inline-size: 9.375rem;">
            <AppSelect
              v-model="selectedPlan"
              placeholder="Select Plan"
              :items="plans"
              clearable
              clear-icon="bx-x"
            />
          </div>

          <!-- 👉 Create app button -->
          <div>
            <VBtn
              prepend-icon="bx-plus"
              @click="isAddNewUserDrawerVisible = true"
            >
              Add New User
            </VBtn>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- SECTION datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :items-per-page-options="[
          { value: 10, title: '10' },
          { value: 20, title: '20' },
          { value: 50, title: '50' },
          { value: -1, title: '$vuetify.dataFooter.itemsPerPageAll' },
        ]"
        :items="users"
        :items-length="totalUsers"
        :headers="headers"
        class="text-no-wrap"
        show-select
        @update:options="updateOptions"
      >
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
                  :to="{ name: 'apps-user-view-id', params: { id: item.id } }"
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
          <div class="d-flex align-items-center gap-4">
            <VIcon
              :color="resolveUserRoleVariant(item.role).color"
              :icon="resolveUserRoleVariant(item.role).icon"
              size="18"
            />
            <span class="text-capitalize">{{ item.role }}</span>
          </div>
        </template>

        <!-- Plan -->
        <template #item.plan="{ item }">
          <span class="text-capitalize">{{ item.currentPlan }}</span>
        </template>

        <!-- Billing -->
        <template #item.billing="{ item }">
          <span class="text-capitalize">{{ item.billing }}</span>
        </template>

        <!-- 👉 Status -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveUserStatusVariant(item.status)"
            size="small"
            class="text-capitalize"
          >
            {{ item.status }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <IconBtn @click="deleteUser(item.id)">
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
                <VListItem :to="{ name: 'apps-user-view-id', params: { id: item.id } }">
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