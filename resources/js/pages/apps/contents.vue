<template>
  <VCard title="Contents Management">
    <VCardText>
      <VRow>
        <VCol cols="12" class="text-center mb-6">
          <h1 class="text-h4">Website Content</h1>
          <p class="text-subtitle-1 mt-2" v-if="domain">
            Managing content for: <strong>{{ domain.domain }}</strong>
          </p>
          <div class="mt-4" v-if="!isLoading && contentPages.length > 0">
            <VChip color="primary" variant="tonal" class="me-2">
              {{ totalPages }} Pages
            </VChip>
            <VChip color="success" variant="tonal" class="me-2">
              {{ totalContent }} Content Items
            </VChip>
            <VChip color="info" variant="tonal">
              {{ contentWithVideos }} With Videos
            </VChip>
          </div>
        </VCol>
      </VRow>

      <!-- Loading state -->
      <VRow v-if="isLoading">
        <VCol cols="12" class="text-center">
          <VProgressCircular indeterminate color="primary" size="64" />
          <p class="mt-4">Loading content...</p>
        </VCol>
      </VRow>

      <!-- No content state -->
      <VRow v-else-if="contentPages.length === 0">
        <VCol cols="12" class="text-center">
          <VIcon size="64" color="muted" class="mb-4">bx-file-blank</VIcon>
          <h3 class="text-h6 mb-2">No Content Found</h3>
          <p class="text-body-2 text-medium-emphasis">
            No content has been detected for your domain yet. Install and activate the HoverVid plugin on your website to start collecting content.
          </p>
        </VCol>
      </VRow>

      <!-- Content organized by pages -->
      <div v-else>
        <VExpansionPanels v-model="expandedPanels" multiple>
          <VExpansionPanel
            v-for="(page, pageIndex) in contentPages"
            :key="pageIndex"
            :value="pageIndex"
            class="mb-4"
          >
            <VExpansionPanelTitle class="page-header">
              <div class="d-flex align-center w-100">
                <VIcon class="me-3">bx-file</VIcon>
                <div class="flex-grow-1">
                  <h5 class="text-h6">{{ page.page_name }}</h5>
                  <p class="text-body-2 mt-1 mb-0 text-medium-emphasis">
                    {{ page.content_count }} content {{ page.content_count === 1 ? 'item' : 'items' }}
                  </p>
                </div>
                <VChip 
                  :color="page.items.filter(item => item.has_video).length > 0 ? 'success' : 'warning'"
                  variant="tonal"
                  size="small"
                  class="me-4"
                >
                  {{ page.items.filter(item => item.has_video).length }}/{{ page.content_count }} with videos
                </VChip>
              </div>
            </VExpansionPanelTitle>

            <VExpansionPanelText class="pa-0">
              <VDivider />
              <div class="pa-4">
                <!-- Content Items for this page -->
                <VRow>
                  <VCol 
                    cols="12"
                    v-for="(item, itemIndex) in page.items"
                    :key="item.id"
                    class="mb-4"
                  >
                    <VCard variant="outlined" class="content-item-card">
                      <VCardText>
                        <VRow align="center">
                          <!-- Content Input Field -->
                          <VCol cols="12" md="6">
                            <VTextarea
                              :model-value="item.text"
                              :label="`Content ${itemIndex + 1}`"
                              variant="outlined"
                              readonly
                              hide-details="auto"
                              rows="3"
                              auto-grow
                              class="content-input"
                            />
                            <div class="mt-2 d-flex flex-column align-start gap-2">
                              <VChip 
                                size="small" 
                                color="info" 
                                variant="tonal"
                              >
                                ID: {{ item.id.substring(0, 8) }}...
                              </VChip>
                              <VChip 
                                size="small" 
                                :color="item.has_video ? 'success' : 'warning'"
                                variant="tonal"
                              >
                                {{ item.has_video ? 'Has Video' : 'No Video' }}
                              </VChip>
                              <VChip 
                                size="small" 
                                color="purple" 
                                variant="tonal"
                                v-if="item.context"
                              >
                                {{ item.context }}
                              </VChip>
                            </div>
                          </VCol>

                          <!-- Action Buttons -->
                          <VCol cols="12" md="6" class="d-flex justify-end align-center">
                            <div class="d-flex flex-column flex-sm-row gap-3">
                              <!-- Upload Button -->
                              <VBtn
                                color="primary"
                                variant="elevated"
                                prepend-icon="bx-upload"
                                size="default"
                                :loading="isUploading && currentUploadingId === item.id"
                                :disabled="isUploading || isRejecting"
                                @click="selectFile(item.id)"
                                class="upload-btn"
                              >
                                {{ item.has_video ? 'Replace Video' : 'Upload Video' }}
                              </VBtn>
                              
                              <!-- Upload Progress (shown during upload) -->
                              <div v-if="isUploading && currentUploadingId === item.id && uploadProgress > 0" class="mt-2">
                                <VProgressLinear
                                  :model-value="uploadProgress"
                                  color="primary"
                                  height="6"
                                  striped
                                />
                                <div class="text-caption mt-1 text-center">
                                  {{ uploadProgress }}% 
                                  <span v-if="uploadMethod" class="text-muted">
                                    ({{ uploadMethod === 'laravel' ? 'Server' : 'Direct' }} Upload)
                                  </span>
                                </div>
                              </div>

                              <!-- Remove Button -->
                              <VBtn
                                color="error"
                                variant="outlined"
                                prepend-icon="bx-trash"
                                size="default"
                                :loading="isRejecting && currentRejectingId === item.id"
                                :disabled="isUploading || isRejecting"
                                @click="rejectContent(item.id)"
                                class="remove-btn"
                              >
                                Remove
                              </VBtn>
                            </div>
                          </VCol>
                        </VRow>

                        <!-- Video Preview (if exists) -->
                        <VRow v-if="item.video_url" class="mt-4">
                          <VCol cols="12">
                            <VAlert color="success" variant="tonal" icon="bx-check-circle">
                              <div class="d-flex align-center">
                                <div class="flex-grow-1">
                                  <strong>Video attached:</strong> {{ item.video_url.split('/').pop() }}
                                </div>
                                <VBtn
                                  color="success"
                                  variant="text"
                                  icon="bx-play-circle"
                                  size="small"
                                  @click="previewVideo(item.video_url)"
                                />
                              </div>
                            </VAlert>
                          </VCol>
                        </VRow>
                      </VCardText>
                    </VCard>
                  </VCol>
                </VRow>
              </div>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>
      </div>

      <!-- Hidden File Input -->
      <VFileInput
        ref="fileInput"
        v-model="files"
        label="Select Video File"
        hide-details
        variant="outlined"
        accept="video/*,.mp4,.avi,.mov,.wmv,.flv,.webm"
        class="d-none"
        @update:model-value="handleFileChange"
      />

      <!-- Alerts -->
      <VAlert 
        v-if="errorMessage"
        type="error"
        class="mt-4"
        closable
        @click:close="errorMessage = ''"
      >
        {{ errorMessage }}
      </VAlert>

      <VAlert 
        v-if="successMessage"
        type="success"
        class="mt-4"
        closable
        @click:close="successMessage = ''"
      >
        {{ successMessage }}
      </VAlert>
    </VCardText>
  </VCard>

  <!-- Video Preview Dialog -->
  <VDialog v-model="videoPreviewDialog" max-width="800px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon class="me-3">bx-play-circle</VIcon>
        Video Preview
        <VSpacer />
        <VBtn icon="bx-x" variant="text" @click="videoPreviewDialog = false" />
      </VCardTitle>
      <VCardText>
        <video 
          v-if="previewVideoUrl"
          :src="previewVideoUrl"
          controls
          width="100%"
          height="auto"
        >
          Your browser does not support the video tag.
        </video>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import axios from 'axios'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

