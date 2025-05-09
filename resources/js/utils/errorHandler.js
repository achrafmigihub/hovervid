/**
 * Comprehensive error handling utility for API requests and network errors
 */
import axios from 'axios'

// Constants
const DEFAULT_ERROR_MESSAGE = 'An unexpected error occurred. Please try again.'
const NETWORK_ERROR_MESSAGE = 'Network connection issue. Please check your internet connection and try again.'
const SERVER_ERROR_MESSAGE = 'The server encountered an error. Our team has been notified.'
const VALIDATION_ERROR_MESSAGE = 'Please check the form for errors.'

/**
 * Formats Laravel validation errors into a usable format for the frontend
 * 
 * @param {Object} validationErrors - Laravel validation errors object
 * @returns {Object} Formatted errors object
 */
export const formatValidationErrors = (validationErrors) => {
  if (!validationErrors) return {}
  
  // If it's already in the format we want, return it
  if (typeof validationErrors === 'object' && !Array.isArray(validationErrors)) {
    const formattedErrors = {}
    
    // Handle Laravel's array of messages format for each field
    Object.keys(validationErrors).forEach(field => {
      const errorMessages = validationErrors[field]
      if (Array.isArray(errorMessages)) {
        formattedErrors[field] = errorMessages[0] // Take first error message
      } else {
        formattedErrors[field] = errorMessages
      }
    })
    
    return formattedErrors
  }
  
  // Handle Laravel specific validation error format
  const formattedErrors = {}
  
  Object.keys(validationErrors).forEach(field => {
    const errorMessages = validationErrors[field]
    formattedErrors[field] = Array.isArray(errorMessages) 
      ? errorMessages[0] // Take first error message
      : errorMessages
  })
  
  return formattedErrors
}

/**
 * Get a user-friendly error message from an API error
 * 
 * @param {Error} error - The error object
 * @returns {string} User-friendly error message
 */
export const getUserFriendlyErrorMessage = (error) => {
  // Network error (no response)
  if (!error.response) {
    return NETWORK_ERROR_MESSAGE
  }
  
  const { status, data } = error.response
  
  // Authentication errors
  if (status === 401) {
    return data?.message || 'Authentication required. Please log in again.'
  }
  
  // Authorization errors
  if (status === 403) {
    return data?.message || 'You don\'t have permission to perform this action.'
  }
  
  // Resource not found
  if (status === 404) {
    return data?.message || 'The requested resource was not found.'
  }
  
  // Validation errors
  if (status === 422) {
    return data?.message || VALIDATION_ERROR_MESSAGE
  }
  
  // Server errors
  if (status >= 500) {
    return data?.message || SERVER_ERROR_MESSAGE
  }
  
  // Bad request errors
  if (status === 400) {
    return data?.message || 'The request was invalid. Please check your input.'
  }
  
  // Use message from the server if available
  if (data?.message) {
    return data.message
  }
  
  // Default error message
  return DEFAULT_ERROR_MESSAGE
}

/**
 * Handle API errors consistently across the application
 * 
 * @param {Error} error - The error object
 * @returns {Object} Object containing error details
 */
export const handleApiError = (error) => {
  // Determine error type
  const isNetworkError = !error.response;
  const isServerError = error.response?.status >= 500;
  const isValidationError = error.response?.status === 422;
  const isAuthError = error.response?.status === 401;
  const isForbiddenError = error.response?.status === 403;
  const isNotFoundError = error.response?.status === 404;
  const isBadRequest = error.response?.status === 400;
  
  // Create the error result object
  const errorResult = {
    message: getUserFriendlyErrorMessage(error),
    status: error.response?.status,
    statusText: error.response?.statusText,
    validationErrors: {},
    isNetworkError,
    isServerError,
    isValidationError,
    isBadRequest,
    isAuthError,
    isForbiddenError,
    isNotFoundError,
    isApiError: true,
    originalError: error,
    rawResponseData: error.response?.data
  }
  
  // Extract validation errors if present (handle both formats)
  if (error.response?.data) {
    const { data } = error.response
    
    // Standard Laravel validation format
    if (data.errors && typeof data.errors === 'object') {
      errorResult.validationErrors = formatValidationErrors(data.errors)
    } 
    // Some APIs return errors directly at the root level
    else if (data.error && typeof data.error === 'object') {
      errorResult.validationErrors = formatValidationErrors(data.error)
    }
    // Laravel sometimes returns errors at root level
    else if (isValidationError && typeof data === 'object') {
      errorResult.validationErrors = formatValidationErrors(data)
    }
    // Bad request with validation errors might be in a different format
    else if (errorResult.isBadRequest && data.message && data.details) {
      // Format for APIs that return details array with validation errors
      const details = Array.isArray(data.details) ? data.details : [data.details]
      
      details.forEach(detail => {
        if (detail.field && detail.message) {
          errorResult.validationErrors[detail.field] = detail.message
        }
      })
    }
  }
  
  // Log detailed error info in development
  if (process.env.NODE_ENV !== 'production') {
    // Commented out console logging to reduce developer console noise
    /*
    console.group('API Error')
    console.error('Status:', errorResult.status, errorResult.statusText)
    console.error('Message:', errorResult.message)
    console.error('URL:', error.config?.url)
    console.error('Method:', error.config?.method?.toUpperCase())
    
    if (errorResult.isValidationError || Object.keys(errorResult.validationErrors).length > 0) {
      console.error('Validation Errors:', errorResult.validationErrors)
    }
    
    console.error('Complete Response Data:', error.response?.data)
    
    // Log request data but filter sensitive information
    const requestData = error.config?.data ? JSON.parse(error.config.data) : null
    if (requestData) {
      // Filter out sensitive fields like passwords
      const filteredData = { ...requestData }
      const sensitiveFields = ['password', 'password_confirmation', 'current_password']
      sensitiveFields.forEach(field => {
        if (field in filteredData) filteredData[field] = '[FILTERED]'
      })
      console.error('Request Data:', filteredData)
    }
    
    console.error('Stack Trace:', error.stack)
    console.groupEnd()
    */
  }
  
  return errorResult
}

