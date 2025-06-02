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
        <VRow
          v-for="(page, pageIndex) in contentPages"
          :key="pageIndex"
          class="mb-8"
        >
          <VCol cols="12">
            <!-- Page Title Header -->
            <VCard variant="outlined" class="mb-4" color="primary">
              <VCardTitle class="d-flex align-center">
                <VIcon class="me-3">bx-file</VIcon>
                <div class="flex-grow-1">
                  <h5 class="text-h5">{{ page.page_name }}</h5>
                  <p class="text-body-2 mt-1 mb-0">
                    {{ page.content_count }} content {{ page.content_count === 1 ? 'item' : 'items' }}
                  </p>
                </div>
                <VChip 
                  :color="page.items.filter(item => item.has_video).length > 0 ? 'success' : 'warning'"
                  variant="tonal"
                >
                  {{ page.items.filter(item => item.has_video).length }}/{{ page.content_count }} with videos
                </VChip>
              </VCardTitle>
            </VCard>

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
                        <VTextField
                          :model-value="item.text"
                          :label="`Content ${itemIndex + 1}`"
                          variant="outlined"
                          readonly
                          hide-details="auto"
                          class="content-input"
                        />
                        <div class="mt-2 d-flex align-center">
                          <VChip 
                            size="small" 
                            color="info" 
                            variant="tonal"
                            class="me-2"
                          >
                            ID: {{ item.id.substring(0, 8) }}...
                          </VChip>
                          <VChip 
                            size="small" 
                            :color="item.has_video ? 'success' : 'warning'"
                            variant="tonal"
                            class="me-2"
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
          </VCol>
        </VRow>
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
import axios from 'axios'
import { computed, nextTick, onMounted, ref } from 'vue'

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
  await fetchContent()
})

// Methods
const fetchContent = async () => {
  try {
    isLoading.value = true
    errorMessage.value = ''

    const sessionApi = axios.create({
      baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
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
    errorMessage.value = error.response?.data?.message || 'Failed to fetch content'
  } finally {
    isLoading.value = false
  }
}

const selectFile = (contentId) => {
  currentUploadingId.value = contentId
  nextTick(() => {
    fileInput.value.$el.querySelector('input').click()
  })
}

const handleFileChange = async () => {
  if (files.value && files.value.length > 0 && currentUploadingId.value) {
    await uploadVideo(files.value[0], currentUploadingId.value)
  }
}

const uploadVideo = async (file, contentId) => {
  try {
    isUploading.value = true
    errorMessage.value = ''
    successMessage.value = ''

    const formData = new FormData()
    formData.append('video', file)

    const sessionApi = axios.create({
      baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      withCredentials: true
    })

    const response = await sessionApi.post(`/client/content/${contentId}/upload-video`, formData)
    
    if (response.data.success) {
      successMessage.value = `Video uploaded successfully for content ${contentId.substring(0, 8)}...`
      
      // Update the local data
      contentPages.value.forEach(page => {
        page.items.forEach(item => {
          if (item.id === contentId) {
            item.video_url = response.data.data.video_url
            item.has_video = true
          }
        })
      })
      
      // Clear file input
      files.value = []
    } else {
      errorMessage.value = response.data.message || 'Failed to upload video'
    }
  } catch (error) {
    console.error('Error uploading video:', error)
    errorMessage.value = error.response?.data?.message || 'Failed to upload video'
  } finally {
    isUploading.value = false
    currentUploadingId.value = null
  }
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

    const sessionApi = axios.create({
      baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
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
    errorMessage.value = error.response?.data?.message || 'Failed to remove content'
  } finally {
    isRejecting.value = false
    currentRejectingId.value = null
  }
}

const previewVideo = (videoUrl) => {
  previewVideoUrl.value = videoUrl
  videoPreviewDialog.value = true
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

@media (max-width: 768px) {
  .upload-btn,
  .remove-btn {
    width: 100%;
    min-width: unset;
  }
}
</style> 
