<template>
  <div>
    <!-- Alert notification popup -->
    <VSnackbar
      v-model="showNewAlert"
      :timeout="8000"
      location="top"
      color="error"
      variant="tonal"
    >
      <VRow align="center">
        <VCol cols="auto">
          <VIcon icon="tabler-shield-alert" />
        </VCol>
        <VCol>
          <div>
            <strong>Security Alert</strong>
            <div>{{ latestAlert?.message }}</div>
          </div>
        </VCol>
        <VCol cols="auto">
          <VBtn
            color="error"
            variant="text"
            @click="viewSecurityCenter"
          >
            View Details
          </VBtn>
          <VBtn
            color="default"
            variant="text"
            @click="dismissLatestAlert"
          >
            Dismiss
          </VBtn>
        </VCol>
      </VRow>
    </VSnackbar>
    
    <!-- Security Alert Indicator (shown when there are unacknowledged alerts) -->
    <VMenu activator="parent" v-if="hasUnacknowledgedAlerts">
      <VCard min-width="350" max-width="400">
        <VCardTitle class="d-flex align-center">
          <VIcon icon="tabler-shield-alert" class="mr-2" color="error" />
          Security Alerts
          <VSpacer />
          <VChip color="error" size="small">{{ unacknowledgedCount }}</VChip>
        </VCardTitle>
        
        <VDivider />
        
        <VList>
          <VListItem v-for="alert in recentAlerts" :key="alert.id">
            <VListItemTitle>{{ alert.message }}</VListItemTitle>
            <VListItemSubtitle>
              {{ formatTimestamp(alert.timestamp) }}
            </VListItemSubtitle>
            <template #append>
              <VBtn
                size="small"
                icon
                variant="text"
                @click="acknowledgeAlert(alert.id)"
              >
                <VIcon icon="tabler-check" />
              </VBtn>
            </template>
          </VListItem>
        </VList>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            color="primary"
            variant="text"
            @click="viewSecurityCenter"
          >
            View All
          </VBtn>
          <VBtn
            color="error"
            variant="text"
            @click="acknowledgeAllAlerts"
          >
            Dismiss All
          </VBtn>
        </VCardActions>
      </VCard>
    </VMenu>
  </div>
</template>

<script setup>
import sessionService from '@/services/sessionService'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const sessionStore = sessionService.getStore()

// Component state
const showNewAlert = ref(false)
const latestAlert = ref(null)

// Get all security alerts
const securityAlerts = computed(() => sessionStore.securityAlerts || [])

// Filter to only get unacknowledged alerts
const unacknowledgedAlerts = computed(() => {
  return securityAlerts.value.filter(alert => !alert.acknowledged)
})

// Get count of unacknowledged alerts
const unacknowledgedCount = computed(() => {
  return unacknowledgedAlerts.value.length
})

// Check if there are any unacknowledged alerts
const hasUnacknowledgedAlerts = computed(() => {
  return unacknowledgedCount.value > 0
})

// Get the 5 most recent alerts
const recentAlerts = computed(() => {
  return [...unacknowledgedAlerts.value]
    .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp))
    .slice(0, 5)
})

// Format timestamp to relative time
const formatTimestamp = (timestamp) => {
  const date = new Date(timestamp)
  const now = new Date()
  const diffMs = now - date
  
  // Less than a minute
  if (diffMs < 60000) {
    return 'Just now'
  }
  
  // Less than an hour
  if (diffMs < 3600000) {
    const minutes = Math.floor(diffMs / 60000)
    return `${minutes} minute${minutes > 1 ? 's' : ''} ago`
  }
  
  // Less than a day
  if (diffMs < 86400000) {
    const hours = Math.floor(diffMs / 3600000)
    return `${hours} hour${hours > 1 ? 's' : ''} ago`
  }
  
  // Otherwise return date string
  return date.toLocaleString()
}

// Methods
const acknowledgeAlert = (alertId) => {
  sessionService.acknowledgeAlert(alertId)
}

const acknowledgeAllAlerts = () => {
  unacknowledgedAlerts.value.forEach(alert => {
    sessionService.acknowledgeAlert(alert.id)
  })
}

const dismissLatestAlert = () => {
  if (latestAlert.value) {
    acknowledgeAlert(latestAlert.value.id)
  }
  showNewAlert.value = false
}

const viewSecurityCenter = () => {
  router.push('/account/security')
  showNewAlert.value = false
}

// Handle new security alerts
const handleSecurityAlert = (event) => {
  // Update the latest alert
  latestAlert.value = {
    id: Date.now(),
    type: event.detail.type,
    message: event.detail.message,
    timestamp: new Date().toISOString()
  }
  
  // Show the alert
  showNewAlert.value = true
}

// Lifecycle hooks
onMounted(() => {
  // Listen for security alerts
  window.addEventListener('security-alert', handleSecurityAlert)
  
  // Check existing alerts
  if (unacknowledgedAlerts.value.length > 0) {
    latestAlert.value = unacknowledgedAlerts.value[0]
    showNewAlert.value = true
  }
})

onUnmounted(() => {
  window.removeEventListener('security-alert', handleSecurityAlert)
})
</script> 
