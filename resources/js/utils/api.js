import { ofetch } from 'ofetch'
import axios from 'axios'
import { apiCall } from './errorHandler' // Import apiCall from errorHandler

// Configure axios defaults
axios.defaults.baseURL = import.meta.env.VITE_API_BASE_URL || '/api'
axios.defaults.withCredentials = true // Include cookies in requests

// Intercept axios requests to add auth token
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('accessToken')
  config.headers = config.headers || {}
  
  // Always include API headers
  config.headers['Accept'] = 'application/json'
  config.headers['X-Requested-With'] = 'XMLHttpRequest'
  
  // Add auth token if available
  if (token) {
    config.headers['Authorization'] = `Bearer ${token}`
  }
  
  return config
}, error => {
  return Promise.reject(error)
})

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  async onRequest({ options }) {
    // Initialize headers if not set
    if (!options.headers) {
      options.headers = {}
    }
    
    // Always include API headers
    options.headers['Accept'] = 'application/json'
    options.headers['X-Requested-With'] = 'XMLHttpRequest'
    
    // Get token from localStorage instead of cookie for SPA auth
    const accessToken = localStorage.getItem('accessToken')
    if (accessToken) {
      options.headers['Authorization'] = `Bearer ${accessToken}`
    }
  },
})

// Re-export apiCall from errorHandler
export { apiCall }
