<script setup>
import { ref, onMounted } from 'vue'
import { apiCall } from '@/utils/errorHandler'

const corsStatus = ref('Testing CORS...')
const corsMessage = ref('')
const corsTimestamp = ref('')
const corsError = ref(null)
const loading = ref(false)

// Test the CORS configuration
const testCors = async () => {
  loading.value = true
  corsError.value = null
  
  try {
    const response = await apiCall('get', '/api/cors-test')
    corsStatus.value = response.status
    corsMessage.value = response.message
    corsTimestamp.value = response.timestamp
  } catch (error) {
    corsStatus.value = 'error'
    corsError.value = error
    console.error('CORS Error:', error)
  } finally {
    loading.value = false
  }
}

// Test CORS with Authorization header
const testCorsWithAuth = async () => {
  loading.value = true
  corsError.value = null
  
  try {
    // Use a mock JWT token for testing
    const mockToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c'
    
    // Add Authorization header to config
    const config = {
      headers: {
        'Authorization': `Bearer ${mockToken}`
      }
    }
    
    const response = await apiCall('get', '/api/cors-test', null, config)
    corsStatus.value = response.status
    corsMessage.value = response.message
    corsTimestamp.value = response.timestamp
  } catch (error) {
    corsStatus.value = 'error'
    corsError.value = error
    console.error('CORS Auth Error:', error)
  } finally {
    loading.value = false
  }
}

// Run the CORS test on mount
onMounted(testCors)
</script>

<template>
  <div class="cors-test pa-6">
    <h2 class="text-h4 mb-4">CORS Configuration Test</h2>
    
    <VCard>
      <VCardTitle>
        <span v-if="loading">Testing CORS configuration...</span>
        <span v-else>CORS Test Results</span>
      </VCardTitle>
      
      <VCardText>
        <div v-if="loading" class="d-flex align-center justify-center py-4">
          <VProgressCircular indeterminate color="primary" />
        </div>
        
        <template v-else>
          <div v-if="corsStatus === 'success'" class="success-container">
            <VAlert
              type="success"
              class="mb-4"
            >
              {{ corsMessage }}
            </VAlert>
            
            <div class="text-body-1 mb-2">
              <strong>Status:</strong> {{ corsStatus }}
            </div>
            
            <div class="text-body-1 mb-4">
              <strong>Timestamp:</strong> {{ corsTimestamp }}
            </div>
          </div>
          
          <div v-else class="error-container">
            <VAlert
              type="error"
              class="mb-4"
            >
              CORS is not properly configured
            </VAlert>
            
            <div v-if="corsError" class="text-body-1 mb-4">
              <strong>Error Message:</strong> {{ corsError.message }}
            </div>
            
            <div v-if="corsError && corsError.isNetworkError" class="mb-4">
              This appears to be a network error. Make sure both your Laravel and Vue servers are running.
            </div>
            
            <VExpansionPanels v-if="corsError">
              <VExpansionPanel>
                <VExpansionPanelTitle>
                  View Error Details
                </VExpansionPanelTitle>
                <VExpansionPanelText>
                  <pre class="error-details">{{ JSON.stringify(corsError, null, 2) }}</pre>
                </VExpansionPanelText>
              </VExpansionPanel>
            </VExpansionPanels>
          </div>
        </template>
      </VCardText>
      
      <VCardActions>
        <VBtn
          color="primary"
          @click="testCors"
          :loading="loading"
          :disabled="loading"
        >
          Test CORS Again
        </VBtn>
        
        <VBtn
          color="secondary"
          @click="testCorsWithAuth"
          :loading="loading"
          :disabled="loading"
          class="ml-4"
        >
          Test With Auth Header
        </VBtn>
      </VCardActions>
    </VCard>
    
    <VCard class="mt-6">
      <VCardTitle>CORS Configuration Guide</VCardTitle>
      <VCardText>
        <p class="mb-4">
          Your CORS configuration handles:
        </p>
        
        <ul class="mb-4">
          <li>✅ Cross-origin requests from Vue.js (port 5173) to Laravel (port 8000)</li>
          <li>✅ Preflight OPTIONS requests</li>
          <li>✅ Authorization header for JWT authentication</li>
          <li>✅ Exposed headers for pagination and other metadata</li>
        </ul>
        
        <p class="mb-2">
          <strong>Troubleshooting:</strong>
        </p>
        
        <ul>
          <li>Make sure both servers are running</li>
          <li>Check browser console for CORS error details</li>
          <li>Verify that <code>cors.php</code> configuration is properly set</li>
          <li>Ensure the HandleOptions middleware is registered</li>
        </ul>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.cors-test {
  max-width: 800px;
  margin: 0 auto;
}

.error-details {
  background-color: rgba(0, 0, 0, 0.05);
  padding: 1rem;
  border-radius: 4px;
  max-height: 300px;
  overflow: auto;
  font-size: 0.85rem;
}
</style> 