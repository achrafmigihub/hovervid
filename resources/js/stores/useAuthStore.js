import { defineStore } from 'pinia'
import axios from 'axios'
import { apiCall, handleApiError } from '@/utils/errorHandler'
import { 
  notifyLoginToServiceWorker, 
  notifyLogoutToServiceWorker, 
  checkServiceWorkerAuth 
} from '@/utils/service-worker-setup'
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
          
          if (!this.isAuthenticated) {
            console.log('Session recovery failed, clearing auth data')
            this.clearAuthData()
          }
        }
      } catch (e) {
        console.error('Auth initialization error:', e)
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
        
        // Get CSRF cookie first from Laravel Sanctum
        await axios.get('/sanctum/csrf-cookie', {
          withCredentials: true
        });
        
        // Login request
        const response = await axios({
          method: 'post',
          url: '/api/auth/login',
          data: {
            email: credentials.email,
            password: credentials.password,
            remember: true // Always remember the user for persistent sessions
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
        this.error = error.message || 'Login failed'
        
        // Enhanced error logging
        console.error('Login error details:', {
          status: error.response?.status,
          message: error.message,
          responseData: error.response?.data
        })
        
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
        // Add the session ID to the request if available
        const config = {
          headers: {},
          params: this.sessionId ? { session_id: this.sessionId } : {},
          withCredentials: true
        }
        
        // Add authorization header if token exists
        if (this.token) {
          config.headers['Authorization'] = `Bearer ${this.token}`
        }
        
        const { data } = await axios.get('/api/auth/user', config)
        
        console.log('User data response:', data)
        
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
    },

    clearAuthData() {
      // Clear all authentication data
      this.user = null
      this.token = null
      this.sessionId = null
      this.sessionInitialized = false
      
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

    async recoverSessionAuth() {
      console.log('Attempting to recover session authentication')
      
      try {
        // Use special session-only endpoint
        const { data } = await axios.get('/api/auth/session-user', { 
          withCredentials: true 
        })
        
        console.log('Session recovery response:', data)
        
        if (data && data.status === 'success' && data.user) {
          console.log('Successfully recovered session auth')
          this.setAuthData(data)
          return true
        } else {
          console.log('Session recovery had no valid user data')
          return false
        }
      } catch (error) {
        console.log('Session recovery failed:', error.message)
        return false
      }
    },
  }
}) 