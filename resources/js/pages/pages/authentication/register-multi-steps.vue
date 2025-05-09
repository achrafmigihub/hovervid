<script setup>
import registerMultiStepIllustration from '@images/pages/register-multi-step-illustration.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const currentStep = ref(0)
const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)

const radioContent = [
  {
    title: 'Starter',
    desc: 'A simple start for everyone.',
    value: '0',
  },
  {
    title: 'Standard',
    desc: 'For small to medium businesses.',
    value: '99',
  },
  {
    title: 'Enterprise',
    desc: 'Solution for big organizations.',
    value: '499',
  },
]

const items = [
  {
    title: 'Account',
    subtitle: 'Account Details',
    icon: 'bx-home',
  },
  {
    title: 'Personal',
    subtitle: 'Enter Information',
    icon: 'bx-user',
  },
  {
    title: 'Billing',
    subtitle: 'Payment Details',
    icon: 'bx-detail',
  },
]

const form = ref({
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
  link: '',
  firstName: '',
  lastName: '',
  mobile: '',
  pincode: '',
  address: '',
  landmark: '',
  city: '',
  state: null,
  selectedPlan: '0',
  cardNumber: '',
  cardName: '',
  expiryDate: '',
  cvv: '',
})

const onSubmit = () => {

  // eslint-disable-next-line no-alert
  alert('Submitted..!!')
}
</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-2">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

  <VRow
    no-gutters
    class="auth-wrapper"
  >
    <VCol
      md="4"
      class="d-none d-md-flex"
    >
      <!-- here your illustration -->
      <div class="d-flex justify-end pa-8 pe-0 align-center w-100">
        <VImg
          :src="registerMultiStepIllustration"
          class="bg-image flip-in-rtl"
          max-width="350"
        />
      </div>
    </VCol>

    <VCol
      cols="12"
      md="8"
      class="auth-card-v2 d-flex align-center justify-center pa-10"
      style="background-color: rgb(var(--v-theme-surface))"
    >
      <VCard
        flat
        class="mt-12 mt-sm-10"
      >
        <AppStepper
          v-model:current-step="currentStep"
          :items="items"
          :direction="$vuetify.display.smAndUp ? 'horizontal' : 'vertical'"
          icon-size="22"
          class="stepper-icon-step-bg mb-12 mt-8"
        />

        <VWindow
          v-model="currentStep"
          class="disable-tab-transition"
          style="max-inline-size: 681px"
        >
          <VForm>
            <VWindowItem>
              <h4 class="text-h4">
                Account Information
              </h4>
              <p class="text-body-1 mb-6">
                Enter Your Account Details
              </p>

              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.username"
                    autocomplete
                    label="Username"
                    placeholder="Johndoe"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.email"
                    label="Email"
                    autocomplete
                    placeholder="johndoe@email.com"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.password"
                    label="Password"
                    placeholder="············"
                    :type="isPasswordVisible ? 'text' : 'password'"
                    autocomplete="password"
                    :append-inner-icon="
                      isPasswordVisible ? 'bx-hide' : 'bx-show'
                    "
                    @click:append-inner="isPasswordVisible = !isPasswordVisible"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.confirmPassword"
                    label="Confirm Password"
                    autocomplete="confirm-password"
                    placeholder="············"
                    :type="isConfirmPasswordVisible ? 'text' : 'password'"
                    :append-inner-icon="
                      isConfirmPasswordVisible ? 'bx-hide' : 'bx-show'
                    "
                    @click:append-inner="
                      isConfirmPasswordVisible = !isConfirmPasswordVisible
                    "
                  />
                </VCol>

                <VCol cols="12">
                  <AppTextField
                    v-model="form.link"
                    autocomplete
                    label="Profile Link"
                    placeholder="https://profile.com/johndoe"
                    type="url"
                  />
                </VCol>
              </VRow>
            </VWindowItem>

            <VWindowItem>
              <h4 class="text-h4">
                Personal Information
              </h4>
              <p class="text-body-1 mb-6">
                Enter Your Personal Information
              </p>

              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.firstName"
                    label="First Name"
                    autocomplete
                    placeholder="John"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.lastName"
                    label="Last Name"
                    autocomplete
                    placeholder="Doe"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.mobile"
                    type="number"
                    autocomplete
                    label="Mobile"
                    placeholder="+1 123 456 7890"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.pincode"
                    type="number"
                    label="Pincode"
                    autocomplete
                    placeholder="123456"
                  />
                </VCol>

                <VCol cols="12">
                  <AppTextField
                    v-model="form.address"
                    autocomplete
                    label="Address"
                    placeholder="1234 Main St, New York, NY 10001, USA"
                  />
                </VCol>

                <VCol cols="12">
                  <AppTextField
                    v-model="form.landmark"
                    autocomplete
                    label="Landmark"
                    placeholder="Near Central Park"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.city"
                    autocomplete
                    label="City"
                    placeholder="New York"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <AppSelect
                    v-model="form.state"
                    autocomplete
                    label="State"
                    placeholder="Select State"
                    :items="[
                      'New York',
                      'California',
                      'Florida',
                      'Washington',
                      'Texas',
                    ]"
                  />
                </VCol>
              </VRow>
            </VWindowItem>

            <VWindowItem>
              <h4 class="text-h4">
                Select Plan
              </h4>
              <p class="text-body-1 mb-6">
                Select plan as per your requirement
              </p>

              <CustomRadiosWithIcon
                v-model:selected-radio="form.selectedPlan"
                :radio-content="radioContent"
                :grid-column="{ sm: '4', cols: '12' }"
              >
                <template #default="{ item }">
                  <div class="text-center">
                    <h5 class="text-h5 mb-2">
                      {{ item.title }}
                    </h5>
                    <p class="clamp-text text-body-1 mb-2">
                      {{ item.desc }}
                    </p>

                    <div class="d-flex align-center justify-center">
                      <span class="text-primary text-body-1 mb-2">$</span>
                      <span class="text-h3 text-primary">
                        {{ item.value }}
                      </span>
                      <span class="mt-2 text-body-1 text-disabled">/month</span>
                    </div>
                  </div>
                </template>
              </CustomRadiosWithIcon>

              <h4 class="text-h4 mt-12">
                Payment Information
              </h4>
              <p class="text-body-1 mb-6">
                Enter your card information
              </p>

              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="form.cardNumber"
                    autocomplete
                    type="number"
                    label="Card Number"
                    placeholder="1234 1234 1234 1234"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="form.cardName"
                    label="Name on Card"
                    autocomplete
                    placeholder="John Doe"
                  />
                </VCol>

                <VCol
                  cols="6"
                  md="3"
                >
                  <AppTextField
                    v-model="form.expiryDate"
                    autocomplete
                    label="Expiry"
                    placeholder="MM/YY"
                  />
                </VCol>

                <VCol
                  cols="6"
                  md="3"
                >
                  <AppTextField
                    v-model="form.cvv"
                    type="number"
                    autocomplete
                    label="CVV"
                    placeholder="123"
                  />
                </VCol>
              </VRow>
            </VWindowItem>
          </VForm>
        </VWindow>

        <div class="d-flex flex-wrap justify-space-between gap-x-4 mt-6">
          <VBtn
            color="secondary"
            :disabled="currentStep === 0"
            variant="tonal"
            @click="currentStep--"
          >
            <VIcon
              icon="bx-left-arrow-alt"
              start
              class="flip-in-rtl"
            />
            Previous
          </VBtn>

          <VBtn
            v-if="items.length - 1 === currentStep"
            color="success"
            @click="onSubmit"
          >
            submit
          </VBtn>

          <VBtn
            v-else
            @click="currentStep++"
          >
            Next

            <VIcon
              icon="bx-right-arrow-alt"
              end
              class="flip-in-rtl"
            />
          </VBtn>
        </div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