// Add AWS SDK for direct Wasabi uploads
const loadAWSSDK = () => {
  return new Promise((resolve, reject) => {
    if (window.AWS) {
      resolve(window.AWS)
      return
    }
    
    const script = document.createElement('script')
    script.src = 'https://sdk.amazonaws.com/js/aws-sdk-2.283.1.min.js'
    script.onload = () => resolve(window.AWS)
    script.onerror = reject
    document.head.appendChild(script)
  })
}

// Stores
const authStore = useAuthStore()
const router = useRouter()

// Reactive variables
const contentPages = ref([])
const domain = ref(null)
const files = ref([])
const fileInput = ref(null)
const isLoading = ref(true)
const isRejecting = ref(false)
const isUploading = ref(false)
const currentRejectingId = ref(null)
const currentUploadingId = ref(null)
const errorMessage = ref('')
const successMessage = ref('')
const videoPreviewDialog = ref(false)
const previewVideoUrl = ref('')
const expandedPanels = ref([])
const uploadProgress = ref(0)
const uploadMethod = ref('') // 'laravel' or 'direct'

// Computed properties
const totalPages = computed(() => contentPages.value.length)
const totalContent = computed(() => contentPages.value.reduce((sum, page) => sum + page.content_count, 0))
const contentWithVideos = computed(() => {
  return contentPages.value.reduce((sum, page) => {
    return sum + page.items.filter(item => item.has_video).length
  }, 0)
})

