<script setup>
import usFlag from '@images/icons/countries/us.png'
import americanExDark from '@images/icons/payments/img/ae-dark.png'
import americanExLight from '@images/icons/payments/img/american-express.png'
import masterCardDark from '@images/icons/payments/img/master-dark.png'
import masterCardLight from '@images/icons/payments/img/mastercard.png'
import visaDark from '@images/icons/payments/img/visa-dark.png'
import visaLight from '@images/icons/payments/img/visa-light.png'

const visa = useGenerateImageVariant(visaLight, visaDark)
const masterCard = useGenerateImageVariant(masterCardLight, masterCardDark)
const americanExpress = useGenerateImageVariant(americanExLight, americanExDark)

const currentCardDetails = {
  number: '1234567890123456',
  name: 'John Doe',
  expiry: '12/2028',
  cvv: '123',
  isPrimary: false,
  type: '',
}

const editBillingData = {
  firstName: 'Gertrude',
  lastName: 'Jennings',
  selectedCountry: 'USA',
  addressLine1: '100 Water Plant Avenue',
  addressLine2: 'Building 1303 Wake Island',
  landmark: 'Near Wake Island',
  contact: '+1(609) 933-44-22',
  country: 'USA',
  state: 'Queensland',
  zipCode: 403114,
  city: 'Brisbane',
}

const show = ref([
  false,
  true,
  false,
])

const paymentShow = ref([
  false,
  true,
  false,
])

const isEditAddressDialogVisible = ref(false)
const isCardAddDialogVisible = ref(false)
const isNewEditAddressDialogVisible = ref(false)
const isNewCardAddDialogVisible = ref(false)

const addressData = [
  {
    title: 'Home',
    subtitle: '23 Shatinon Mekalan',
    owner: 'Violet Mendoza',
    defaultAddress: true,
    address: ` 23 Shatinon Mekalan,
    <br>
    Melbourne, VIC 3000,
    <br>
    LondonUK`,
  },
  {
    title: 'Office',
    subtitle: '45 Rocker Terrace',
    owner: 'Violet Mendoza',
    defaultAddress: false,
    address: ` 45 Rocker Terrace,
    <br>
    Latheronwheel,
    <br>
    KW5 8NW, London,
    <br>
    UK`,
  },
  {
    title: 'Family',
    subtitle: '512 Water Plant',
    owner: 'Violet Mendoza',
    defaultAddress: false,
    address: ` 512 Water Plant,
    <br>
    Melbourne, VIC 3000,
    <br>
    LondonUK`,
  },
]

const paymentData = [
  {
    title: 'Mastercard',
    subtitle: 'Expires Apr 2028',
    isDefaultMethod: false,
    image: masterCard,
  },
  {
    title: 'American Express',
    subtitle: 'Expires Apr 2028',
    isDefaultMethod: false,
    image: americanExpress,
  },
  {
    title: 'Visa',
    subtitle: '45 Roker Terrace',
    isDefaultMethod: true,
    image: visa,
  },
]
</script>

