<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

const props = defineProps({
  isDrawerOpen: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits([
  'update:isDrawerOpen',
  'domainData',
])

const isFormValid = ref(false)
const refForm = ref()
const domainName = ref('')
const userEmail = ref('')
const platform = ref('wordpress')
const isVerified = ref(false)

// Validation functions
const requiredValidator = value => {
  if (!value || String(value).trim().length === 0) {
    return 'This field is required'
  }
  return true
}

const emailValidator = value => {
  if (!value) return true // Let required validator handle empty values
  
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(value)) {
    return 'Please enter a valid email address'
  }
  return true
}

const domainValidator = value => {
  if (!value) return true // Let required validator handle empty values
  
  const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]*\.[a-zA-Z]{2,}$/
  if (!domainRegex.test(value)) {
    return 'Please enter a valid domain name (e.g., example.com)'
  }
  return true
}

const platforms = [
  { title: 'WordPress', value: 'wordpress' },
  { title: 'Static Website', value: 'static' },
]

// 👉 drawer close
const closeNavigationDrawer = () => {
  emit('update:isDrawerOpen', false)
  nextTick(() => {
    refForm.value?.reset()
    refForm.value?.resetValidation()
  })
}

const onSubmit = () => {
  // Manual validation for required fields
  if (!domainName.value.trim()) {
    refForm.value?.validate()
    return
  }
  
  if (!userEmail.value.trim()) {
    refForm.value?.validate()
    return
  }
  
  // Validate form and submit if valid
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      // Create the domain data to send to the backend
      emit('domainData', {
        domain: domainName.value.trim(),
        user_email: userEmail.value.trim(),
        platform: platform.value,
        is_verified: isVerified.value,
      })
      emit('update:isDrawerOpen', false)
      nextTick(() => {
        refForm.value?.reset()
        refForm.value?.resetValidation()
      })
    }
  })
}

const handleDrawerModelValueUpdate = val => {
  emit('update:isDrawerOpen', val)
}
</script>

<template>
  <VNavigationDrawer
    data-allow-mismatch
    temporary
    :width="400"
    location="end"
    border="none"
    class="scrollable-content"
    :model-value="props.isDrawerOpen"
    @update:model-value="handleDrawerModelValueUpdate"
  >
    <!-- 👉 Title -->
    <AppDrawerHeaderSection
      title="Add New Domain"
      @cancel="closeNavigationDrawer"
    />

    <VDivider />

    <PerfectScrollbar :options="{ wheelPropagation: false }">
      <VCard flat>
        <VCardText>
          <!-- 👉 Form -->
          <VForm
            ref="refForm"
            v-model="isFormValid"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <!-- 👉 Domain Name -->
              <VCol cols="12">
                <AppTextField
                  v-model="domainName"
                  :rules="[requiredValidator, domainValidator]"
                  label="Domain Name"
                  placeholder="example.com"
                />
              </VCol>

              <!-- 👉 User Email -->
              <VCol cols="12">
                <AppTextField
                  v-model="userEmail"
                  :rules="[requiredValidator, emailValidator]"
                  label="User Email"
                  placeholder="user@example.com"
                />
              </VCol>
              
              <!-- 👉 Platform -->
              <VCol cols="12">
                <AppSelect
                  v-model="platform"
                  label="Platform"
                  placeholder="Select Platform"
                  :items="platforms"
                />
              </VCol>

              <!-- 👉 Initial Verification Status -->
              <VCol cols="12">
                <VCheckbox
                  v-model="isVerified"
                  label="Enable domain immediately"
                />
              </VCol>

              <!-- 👉 Submit and Cancel -->
              <VCol cols="12">
                <VBtn
                  type="submit"
                  class="me-4"
                >
                  Add Domain
                </VBtn>
                <VBtn
                  type="reset"
                  variant="tonal"
                  color="secondary"
                  @click="closeNavigationDrawer"
                >
                  Cancel
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </PerfectScrollbar>
  </VNavigationDrawer>
</template> 