import axios from 'axios'
import { handleApiError, apiCall } from '@/utils/errorHandler'

// Base URLs based on environment
const apiBaseUrls = {
  development: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000',
  production: import.meta.env.VITE_API_BASE_URL || '',
  test: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
}

// Get current environment
const environment = import.meta.env.MODE || 'development'

// Set base URL based on environment
const baseURL = apiBaseUrls[environment]

// Create a custom axios instance
const api = axios.create({
  baseURL: baseURL + '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  // Add timeout to catch network issues
  timeout: 30000, // 30 seconds
  // Control CORS behavior
  withCredentials: true, // Important for cookies, authorization headers with HTTPS
})

// Add a request interceptor to include auth token
api.interceptors.request.use(
  config => {
    // Get token from localStorage or cookie
    const token = localStorage.getItem('accessToken');
    
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
      
      // Debug token in development
      if (process.env.NODE_ENV !== 'production') {
        console.log(`Token attached: ${token.substring(0, 15)}...`);
      }
    } else {
      console.warn('No token found for request to:', config.url);
    }
    
    // Debug request in development
    if (process.env.NODE_ENV !== 'production') {
      console.log('API Request:', config.method.toUpperCase(), config.url);
      console.log('Request Headers:', config.headers);
    }
    
    return config;
  },
  error => {
    // Handle request errors
    console.error('Request error:', error);
    handleApiError(error);
    return Promise.reject(error);
  }
)

// Add a response interceptor to handle errors
api.interceptors.response.use(
  response => {
    // Debug successful response in development
    if (process.env.NODE_ENV !== 'production') {
      console.log('Response Status:', response.status);
      console.log('Response Data:', response.data);
    }
    return response;
  },
  async error => {
    const originalRequest = error.config;
    
    // Check if response exists to avoid issues with network errors
    if (!error.response) {
      console.error('Network error (no response):', error);
      return Promise.reject(error);
    }
    
    console.error('API Error:', error.response.status, error.response.data);
    
    // If 401 Unauthorized and not already retrying
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      
      console.log('Auth failed. Clearing token and redirecting to login');
      
      // Clear tokens
      localStorage.removeItem('accessToken');
      localStorage.removeItem('userData');
      
      // You could implement token refresh logic here
      // Example: const newToken = await refreshToken()
      
      // Redirect to login with expired=true param
      window.location.href = '/login?expired=true';
      return Promise.reject(error);
    }
    
    // Handle auth token expiration in router guards.js
    // This interceptor handles all other errors
    
    // Only handle non-401 errors here (401 is handled in router guards)
    if (!error.response || error.response.status !== 401) {
      const errorDetails = handleApiError(error);
      
      // Emit a global event that can be listened to by error notification components
      if (window.dispatchEvent) {
        window.dispatchEvent(new CustomEvent('api-error', { 
          detail: errorDetails 
        }));
      }
    }
    
    return Promise.reject(error);
  }
)

// Add global axios configuration for AJAX requests
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

export default function(app) {
  // Make axios instance available throughout the app
  app.config.globalProperties.$api = api
  app.config.globalProperties.$apiCall = apiCall
  
  // Also make it available through inject/provide system
  app.provide('api', api)
  app.provide('apiCall', apiCall)
  
  // Save router instance for use in the interceptor
  if (app.config.globalProperties.$router) {
    window.$router = app.config.globalProperties.$router
  }
  
  // Add global error handler for uncaught API errors
  app.config.errorHandler = (err, vm, info) => {
    // If it's our formatted API error
    if (err && err.isApiError) {
      console.error('Uncaught API Error:', err.message)
    } else {
      console.error('Vue Error:', err, info)
    }
  }
} 