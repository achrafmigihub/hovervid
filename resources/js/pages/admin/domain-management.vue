<template>
  <section>
    <VCard title="Domain Management">
      <VCardText>
        <div class="d-flex flex-wrap py-4 gap-4">
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search Domain"
            />
          </div>

          <VSpacer />

          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="bx-export"
          >
            Export
          </VBtn>

          <VBtn
            variant="tonal"
            color="primary"
            prepend-icon="bx-refresh"
            @click="fetchDomains"
            :loading="isLoading"
          >
            Refresh
          </VBtn>

          <VBtn
            prepend-icon="bx-plus"
            @click="isAddNewDomainDrawerVisible = true"
          >
            Add New Domain
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- Domains Table -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="domains"
        item-value="id"
        :items-length="totalDomains"
        :headers="headers"
        :loading="isLoading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- No data message -->
        <template #no-data>
          <div class="d-flex align-center justify-center flex-column pa-8">
            <VIcon
              :icon="fetchError ? 'bx-error-circle' : 'bx-world'"
              size="64"
              :color="fetchError ? 'error' : 'primary'"
              class="mb-4"
            />
            <h3 class="text-h6 text-center mb-2">
              {{ fetchError ? 'Error Loading Domains' : 'No Domains Found' }}
            </h3>
            <p v-if="fetchError" class="text-error text-center mb-4">
              {{ fetchError }}
            </p>
            <p v-else class="text-medium-emphasis text-center mb-4">
              {{ searchQuery ? `No domains match your search "${searchQuery}"` : 'Get started by adding your first domain to the system.' }}
            </p>
            <VBtn
              v-if="fetchError"
              color="primary"
              variant="outlined"
              prepend-icon="bx-refresh"
              @click="fetchDomains"
              :loading="isLoading"
            >
              Try Again
            </VBtn>
            <VBtn
              v-else-if="!searchQuery"
              color="primary"
              prepend-icon="bx-plus"
              @click="isAddNewDomainDrawerVisible = true"
            >
              Add First Domain
            </VBtn>
          </div>
        </template>
        
        <!-- Domain Name -->
        <template #item.domain="{ item }">
          <div class="d-flex align-center">
            <h6 class="text-base">
              <a
                :href="`https://${item.domain}`"
                target="_blank"
                class="font-weight-medium text-link"
              >
                {{ item.domain }}
              </a>
            </h6>
          </div>
        </template>

        <!-- Owner -->
        <template #item.owner="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              size="34"
              variant="tonal"
              :color="resolveUserRoleVariant(item.user_role).color"
            >
              <VIcon icon="bx-user" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h6 class="text-base">
                <RouterLink
                  :to="{ path: `/admin/users/${item.user_id}` }"
                  class="font-weight-medium text-link"
                >
                  {{ item.owner_name || 'Unknown' }}
                </RouterLink>
              </h6>
              <div class="text-sm">
                {{ item.owner_email || 'No email' }}
              </div>
            </div>
          </div>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveDomainStatusVariant(item.status)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.status }}
          </VChip>
        </template>

        <!-- Verification Status -->
        <template #item.is_verified="{ item }">
          <VChip
            :color="item.is_verified ? 'success' : 'warning'"
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.is_verified ? 'Verified' : 'Unverified' }}
          </VChip>
        </template>

        <!-- Creation Date -->
        <template #item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>

        <!-- Platform -->
        <template #item.platform="{ item }">
          <VChip
            :color="item.platform === 'wordpress' ? 'primary' : 'secondary'"
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.platform }}
          </VChip>
        </template>

        <!-- License Count -->
        <template #item.license_count="{ item }">
          <VBadge
            :content="item.license_count"
            color="primary"
            location="top end"
          >
            <VIcon icon="bx-key" />
          </VBadge>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <VBtn 
            v-if="item.status === 'inactive'"
            color="success" 
            size="small" 
            variant="tonal"
            :loading="activating === item.id"
            @click="activateDomain(item.id)"
          >
            Activate
          </VBtn>
          
          <VBtn 
            v-else-if="item.status === 'active'"
            color="error" 
            size="small" 
            variant="tonal"
            :loading="deactivating === item.id"
            @click="deactivateDomain(item.id)"
          >
            Deactivate
          </VBtn>

          <IconBtn @click="confirmDeleteDomain(item.id)" class="ml-2">
            <VIcon icon="bx-trash" />
          </IconBtn>

          <IconBtn @click="editDomain(item)">
            <VIcon icon="bx-pencil" />
          </IconBtn>

          <VBtn
            icon
            variant="text"
            color="medium-emphasis"
          >
            <VIcon icon="bx-dots-vertical-rounded" />
            <VMenu activator="parent">
              <VList>
                <VListItem @click="renewDomain(item.id)">
                  <template #prepend>
                    <VIcon icon="bx-rotate-right" />
                  </template>
                  <VListItemTitle>Renew License</VListItemTitle>
                </VListItem>
                <VListItem v-if="!item.is_verified" @click="verifyDomain(item.id)">
                  <template #prepend>
                    <VIcon icon="bx-check-circle" />
                  </template>
                  <VListItemTitle>Verify Domain</VListItemTitle>
                </VListItem>
                <VListItem v-if="item.is_verified" @click="viewPlugin(item.id)">
                  <template #prepend>
                    <VIcon icon="bx-plugin" />
                  </template>
                  <VListItemTitle>View Plugin Status</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </VBtn>
        </template>

        <!-- Pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalDomains"
          />
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Delete Domain Confirmation Dialog -->
    <VDialog
      v-model="deleteDialog"
      persistent
      max-width="500"
    >
      <VCard>
        <VCardTitle class="text-h5">
          Delete Domain
        </VCardTitle>
        <VCardText>
          <p class="mb-2">Are you sure you want to delete this domain?</p>
          <p class="font-weight-medium" v-if="domainToDelete">{{ domainToDelete.domain }}</p>
          
          <VDivider class="my-4" />
          
          <div class="mt-4">
            <p class="text-caption text-error">
              <strong>Warning:</strong> This action cannot be undone and may affect associated services.
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
            @click="deleteDomain"
            :loading="isLoading"
          >
            Delete Domain
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