// Lifecycle
onMounted(async () => {
  // Check if user is authenticated
  if (!authStore.isAuthenticated) {
    console.error('User not authenticated, redirecting to login')
    router.push('/login')
    return
  }
  
  await fetchContent()
})

// Methods
const fetchContent = async () => {
  try {
    isLoading.value = true
    errorMessage.value = ''

    // Check authentication before making API call
    if (!authStore.isAuthenticated) {
      errorMessage.value = 'You must be logged in to view content'
      router.push('/login')
      return
    }

    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }

    // Add authorization header if token exists
    if (authStore.token) {
      headers['Authorization'] = `Bearer ${authStore.token}`
    }

    const sessionApi = axios.create({
      baseURL: (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api',
      headers,
      withCredentials: true
    })

    const response = await sessionApi.get('/client/content')
    
    if (response.data.success) {
      contentPages.value = response.data.data.pages
      domain.value = response.data.data.domain
    } else {
      errorMessage.value = response.data.message || 'Failed to fetch content'
    }
  } catch (error) {
    console.error('Error fetching content:', error)
    
    // Handle authentication errors
    if (error.response?.status === 401) {
      errorMessage.value = 'Session expired. Please log in again.'
      authStore.clearAuthData()
      router.push('/login')
    } else {
      errorMessage.value = error.response?.data?.message || error.message || 'Failed to fetch content'
    }
  } finally {
    isLoading.value = false
  }
}

const selectFile = (contentId) => {
  console.log('=== FILE SELECTION DEBUG START ===')
  console.log('Upload Video clicked for content:', contentId)
  currentUploadingId.value = contentId
  
  // Clear any previous files
  files.value = []
  
  nextTick(() => {
    try {
      console.log('Attempting to find file input...')
      console.log('fileInput.value:', fileInput.value)
      
      // Try multiple selectors to find the file input
      let input = fileInput.value?.$el?.querySelector('input[type="file"]')
      console.log('Method 1 - querySelector input[type="file"]:', input)
      
      if (!input) {
        // Try alternative selectors for Vuetify file input
        input = fileInput.value?.$el?.querySelector('input')
        console.log('Method 2 - querySelector input:', input)
      }
      
      if (!input) {
        // Try accessing the input directly from the component
        input = fileInput.value?.$refs?.input
        console.log('Method 3 - $refs.input:', input)
      }
      
      if (input) {
        console.log('Found file input, opening dialog...')
        console.log('Input element:', input)
        console.log('Input type:', input.type)
        console.log('Input accept:', input.accept)
        
        // Add event listener to detect when file is selected
        input.addEventListener('change', (event) => {
          console.log('File input change event triggered')
          console.log('Selected files:', event.target.files)
          if (event.target.files && event.target.files.length > 0) {
            const file = event.target.files[0]
            console.log('File selected via input change:', file.name, file.size, file.type)
            files.value = [file]
            handleFileChange()
          }
        }, { once: true }) // Use once: true to avoid duplicate listeners
        
        input.click()
        console.log('File dialog should be open now')
      } else {
        console.log('Could not find file input, using fallback method...')
        // Fallback: create a temporary file input
        const tempInput = document.createElement('input')
        tempInput.type = 'file'
        tempInput.accept = 'video/*,.mp4,.avi,.mov,.wmv,.flv,.webm'
        tempInput.style.display = 'none'
        
        console.log('Created temporary input:', tempInput)
        
        tempInput.onchange = (event) => {
          console.log('Temporary input change event')
          const file = event.target.files[0]
          if (file) {
            console.log('File selected via temporary input:', file.name, file.size, file.type)
            files.value = [file]
            handleFileChange()
          } else {
            console.log('No file selected in temporary input')
          }
          document.body.removeChild(tempInput)
          console.log('Temporary input removed')
        }
        
        tempInput.oncancel = () => {
          console.log('File selection was cancelled')
          document.body.removeChild(tempInput)
        }
        
        document.body.appendChild(tempInput)
        tempInput.click()
        console.log('Temporary file dialog opened')
      }
    } catch (error) {
      console.error('Error in file selection:', error)
      console.error('Error stack:', error.stack)
    }
  })
  
  console.log('=== FILE SELECTION DEBUG END ===')
}

