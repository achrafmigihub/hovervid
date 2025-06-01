import { ability } from '@/plugins/casl/ability'
import sessionService from '@/services/sessionService'
import { apiCall } from '@/utils/errorHandler'
import { getBrowserFingerprint } from '@/utils/fingerprint'
import {
    checkServiceWorkerAuth,
    notifyLoginToServiceWorker,
    notifyLogoutToServiceWorker
} from '@/utils/service-worker-setup'
import axios from 'axios'
import { defineStore } from 'pinia'
import { useRouter } from 'vue-router'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    sessionId: null,
    returnUrl: null,
    isLoading: false,
    error: null,
    sessionInitialized: false, // Track if session was initialized
    lastTokenRefresh: null, // Track when token was last refreshed
    tokenRefreshInterval: null, // Store the interval ID
    lastKnownFingerprint: null, // Store browser fingerprint for security
  }),

  getters: {
    isAuthenticated: state => !!state.user,
    isAdmin: state => state.user?.role === 'admin',
    isClient: state => state.user?.role === 'client',
  },

  actions: {
    async init() {
      console.log('Auth store init started')
      try {
        // First check service worker for authentication state
        const isAuthenticatedInSW = await checkServiceWorkerAuth()
        console.log('Service worker authentication check:', isAuthenticatedInSW)
        
        // Use localStorage for persistent sessions
        const token = localStorage.getItem('accessToken')
        const sessionId = localStorage.getItem('sessionId')
        let userData = null
        
        // Try to parse user data first
        try {
          const storedUserData = localStorage.getItem('userData')
          
          // Validate stored user data
          if (storedUserData && storedUserData !== 'undefined' && storedUserData !== 'null') {
            // Check for HTML content which would indicate a server error
            if (!storedUserData.includes('<!DOCTYPE html>') && !storedUserData.includes('<html>')) {
              userData = JSON.parse(storedUserData)
            }
          }
        } catch (e) {
          console.error('Failed to parse userData:', e)
          userData = null
        }
        
        // Get browser fingerprint
        try {
          this.lastKnownFingerprint = await getBrowserFingerprint()
          console.log('Generated browser fingerprint during init')
        } catch (e) {
          console.error('Failed to generate fingerprint:', e)
        }
        
        // If we have either a token, sessionId, or userData, we can initialize the session
        if (token || sessionId || (userData && userData.id)) {
          console.log('Found existing auth data, initializing session')
          
          // Set available data
          if (token && token !== 'undefined' && token !== 'null') {
            this.token = token
            // Set axios authorization header
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
          }
          
          if (sessionId && sessionId !== 'undefined' && sessionId !== 'null') {
            this.sessionId = sessionId
          }
          
          if (userData && userData.id) {
            // Always normalize role to lowercase for consistent comparisons
            if (userData.role) {
              userData.role = userData.role.toLowerCase()
            }
            this.user = userData
          }
          
          // Mark session as initialized
          this.sessionInitialized = true
          
          // Set credentials for all requests
          axios.defaults.withCredentials = true
          
          console.log('Auth store initialized with existing data:', {
            userId: this.user?.id,
            role: this.user?.role,
            hasToken: !!this.token,
            hasSessionId: !!this.sessionId
          })
          
          // Setup service worker and session handling
          this.setupServiceWorkerListener()
          this.setupPersistentSession()
          this.handleNavigation()
          
          // Verify the user with backend, but don't clear data if it fails
          try {
            await this.fetchUser()
          } catch (e) {
            console.error('Failed to fetch user data during init, trying session recovery:', e)
            await this.recoverSessionAuth()
          }
        } else {
          // No auth data found, still try session-based recovery
          console.log('No valid auth data found in localStorage, trying session recovery')
          await this.recoverSessionAuth()
          
          // Mark session as initialized even if no auth data was found
          // This allows navigation to render properly for guests
          this.sessionInitialized = true
          
          // Only clear auth data if we had some data to begin with
          // For completely new/guest users, failed session recovery is normal
          if (!this.isAuthenticated) {
            console.log('No existing session found - this is normal for guest users')
            // Don't call clearAuthData() here as there's nothing to clear for guests
            // and it prevents unnecessary error logging
          }
        }
      } catch (e) {
        console.error('Auth initialization error:', e)
        
        // Ensure session is marked as initialized even on error
        this.sessionInitialized = true
        
        // Try session recovery as last resort
        try {
          await this.recoverSessionAuth()
        } catch (recoveryError) {
          console.error('Session recovery also failed:', recoveryError)
        }
      }
    },

    setupPersistentSession() {
      // Listen for page visibility changes
      document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible') {
          console.log('Page became visible, checking session')
          try {
            // First try normal user endpoint if we're authenticated
            if (this.isAuthenticated) {
              await this.fetchUser()
            } 
            // If not authenticated or fetchUser failed, try session-only endpoint
            else {
              await this.recoverSessionAuth()
            }
          } catch (error) {
            console.error('Session check failed:', error)
            // Try session recovery as last resort
            await this.recoverSessionAuth()
          }
        }
      })

      // Listen for page load
      window.addEventListener('load', async () => {
        console.log('Page loaded, checking session')
        try {
          if (this.isAuthenticated) {
            await this.fetchUser()
          } else {
            await this.recoverSessionAuth()
          }
        } catch (error) {
          console.error('Session check failed:', error)
          // Try session recovery as last resort
          await this.recoverSessionAuth()
        }
      })

      // Listen for popstate (back/forward button)
      window.addEventListener('popstate', () => {
        if (this.isAuthenticated && window.location.pathname === '/login') {
          console.log('Preventing navigation to login page while authenticated')
          window.history.pushState(null, '', '/dashboard')
          window.location.href = '/dashboard'
        }
      })

      // Setup token refresh
      this.setupTokenRefresh()
    },

    setupTokenRefresh() {
      // Clear any existing interval
      if (this.tokenRefreshInterval) {
        clearInterval(this.tokenRefreshInterval)
      }

      // Set last refresh time if not set
      if (!this.lastTokenRefresh) {
        this.lastTokenRefresh = Date.now()
      }

      // Refresh token once a day to keep the session alive
      const REFRESH_INTERVAL = 24 * 60 * 60 * 1000 // 24 hours
      
      this.tokenRefreshInterval = setInterval(async () => {
        if (this.isAuthenticated) {
          console.log('Performing daily token refresh')
          try {
            await this.refreshToken()
            this.lastTokenRefresh = Date.now()
            console.log('Token refreshed successfully')
          } catch (error) {
            console.error('Periodic token refresh failed:', error)
          }
        }
      }, REFRESH_INTERVAL)
    },

    setupServiceWorkerListener() {
      // Listen for messages from service worker
      if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.addEventListener('message', (event) => {
          if (event.data && event.data.type === 'LOGOUT_CONFIRMED') {
            console.log('Received logout confirmation from service worker')
            if (window.location.pathname !== '/login') {
              window.location.href = '/login'
            }
          } else if (event.data && event.data.type === 'SESSION_EXPIRED') {
            console.log('Received session expired from service worker')
            this.clearAuthData()
            window.location.href = '/login?expired=true'
          }
        })
      }
    },

    async login(credentials) {
      this.isLoading = true
      this.error = null
      
      try {
        console.log('Attempting login with credentials:', {
          email: credentials.email,
          password: credentials.password ? '[REDACTED]' : null
        })
        
        // Generate fingerprint for device identification
        const fingerprint = await getBrowserFingerprint()
        this.lastKnownFingerprint = fingerprint
        
        // Get CSRF cookie first from Laravel Sanctum
        await axios.get('/sanctum/csrf-cookie', {
          withCredentials: true
        });
        
        // Login request with fingerprint data for security
        const response = await axios({
          method: 'post',
          url: '/api/auth/login',
          data: {
            email: credentials.email,
            password: credentials.password,
            remember: true, // Always remember the user for persistent sessions
            fingerprint: fingerprint.hash,
            fingerprint_components: {
              platform: fingerprint.components.platform,
              browser: fingerprint.components.userAgent,
              screen: `${fingerprint.components.screenWidth}x${fingerprint.components.screenHeight}`,
              timezone: fingerprint.components.timezone
            }
          },
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          withCredentials: true // Include cookies in the request
        });
        
        console.log('Login response received:', response.data);
        
        if (response.data) {
          if (response.data.status === 'success') {
            this.setAuthData(response.data)
            console.log('Auth data set. Current user:', this.user);
            
            // Notify service worker about login
            notifyLoginToServiceWorker();
            
            // Integrate with session service
            try {
              // Initialize the session service with the current session
              if (this.sessionId) {
                const sessionStore = sessionService.getStore()
                sessionStore.onLogin(this.sessionId)
              }
            } catch (e) {
              console.error('Failed to initialize session service:', e)
            }
            
            return response.data
          } else {
            console.error('Login response error:', response.data)
            throw new Error(response.data.message || 'Authentication failed')
          }
        } else {
          console.error('Login response format error:', response.data)
          throw new Error('Invalid response format from server')
        }
      } catch (error) {
        // Handle suspended account specific error
        if (error.response?.status === 403 && error.response?.data?.message?.includes('suspended')) {
          this.error = 'Your account has been suspended. Please contact administration for assistance.'
          console.error('Login failed: Account suspended', {
            status: error.response.status,
            message: error.response.data.message
          })
        } else {
          this.error = error.message || 'Login failed'
          
          // Enhanced error logging
          console.error('Login error details:', {
            status: error.response?.status,
            message: error.message,
            responseData: error.response?.data
          })
        }
        
        if (error.response?.data?.errors) {
          error.validationErrors = error.response.data.errors;
          error.isValidationError = true;
        }
        
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async register(userData) {
      this.isLoading = true
      this.error = null
      
      try {
        // Get CSRF cookie first if using Laravel Sanctum
        await axios.get('/sanctum/csrf-cookie')
        
        const response = await apiCall('post', '/api/auth/register', userData)
        
        // If we have a response, it's a success
        if (response) {
          // Set auth data if available
          this.setAuthData(response)
          return response
        }
        
        throw new Error('Registration failed: Invalid response from server')
      } catch (error) {
        // Special case: handle 422 responses that might actually be successful registrations
        // Some Laravel endpoints return 422 even when the registration was successful
        if (error.response?.status === 422) {
          // Check if there are actual validation errors
          const errors = error.response.data.errors || {}
          const hasRealErrors = Object.values(errors).some(
            err => err && (Array.isArray(err) ? err.length > 0 : err.toString().trim() !== '')
          )
          
          // If no real validation errors, treat as success
          if (!hasRealErrors) {
            console.log('422 without real errors, treating as success')
            // Try to extract user data from response if available
            if (error.response.data.user || error.response.data.data) {
              const userData = error.response.data.user || error.response.data.data
              this.setAuthData(userData)
              return userData
            }
            
            // Return a minimal success object
            return { success: true }
          }
        }
        
        // Handle validation errors
        if (error.response?.data?.errors) {
          const errors = error.response.data.errors
          const errorMessages = Object.entries(errors).map(([field, messages]) => ({
            field,
            message: Array.isArray(messages) ? messages[0] : messages
          }))
          this.error = errorMessages
          throw error
        }
        
        // Handle specific error messages
        if (error.response?.data?.message) {
          this.error = error.response.data.message
          throw error
        }
        
        // If it's an axios error with response, check the status
        if (error.response) {
          // If status is 201 (Created) or 200 (OK), it's actually a success
          if (error.response.status === 201 || error.response.status === 200) {
            const responseData = error.response.data
            this.setAuthData(responseData)
            return responseData
          }
          
          // Handle common error cases
          switch (error.response.status) {
            case 422:
              this.error = 'Please check your input and try again'
              break
            case 409:
              this.error = 'This email is already registered'
              break
            default:
              this.error = error.response.data?.message || 'Registration failed. Please try again'
          }
        } else {
          this.error = 'Network error. Please check your connection and try again'
        }
        
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async logout(silent = false) {
      this.isLoading = true
      
      try {
        // Notify session service of logout
        try {
          const sessionStore = sessionService.getStore()
          sessionStore.onLogout()
        } catch (e) {
          console.error('Failed to notify session service of logout:', e)
        }
        
        if (!silent) {
          // Only send request to server if this is not a silent logout
          await axios({
            method: 'post',
            url: '/api/auth/logout',
            headers: {
              'Authorization': `Bearer ${this.token}`
            },
            withCredentials: true // Include cookies in the request
          })
        }
        
        // Clean up regardless of server response
        this.clearAuthData()
        
        // Notify service worker of logout
        notifyLogoutToServiceWorker()
        
        return { success: true }
      } catch (error) {
        console.error('Logout error:', error)
        
        // Still clear data locally even if server logout failed
        this.clearAuthData()
        
        // Notify service worker of logout anyway
        notifyLogoutToServiceWorker()
        
        return { success: false, error: error.message }
      } finally {
        this.isLoading = false
      }
    },

    async refreshToken() {
      try {
        const response = await apiCall('post', '/api/auth/refresh')
        this.setAuthData(response)
        this.lastTokenRefresh = Date.now()
        return response
      } catch (error) {
        console.error('Token refresh failed:', error.message)
        
        // If token is invalid, clear auth data
        if (error.status === 401) {
          this.clearAuthData()
          window.location.href = '/login?expired=true'
        }
        
        throw error
      }
    },

    async fetchUser() {
      if (!this.token && !this.sessionId) return null

      console.log('Fetching user data with token:', this.token ? 'Token exists' : 'No token', 
        'Session ID:', this.sessionId ? this.sessionId : 'No session ID')
      
      try {
        // Generate current fingerprint for verification
        const currentFingerprint = await getBrowserFingerprint()
        
        // Add the session ID to the request if available
        const config = {
          headers: {},
          params: this.sessionId ? { session_id: this.sessionId } : {},
          withCredentials: true
        }
        
        // Include fingerprint for verification
        if (currentFingerprint) {
          config.params.fingerprint = currentFingerprint.hash
        }
        
        // Add authorization header if token exists
        if (this.token) {
          config.headers['Authorization'] = `Bearer ${this.token}`
        }
        
        const { data } = await axios.get('/api/auth/user', config)
        
        console.log('User data response:', data)
        
        // Check if there are security issues reported from the backend
        if (data.security_issues) {
          this.handleSecurityIssues(data.security_issues, currentFingerprint)
        }
        
        // Handle different response formats
        if (data && data.status === 'success' && data.user) {
          // Standard response format with user object
          this.user = data.user
          this.sessionId = data.session_id || this.sessionId
          localStorage.setItem('userData', JSON.stringify(this.user))
          localStorage.setItem('sessionId', this.sessionId)
          
          if (data.access_token) {
            this.token = data.access_token
            localStorage.setItem('accessToken', this.token)
            // Set authorization header for subsequent API requests
            axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
          }
          
          // Update stored fingerprint
          this.lastKnownFingerprint = currentFingerprint
          
          // Ensure session is initialized
          this.sessionInitialized = true
          
          console.log('User data successfully updated:', this.user)
          return this.user
        } 
        else if (data && typeof data === 'object' && data.id && data.email) {
          // Direct user object in response
          this.user = data
          this.sessionId = data.session_id || this.sessionId
          localStorage.setItem('userData', JSON.stringify(this.user))
          localStorage.setItem('sessionId', this.sessionId)
          
          // Update stored fingerprint
          this.lastKnownFingerprint = currentFingerprint
          
          // Ensure session is initialized
          this.sessionInitialized = true
          
          console.log('User data successfully updated (direct format):', this.user)
          return this.user
        }
        else {
          // Data doesn't match expected format but don't logout - server might be having issues
          console.warn('Invalid user data received from server:', data)
          return null
        }
      } catch (error) {
        console.error('Error fetching user data:', error.message)
        
        // Log detailed error information
        if (error.response) {
          console.error('Response data:', error.response.data)
          console.error('Response status:', error.response.status)
          
          // Only clear auth data on a definite 401 unauthorized response with a clear error message
          if (error.response.status === 401 && 
              error.response.data && 
              error.response.data.message === 'Unauthenticated') {
            console.warn('Definite unauthorized response, clearing auth data')
            this.clearAuthData()
            const router = useRouter()
            router.push({ name: 'login' })
          } else {
            // For other errors, keep the session data but return null
            // This prevents logouts on server errors or timeouts
            console.warn('Error occurred but keeping session data, may be a temporary server issue')
          }
        } else if (error.request) {
          // Network error - keep the session data
          console.error('Network error, no response received - keeping session active')
        } else {
          // Something else happened - keep the session
          console.error('Unexpected error occurred - keeping session active:', error.message)
        }
        
        // Don't clear auth data on errors - just return null
        return null
      }
    },

    setAuthData(data) {
      // Support different response formats (for backward compatibility)
      this.token = data.access_token || data.token || null
      
      // Store session ID if provided
      if (data.session_id) {
        this.sessionId = data.session_id
        localStorage.setItem('sessionId', this.sessionId)
      }
      
      // Extract user data
      if (data.user) {
        this.user = data.user
      } else if (data.userData) {
        this.user = data.userData
      }
      
      // Normalize role to lowercase for consistent comparisons
      if (this.user && this.user.role) {
        this.user.role = this.user.role.toLowerCase()
      }
      
      // Store authentication state in localStorage
      if (this.token) {
        localStorage.setItem('accessToken', this.token)
        // Set authorization header for subsequent API requests
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
      }
      
      localStorage.setItem('userData', JSON.stringify(this.user))
      
      // Set withCredentials to true to include cookies in requests
      axios.defaults.withCredentials = true
      
      // Mark session as initialized
      this.sessionInitialized = true
      this.error = null
      
      // Setup persistent session handling
      this.setupPersistentSession()
      
      // Initialize CASL abilities based on user role
      this.initializeAbilities()
    },

    clearAuthData() {
      // Clear all authentication data
      this.user = null
      this.token = null
      this.sessionId = null
      this.sessionInitialized = false
      this.lastKnownFingerprint = null
      
      // Clear the token refresh interval if it exists
      if (this.tokenRefreshInterval) {
        clearInterval(this.tokenRefreshInterval)
        this.tokenRefreshInterval = null
      }
      
      // Remove authorization header
      delete axios.defaults.headers.common['Authorization']
      
      // Clear localStorage
      localStorage.removeItem('accessToken')
      localStorage.removeItem('userData')
      localStorage.removeItem('sessionId')
      
      // Clear CASL abilities
      ability.update([])
      
      // Clear any errors
      this.error = null
    },

    // Add new method to handle navigation
    handleNavigation() {
      if (this.isAuthenticated) {
        // If user is authenticated and tries to go to login page, redirect to dashboard
        if (window.location.pathname === '/login') {
          console.log('Redirecting authenticated user to dashboard')
          window.history.pushState(null, '', '/dashboard')
          window.location.href = '/dashboard'
        }
      }
    },

    // Initialize CASL abilities based on user role
    initializeAbilities() {
      if (!this.user) {
        ability.update([])
        return
      }
      
      // Clear existing abilities
      ability.update([])
      
      const role = this.user.role
      
      // Set up basic abilities for all users
      const abilities = [
        { action: 'read', subject: 'Auth' },
      ]
      
      // Add admin-specific abilities
      if (role === 'admin') {
        abilities.push(
          { action: 'read', subject: 'AclDemo' },
          { action: 'read', subject: 'all' },
          { action: 'manage', subject: 'all' }
        )
      }
      
      // Add client-specific abilities
      if (role === 'client') {
        abilities.push(
          { action: 'read', subject: 'AclDemo' },
          { action: 'read', subject: 'ClientPages' }
        )
      }
      
      // Update CASL ability instance
      ability.update(abilities)
      
      // Save to cookie for persistence
      const abilityStringified = JSON.stringify(abilities)
      document.cookie = `userAbilityRules=${encodeURIComponent(abilityStringified)}; path=/; max-age=86400`
      
      console.log('CASL abilities initialized for role:', role, abilities)
    },

    async recoverSessionAuth() {
      console.log('Attempting to recover session authentication')
      
      try {
        // Generate current fingerprint for validation
        let fingerprint = null;
        try {
          fingerprint = await getBrowserFingerprint();
          console.log('Generated fingerprint for session recovery')
        } catch (e) {
          console.warn('Could not generate fingerprint for session recovery:', e.message)
        }
        
        // NOTE: The browser will show a 401 network error in the console for guest users.
        // This is normal browser behavior and cannot be suppressed. Our error handling
        // below properly treats 401 responses as expected behavior for guests.
        const { data } = await axios.get('/api/auth/session-user', { 
          withCredentials: true,
          params: {
            fingerprint: fingerprint ? fingerprint.hash : undefined,
            recovery: true,
            timestamp: Date.now() // Prevent caching
          }
        })
        
        console.log('Session recovery response:', data)
        
        if (data && data.status === 'success' && data.user) {
          console.log('Successfully recovered session auth')
          this.setAuthData(data)
          
          // Store the fingerprint for future use
          if (fingerprint) {
            this.lastKnownFingerprint = fingerprint
          }
          
          // If there are security issues reported from the backend, handle them
          if (data.security_issues) {
            this.handleSecurityIssues(data.security_issues, fingerprint)
          }
          
          return true
        } else {
          console.log('Session recovery had no valid user data')
          return false
        }
      } catch (error) {
        // Handle different error types appropriately
        if (error.response) {
          const status = error.response.status
          
          // 401 is expected when there's no valid session - log as info, not error
          if (status === 401) {
            console.log('No valid session found during recovery (401) - this is expected for guests')
            return false
          }
          
          // 403 is also expected for unauthorized access - log as info
          if (status === 403) {
            console.log('Session access forbidden (403) - this is expected for guests')
            return false
          }
          
          // Other client errors (4xx) are warnings
          if (status >= 400 && status < 500) {
            console.warn('Client error during session recovery:', status, error.response.data)
            return false
          }
          
          // Server errors (5xx) are actual errors we should know about
          if (status >= 500) {
            console.error('Server error during session recovery:', status)
            console.error('Response data:', error.response.data)
            console.error('Debug info:', error.response.data?.debug || 'No debug info available')
            return false
          }
          
          // Other status codes
          console.warn('Unexpected status during session recovery:', status, error.response.data)
          return false
        } else if (error.request) {
          console.error('No response received during session recovery:', error.request)
          return false
        } else {
          console.error('Error setting up session recovery request:', error.message)
          return false
        }
      }
    },

    // Handle security issues from the backend
    handleSecurityIssues(securityIssues, currentFingerprint) {
      if (!securityIssues) return
      
      // Check for fingerprint mismatch
      if (securityIssues.fingerprint_mismatch) {
        console.warn('Fingerprint mismatch detected by the server')
        
        // Get the session store to add an alert
        try {
          const sessionStore = sessionService.getStore()
          sessionStore.securityAlerts.push({
            id: Date.now(),
            type: 'fingerprint_mismatch',
            message: 'Your account is being accessed from a different device or browser. If this wasn\'t you, please change your password immediately.',
            timestamp: new Date().toISOString(),
            details: securityIssues.fingerprint_details || {},
            acknowledged: false,
            severity: 'error'
          })
          
          // Trigger event for immediate notification
          window.dispatchEvent(new CustomEvent('security-alert', {
            detail: {
              type: 'fingerprint_mismatch',
              message: 'Unusual device activity detected on your account'
            }
          }))
        } catch (e) {
          console.error('Failed to add security alert for fingerprint mismatch:', e)
        }
      }
      
      // Check for IP change
      if (securityIssues.ip_change) {
        console.warn('IP address change detected by the server')
        
        // Get the session store to add an alert
        try {
          const sessionStore = sessionService.getStore()
          sessionStore.securityAlerts.push({
            id: Date.now(),
            type: 'ip_change',
            message: 'Your account is being accessed from a new location. If this wasn\'t you, please change your password immediately.',
            timestamp: new Date().toISOString(),
            details: securityIssues.ip_details || {},
            acknowledged: false,
            severity: 'warning'
          })
          
          // Trigger event for immediate notification
          window.dispatchEvent(new CustomEvent('security-alert', {
            detail: {
              type: 'ip_change',
              message: 'New login location detected'
            }
          }))
        } catch (e) {
          console.error('Failed to add security alert for IP change:', e)
        }
      }
      
      // Check for suspicious activity
      if (securityIssues.suspicious_activity) {
        console.warn('Suspicious activity detected by the server')
        
        // Get the session store to add an alert
        try {
          const sessionStore = sessionService.getStore()
          sessionStore.securityAlerts.push({
            id: Date.now(),
            type: 'suspicious_activity',
            message: securityIssues.suspicious_activity.message || 'Suspicious activity has been detected on your account.',
            timestamp: new Date().toISOString(),
            details: securityIssues.suspicious_activity || {},
            acknowledged: false,
            severity: 'error'
          })
          
          // Trigger event for immediate notification
          window.dispatchEvent(new CustomEvent('security-alert', {
            detail: {
              type: 'suspicious_activity',
              message: 'Suspicious account activity detected'
            }
          }))
        } catch (e) {
          console.error('Failed to add security alert for suspicious activity:', e)
        }
      }
    },
  }
}) 
