import { useAuthStore } from '@/stores/useAuthStore'
import { getBrowserFingerprint } from '@/utils/fingerprint'
import axios from 'axios'
import { defineStore } from 'pinia'

// Define the Pinia store for session management
export const useSessionStore = defineStore('session', {
  state: () => ({
    sessions: [],
    currentSessionId: null,
    isLoading: false,
    error: null,
    stats: null,
    sessionTimeoutWarning: false,
    timeoutWarningThreshold: 5 * 60 * 1000, // 5 minutes before expiry
    timeoutCheckInterval: null,
    securityAlerts: [],
    currentFingerprint: null,
    lastKnownIp: null,
    rateLimitStatus: {
      limitReached: false,
      resetTime: null
    }
  }),

  getters: {
    // Get current session
    currentSession: (state) => {
      return state.sessions.find(session => session.id === state.currentSessionId) || null
    },
    
    // Check if there are any active sessions
    hasActiveSessions: (state) => {
      return state.sessions.some(session => session.is_active === true)
    },
    
    // Get number of active sessions
    activeSessionsCount: (state) => {
      return state.sessions.filter(session => session.is_active === true).length
    },
    
    // Get all sessions sorted by last activity (most recent first)
    sortedSessions: (state) => {
      return [...state.sessions].sort((a, b) => {
        return new Date(b.last_activity) - new Date(a.last_activity)
      })
    },
    
    // Get session expiration time
    sessionExpiresAt: (state) => {
      const currentSession = state.currentSession
      return currentSession?.expires_at ? new Date(currentSession.expires_at) : null
    },
    
    // Check if session is about to expire
    isSessionAboutToExpire: (state) => {
      const currentSession = state.currentSession
      if (!currentSession?.expires_at) return false
      
      const expiresAt = new Date(currentSession.expires_at)
      const now = new Date()
      const timeUntilExpiry = expiresAt.getTime() - now.getTime()
      
      return timeUntilExpiry > 0 && timeUntilExpiry <= state.timeoutWarningThreshold
    },
    
    // Get the current security alert count
    securityAlertCount: (state) => {
      return state.securityAlerts.filter(alert => !alert.acknowledged).length
    },
    
    // Check if there are any unacknowledged security alerts
    hasUnacknowledgedAlerts: (state) => {
      return state.securityAlerts.some(alert => !alert.acknowledged)
    }
  },

  actions: {
    // Initialize the session store and start monitoring
    async init() {
      console.log('Initializing session store')
      
      // Generate browser fingerprint for security
      this.currentFingerprint = await getBrowserFingerprint()
      console.log('Browser fingerprint generated', this.currentFingerprint.hash)
      
      this.startSessionMonitoring()
      
      // Set up event handlers for auth events
      const authStore = useAuthStore()
      
      // Watch for login events
      if (authStore.isAuthenticated) {
        this.fetchSessions()
      }
      
      // Listen for security notices in the session
      this.checkSecurityNotices()
    },
    
    // Check for security notices from the backend
    async checkSecurityNotices() {
      const authStore = useAuthStore()
      
      if (!authStore.isAuthenticated) return
      
      try {
        // Check if there is a security notice in the session data
        if (authStore.user && authStore.user.security_notice) {
          this.handleSecurityNotice(authStore.user.security_notice)
        }
      } catch (error) {
        console.error('Error checking security notices:', error)
      }
    },
    
    // Handle security notice from the backend
    handleSecurityNotice(notice) {
      if (!notice) return
      
      // Add the alert to the security alerts
      this.securityAlerts.push({
        id: Date.now(),
        type: notice.type || 'suspicious_activity',
        message: notice.message || 'Unusual activity has been detected on your account.',
        timestamp: notice.detected_at || new Date().toISOString(),
        details: notice.details || {},
        acknowledged: false,
        severity: notice.severity || 'warning'
      })
      
      // Emit an event for components to react to
      window.dispatchEvent(new CustomEvent('security-alert', {
        detail: {
          type: notice.type,
          message: notice.message
        }
      }))
    },
    
    // Acknowledge a security alert
    acknowledgeAlert(alertId) {
      const alertIndex = this.securityAlerts.findIndex(alert => alert.id === alertId)
      if (alertIndex !== -1) {
        this.securityAlerts[alertIndex].acknowledged = true
      }
    },
    
    // Clear all acknowledged alerts
    clearAcknowledgedAlerts() {
      this.securityAlerts = this.securityAlerts.filter(alert => !alert.acknowledged)
    },
    
    // Start monitoring session timeouts
    startSessionMonitoring() {
      // Clear any existing interval
      if (this.timeoutCheckInterval) {
        clearInterval(this.timeoutCheckInterval)
      }
      
      // Check for session timeout every minute
      this.timeoutCheckInterval = setInterval(() => {
        this.checkSessionTimeout()
      }, 60 * 1000) // Every minute
      
      // Do an initial check
      this.checkSessionTimeout()
    },
    
    // Check if the session is about to expire
    checkSessionTimeout() {
      // Don't check if we don't have sessions loaded yet
      if (!this.sessions || !this.sessions.length) return
      
      const currentSession = this.currentSession
      if (!currentSession?.expires_at) return
      
      const expiresAt = new Date(currentSession.expires_at)
      const now = new Date()
      const timeUntilExpiry = expiresAt.getTime() - now.getTime()
      
      // If session is about to expire (within warning threshold), show warning
      if (timeUntilExpiry > 0 && timeUntilExpiry <= this.timeoutWarningThreshold) {
        this.sessionTimeoutWarning = true
        
        // Emit a custom event that can be listened to by components
        window.dispatchEvent(new CustomEvent('session-timeout-warning', {
          detail: {
            expiresAt: expiresAt,
            timeUntilExpiry: timeUntilExpiry
          }
        }))
        
        console.warn(`Session will expire in ${Math.round(timeUntilExpiry / 1000)} seconds`)
      } else {
        this.sessionTimeoutWarning = false
      }
      
      // If session has expired, redirect to login
      if (timeUntilExpiry <= 0) {
        console.warn('Session has expired')
        const authStore = useAuthStore()
        authStore.logout(true) // Silent logout
        window.location.href = '/login?expired=true'
      }
    },
    
    // Fetch all active sessions for the current user
    async fetchSessions() {
      this.isLoading = true
      this.error = null
      
      try {
        const response = await axios.get('/api/sessions')
        
        // Initialize sessions array with default value if needed
        this.sessions = response.data.sessions || []
        this.currentSessionId = response.data.current_session_id
        
        console.log('Fetched sessions:', this.sessions.length ? this.sessions.length : 'none')
        
        // Only start monitoring if we have valid session data
        if (this.sessions && this.sessions.length > 0) {
          // Start monitoring once we have session data
          this.startSessionMonitoring()
          
          // Store information for security checks
          const currentSession = this.sessions.find(s => s.id === this.currentSessionId)
          if (currentSession) {
            this.lastKnownIp = currentSession.ip_address
            
            // Check for security notices
            if (currentSession.security_notice) {
              this.handleSecurityNotice(currentSession.security_notice)
            }
          }
          
          // Check for IP changes across sessions
          this.detectIpChanges()
        } else {
          console.warn('No active sessions found for the current user')
        }
        
        // Check for rate limit headers
        this.checkRateLimit(response.headers)
        
        return response.data
      } catch (error) {
        // Initialize sessions array to empty on error
        this.sessions = []
        this.handleApiError(error, 'fetch sessions')
        throw error
      } finally {
        this.isLoading = false
      }
    },
    
    // Detect IP changes across sessions
    detectIpChanges() {
      if (!this.sessions || this.sessions.length < 2) return
      
      // Get unique IPs from recent sessions
      const last24Hours = new Date()
      last24Hours.setHours(last24Hours.getHours() - 24)
      
      const recentSessions = this.sessions.filter(s => {
        const activityTime = new Date(s.last_activity)
        return activityTime > last24Hours
      })
      
      const uniqueIps = [...new Set(recentSessions.map(s => s.ip_address))]
      
      // If there are more than 3 unique IPs in the last 24 hours, raise an alert
      if (uniqueIps.length > 3) {
        this.securityAlerts.push({
          id: Date.now(),
          type: 'multiple_ip_addresses',
          message: 'Your account has been accessed from multiple IP addresses in the last 24 hours.',
          timestamp: new Date().toISOString(),
          details: { ips: uniqueIps },
          acknowledged: false,
          severity: 'warning'
        })
        
        window.dispatchEvent(new CustomEvent('security-alert', {
          detail: {
            type: 'multiple_ip_addresses',
            message: 'Your account has been accessed from multiple locations.'
          }
        }))
      }
    },
    
    // Check for rate limit headers in API responses
    checkRateLimit(headers) {
      if (!headers) return
      
      const rateLimit = headers['x-ratelimit-limit']
      const rateRemaining = headers['x-ratelimit-remaining']
      const rateReset = headers['x-ratelimit-reset']
      
      if (rateLimit && rateRemaining && rateReset) {
        const limitReached = parseInt(rateRemaining) <= 5 // Warning when 5 or fewer requests remaining
        const resetTime = new Date(parseInt(rateReset) * 1000)
        
        this.rateLimitStatus = {
          limitReached,
          resetTime: resetTime.toISOString(),
          remaining: parseInt(rateRemaining),
          limit: parseInt(rateLimit)
        }
        
        // If limit nearly reached, raise an alert
        if (limitReached) {
          this.securityAlerts.push({
            id: Date.now(),
            type: 'rate_limit_warning',
            message: 'You are approaching the rate limit for session operations. Please wait a few minutes before making more requests.',
            timestamp: new Date().toISOString(),
            details: { resetTime: resetTime.toISOString() },
            acknowledged: false,
            severity: 'info'
          })
        }
      }
    },
    
    // Handle API errors with consistent error formatting and security checks
    handleApiError(error, operation) {
      this.error = error.response?.data?.message || `Failed to ${operation}`
      console.error(`Error during ${operation}:`, error)
      
      // Check for rate limiting responses
      if (error.response?.status === 429) {
        this.rateLimitStatus.limitReached = true
        const retryAfter = error.response.headers['retry-after']
        if (retryAfter) {
          const resetTime = new Date()
          resetTime.setSeconds(resetTime.getSeconds() + parseInt(retryAfter))
          this.rateLimitStatus.resetTime = resetTime.toISOString()
          
          this.securityAlerts.push({
            id: Date.now(),
            type: 'rate_limit_exceeded',
            message: 'Rate limit exceeded for session operations. Please try again later.',
            timestamp: new Date().toISOString(),
            details: { resetTime: resetTime.toISOString() },
            acknowledged: false,
            severity: 'warning'
          })
        }
      }
    },
    
    // End a specific session
    async endSession(sessionId) {
      this.isLoading = true
      this.error = null
      
      // Check rate limit before proceeding
      if (this.rateLimitStatus.limitReached) {
        const resetTime = new Date(this.rateLimitStatus.resetTime)
        const waitTime = Math.ceil((resetTime - new Date()) / 1000)
        this.error = `Rate limit reached. Please try again in ${waitTime} seconds.`
        this.isLoading = false
        throw new Error(this.error)
      }
      
      try {
        // Check if trying to end current session
        if (sessionId === this.currentSessionId) {
          throw new Error('Cannot end current session. Use logout instead.')
        }
        
        const response = await axios.delete(`/api/sessions/${sessionId}`)
        
        // Update the session in the local store
        const sessionIndex = this.sessions.findIndex(s => s.id === sessionId)
        if (sessionIndex !== -1) {
          this.sessions[sessionIndex].is_active = false
        }
        
        // Check for rate limit headers
        this.checkRateLimit(response.headers)
        
        return response.data
      } catch (error) {
        this.handleApiError(error, 'end session')
        throw error
      } finally {
        this.isLoading = false
      }
    },
    
    // End all sessions except the current one
    async endOtherSessions() {
      this.isLoading = true
      this.error = null
      
      // Check rate limit before proceeding
      if (this.rateLimitStatus.limitReached) {
        const resetTime = new Date(this.rateLimitStatus.resetTime)
        const waitTime = Math.ceil((resetTime - new Date()) / 1000)
        this.error = `Rate limit reached. Please try again in ${waitTime} seconds.`
        this.isLoading = false
        throw new Error(this.error)
      }
      
      try {
        const response = await axios.delete('/api/sessions/other')
        
        // Update all sessions in the local store
        this.sessions.forEach(session => {
          if (session.id !== this.currentSessionId) {
            session.is_active = false
          }
        })
        
        // Check for rate limit headers
        this.checkRateLimit(response.headers)
        
        return response.data
      } catch (error) {
        this.handleApiError(error, 'end other sessions')
        throw error
      } finally {
        this.isLoading = false
      }
    },
    
    // Refresh the current session
    async refreshSession() {
      this.isLoading = true
      this.error = null
      this.sessionTimeoutWarning = false // Clear any warning
      
      // Check rate limit before proceeding
      if (this.rateLimitStatus.limitReached) {
        const resetTime = new Date(this.rateLimitStatus.resetTime)
        const waitTime = Math.ceil((resetTime - new Date()) / 1000)
        this.error = `Rate limit reached. Please try again in ${waitTime} seconds.`
        this.isLoading = false
        throw new Error(this.error)
      }
      
      try {
        // Include current fingerprint for security
        const fingerprint = await getBrowserFingerprint()
        
        const response = await axios.post('/api/sessions/current/refresh', {
          fingerprint: fingerprint.hash,
          fingerprint_components: fingerprint.components
        })
        
        // Update the current session in the local store
        const sessionIndex = this.sessions.findIndex(s => s.id === this.currentSessionId)
        if (sessionIndex !== -1) {
          this.sessions[sessionIndex].last_activity = new Date().toISOString()
          if (response.data.expires_at) {
            this.sessions[sessionIndex].expires_at = response.data.expires_at
          }
        }
        
        // Reset the auth store's last token refresh time
        const authStore = useAuthStore()
        authStore.lastTokenRefresh = Date.now()
        
        // Check for rate limit headers
        this.checkRateLimit(response.headers)
        
        return response.data
      } catch (error) {
        this.handleApiError(error, 'refresh session')
        throw error
      } finally {
        this.isLoading = false
      }
    },
    
    // Get session statistics
    async fetchSessionStats() {
      this.isLoading = true
      this.error = null
      
      try {
        const response = await axios.get('/api/sessions/stats')
        this.stats = response.data
        
        // Check for rate limit headers
        this.checkRateLimit(response.headers)
        
        return response.data
      } catch (error) {
        this.handleApiError(error, 'fetch session statistics')
        throw error
      } finally {
        this.isLoading = false
      }
    },
    
    // Check if current session is active
    async checkSessionStatus() {
      if (!this.currentSessionId) {
        return false
      }
      
      try {
        // Fetch fresh session data
        await this.fetchSessions()
        
        // Find current session
        const currentSession = this.sessions.find(s => s.id === this.currentSessionId)
        return currentSession && currentSession.is_active
      } catch (error) {
        console.error('Error checking session status:', error)
        return false
      }
    },
    
    // Called when auth store logs in the user
    async onLogin(sessionId) {
      try {
        if (sessionId) {
          this.currentSessionId = sessionId
        }
        
        // Generate and save fingerprint on login
        this.currentFingerprint = await getBrowserFingerprint()
        
        // Fetch sessions after login
        await this.fetchSessions()
      } catch (error) {
        console.error('Error initializing session after login:', error)
        // Make sure sessions array is initialized even if fetch fails
        if (!this.sessions) {
          this.sessions = []
        }
      }
    },
    
    // Called when auth store logs out the user
    onLogout() {
      this.sessions = []
      this.currentSessionId = null
      this.stats = null
      this.sessionTimeoutWarning = false
      this.securityAlerts = []
      this.currentFingerprint = null
      this.lastKnownIp = null
      this.rateLimitStatus = {
        limitReached: false,
        resetTime: null
      }
      
      // Clear timeout check interval
      if (this.timeoutCheckInterval) {
        clearInterval(this.timeoutCheckInterval)
        this.timeoutCheckInterval = null
      }
    },
    
    // Reset store state
    resetState() {
      this.sessions = []
      this.currentSessionId = null
      this.isLoading = false
      this.error = null
      this.stats = null
      this.sessionTimeoutWarning = false
      this.securityAlerts = []
      this.currentFingerprint = null
      this.lastKnownIp = null
      this.rateLimitStatus = {
        limitReached: false,
        resetTime: null
      }
      
      // Clear timeout check interval
      if (this.timeoutCheckInterval) {
        clearInterval(this.timeoutCheckInterval)
        this.timeoutCheckInterval = null
      }
    }
  }
})