<script setup>
import { format, isAfter, parseISO, subDays } from 'date-fns'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const searchQuery = ref('')
const isLoading = ref(false)
const fetchError = ref(null)

// Action status trackers
const activating = ref(null)
const deactivating = ref(null)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('created_at')
const orderBy = ref('desc')

// Headers for the data table
const headers = [
  {
    title: 'Domain',
    key: 'domain',
  },
  {
    title: 'Owner',
    key: 'owner',
  },
  {
    title: 'Platform',
    key: 'platform',
  },
  {
    title: 'Plugin Status',
    key: 'status',
  },
  {
    title: 'Verification',
    key: 'is_verified',
  },
  {
    title: 'Created',
    key: 'created_at',
  },
  {
    title: 'Licenses',
    key: 'license_count',
  },
  {
    title: 'Actions',
    key: 'actions',
    sortable: false,
  },
]

// Domains data for display
const domainsData = ref({
  domains: [],
  totalDomains: 0
})

// Computed properties
const domains = computed(() => domainsData.value.domains || [])
const totalDomains = computed(() => domainsData.value.totalDomains || 0)

// Dialog states
const deleteDialog = ref(false)
const domainToDelete = ref(null)
const isAddNewDomainDrawerVisible = ref(false)

// Notification states
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

// Update options for data table
const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key || 'created_at'
  orderBy.value = options.sortBy[0]?.order || 'desc'
  fetchDomains()
}

// Format date for display
const formatDate = dateString => {
  if (!dateString) return 'N/A'
  try {
    return format(new Date(dateString), 'MMM dd, yyyy')
  } catch (error) {
    return 'Invalid Date'
  }
}