<template>
  <!-- eslint-disable vue/no-v-html -->

  <!-- 👉 Address Book -->
  <VCard class="mb-6">
    <VCardText>
      <div class="d-flex justify-space-between mb-6 flex-wrap align-center gap-y-4 gap-x-6">
        <h5 class="text-h5">
          Address Book
        </h5>
        <VBtn
          variant="tonal"
          size="small"
          @click="isNewEditAddressDialogVisible = !isNewEditAddressDialogVisible"
        >
          Add new Address
        </VBtn>
      </div>
      <template
        v-for="(address, index) in addressData"
        :key="index"
      >
        <div>
          <div class="d-flex justify-space-between py-3 gap-y-2 flex-wrap align-center">
            <div class="d-flex align-center gap-x-4">
              <VIcon
                :icon="show[index] ? 'bx-chevron-down' : 'bx-chevron-right'"
                class="flip-in-rtl text-high-emphasis"
                size="24"
                @click="show[index] = !show[index]"
              />
              <div>
                <div class="d-flex align-center gap-x-2 mb-1">
                  <h6 class="text-h6">
                    {{ address.title }}
                  </h6>
                  <VChip
                    v-if="address.defaultAddress"
                    color="success"
                    label
                    size="small"
                  >
                    Default Address
                  </VChip>
                </div>
                <div class="text-body-1">
                  {{ address.subtitle }}
                </div>
              </div>
            </div>
            <div class="ms-5">
              <IconBtn @click="isEditAddressDialogVisible = !isEditAddressDialogVisible">
                <VIcon
                  icon="bx-edit"
                  class="flip-in-rtl"
                />
              </IconBtn>
              <IconBtn>
                <VIcon
                  icon="bx-trash"
                  class="flip-in-rtl"
                />
              </IconBtn>
              <IconBtn>
                <VIcon
                  icon="bx-dots-vertical-rounded"
                  class="flip-in-rtl"
                />
              </IconBtn>
            </div>
          </div>

          <VExpandTransition>
            <div v-show="show[index]">
              <div class="px-10 pb-3">
                <h6 class="mb-1 text-h6">
                  {{ address.owner }}
                </h6>
                <div
                  class="text-body-1"
                  v-html="address.address"
                />
              </div>
            </div>
          </VExpandTransition>

          <VDivider v-if="index !== addressData.length - 1" />
        </div>
      </template>
    </VCardText>
  </VCard>

  <!-- 👉 Payment Methods -->
  <VCard>
    <VCardText>
      <div class="d-flex justify-space-between mb-6 flex-wrap align-center gap-y-4 gap-x-6">
        <h5 class="text-h5">
          Payment Methods
        </h5>
        <VBtn
          variant="tonal"
          size="small"
          @click="isNewCardAddDialogVisible = !isNewCardAddDialogVisible"
        >
          Add Payment Methods
        </VBtn>
      </div>
      <template
        v-for="(payment, index) in paymentData"
        :key="index"
      >
        <div>
          <div class="d-flex justify-space-between py-3 gap-y-2 flex-wrap align-center">
            <div class="d-flex align-center gap-x-4">
              <VIcon
                :icon="paymentShow[index] ? 'bx-chevron-down' : 'bx-chevron-right'"
                size="24"
                class="flip-in-rtl text-high-emphasis"
                @click="paymentShow[index] = !paymentShow[index]"
              />
              <VImg
                :src="payment.image.value"
                height="30"
                width="50"
              />
              <div>
                <div class="d-flex gap-x-2 mb-1">
                  <h6 class="text-h6">
                    {{ payment.title }}
                  </h6>
                  <VChip
                    v-if="payment.isDefaultMethod"
                    color="success"
                    label
                    size="small"
                  >
                    Default Method
                  </VChip>
                </div>
                <div class="text-body-1">
                  {{ payment.subtitle }}
                </div>
              </div>
            </div>
            <div class="ms-5">
              <IconBtn @click="isCardAddDialogVisible = !isCardAddDialogVisible">
                <VIcon
                  icon="bx-edit"
                  class="flip-in-rtl"
                />
              </IconBtn>
              <IconBtn>
                <VIcon
                  icon="bx-trash"
                  class="flip-in-rtl"
                />
              </IconBtn>
              <IconBtn>
                <VIcon
                  icon="bx-dots-vertical-rounded"
                  class="flip-in-rtl"
                />
              </IconBtn>
            </div>
          </div>
          <VExpandTransition>
            <div v-show="paymentShow[index]">
              <div class="px-10 pb-3">
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTable>
                      <tr class="text-sm">
                        <td class="pb-1">
                          Name
                        </td>
                        <td class="font-weight-medium text-high-emphasis pb-1">
                          Violet Mendoza
                        </td>
                      </tr>
                      <tr class="text-sm">
                        <td class="pb-1">
                          Number
                        </td>
                        <td class="font-weight-medium text-high-emphasis pb-1">
                          **** 4487
                        </td>
                      </tr>
                      <tr class="text-sm">
                        <td class="pb-1">
                          Expires
                        </td>
                        <td class="font-weight-medium text-high-emphasis pb-1">
                          08/2028
                        </td>
                      </tr>
                      <tr class="text-sm">
                        <td class="pb-1">
                          Type
                        </td>
                        <td class="font-weight-medium text-high-emphasis pb-1">
                          Master Card
                        </td>
                      </tr>
                      <tr class="text-sm">
                        <td class="pb-1">
                          Issuer
                        </td>
                        <td class="font-weight-medium text-high-emphasis pb-1">
                          VICBANK
                        </td>
                      </tr>
                      <tr class="text-sm">
                        <td class="pb-1">
                          ID
                        </td>
                        <td class="font-weight-medium text-high-emphasis pb-1">
                          DH73DJ8
                        </td>
                      </tr>
                    </VTable>
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTable>
                      <tr class="text-sm">
                        <td class="pb-1">
                          Billing
                        </td>
                        <td class="font-weight-medium text-high-emphasis pb-1">
                          United Kingdom
                        </td>
                      </tr>
                      <tr class="text-sm">
                        <td class="pb-1">
                          Number
                        </td>
                        <td class="font-weight-medium text-high-emphasis pb-1">
                          +7634 983 637
                        </td>
                      </tr>
                      <tr class="text-sm">
                        <td class="pb-1">
                          Email
                        </td>
                        <td class="font-weight-medium text-high-emphasis pb-1">
                          vafgot@vultukir.org
                        </td>
                      </tr>
                      <tr class="text-sm">
                        <td class="pb-1">
                          Origin
                        </td>
                        <td class="d-flex pb-1">
                          <div class="me-2 font-weight-medium text-high-emphasis">
                            United States
                          </div>
                          <img
                            :src="usFlag"
                            height="20"
                            width="20"
                          >
                        </td>
                      </tr>
                      <tr class="text-sm mb-1">
                        <td>CVC Check</td>
                        <td class="d-flex">
                          <div class="me-2 font-weight-medium text-high-emphasis">
                            Passed
                          </div>
                          <VAvatar
                            variant="tonal"
                            color="success"
                            size="20"
                            inline
                          >
                            <VIcon
                              icon="bx-check"
                              color="success"
                              size="12"
                            />
                          </VAvatar>
                        </td>
                      </tr>
                    </VTable>
                  </VCol>
                </VRow>
              </div>
            </div>
          </VExpandTransition>
          <VDivider v-if="index !== paymentData.length - 1" />
        </div>
      </template>
    </VCardText>
  </VCard>
  <AddEditAddressDialog
    v-model:is-dialog-visible="isEditAddressDialogVisible"
    :billing-address="editBillingData"
  />
  <AddEditAddressDialog v-model:is-dialog-visible="isNewEditAddressDialogVisible" />
  <CardAddEditDialog
    v-model:is-dialog-visible="isCardAddDialogVisible"
    :card-details="currentCardDetails"
  />
  <CardAddEditDialog v-model:is-dialog-visible="isNewCardAddDialogVisible" />
</template>
