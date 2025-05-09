<script setup>
const {
  data: productList,
  error,
} = await useApi('pages/datatable')

const search = ref('')

// headers
const headers = [
  {
    title: 'PRODUCT',
    key: 'product.name',
  },
  {
    title: 'DATE',
    key: 'date',
  },
  {
    title: 'CATEGORY',
    key: 'product.category',
  },
  {
    title: 'BUYERS',
    key: 'buyer.name',
  },
  {
    title: 'PAYMENT',
    key: 'payment',
    sortable: false,
  },
  {
    title: 'STATUS',
    key: 'status',
    sortable: false,
  },
  {
    title: 'DELETE',
    key: 'delete',
    sortable: false,
  },
]

const deleteItem = itemId => {
  if (!productList.value)
    return
  const index = productList.value.findIndex(item => item.product.id === itemId)

  productList.value.splice(index, 1)
}

const categoryIcons = [
  {
    name: 'Mouse',
    icon: 'bx-mouse',
    color: 'warning',
  },
  {
    name: 'Glass',
    icon: 'bx-glasses',
    color: 'primary',
  },
  {
    name: 'Smart Watch',
    icon: 'bx-bxs-watch',
    color: 'success',
  },
  {
    name: 'Bag',
    icon: 'bx-briefcase',
    color: 'info',
  },
  {
    name: 'Storage Device',
    icon: 'bx-memory-card',
    color: 'warning',
  },
  {
    name: 'Bluetooth',
    icon: 'bx-bluetooth',
    color: 'error',
  },
  {
    name: 'Gaming',
    icon: 'bx-game',
    color: 'warning',
  },
  {
    name: 'Home',
    icon: 'bx-home',
    color: 'error',
  },
  {
    name: 'VR',
    icon: 'bx-camera-movie',
    color: 'primary',
  },
  {
    name: 'Shoes',
    icon: 'bx-store',
    color: 'success',
  },
  {
    name: 'Electronics',
    icon: 'bx-chip',
    color: 'info',
  },
  {
    name: 'Projector',
    icon: 'bx-movie',
    color: 'warning',
  },
  {
    name: 'iPod',
    icon: 'bx-podcast',
    color: 'error',
  },
  {
    name: 'Keyboard',
    icon: 'bx-bxs-keyboard',
    color: 'primary',
  },
  {
    name: 'Smart Phone',
    icon: 'bx-mobile',
    color: 'success',
  },
  {
    name: 'Smart TV',
    icon: 'bx-tv',
    color: 'info',
  },
  {
    name: 'Google Home',
    icon: 'bx-bxl-google',
    color: 'warning',
  },
  {
    name: 'Mac',
    icon: 'bx-bxl-apple',
    color: 'error',
  },
  {
    name: 'Headphone',
    icon: 'bx-headphone',
    color: 'primary',
  },
  {
    name: 'iMac',
    icon: 'bx-laptop',
    color: 'success',
  },
  {
    name: 'iPhone',
    icon: 'bx-bxl-apple',
    color: 'warning',
  },
]

const resolveStatusColor = status => {
  if (status === 'Confirmed')
    return 'primary'
  if (status === 'Completed')
    return 'success'
  if (status === 'Cancelled')
    return 'error'
}

const categoryIconFilter = categoryName => {
  const index = categoryIcons.findIndex(category => category.name === categoryName)
  if (index !== -1)
    return [{
      icon: categoryIcons[index].icon,
      color: categoryIcons[index].color,
    }]
  
  return [{
    icon: 'bx-help-circle',
    color: 'primary',
  }]
}

if (error.value)
  console.error(error.value)
</script>

<template>
  <div>
    <VCardText class="pt-0">
      <VRow>
        <VCol
          cols="12"
          offset-md="8"
          md="4"
        >
          <AppTextField
            v-model="search"
            placeholder="Search ..."
            append-inner-icon="bx-search"
            single-line
            hide-details
            dense
            outlined
          />
        </VCol>
      </VRow>
    </VCardText>

    <!-- 👉 Data Table  -->
    <VDataTable
      :headers="headers"
      :items="productList || []"
      :search="search"
      :items-per-page="5"
      class="text-no-wrap"
    >
      <!-- product -->
      <template #item.product.name="{ item }">
        <div class="d-flex align-center">
          <div>
            <VImg
              :src="item.product.image"
              height="40"
              width="40"
            />
          </div>
          <div class="d-flex flex-column ms-3">
            <span class="d-block font-weight-medium text-truncate text-high-emphasis">{{ item.product.name }}</span>
            <span class="text-xs">{{ item.product.brand }}</span>
          </div>
        </div>
      </template>

      <!-- category -->
      <template #item.product.category="{ item }">
        <div class="d-flex align-center">
          <VAvatar
            v-for="(category, index) in categoryIconFilter(item.product.category)"
            :key="index"
            size="26"
            :color="category.color"
            variant="tonal"
          >
            <VIcon
              size="20"
              :color="category.color"
              class="rounded-0"
            >
              {{ category.icon }}
            </VIcon>
          </VAvatar>
          <span class="ms-1 text-no-wrap">{{ item.product.category }}</span>
        </div>
      </template>

      <!-- buyer -->
      <template #item.buyer.name="{ item }">
        <div class="d-flex align-center">
          <VAvatar
            size="1.875rem"
            :color="!item.buyer.avatar ? 'primary' : undefined"
            :variant="!item.buyer.avatar ? 'tonal' : undefined"
          >
            <VImg
              v-if="item.buyer.avatar"
              :src="item.buyer.avatar"
            />
            <span v-else>{{ item.buyer.name.slice(0, 2).toUpperCase() }}</span>
          </VAvatar>
          <span class="text-no-wrap font-weight-medium text-high-emphasis ms-2">{{ item.buyer.name }}</span>
        </div>
      </template>

      <!-- Payment -->
      <template #item.payment="{ item }">
        <div class="d-flex flex-column">
          <div class="d-flex align-center">
            <span class="text-high-emphasis font-weight-medium">${{ item.payment.paidAmount }}</span>
            <span v-if="item.payment.paidAmount !== item.payment.total">/{{ item.payment.total }}</span>
          </div>
          <span class="text-xs text-no-wrap">{{ item.payment.receivedPaymentStatus }}</span>
        </div>
      </template>

      <!-- Status -->
      <template #item.status="{ item }">
        <VChip
          :color="resolveStatusColor(item.payment.status)"
          :class="`text-${resolveStatusColor(item.payment.status)}`"
          size="small"
          class="font-weight-medium"
        >
          {{ item.payment.status }}
        </VChip>
      </template>

      <!-- Delete -->
      <template #item.delete="{ item }">
        <IconBtn @click="deleteItem(item.product.id)">
          <VIcon icon="bx-trash" />
        </IconBtn>
      </template>
    </VDataTable>
  </div>
</template>