// Initialize the session store
const initializeSessionStore = () => {
  try {
    const sessionStore = useSessionStore()
    
    // Initialize with error handling
    try {
      sessionStore.init()
    } catch (error) {
      console.error('Error during session store initialization:', error)
      
      // Ensure sessions array is initialized to prevent errors
      if (!sessionStore.sessions) {
        sessionStore.sessions = []
      }
    }
    
    // Check for existing authentication safely
    const authStore = useAuthStore()
    
    if (authStore.isAuthenticated) {
      try {
        sessionStore.fetchSessions().catch(error => {
          console.error('Error fetching initial sessions:', error)
        })
      } catch (error) {
        console.error('Error in initial session fetch:', error)
      }
    }
    
    return sessionStore
  } catch (error) {
    console.error('Critical error creating session store:', error)
    // Return a minimal store-like object to prevent errors when methods are called
    return {
      sessions: [],
      currentSessionId: null,
      isLoading: false,
      error: 'Failed to initialize session store',
      fetchSessions: () => Promise.resolve({ sessions: [] }),
      checkSessionTimeout: () => {},
      startSessionMonitoring: () => {},
      onLogin: () => {},
      onLogout: () => {}
    }
  }
}

// Service methods for components to use
export default {
  // Initialize the session service
  init() {
    return initializeSessionStore()
  },
  
  // Get all sessions for the current user
  async getSessions() {
    const sessionStore = useSessionStore()
    return sessionStore.fetchSessions()
  },
  
  // End a specific session
  async endSession(sessionId) {
    const sessionStore = useSessionStore()
    return sessionStore.endSession(sessionId)
  },
  
  // End all other sessions except current
  async endOtherSessions() {
    const sessionStore = useSessionStore()
    return sessionStore.endOtherSessions()
  },
  
  // Refresh current session
  async refreshSession() {
    const sessionStore = useSessionStore()
    return sessionStore.refreshSession()
  },
  
  // Get session statistics
  async getSessionStats() {
    const sessionStore = useSessionStore()
    return sessionStore.fetchSessionStats()
  },
  
  // Check if current session is active
  async isSessionActive() {
    const sessionStore = useSessionStore()
    return sessionStore.checkSessionStatus()
  },
  
  // Check if session is about to expire
  isSessionAboutToExpire() {
    const sessionStore = useSessionStore()
    return sessionStore.isSessionAboutToExpire
  },
  
  // Reset session timeout warning
  dismissTimeoutWarning() {
    const sessionStore = useSessionStore()
    sessionStore.sessionTimeoutWarning = false
  },
  
  // Get security alerts
  getSecurityAlerts() {
    const sessionStore = useSessionStore()
    return sessionStore.securityAlerts
  },
  
  // Acknowledge a security alert
  acknowledgeAlert(alertId) {
    const sessionStore = useSessionStore()
    sessionStore.acknowledgeAlert(alertId)
  },
  
  // Clear acknowledged alerts
  clearAcknowledgedAlerts() {
    const sessionStore = useSessionStore()
    sessionStore.clearAcknowledgedAlerts()
  },
  
  // Get rate limit status
  getRateLimitStatus() {
    const sessionStore = useSessionStore()
    return sessionStore.rateLimitStatus
  },
  
  // Get the store instance (for using in components with more complex needs)
  getStore() {
    return useSessionStore()
  }
} 
