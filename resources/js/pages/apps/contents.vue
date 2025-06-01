<template>
  <VCard title="Contents">
    <VCardText>
      <VRow>
        <VCol cols="12" class="text-center mb-6">
          <h1 class="text-h4">Contents Management</h1>
          <p class="text-subtitle-1 mt-2" v-if="domain">
            Managing content for: <strong>{{ domain.domain }}</strong>
          </p>
          <p class="text-subtitle-1 mt-2" v-else-if="!isLoading">
            Managing all your content
          </p>
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
      <VRow v-else-if="contentItems.length === 0">
        <VCol cols="12" class="text-center">
          <VIcon size="64" color="muted" class="mb-4">bx-file-blank</VIcon>
          <h3 class="text-h6 mb-2">No Content Found</h3>
          <p class="text-body-2 text-medium-emphasis">
            No content has been detected for your domain yet. Install and activate the HoverVid plugin on your website to start collecting content.
          </p>
        </VCol>
      </VRow>

      <!-- Content items -->
      <div v-else>
        <VRow 
          v-for="(item, index) in contentItems" 
          :key="item.id"
          class="mb-6"
        >
          <VCol cols="12">
            <VCard variant="outlined" class="pa-4">
              <VRow>
                <VCol cols="12" md="8">
                  <VTextarea
                    :model-value="item.content_element"
                    :label="`Content ${index + 1}`"
                    readonly
                    rows="4"
                    auto-grow
                    variant="outlined"
                    class="content-textarea"
                  />
                  <div class="mt-2">
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
                      color="success" 
                      variant="tonal"
                      v-if="item.video_url"
                    >
                      Has Video
                    </VChip>
                    <VChip 
                      size="small" 
                      color="warning" 
                      variant="tonal"
                      v-else
                    >
                      No Video
                    </VChip>
                  </div>
                </VCol>
                <VCol cols="12" md="4" class="d-flex flex-column justify-center">
                  <VBtn
                    prepend-icon="bx-upload"
                    color="primary"
                    size="large"
                    class="mb-3"
                    @click="selectFile(item.id)"
                    :disabled="isRejecting"
                  >
                    Upload Video
                  </VBtn>
                  <VBtn
                    prepend-icon="bx-x"
                    color="error"
                    size="large"
                    variant="outlined"
                    @click="rejectContent(item.id)"
                    :loading="isRejecting && currentRejectingId === item.id"
                    :disabled="isRejecting"
                  >
                    Reject Content
                  </VBtn>
                </VCol>
              </VRow>
            </VCard>
          </VCol>
        </VRow>
      </div>

      <!-- File input (hidden) -->
      <VFileInput
        ref="fileInput"
        v-model="files"
        label="Select File"
        hide-details
        variant="outlined"
        accept="video/*,.mp4,.avi,.mov,.wmv,.flv,.webm"
        class="d-none"
        @update:model-value="handleFileChange"
      />

      <!-- Error message -->
      <VAlert 
        v-if="errorMessage"
        type="error"
        class="mt-4"
        closable
        @click:close="errorMessage = ''"
      >
        {{ errorMessage }}
      </VAlert>

      <!-- Success message -->
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
</template>

<script setup>
import axios from 'axios'
import { nextTick, onMounted, ref } from 'vue'

const contentItems = ref([])
const domain = ref(null)
const files = ref([])
const fileInput = ref(null)
const isLoading = ref(true)
const isRejecting = ref(false)
const currentRejectingId = ref(null)
const errorMessage = ref('')
const successMessage = ref('')
const currentUploadingContentId = ref(null)

// Fetch content on component mount
onMounted(async () => {
  await fetchContent()
})

const fetchContent = async () => {
  try {
    isLoading.value = true
    errorMessage.value = ''

    // Create a session-only axios instance (like in DomainSetupPopup)
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
      contentItems.value = response.data.data.content
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
  currentUploadingContentId.value = contentId
  nextTick(() => {
    fileInput.value.$el.click()
  })
}

const handleFileChange = () => {
  if (files.value && files.value.length > 0) {
    const selectedFile = files.value[0]
    successMessage.value = `Selected file: ${selectedFile.name} for content ID: ${currentUploadingContentId.value?.substring(0, 8)}...`
    // TODO: Implement file upload functionality
  }
}

const rejectContent = async (contentId) => {
  if (!confirm('Are you sure you want to reject this content? This action cannot be undone.')) {
    return
  }

  try {
    isRejecting.value = true
    currentRejectingId.value = contentId
    errorMessage.value = ''
    successMessage.value = ''

    // Create a session-only axios instance
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
      // Remove the content from the list
      contentItems.value = contentItems.value.filter(item => item.id !== contentId)
      successMessage.value = 'Content rejected successfully'
    } else {
      errorMessage.value = response.data.message || 'Failed to reject content'
    }
  } catch (error) {
    console.error('Error rejecting content:', error)
    errorMessage.value = error.response?.data?.message || 'Failed to reject content'
  } finally {
    isRejecting.value = false
    currentRejectingId.value = null
  }
}
</script>

<style scoped>
.content-textarea :deep(.v-field__input) {
  cursor: default !important;
}

.content-textarea :deep(.v-field--disabled) {
  opacity: 1 !important;
}
</style> 
