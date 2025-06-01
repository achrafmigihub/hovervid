<template>
  <VCard>
    <VCardTitle class="d-flex align-center flex-wrap">
      <span class="text-h5 me-2">Manage Your Sessions</span>
      <VSpacer />
      <VBtn
        color="primary"
        :loading="isLoading"
        @click="refreshData"
      >
        <VIcon start icon="tabler-refresh" />
        Refresh
      </VBtn>
    </VCardTitle>

    <VCardText>
      <VAlert
        v-if="error"
        type="error"
        class="mb-4"
        variant="tonal"
        closable
        @click:close="error = null"
      >
        {{ error }}
      </VAlert>

      <VRow>
        <VCol cols="12" md="8">
          <VCard variant="flat" border>
            <VCardTitle>Your Active Sessions</VCardTitle>
            <VCardText v-if="isLoading">
              <VProgressLinear indeterminate />
              <div class="mt-4 text-center">Loading sessions...</div>
            </VCardText>
            <VCardText v-else-if="sessions.length === 0">
              <VAlert type="info" variant="tonal">
                No active sessions found. You are currently using your only active session.
              </VAlert>
            </VCardText>
            <template v-else>
              <VList lines="two">
                <VListItem
                  v-for="session in sessions"
                  :key="session.id"
                  :subtitle="formatLastActivity(session.last_activity)"
                  :class="session.is_current ? 'bg-primary-subtle' : ''"
                >
                  <template #prepend>
                    <VAvatar color="primary" variant="tonal">
                      <VIcon
                        :icon="getDeviceIcon(session.user_agent)"
                      />
                    </VAvatar>
                  </template>

                  <template #title>
                    <div class="d-flex align-center">
                      <span>{{ getBrowserName(session.user_agent) }}</span>
                      <VChip
                        v-if="session.is_current"
                        color="primary"
                        size="small"
                        class="ml-2"
                      >
                        Current Session
                      </VChip>
                    </div>
                  </template>

                  <template #append>
                    <VChip
                      v-if="!session.is_active"
                      color="error"
                      size="small"
                      class="me-2"
                    >
                      Inactive
                    </VChip>
                    <VBtn
                      v-if="session.is_active && !session.is_current"
                      color="error"
                      variant="text"
                      size="small"
                      icon
                      @click="endSession(session.id)"
                    >
                      <VIcon icon="tabler-power" />
                    </VBtn>
                  </template>

                  <template #subtitle>
                    <div class="d-flex flex-column">
                      <span>{{ session.ip_address }}</span>
                      <span>{{ formatLastActivity(session.last_activity) }}</span>
                    </div>
                  </template>
                </VListItem>
              </VList>
            </template>
            <VDivider />
            <VCardActions>
              <VBtn
                color="error"
                variant="outlined"
                :disabled="!hasOtherSessions || isLoading"
                @click="confirmEndOtherSessions"
              >
                <VIcon start icon="tabler-logout" />
                End All Other Sessions
              </VBtn>
              <VSpacer />
              <VBtn
                color="primary"
                variant="outlined"
                @click="refreshCurrentSession"
                :loading="refreshingCurrentSession"
              >
                <VIcon start icon="tabler-rotate-clockwise" />
                Refresh Current Session
              </VBtn>
            </VCardActions>
          </VCard>
        </VCol>

        <VCol cols="12" md="4">
          <VCard variant="flat" border>
            <VCardTitle>Session Statistics</VCardTitle>
            <VCardText v-if="isStatsLoading">
              <VProgressLinear indeterminate />
              <div class="mt-4 text-center">Loading statistics...</div>
            </VCardText>
            <VCardText v-else-if="stats">
              <div class="d-flex flex-column gap-4">
                <VListItem>
                  <template #prepend>
                    <VAvatar color="success" variant="tonal">
                      <VIcon icon="tabler-check" />
                    </VAvatar>
                  </template>
                  <template #title>
                    Active Sessions
                  </template>
                  <template #subtitle>
                    {{ stats.total_active_sessions || 0 }}
                  </template>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VAvatar color="error" variant="tonal">
                      <VIcon icon="tabler-x" />
                    </VAvatar>
                  </template>
                  <template #title>
                    Inactive Sessions
                  </template>
                  <template #subtitle>
                    {{ stats.total_inactive_sessions || 0 }}
                  </template>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VAvatar color="info" variant="tonal">
                      <VIcon icon="tabler-devices" />
                    </VAvatar>
                  </template>
                  <template #title>
                    Unique Devices
                  </template>
                  <template #subtitle>
                    {{ stats.unique_devices || 0 }}
                  </template>
                </VListItem>
              </div>
            </VCardText>
            <VCardText v-else>
              <VAlert type="info" variant="tonal">
                No statistics available
              </VAlert>
            </VCardText>
          </VCard>

          <VCard v-if="stats?.recent_activity?.length" class="mt-4" variant="flat" border>
            <VCardTitle>Recent Activity</VCardTitle>
            <VCardText>
              <VTimeline density="compact" align="start">
                <VTimelineItem
                  v-for="(activity, index) in stats.recent_activity"
                  :key="index"
                  :dot-color="activity.is_active ? 'success' : 'error'"
                  size="small"
                >
                  <template #opposite>
                    {{ formatTimeAgo(activity.last_activity) }}
                  </template>
                  <VCard variant="text">
                    <VCardText>
                      <div class="text-caption">IP: {{ activity.ip_address }}</div>
                      <div class="text-caption">{{ formatDateTime(activity.last_activity) }}</div>
                    </VCardText>
                  </VCard>
                </VTimelineItem>
              </VTimeline>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>

    <VDialog v-model="endOtherSessionsDialog" max-width="500">
      <VCard>
        <VCardTitle>End All Other Sessions?</VCardTitle>
        <VCardText>
          Are you sure you want to end all your other active sessions? This will log you out from all other devices and browsers.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn color="default" variant="text" @click="endOtherSessionsDialog = false">
            Cancel
          </VBtn>
          <VBtn color="error" variant="elevated" @click="endOtherSessions" :loading="endingOtherSessions">
            End All Other Sessions
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

