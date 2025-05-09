<script setup>
import appleImacPro from '@images/eCommerce/imac-pro.png'
import appleIPhone11Pro from '@images/eCommerce/iphone-11.png'
import logitechMx from '@images/eCommerce/logitech-mx.png'
import miLedTv4x from '@images/eCommerce/mi-tv.png'
import samsungNote10 from '@images/eCommerce/note10.png'
import onePlus7 from '@images/ecommerce-images/product-21.png'
import appleMagicMouse from '@images/ecommerce-images/product-22.png'

const tableData = [
  {
    amountPaid: 120,
    brand: 'OnePlus',
    amountToPay: 499,
    status: 'Confirmed',
    category: 'smartphone',
    product: 'OnePlus 7Pro',
    src: onePlus7,
  },
  {
    brand: 'Apple',
    amountPaid: 149,
    amountToPay: 149,
    category: 'mouse',
    status: 'Completed',
    product: 'Magic Mouse',
    src: appleMagicMouse,
  },
  {
    amountPaid: 0,
    brand: 'Apple',
    amountToPay: 899,
    status: 'Cancelled',
    product: 'iMac Pro',
    category: 'computer',
    src: appleImacPro,
  },
  {
    amountPaid: 169,
    brand: 'Samsung',
    amountToPay: 169,
    product: 'Note 10',
    status: 'Completed',
    category: 'smartphone',
    src: samsungNote10,
  },
  {
    brand: 'Apple',
    amountPaid: 399,
    amountToPay: 399,
    status: 'Completed',
    category: 'smartphone',
    product: 'iPhone 11 Pro',
    src: appleIPhone11Pro,
  },
  {
    brand: 'Xiaomi',
    amountPaid: 349,
    amountToPay: 2599,
    status: 'Confirmed',
    category: 'smart-tv',
    product: 'Mi Led TV 4X',
    src: miLedTv4x,
  },
  {
    amountPaid: 89,
    amountToPay: 89,
    brand: 'Logitech',
    category: 'mouse',
    status: 'Completed',
    product: 'Logitech MX',
    src: logitechMx,
  },
]

const resolveCategoryIcon = value => {
  if (value === 'smartphone')
    return {
      icon: 'bx-mobile',
      color: 'primary',
    }
  else if (value === 'mouse')
    return {
      icon: 'bx-mouse',
      color: 'warning',
    }
  else if (value === 'computer')
    return {
      icon: 'bx-desktop',
      color: 'info',
    }
  else if (value === 'smart-tv')
    return {
      icon: 'bx-tv',
      color: 'error',
    }
  
  return 'bx-mobile-alt'
}

const checkPaidOrNot = (value, total) => {
  if (value === 0)
    return 'Unpaid'
  else if (value < total)
    return 'Partially Paid'
  
  return 'Fully Paid'
}

const resolveStatusColor = value => {
  if (value === 'Completed')
    return 'success'
  else if (value === 'Cancelled')
    return 'error'
  
  return 'primary'
}
</script>

<template>
  <VCard>
    <VTable class="text-no-wrap">
      <thead>
        <tr>
          <th>PRODUCT</th>
          <th>CATEGORY</th>
          <th>PAYMENT</th>
          <th>ORDER STATUS</th>
          <th>ACTIONS</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="data in tableData"
          :key="data.brand"
        >
          <td style="block-size: 62px;">
            <div class="d-flex align-center gap-3">
              <div>
                <VImg
                  height="32"
                  width="32"
                  :src="data.src"
                />
              </div>

              <div>
                <h6 class="text-base font-weight-medium">
                  {{ data.product }}
                </h6>
                <span class="text-sm d-inline-block">{{ data.brand }}</span>
              </div>
            </div>
          </td>

          <td>
            <div class="d-flex gap-3 align-center">
              <VAvatar
                variant="tonal"
                size="28"
                v-bind="resolveCategoryIcon(data.category)"
              >
                <VIcon size="16" />
              </VAvatar>
              <span class="text-base text-capitalize">{{ data.category }}</span>
            </div>
          </td>

          <td>
            <p class="text-base mb-0">
              <span class="text-primary font-weight-medium">${{ data.amountPaid }}</span>
              <span v-if="data.amountToPay !== data.amountPaid">/{{ data.amountToPay }}</span>
            </p>
            <span class="text-sm d-inline-block">{{ checkPaidOrNot(data.amountPaid, data.amountToPay) }}</span>
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
            <MoreBtn class="text-medium-emphasis" />
          </td>
        </tr>
      </tbody>
    </VTable>
  </VCard>
</template>