// Debug: Make function globally accessible for testing
if (typeof window !== 'undefined') {
  window.debugSelectFile = selectFile
}

const handleFileChange = async () => {
  console.log('=== HANDLE FILE CHANGE DEBUG START ===')
  console.log('File selection detected')
  console.log('files.value:', files.value)
  console.log('currentUploadingId.value:', currentUploadingId.value)
  
  if (files.value && files.value.length > 0 && currentUploadingId.value) {
    const file = files.value[0]
    console.log('Starting upload for:', file.name)
    console.log('File details:', {
      name: file.name,
      size: file.size,
      type: file.type,
      lastModified: file.lastModified
    })
    console.log('Content ID:', currentUploadingId.value)
    
    await uploadVideo(file, currentUploadingId.value)
  } else {
    console.log('Upload conditions not met:')
    console.log('- Has files:', !!(files.value && files.value.length > 0))
    console.log('- Has content ID:', !!currentUploadingId.value)
    console.log('- Files array:', files.value)
    console.log('- Current uploading ID:', currentUploadingId.value)
  }
  
  console.log('=== HANDLE FILE CHANGE DEBUG END ===')
}

const uploadVideo = async (file, contentId) => {
  try {
    console.log('=== UPLOAD DEBUG START ===')
    console.log('Uploading video:', file.name, 'to content:', contentId)
    console.log('File size:', file.size, 'bytes', `(${(file.size / (1024 * 1024)).toFixed(1)}MB)`)
    console.log('File type:', file.type)
    console.log('Auth status:', authStore.isAuthenticated)
    console.log('Auth token exists:', !!authStore.token)
    
    isUploading.value = true
    errorMessage.value = ''
    successMessage.value = ''
    uploadProgress.value = 0

    // Check authentication
    if (!authStore.isAuthenticated) {
      errorMessage.value = 'You must be logged in to upload videos'
      router.push('/login')
      return
    }

    const fileSizeMB = file.size / (1024 * 1024)
    console.log(`File size: ${fileSizeMB.toFixed(1)}MB`)
    
    // Decide upload method based on file size
    // Try Laravel first for smaller files, fall back to direct upload for large files or failures
    if (fileSizeMB <= 10) {
      console.log('File <= 10MB, attempting Laravel upload first...')
      try {
        await uploadViaLaravel(file, contentId)
        return // Success, exit
      } catch (error) {
        console.log('Laravel upload failed, falling back to direct upload...')
        console.log('Laravel error:', error.message)
        
        // If it's a size error or server error, try direct upload
        if (error.response?.status === 413 || error.response?.status >= 500 || !error.response) {
          console.log('Attempting direct Wasabi upload as fallback...')
          await uploadToWasabiDirect(file, contentId)
        } else {
          throw error // Re-throw other errors (auth, validation, etc.)
        }
      }
    } else {
      console.log('File > 10MB, using direct Wasabi upload...')
      await uploadToWasabiDirect(file, contentId)
    }
    
  } catch (error) {
    console.log('=== UPLOAD ERROR ===')
    console.error('Upload error details:', {
      message: error.message,
      status: error.response?.status,
      statusText: error.response?.statusText,
      data: error.response?.data,
      config: error.config ? {
        url: error.config.url,
        method: error.config.method,
        headers: error.config.headers
      } : 'No config'
    })
    
    // Handle different types of errors
    if (error.response?.status === 401) {
      errorMessage.value = 'Session expired. Please log in again.'
      authStore.clearAuthData()
      router.push('/login')
    } else if (error.response?.status === 413) {
      // File too large error
      const fileSizeMB = (file.size / (1024 * 1024)).toFixed(1)
      errorMessage.value = `File too large! Your video (${fileSizeMB}MB) exceeds the upload limit. Please use a smaller video file (max 10MB for Laravel upload).`
    } else if (error.response?.status === 422) {
      // Validation errors
      const validationErrors = error.response.data.errors || {}
      const errorMessages = Object.values(validationErrors).flat()
      if (errorMessages.length > 0) {
        errorMessage.value = errorMessages.join(', ')
      } else {
        errorMessage.value = 'Validation failed. Please check your file format (supported: MP4, AVI, MOV, WMV, FLV, WEBM) and size (max 10MB).'
      }
    } else if (error.response?.status === 404) {
      errorMessage.value = 'Content not found. Please refresh the page and try again.'
    } else if (error.response?.status >= 500) {
      errorMessage.value = 'Server error occurred while uploading. Please try again later.'
    } else if (error.code === 'NETWORK_ERROR' || !error.response) {
      errorMessage.value = 'Network error. Please check your internet connection and try again.'
    } else if (error.message.includes('AWS') || error.message.includes('Wasabi')) {
      errorMessage.value = 'Wasabi storage error. Please check your configuration and try again.'
    } else {
      errorMessage.value = error.response?.data?.message || error.message || 'Failed to upload video. Please try again.'
    }
  } finally {
    isUploading.value = false
    currentUploadingId.value = null
    uploadProgress.value = 0
    uploadMethod.value = ''
    console.log('=== UPLOAD DEBUG END ===')
  }
}