<script setup>
import sessionService from '@/services/sessionService'
import { formatDistance, parseISO } from 'date-fns'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toast-notification'

// Toast notifications
const $toast = useToast()

// Session store
const sessionStore = sessionService.getStore()

// Component state
const sessions = computed(() => sessionStore.sortedSessions)
const isLoading = computed(() => sessionStore.isLoading)
const error = ref(null)
const stats = ref(null)
const isStatsLoading = ref(false)
const refreshingCurrentSession = ref(false)
const endingOtherSessions = ref(false)
const endOtherSessionsDialog = ref(false)

// Computed values
const hasOtherSessions = computed(() => {
  if (!sessions.value) return false
  return sessions.value.some(s => s.is_active && !s.is_current)
})

// Fetch sessions data
const fetchSessions = async () => {
  try {
    error.value = null
    await sessionStore.fetchSessions()
  } catch (err) {
    error.value = err.message || 'Failed to fetch sessions'
  }
}

// Fetch session statistics
const fetchStats = async () => {
  try {
    isStatsLoading.value = true
    stats.value = await sessionService.getSessionStats()
  } catch (err) {
    error.value = err.message || 'Failed to fetch session statistics'
  } finally {
    isStatsLoading.value = false
  }
}

// Refresh all data
const refreshData = async () => {
  await Promise.all([fetchSessions(), fetchStats()])
}

// End a specific session
const endSession = async (sessionId) => {
  try {
    await sessionService.endSession(sessionId)
    $toast.success('Session ended successfully')
  } catch (err) {
    error.value = err.message || 'Failed to end session'
    $toast.error(error.value)
  }
}

// Confirm ending other sessions
const confirmEndOtherSessions = () => {
  endOtherSessionsDialog.value = true
}

// End all other sessions
const endOtherSessions = async () => {
  try {
    endingOtherSessions.value = true
    await sessionService.endOtherSessions()
    endOtherSessionsDialog.value = false
    $toast.success('All other sessions ended successfully')
    await refreshData()
  } catch (err) {
    error.value = err.message || 'Failed to end other sessions'
    $toast.error(error.value)
  } finally {
    endingOtherSessions.value = false
  }
}

// Refresh current session
const refreshCurrentSession = async () => {
  try {
    refreshingCurrentSession.value = true
    await sessionService.refreshSession()
    $toast.success('Session refreshed successfully')
    await refreshData()
  } catch (err) {
    error.value = err.message || 'Failed to refresh session'
    $toast.error(error.value)
  } finally {
    refreshingCurrentSession.value = false
  }
}

// Utility functions
const formatLastActivity = (dateString) => {
  try {
    return `Last active ${formatDistance(parseISO(dateString), new Date(), { addSuffix: true })}`
  } catch (err) {
    return 'Unknown'
  }
}

const formatTimeAgo = (dateString) => {
  try {
    return formatDistance(parseISO(dateString), new Date(), { addSuffix: true })
  } catch (err) {
    return 'Unknown'
  }
}

const formatDateTime = (dateString) => {
  try {
    const date = parseISO(dateString)
    return date.toLocaleString()
  } catch (err) {
    return 'Unknown'
  }
}

const getBrowserName = (userAgent) => {
  if (!userAgent) return 'Unknown Device'
  
  if (userAgent.includes('Firefox')) return 'Firefox'
  if (userAgent.includes('Chrome')) return 'Chrome'
  if (userAgent.includes('Safari')) return 'Safari'
  if (userAgent.includes('Edge')) return 'Edge'
  if (userAgent.includes('MSIE') || userAgent.includes('Trident')) return 'Internet Explorer'
  if (userAgent.includes('Opera') || userAgent.includes('OPR')) return 'Opera'
  
  return 'Unknown Browser'
}

const getDeviceIcon = (userAgent) => {
  if (!userAgent) return 'tabler-device-unknown'
  
  if (userAgent.includes('Mobile')) return 'tabler-device-mobile'
  if (userAgent.includes('Tablet')) return 'tabler-device-tablet'
  if (userAgent.includes('iPad')) return 'tabler-device-ipad'
  if (userAgent.includes('iPhone')) return 'tabler-device-mobile'
  if (userAgent.includes('Android')) return 'tabler-brand-android'
  if (userAgent.includes('Windows')) return 'tabler-brand-windows'
  if (userAgent.includes('Mac')) return 'tabler-brand-apple'
  if (userAgent.includes('Linux')) return 'tabler-brand-linux'
  
  return 'tabler-device-laptop'
}

// Initialize component
onMounted(async () => {
  await refreshData()
})
</script> 
