import { ref, computed } from 'vue'
import { getErrorType } from '@/utils/errorHandler'

/**
 * Composable for handling API errors
 * 
 * @returns {Object} API error handling utilities
 */
export default function useApiError() {
  // Error state
  const error = ref(null)
  
  // Reset error state
  const resetError = () => {
    error.value = null
  }
  
  // Set error state
  const setError = (err) => {
    error.value = err
  }
  
  // Check if there is an error
  const hasError = computed(() => !!error.value)
  
  // Get error type
  const errorType = computed(() => {
    if (!error.value) return null
    return getErrorType(error.value)
  })
  
  // Check if error is of specific type
  const isErrorType = (type) => {
    if (!error.value) return false
    return getErrorType(error.value) === type
  }
  
  // Get field-specific error message
  const getFieldError = (fieldName) => {
    if (!error.value || !error.value.validationErrors) return null
    
    // Check direct match
    if (error.value.validationErrors[fieldName]) {
      return error.value.validationErrors[fieldName]
    }
    
    // Support nested fields with dot notation
    if (fieldName.includes('.')) {
      const parts = fieldName.split('.')
      const lastPart = parts[parts.length - 1]
      
      if (error.value.validationErrors[lastPart]) {
        return error.value.validationErrors[lastPart]
      }
    }
    
    return null
  }
  
  // Check if a field has an error
  const hasFieldError = (fieldName) => {
    return !!getFieldError(fieldName)
  }
  
  // Raw error response data
  const rawResponseData = computed(() => {
    if (!error.value) return null
    return error.value.rawResponseData
  })
  
  // Get error message
  const errorMessage = computed(() => {
    if (!error.value) return null
    return error.value.message
  })
  
  // Get all validation errors
  const validationErrors = computed(() => {
    if (!error.value || !error.value.validationErrors) return {}
    return error.value.validationErrors
  })
  
  return {
    error,
    resetError,
    setError,
    hasError,
    errorType,
    isErrorType,
    getFieldError,
    hasFieldError,
    rawResponseData,
    errorMessage,
    validationErrors
  }
} 