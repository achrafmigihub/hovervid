/**
 * CORS configuration helper utility
 * Use these functions to help debug and test CORS issues
 */
import axios from 'axios'

/**
 * Test basic CORS connectivity between frontend and backend
 * 
 * @param {string} url - The URL to test
 * @returns {Promise<Object>} - Response data or error
 */
export const testCorsConnection = async (url = 'http://localhost:8000/api/cors-test') => {
  try {
    const response = await axios.get(url, {
      headers: {
        'Accept': 'application/json'
      }
    })
    
    return {
      success: true,
      status: response.status,
      data: response.data,
      headers: response.headers
    }
  } catch (error) {
    return {
      success: false,
      error: error.message,
      status: error.response?.status,
      details: error.response?.data,
      config: error.config
    }
  }
}

/**
 * Test CORS with Authorization header
 * 
 * @param {string} url - The URL to test
 * @param {string} token - JWT token to use
 * @returns {Promise<Object>} - Response data or error
 */
export const testCorsWithAuth = async (url = 'http://localhost:8000/api/cors-test', token) => {
  try {
    const response = await axios.get(url, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`
      }
    })
    
    return {
      success: true,
      status: response.status,
      data: response.data,
      headers: response.headers
    }
  } catch (error) {
    return {
      success: false,
      error: error.message,
      status: error.response?.status,
      details: error.response?.data,
      config: error.config
    }
  }
}

/**
 * Test preflight request by making a non-simple request
 * This will trigger a preflight OPTIONS request
 * 
 * @param {string} url - The URL to test
 * @returns {Promise<Object>} - Response data or error
 */
export const testPreflightRequest = async (url = 'http://localhost:8000/api/cors-test') => {
  try {
    // Using PUT method will trigger a preflight OPTIONS request
    const response = await axios.put(url, { testData: 'This request should trigger preflight' }, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Custom-Header': 'Custom Value' // Custom header also triggers preflight
      }
    })
    
    return {
      success: true,
      status: response.status,
      data: response.data,
      headers: response.headers
    }
  } catch (error) {
    return {
      success: false,
      error: error.message,
      status: error.response?.status,
      details: error.response?.data,
      config: error.config
    }
  }
}

/**
 * Check if the origin is allowed by the CORS configuration
 * 
 * @param {string} origin - The origin to check
 * @param {string} backendUrl - The backend URL to check against
 * @returns {Promise<Object>} - Response with allowed status
 */
export const checkOriginAllowed = async (origin = window.location.origin, backendUrl = 'http://localhost:8000/api/cors-test') => {
  try {
    // Make a request from the specified origin
    const response = await axios.get(backendUrl, {
      headers: {
        'Accept': 'application/json',
        'Origin': origin
      }
    })
    
    return {
      success: true,
      allowed: true,
      origin,
      status: response.status
    }
  } catch (error) {
    // Check if this is a CORS error
    const isCorsError = error.message.includes('CORS') || !error.response
    
    return {
      success: false,
      allowed: !isCorsError,
      origin,
      error: error.message,
      isCorsError
    }
  }
}

export default {
  testCorsConnection,
  testCorsWithAuth,
  testPreflightRequest,
  checkOriginAllowed
} 