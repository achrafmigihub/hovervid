<script setup>
import americanExpress from '@images/icons/payments/american-express.png'
import mastercard from '@images/icons/payments/mastercard.png'
import visa from '@images/icons/payments/visa.png'

const isUpgradePlanDialogVisible = ref(false)
const currentCardDetails = ref()
const isCardEditDialogVisible = ref(false)
const isCardAddDialogVisible = ref(false)
const isEditAddressDialogVisible = ref(false)

const openEditCardDialog = cardDetails => {
  currentCardDetails.value = cardDetails
  isCardEditDialogVisible.value = true
}

const creditCards = [
  {
    name: 'Tom McBride',
    number: '4851234567899865',
    expiry: '12/24',
    isPrimary: true,
    isExpired: false,
    type: 'mastercard',
    cvv: '123',
    image: mastercard,
  },
  {
    name: 'Mildred Wagner',
    number: '5531234567895678',
    expiry: '02/24',
    isPrimary: false,
    isExpired: false,
    type: 'visa',
    cvv: '456',
    image: visa,
  },
  {
    name: 'Lester Jennings',
    number: '5531234567890002',
    expiry: '08/20',
    isPrimary: false,
    isExpired: true,
    type: 'visa',
    cvv: '456',
    image: americanExpress,
  },
]

const currentAddress = {
  companyName: 'Themeselection',
  billingEmail: 'gertrude@gmail.com',
  taxID: 'TAX-875623',
  vatNumber: 'SDF754K77',
  address: '100 Water Plant Avenue, Building 1303 Wake Island',
  contact: '+1(609) 933-44-22',
  country: 'USA',
  state: 'Queensland',
  zipCode: 403114,
}

const currentBillingAddress = {
  firstName: 'Shamus',
  lastName: 'Tuttle',
  selectedCountry: 'USA',
  addressLine1: '45 Rocker Terrace',
  addressLine2: 'Latheronwheel',
  landmark: 'KW5 8NW, London',
  contact: '+1 (609) 972-22-22',
  country: 'USA',
  city: 'London',
  state: 'London',
  zipCode: 110001,
}
</script>

