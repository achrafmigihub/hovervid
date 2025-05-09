<script setup>
import { useGenerateImageVariant } from '@/@core/composable/useGenerateImageVariant'
import avatar7 from '@images/avatars/avatar-1.png'
import avatar14 from '@images/avatars/avatar-14.png'
import avatar15 from '@images/avatars/avatar-15.png'
import avatar8 from '@images/avatars/avatar-8.png'
import avatar9 from '@images/avatars/avatar-9.png'
import paypalDark from '@images/icons/payments/img/paypal-dark.png'
import paypalLight from '@images/icons/payments/img/paypal-light.png'
import masterCard from '@images/icons/payments/mastercard-icon.png'

const paypal = useGenerateImageVariant(paypalLight, paypalDark)

const customersData = [
  {
    amount: 459.65,
    status: 'Paid',
    paidBy: 'mastercard',
    email: 'jok@puc.co.uk',
    customerName: 'Henry Barnes',
    avatarSrc: avatar7,
  },
  {
    amount: 93.81,
    paidBy: 'mastercard',
    status: 'Pending',
    email: 'sami@lelo.com',
    customerName: 'Herman Holland',
    avatarSrc: avatar15,
  },
  {
    paidBy: 'paypal',
    amount: 934.34,
    status: 'Pending',
    email: 'initus@odemi.com',
    customerName: 'Hallie Warner',
    avatarSrc: avatar9,
  },
  {
    status: 'Paid',
    amount: 794.97,
    paidBy: 'mastercard',
    email: 'tum@upkesja.gov',
    customerName: 'John Davidson',
    avatarSrc: avatar14,
  },
  {
    amount: 19.49,
    status: 'Paid',
    paidBy: 'mastercard',
    email: 'wipare@tin.com',
    customerName: 'Cora Schmidt',
  },
  {
    amount: 636.27,
    paidBy: 'mastercard',
    status: 'Failed',
    email: 'nur@kaomor.edu',
    customerName: 'Betty Ross',
    avatarSrc: avatar8,
  },
]

const resolveStatusColor = value => {
  if (value === 'Paid')
    return 'success'
  else if (value === 'Pending')
    return 'warning'
  else if (value === 'Failed')
    return 'error'
  else
    return 'secondary'
}

const resolvePaidByLogo = value => {
  if (value === 'mastercard')
    return masterCard
  else if (value === 'paypal')
    return paypal.value
  else
    return ''
}
</script>

<template>
  <VCard>
    <VTable class="text-no-wrap">
      <thead>
        <tr class="text-uppercase">
          <th>
            Customer
          </th>
          <th>
            Amount
          </th>
          <th>
            Status
          </th>
          <th>
            Paid By
          </th>
          <th class="text-center">
            Actions
          </th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="data in customersData"
          :key="data.customerName"
        >
          <td
            class="d-flex align-center gap-3 py-2"
            style="min-block-size: 60px;"
          >
            <VAvatar
              color="secondary"
              variant="tonal"
              size="34"
            >
              <VImg
                v-if="data.avatarSrc"
                :src="data.avatarSrc"
              />
              <VIcon
                v-else
                icon="bx-bxs-user"
              />
            </VAvatar>

            <div>
              <h6 class="text-base font-weight-medium">
                {{ data.customerName }}
              </h6>
              <span class="text-sm d-inline-block">{{ data.email }}</span>
            </div>
          </td>

          <td>
            <p class="text-base mb-0">
              ${{ data.amount }}
            </p>
          </td>

          <td>
            <VChip
              label
              size="small"
              :color="resolveStatusColor(data.status)"
            >
              {{ data.status }}
            </VChip>
          </td>

          <td>
            <img
              width="30"
              height="18"
              :src="resolvePaidByLogo(data.paidBy)"
            >
          </td>

          <td class="text-center">
            <MoreBtn class="text-medium-emphasis" />
          </td>
        </tr>
      </tbody>
    </VTable>
  </VCard>
</template>