// Upload via Laravel (for smaller files)
const uploadViaLaravel = async (file, contentId) => {
  uploadMethod.value = 'laravel'
  console.log('=== LARAVEL UPLOAD START ===')
  
  const formData = new FormData()
  formData.append('video', file)
  console.log('FormData created with video file')

  // Don't set Content-Type header when using FormData - let browser set it automatically
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }

  // Add authorization header if token exists
  if (authStore.token) {
    headers['Authorization'] = `Bearer ${authStore.token}`
    console.log('Authorization header added')
  }

  const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api'
  const uploadUrl = `${apiBaseUrl}/client/content/${contentId}/upload-video`
  
  console.log('API Base URL:', apiBaseUrl)
  console.log('Upload URL:', uploadUrl)
  console.log('Headers:', headers)

  const sessionApi = axios.create({
    baseURL: apiBaseUrl,
    headers,
    withCredentials: true
  })

  console.log('Sending Laravel upload request...')
  
  const response = await sessionApi.post(`/client/content/${contentId}/upload-video`, formData)
  
  console.log('Laravel upload response:', response.data)
  
  if (response.data.success) {
    console.log('Laravel upload successful!')
    successMessage.value = `Video uploaded successfully for content ${contentId.substring(0, 8)}...`
    
    // Update the local data
    contentPages.value.forEach(page => {
      page.items.forEach(item => {
        if (item.id === contentId) {
          item.video_url = response.data.data.video_url
          item.has_video = true
          console.log('Updated local data for content:', contentId)
        }
      })
    })
    
    // Clear file input
    files.value = []
    console.log('File input cleared')
  } else {
    throw new Error(response.data.message || 'Laravel upload failed')
  }
  
  console.log('=== LARAVEL UPLOAD END ===')
}