<template>
  <VRow>
    <!-- 👉 Current Plan -->
    <VCol cols="12">
      <VCard title="Current Plan">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="6"
              order-md="1"
              order="2"
            >
              <h6 class="text-h6 mb-1">
                Your Current Plan is Basic
              </h6>
              <p>
                A simple start for everyone
              </p>

              <h6 class="text-h6 mb-1">
                Active until Dec 09, 2021
              </h6>
              <p>
                We will send you a notification upon Subscription expiration
              </p>

              <h6 class="text-h6 mb-1">
                <span class="d-inline-block me-2">$99 Per Month</span>
                <VChip
                  color="primary"
                  size="small"
                  label
                >
                  Popular
                </VChip>
              </h6>
              <p class="mb-0">
                Standard plan for small to medium businesses
              </p>
            </VCol>

            <VCol
              cols="12"
              md="6"
              order-md="2"
              order="1"
            >
              <!-- 👉 Alert -->
              <VAlert
                color="warning"
                variant="tonal"
              >
                <VAlertTitle class="mb-1">
                  We need your attention!
                </VAlertTitle>
                <div class="text-base">
                  Your plan requires update
                </div>
              </VAlert>

              <!-- 👉 Progress -->
              <div class="d-flex justify-space-between font-weight-bold mt-4 mb-1">
                <h6 class="text-h6">
                  Days
                </h6>
                <h6 class="text-h6">
                  26 of 30 Days
                </h6>
              </div>

              <VProgressLinear
                rounded
                color="primary"
                :height="10"
                :model-value="75"
              />
              <p class="text-sm mt-1">
                Your plan requires update
              </p>
            </VCol>

            <VCol
              cols="12"
              order="3"
            >
              <div class="d-flex flex-wrap gap-4">
                <VBtn @click="isUpgradePlanDialogVisible = true">
                  upgrade plan
                </VBtn>

                <VBtn
                  color="error"
                  variant="tonal"
                >
                  Cancel Subscription
                </VBtn>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Payment Methods -->
    <VCol cols="12">
      <VCard title="Payment Methods">
        <template #append>
          <VBtn
            prepend-icon="bx-plus"
            size="small"
            @click="isCardAddDialogVisible = !isCardAddDialogVisible"
          >
            Add Card
          </VBtn>
        </template>

        <VCardText class="d-flex flex-column gap-y-4">
          <VCard
            v-for="card in creditCards"
            :key="card.name"
            border
            flat
          >
            <VCardText class="d-flex flex-sm-row flex-column gap-6 justify-space-between">
              <div class="text-no-wrap">
                <img
                  :src="card.image"
                  :height="25"
                >
                <div class="my-2 d-flex gap-x-2 align-center">
                  <h6 class="text-h6">
                    {{ card.name }}
                  </h6>
                  <VChip
                    v-if="card.isPrimary || card.isExpired"
                    label
                    :color="card.isPrimary ? 'primary' : card.isExpired ? 'error' : 'secondary'"
                    size="small"
                  >
                    {{ card.isPrimary ? 'Popular' : card.isExpired ? 'Expired' : '' }}
                  </VChip>
                </div>
                <div class="text-body-1">
                  **** **** **** {{ card.number.substring(card.number.length - 4) }}
                </div>
              </div>

              <div class="d-flex flex-column text-sm-end gap-y-4">
                <div class="order-sm-0 order-1">
                  <VBtn
                    variant="tonal"
                    size="small"
                    class="me-4"
                    @click="openEditCardDialog(card)"
                  >
                    Edit
                  </VBtn>
                  <VBtn
                    color="error"
                    variant="tonal"
                    size="small"
                  >
                    Delete
                  </VBtn>
                </div>

                <div class="order-sm-1 order-0 text-sm">
                  Card expires at {{ card.expiry }}
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCardText>
      </VCard>
    </VCol>

    <VCol cols="12">
      <!-- 👉 Billing Address -->
      <VCard title="Billing Address">
        <template #append>
          <VBtn
            size="small"
            prepend-icon="bx-plus"
            @click="isEditAddressDialogVisible = !isEditAddressDialogVisible"
          >
            Edit Address
          </VBtn>
        </template>

        <VCardText>
          <VRow>
            <VCol
              cols="12"
              lg="6"
            >
              <VTable class="billing-address-table">
                <tr>
                  <td>
                    <h6 class="text-h6 text-no-wrap mb-2">
                      Company Name:
                    </h6>
                  </td>
                  <td>
                    <p class="text-body-1 mb-2">
                      {{ currentAddress.companyName }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td>
                    <h6 class="text-h6 text-no-wrap mb-2">
                      Billing Email:
                    </h6>
                  </td>
                  <td>
                    <p class="text-body-1 mb-2">
                      {{ currentAddress.billingEmail }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td>
                    <h6 class="text-h6 text-no-wrap mb-2">
                      Tax ID:
                    </h6>
                  </td>
                  <td>
                    <p class="text-body-1 mb-2">
                      {{ currentAddress.taxID }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td>
                    <h6 class="text-h6 text-no-wrap mb-2">
                      VAT Number:
                    </h6>
                  </td>
                  <td>
                    <p class="text-body-1 mb-2">
                      {{ currentAddress.vatNumber }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td class="d-flex align-baseline">
                    <h6 class="text-h6 text-no-wrap">
                      Billing Address:
                    </h6>
                  </td>
                  <td>
                    <p class="text-body-1 mb-2">
                      {{ currentAddress.address }}
                    </p>
                  </td>
                </tr>
              </VTable>
            </VCol>

            <VCol
              cols="12"
              lg="6"
            >
              <VTable class="billing-address-table">
                <tr>
                  <td>
                    <h6 class="text-h6 text-no-wrap mb-2">
                      Contact:
                    </h6>
                  </td>
                  <td>
                    <p class="text-body-1 mb-2">
                      {{ currentAddress.contact }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td>
                    <h6 class="text-h6 text-no-wrap mb-2">
                      Country:
                    </h6>
                  </td>
                  <td>
                    <p class="text-body-1 mb-2">
                      {{ currentAddress.country }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td>
                    <h6 class="text-h6 text-no-wrap mb-2">
                      State:
                    </h6>
                  </td>
                  <td>
                    <p class="text-body-1 mb-2">
                      {{ currentAddress.state }}
                    </p>
                  </td>
                </tr>
                <tr>
                  <td>
                    <h6 class="text-h6 text-no-wrap mb-2">
                      Zip Code:
                    </h6>
                  </td>
                  <td>
                    <p class="text-body-1 mb-2">
                      {{ currentAddress.zipCode }}
                    </p>
                  </td>
                </tr>
              </VTable>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <!-- 👉 Edit Card Dialog -->
  <CardAddEditDialog
    v-model:is-dialog-visible="isCardEditDialogVisible"
    :card-details="currentCardDetails"
  />

  <!-- 👉 Add Card Dialog -->
  <CardAddEditDialog v-model:is-dialog-visible="isCardAddDialogVisible" />

  <!-- 👉 Edit Address dialog -->
  <AddEditAddressDialog
    v-model:is-dialog-visible="isEditAddressDialogVisible"
    :billing-address="currentBillingAddress"
  />

  <!-- 👉 Upgrade plan dialog -->
  <UserUpgradePlanDialog v-model:is-dialog-visible="isUpgradePlanDialogVisible" />
</template>

<style lang="scss">
.billing-address-table {
  tr {
    td:first-child {
      inline-size: 148px;
    }
  }
}
</style>