/**
 * Create a wrapper around axios for more convenient error handling
 * 
 * @param {string} method - The HTTP method (get, post, put, patch, delete)
 * @param {string} url - The URL to request
 * @param {Object} data - The data to send
 * @param {Object} config - Additional axios config
 * @returns {Promise<any>} - The response data
 */
export const apiCall = async (method, url, data = null, config = {}) => {
  try {
    // Debug the request data in development
    if (process.env.NODE_ENV !== 'production') {
      // Commented out console logging to reduce developer console noise
      /*
      console.group(`API Request: ${method.toUpperCase()} ${url}`)
      console.log('Request data:', data)
      console.log('Request config:', config)
      console.log('Complete request payload:', JSON.stringify(data, null, 2))
      console.groupEnd()
      */
    }
    
    // Ensure headers are properly set
    if (!config.headers) {
      config.headers = {}
    }
    
    // Add API request headers if not already present
    if (!config.headers['Accept']) {
      config.headers['Accept'] = 'application/json'
    }
    if (!config.headers['X-Requested-With']) {
      config.headers['X-Requested-With'] = 'XMLHttpRequest'
    }
    
    let response
    
    // Configure the request based on method
    switch (method.toLowerCase()) {
      case 'get':
        response = await axios.get(url, { ...config, params: data })
        break
      case 'post':
        response = await axios.post(url, data, config)
        break
      case 'put':
        response = await axios.put(url, data, config)
        break
      case 'patch':
        response = await axios.patch(url, data, config)
        break
      case 'delete':
        response = await axios.delete(url, { ...config, data })
        break
      default:
        throw new Error(`Unsupported method: ${method}`)
    }
    
    // Log successful response in development
    if (process.env.NODE_ENV !== 'production') {
      // Commented out console logging to reduce developer console noise
      /*
      console.group(`API Response: ${method.toUpperCase()} ${url}`)
      console.log('Status:', response.status)
      console.log('Data:', response.data)
      console.groupEnd()
      */
    }
    
    return response.data
  } catch (error) {
    // Transform the error using our error handler
    const errorDetails = handleApiError(error)
    
    // Debug raw response data in development
    if (process.env.NODE_ENV !== 'production') {
      // Commented out console logging to reduce developer console noise
      /*
      console.group('Complete API Error Details')
      console.error('Request URL:', url)
      console.error('Request Method:', method)
      console.error('Request Payload:', data)
      console.error('Response Status:', error.response?.status)
      console.error('Complete Response Data:', error.response?.data)
      console.error('Response Headers:', error.response?.headers)
      console.error('Stack Trace:', error.stack)
      console.groupEnd()
      */
    }
    
    // Rethrow with additional details
    throw errorDetails
  }
}

/**
 * Test the API connectivity
 * 
 * @returns {Promise<boolean>} - True if API is reachable
 */
export const testApiConnectivity = async () => {
  try {
    const response = await apiCall('get', '/api/ping')
    return response?.status === 'success'
  } catch (error) {
    // Silently handle this error without console logging
    // console.error('API connectivity test failed:', error.message)
    return false
  }
}

/**
 * Get human-readable error type
 * 
 * @param {Object} error - The error object from handleApiError
 * @returns {string} The error type
 */
export const getErrorType = (error) => {
  if (!error) return 'unknown'
  
  if (error.isNetworkError) return 'network'
  if (error.isValidationError) return 'validation'
  if (error.isServerError) return 'server'
  if (error.isAuthError) return 'authentication'
  if (error.isForbiddenError) return 'authorization'
  if (error.isNotFoundError) return 'not_found'
  if (error.isBadRequest) return 'bad_request'
  
  return 'unknown'
}

export default {
  formatValidationErrors,
  getUserFriendlyErrorMessage,
  handleApiError,
  apiCall,
  testApiConnectivity,
  getErrorType
} 