const rejectContent = async (contentId) => {
  if (!confirm('Are you sure you want to remove this content? This action cannot be undone.')) {
    return
  }

  try {
    isRejecting.value = true
    currentRejectingId.value = contentId
    errorMessage.value = ''
    successMessage.value = ''

    // Check authentication
    if (!authStore.isAuthenticated) {
      errorMessage.value = 'You must be logged in to remove content'
      router.push('/login')
      return
    }

    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }

    // Add authorization header if token exists
    if (authStore.token) {
      headers['Authorization'] = `Bearer ${authStore.token}`
    }

    const sessionApi = axios.create({
      baseURL: (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api',
      headers,
      withCredentials: true
    })

    const response = await sessionApi.delete(`/client/content/${contentId}`)
    
    if (response.data.success) {
      successMessage.value = 'Content removed successfully'
      
      // Remove the content from local data
      contentPages.value.forEach(page => {
        page.items = page.items.filter(item => item.id !== contentId)
        page.content_count = page.items.length
      })
      
      // Remove empty pages
      contentPages.value = contentPages.value.filter(page => page.content_count > 0)
    } else {
      errorMessage.value = response.data.message || 'Failed to remove content'
    }
  } catch (error) {
    console.error('Error removing content:', error)
    
    // Handle authentication errors
    if (error.response?.status === 401) {
      errorMessage.value = 'Session expired. Please log in again.'
      authStore.clearAuthData()
      router.push('/login')
    } else {
      errorMessage.value = error.response?.data?.message || error.message || 'Failed to remove content'
    }
  } finally {
    isRejecting.value = false
    currentRejectingId.value = null
  }
}

const previewVideo = (videoUrl) => {
  previewVideoUrl.value = videoUrl
  videoPreviewDialog.value = true
}

