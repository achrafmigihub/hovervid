import { ref, reactive } from 'vue'
import { handleApiError } from '@/utils/errorHandler'

/**
 * Composable for handling API errors in components
 * 
 * @returns {Object} Error handling utilities
 */
export function useErrorHandler() {
  // State
  const error = ref(null)
  const validationErrors = reactive({})
  const isLoading = ref(false)
  
  /**
   * Reset all errors
   */
  const resetErrors = () => {
    error.value = null
    Object.keys(validationErrors).forEach(key => {
      delete validationErrors[key]
    })
  }
  
  /**
   * Execute an async operation with automatic error handling
   * 
   * @param {Function} asyncOperation - The async function to execute
   * @param {Object} options - Options for the operation
   * @returns {Promise<any>} - The result of the async operation
   */
  const executeWithErrorHandling = async (asyncOperation, options = {}) => {
    const { 
      showLoader = true,
      clearErrorsBeforeRequest = true,
      rethrowError = false
    } = options
    
    if (showLoader) {
      isLoading.value = true
    }
    
    if (clearErrorsBeforeRequest) {
      resetErrors()
    }
    
    try {
      return await asyncOperation()
    } catch (err) {
      // Format the error using our utility
      const errorDetails = err.isApiError ? err : handleApiError(err)
      
      // Set the main error message
      error.value = errorDetails
      
      // Extract validation errors if present
      if (errorDetails.isValidationError && errorDetails.validationErrors) {
        Object.assign(validationErrors, errorDetails.validationErrors)
      }
      
      // Optionally rethrow the error for additional handling
      if (rethrowError) {
        throw errorDetails
      }
      
      return null
    } finally {
      if (showLoader) {
        isLoading.value = false
      }
    }
  }
  
  /**
   * Determine if there are validation errors for a specific field
   * 
   * @param {string} field - The field name to check
   * @returns {boolean} - True if the field has validation errors
   */
  const hasValidationError = (field) => {
    return !!validationErrors[field]
  }
  
  return {
    // State
    error,
    validationErrors,
    isLoading,
    
    // Methods
    resetErrors,
    executeWithErrorHandling,
    hasValidationError
  }
} 