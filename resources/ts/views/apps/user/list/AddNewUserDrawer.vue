<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import type { VForm } from 'vuetify/components/VForm'

// Define UserProperties interface since we can't import from @db/apps/users/types
interface UserProperties {
  name: string
  email: string
  password?: string
  role: string
  status: string
  company?: string
  country?: string
  contact?: string
  currentPlan?: string
  [key: string]: any
}

interface Emit {
  (e: 'update:isDrawerOpen', value: boolean): void
  (e: 'userData', value: UserProperties): void
}

interface Props {
  isDrawerOpen: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<Emit>()

const isFormValid = ref(false)
const refForm = ref<VForm>()
const fullName = ref('')
const email = ref('')
const password = ref('')
const company = ref('HoverVid')
const country = ref('USA')
const contact = ref('')
const role = ref('client')
const plan = ref('Basic')
const status = ref('active')

const countries = [
  { title: 'USA', value: 'USA' },
  { title: 'UK', value: 'UK' },
  { title: 'Australia', value: 'Australia' },
  { title: 'Germany', value: 'Germany' },
  { title: 'France', value: 'France' },
  { title: 'India', value: 'India' },
  { title: 'Japan', value: 'Japan' },
  { title: 'Canada', value: 'Canada' },
  { title: 'Mexico', value: 'Mexico' },
  { title: 'Brazil', value: 'Brazil' },
]

const roles = [
  { title: 'Admin', value: 'admin' },
  { title: 'Client', value: 'client' },
  { title: 'Manager', value: 'manager' },
]

const plans = [
  { title: 'Basic', value: 'basic' },
  { title: 'Company', value: 'company' },
  { title: 'Enterprise', value: 'enterprise' },
  { title: 'Team', value: 'team' },
]

const statuses = [
  { title: 'Active', value: 'active' },
  { title: 'Inactive', value: 'inactive' },
  { title: 'Pending', value: 'pending' },
  { title: 'Suspended', value: 'suspended' },
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
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      // Create the user data to send to the backend
      // Make sure it matches the format expected by the Laravel backend
      emit('userData', {
        name: fullName.value, // Backend expects 'name', not 'fullName'
        email: email.value,
        password: password.value || undefined,
        role: role.value,
        status: status.value,
        // Additional frontend-only fields for display
        company: company.value,
        country: country.value,
        contact: contact.value,
        currentPlan: plan.value,
      } as UserProperties)
      
      emit('update:isDrawerOpen', false)
      nextTick(() => {
        refForm.value?.reset()
        refForm.value?.resetValidation()
      })
    }
  })
}

const handleDrawerModelValueUpdate = (val: boolean) => {
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
      title="Add New User"
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
              <!-- 👉 Full name -->
              <VCol cols="12">
                <AppTextField
                  v-model="fullName"
                  :rules="[requiredValidator]"
                  label="Full Name"
                  placeholder="John Doe"
                />
              </VCol>

              <!-- 👉 Email -->
              <VCol cols="12">
                <AppTextField
                  v-model="email"
                  :rules="[requiredValidator, emailValidator]"
                  label="Email"
                  placeholder="johndoe@email.com"
                />
              </VCol>
              
              <!-- 👉 Password (optional) -->
              <VCol cols="12">
                <AppTextField
                  v-model="password"
                  label="Password (optional)"
                  type="password"
                  placeholder="Leave blank for auto-generated password"
                />
              </VCol>

              <!-- 👉 company -->
              <VCol cols="12">
                <AppTextField
                  v-model="company"
                  label="Company"
                  placeholder="HoverVid"
                />
              </VCol>

              <!-- 👉 Country -->
              <VCol cols="12">
                <AppSelect
                  v-model="country"
                  label="Select Country"
                  placeholder="Select Country"
                  :items="countries"
                />
              </VCol>

              <!-- 👉 Contact -->
              <VCol cols="12">
                <AppTextField
                  v-model="contact"
                  type="number"
                  label="Contact"
                  placeholder="+1-541-754-3010"
                />
              </VCol>

              <!-- 👉 Role -->
              <VCol cols="12">
                <AppSelect
                  v-model="role"
                  label="Select Role"
                  placeholder="Select Role"
                  :rules="[requiredValidator]"
                  :items="roles"
                />
              </VCol>

              <!-- 👉 Plan -->
              <VCol cols="12">
                <AppSelect
                  v-model="plan"
                  label="Select Plan"
                  placeholder="Select Plan"
                  :items="plans"
                />
              </VCol>

              <!-- 👉 Status -->
              <VCol cols="12">
                <AppSelect
                  v-model="status"
                  label="Select Status"
                  placeholder="Select Status"
                  :rules="[requiredValidator]"
                  :items="statuses"
                />
              </VCol>

              <!-- 👉 Submit and Cancel -->
              <VCol cols="12">
                <VBtn
                  type="submit"
                  class="me-4"
                >
                  Submit
                </VBtn>
                <VBtn
                  type="reset"
                  variant="tonal"
                  color="error"
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