// Upload directly to Wasabi (bypasses PHP size limits)
const uploadToWasabiDirect = async (file, contentId) => {
  try {
    console.log('=== DIRECT WASABI UPLOAD START ===')
    uploadMethod.value = 'direct'
    
    // Debug: Check environment variables
    console.log('Environment variables check:')
    console.log('VITE_WASABI_ACCESS_KEY_ID:', import.meta.env.VITE_WASABI_ACCESS_KEY_ID ? '✓ SET' : '✗ MISSING')
    console.log('VITE_WASABI_SECRET_ACCESS_KEY:', import.meta.env.VITE_WASABI_SECRET_ACCESS_KEY ? '✓ SET' : '✗ MISSING')
    console.log('VITE_WASABI_BUCKET:', import.meta.env.VITE_WASABI_BUCKET ? '✓ SET' : '✗ MISSING')
    console.log('All environment variables:', import.meta.env)
    
    // Check if required variables are available
    if (!import.meta.env.VITE_WASABI_ACCESS_KEY_ID || 
        !import.meta.env.VITE_WASABI_SECRET_ACCESS_KEY || 
        !import.meta.env.VITE_WASABI_BUCKET) {
      throw new Error('Missing Wasabi environment variables. Please add VITE_WASABI_ACCESS_KEY_ID, VITE_WASABI_SECRET_ACCESS_KEY, and VITE_WASABI_BUCKET to your .env file and restart your dev server.')
    }
    
    // Load AWS SDK
    const AWS = await loadAWSSDK()
    console.log('AWS SDK loaded successfully')
    
    // Configure AWS S3 client for Wasabi
    const s3 = new AWS.S3({
      accessKeyId: import.meta.env.VITE_WASABI_ACCESS_KEY_ID,
      secretAccessKey: import.meta.env.VITE_WASABI_SECRET_ACCESS_KEY,
      endpoint: 'https://s3.ca-central-1.wasabisys.com',
      region: 'ca-central-1',
      s3ForcePathStyle: false,
      logger: console
    })
    
    console.log('Wasabi S3 client configured')
    
    // Generate unique filename
    const extension = file.name.split('.').pop()
    const uniqueFileName = `video_${Date.now()}_${Math.random().toString(36).substr(2, 9)}_${contentId}.${extension}`
    const path = `videos/${authStore.user?.id || 'anonymous'}/${uniqueFileName}`
    
    console.log('Upload path:', path)
    console.log('File details:', {
      name: file.name,
      size: file.size,
      type: file.type
    })
    
    // Upload parameters
    const uploadParams = {
      Bucket: import.meta.env.VITE_WASABI_BUCKET,
      Key: path,
      Body: file,
      ContentType: file.type,
      ACL: 'public-read'
    }
    
    console.log('Upload parameters:', {
      Bucket: uploadParams.Bucket,
      Key: uploadParams.Key,
      ContentType: uploadParams.ContentType,
      ACL: uploadParams.ACL,
      BodySize: uploadParams.Body.size
    })
    
    console.log('Starting direct upload to Wasabi...')
    
    // Create managed upload with progress tracking
    const upload = new AWS.S3.ManagedUpload({
      params: uploadParams,
      service: s3
    })
    
    // Track upload progress
    upload.on('httpUploadProgress', (progress) => {
      const percentage = Math.round((progress.loaded * 100) / progress.total)
      uploadProgress.value = percentage
      console.log(`Upload progress: ${percentage}%`)
    })
    
    // Perform upload
    const result = await upload.promise()
    console.log('Direct upload successful:', result)
    
    // Generate public URL
    const videoUrl = `https://s3.ca-central-1.wasabisys.com/${import.meta.env.VITE_WASABI_BUCKET}/${path}`
    console.log('Generated video URL:', videoUrl)
    
    // Update the content record in Laravel with the video URL
    const updateResponse = await updateContentVideoUrl(contentId, videoUrl, path)
    
    if (updateResponse.success) {
      successMessage.value = `Video uploaded successfully to Wasabi storage (${(file.size / (1024 * 1024)).toFixed(1)}MB)`
      
      // Update local data
      contentPages.value.forEach(page => {
        page.items.forEach(item => {
          if (item.id === contentId) {
            item.video_url = videoUrl
            item.has_video = true
            console.log('Updated local data for content:', contentId)
          }
        })
      })
      
      return { success: true, videoUrl, path }
    } else {
      throw new Error('Failed to update content record in database')
    }
    
  } catch (error) {
    console.error('Direct Wasabi upload failed:', error)
    throw error
  } finally {
    uploadProgress.value = 0
    console.log('=== DIRECT WASABI UPLOAD END ===')
  }
}

// Update content record with video URL (after direct upload)
const updateContentVideoUrl = async (contentId, videoUrl, path) => {
  try {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }

    if (authStore.token) {
      headers['Authorization'] = `Bearer ${authStore.token}`
    }

    const sessionApi = axios.create({
      baseURL: (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api',
      headers,
      withCredentials: true
    })

    const response = await sessionApi.patch(`/client/content/${contentId}/video-url`, {
      video_url: videoUrl,
      file_path: path
    })

    return response.data
  } catch (error) {
    console.error('Failed to update content video URL:', error)
    return { success: false, message: error.message }
  }
}
</script>

<style scoped>
.content-item-card {
  transition: all 0.3s ease;
}

.content-item-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.content-input :deep(.v-field__input) {
  cursor: default !important;
}

.upload-btn {
  min-width: 140px;
}

.remove-btn {
  min-width: 120px;
}

.page-header {
  background-color: rgb(var(--v-theme-primary-lighten-5));
  border-radius: 8px;
}

.page-header:hover {
  background-color: rgb(var(--v-theme-primary-lighten-4));
}

:deep(.v-expansion-panel) {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px !important;
  overflow: hidden;
}

:deep(.v-expansion-panel-title) {
  min-height: 64px;
  padding: 16px 24px;
  font-weight: 500;
}

:deep(.v-expansion-panel-text__wrapper) {
  padding: 0;
}

@media (max-width: 768px) {
  .upload-btn,
  .remove-btn {
    width: 100%;
    min-width: unset;
  }
  
  :deep(.v-expansion-panel-title) {
    padding: 12px 16px;
  }
}
</style> 