// Check if a license is expiring soon (within 7 days)
const isExpiringSoon = dateString => {
  if (!dateString) return false
  try {
    const expiryDate = parseISO(dateString)
    const today = new Date()
    const sevenDaysLater = subDays(expiryDate, 7)
    
    return isAfter(today, sevenDaysLater)
  } catch (error) {
    return false
  }
}

// Functions for domain statuses (now handling plugin_status)
const resolveDomainStatusVariant = status => {
  const statusLower = (status || '').toLowerCase()
  if (statusLower === 'active') return 'success'
  if (statusLower === 'inactive') return 'secondary'
  if (statusLower === 'pending') return 'warning'
  if (statusLower === 'suspended') return 'error'
  if (statusLower === 'disabled') return 'error'
  return 'secondary' // default for unknown statuses
}

// Functions for user roles
const resolveUserRoleVariant = role => {
  const roleLowerCase = (role || '').toLowerCase()
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

// Function to fetch domains from the backend API
const fetchDomains = async () => {
  try {
    isLoading.value = true
    fetchError.value = null
    
    // Build query parameters
    const params = new URLSearchParams()
    if (searchQuery.value) params.append('search', searchQuery.value)
    if (itemsPerPage.value) params.append('per_page', itemsPerPage.value.toString())
    if (page.value) params.append('page', page.value.toString())
    if (sortBy.value) params.append('sort_by', sortBy.value)
    if (orderBy.value) params.append('sort_dir', orderBy.value.toLowerCase())
    
    // Make API call to fetch domains using admin endpoint
    const response = await fetch(`/api/admin/domains?${params.toString()}`, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      domainsData.value = {
        domains: data.data || [],
        totalDomains: data.meta?.total || 0
      }
      fetchError.value = null
      
      // Show friendly message if no domains found
      if (data.data && data.data.length === 0 && data.message) {
        console.log(data.message)
      }
    } else {
      console.error('Error fetching domains:', data.message)
      fetchError.value = data.message || 'Failed to load domains'
      
      // If error, use empty array
      domainsData.value = {
        domains: [],
        totalDomains: 0
      }
    }
  } catch (error) {
    console.error('Error fetching domains:', error)
    fetchError.value = error.message || 'Failed to connect to server'
    
    // If error, use empty array
    domainsData.value = {
      domains: [],
      totalDomains: 0
    }
  } finally {
    isLoading.value = false
  }
}

// Function to activate a domain
const activateDomain = async (domainId) => {
  try {
    activating.value = domainId
    
    // Call API to activate domain using admin endpoint
    const response = await fetch(`/api/admin/domains/${domainId}/activate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      // Update the domain status in our local data
      const domain = domains.value.find(d => d.id === domainId)
      if (domain) {
        domain.status = 'active'
        domain.is_active = true
      }
      
      // Show success message
      snackbarColor.value = 'success'
      snackbarText.value = 'Domain activated successfully!'
      snackbar.value = true
    } else {
      // Show error message
      snackbarColor.value = 'error'
      snackbarText.value = `Failed to activate domain: ${data.message}`
      snackbar.value = true
    }
  } catch (error) {
    console.error('Error activating domain:', error)
    snackbarColor.value = 'error'
    snackbarText.value = `Error: ${error.message}`
    snackbar.value = true
  } finally {
    activating.value = null
  }
}

// Function to deactivate a domain
const deactivateDomain = async (domainId) => {
  try {
    deactivating.value = domainId
    
    // Call API to deactivate domain using admin endpoint
    const response = await fetch(`/api/admin/domains/${domainId}/deactivate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      // Update the domain status in our local data
      const domain = domains.value.find(d => d.id === domainId)
      if (domain) {
        domain.status = 'inactive'
        domain.is_active = false
      }
      
      // Show success message
      snackbarColor.value = 'success'
      snackbarText.value = 'Domain deactivated successfully!'
      snackbar.value = true
    } else {
      // Show error message
      snackbarColor.value = 'error'
      snackbarText.value = `Failed to deactivate domain: ${data.message}`
      snackbar.value = true
    }
  } catch (error) {
    console.error('Error deactivating domain:', error)
    snackbarColor.value = 'error'
    snackbarText.value = `Error: ${error.message}`
    snackbar.value = true
  } finally {
    deactivating.value = null
  }
}

// Function to confirm domain deletion
const confirmDeleteDomain = (domainId) => {
  const domain = domains.value.find(d => d.id === domainId)
  if (domain) {
    domainToDelete.value = {
      id: domainId,
      domain: domain.domain
    }
    deleteDialog.value = true
  }
}

// Function to delete domain
const deleteDomain = async () => {
  if (!domainToDelete.value) return
  
  try {
    isLoading.value = true
    
    // Call API to delete domain using admin endpoint
    const response = await fetch(`/api/admin/domains/${domainToDelete.value.id}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      // Remove domain from our local data
      const index = domainsData.value.domains.findIndex(d => d.id === domainToDelete.value.id)
      if (index !== -1) {
        domainsData.value.domains.splice(index, 1)
        domainsData.value.totalDomains--
      }
      
      // Show success message
      snackbarColor.value = 'success'
      snackbarText.value = `Domain ${domainToDelete.value.domain} deleted successfully!`
      snackbar.value = true
    } else {
      // Show error message
      snackbarColor.value = 'error'
      snackbarText.value = `Failed to delete domain: ${data.message}`
      snackbar.value = true
    }
  } catch (error) {
    console.error('Error deleting domain:', error)
    snackbarColor.value = 'error'
    snackbarText.value = `Error: ${error.message}`
    snackbar.value = true
  } finally {
    isLoading.value = false
    deleteDialog.value = false
    domainToDelete.value = null
  }
}

// Function to edit domain
const editDomain = (domain) => {
  // Implementation would open an edit dialog or navigate to an edit page
  console.log('Edit domain:', domain)
  snackbarColor.value = 'info'
  snackbarText.value = `Editing domain ${domain.domain}`
  snackbar.value = true
}

// Function to renew domain license
const renewDomain = (domainId) => {
  const domain = domains.value.find(d => d.id === domainId)
  if (domain) {
    // Implementation would open a renewal dialog or process
    console.log('Renew domain:', domain)
    snackbarColor.value = 'info'
    snackbarText.value = `Renewing license for ${domain.domain}`
    snackbar.value = true
  }
}

// Function to verify domain
const verifyDomain = async (domainId) => {
  try {
    isLoading.value = true
    
    const domain = domains.value.find(d => d.id === domainId)
    if (!domain) return
    
    // Call verification API
    const response = await fetch(`/api/domains/${domainId}/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`)
    }
    
    const data = await response.json()
    
    if (data.success) {
      // Update domain verification status
      domain.is_verified = true
      
      // Show success message
      snackbarColor.value = 'success'
      snackbarText.value = `Domain ${domain.domain} verified successfully!`
      snackbar.value = true
    } else {
      // Show error message
      snackbarColor.value = 'error'
      snackbarText.value = `Failed to verify domain: ${data.message}`
      snackbar.value = true
    }
  } catch (error) {
    console.error('Error verifying domain:', error)
    snackbarColor.value = 'error'
    snackbarText.value = `Error: ${error.message}`
    snackbar.value = true
  } finally {
    isLoading.value = false
  }
}

// Function to view plugin status
const viewPlugin = (domainId) => {
  const domain = domains.value.find(d => d.id === domainId)
  if (domain) {
    // Implementation would navigate to plugin status page
    console.log('View plugin status for domain:', domain)
    snackbarColor.value = 'info'
    snackbarText.value = `Viewing plugin status for ${domain.domain}`
    snackbar.value = true
  }
}

// Fetch domains on component mount
onMounted(() => {
  fetchDomains()
})
</script>

<style lang="scss">
.text-capitalize {
  text-transform: capitalize;
}
</style> 
