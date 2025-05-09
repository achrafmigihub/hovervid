<script setup>
const searchQuery = ref('')
const selectedStatus = ref()
const selectedRows = ref([])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

// 👉 headers
const headers = [
  {
    title: '#',
    key: 'id',
  },
  {
    title: 'Status',
    key: 'status',
    sortable: false,
  },
  {
    title: 'Client',
    key: 'client',
  },
  {
    title: 'Total',
    key: 'total',
  },
  {
    title: 'Issued Date',
    key: 'date',
  },
  {
    title: 'Balance',
    key: 'balance',
  },
  {
    title: 'Actions',
    key: 'actions',
    sortable: false,
  },
]

const {
  data: invoiceData,
  execute: fetchInvoices,
} = await useApi(createUrl('/apps/invoice', {
  query: {
    q: searchQuery,
    status: selectedStatus,
    itemsPerPage,
    page,
    sortBy,
    orderBy,
  },
}))

const invoices = computed(() => invoiceData.value?.invoices)
const totalInvoices = computed(() => invoiceData.value?.totalInvoices)

// 👉 Invoice balance variant resolver
const resolveInvoiceBalanceVariant = (balance, total) => {
  if (balance === total)
    return {
      status: 'Unpaid',
      chip: { color: 'error' },
    }
  if (balance === 0)
    return {
      status: 'Paid',
      chip: { color: 'success' },
    }
  
  return {
    status: balance,
    chip: { variant: 'text' },
  }
}

const resolveInvoiceStatusVariantAndIcon = status => {
  if (status === 'Partial Payment')
    return {
      variant: 'warning',
      icon: 'bx-pie-chart-alt',
    }
  if (status === 'Paid')
    return {
      variant: 'success',
      icon: 'bx-check',
    }
  if (status === 'Downloaded')
    return {
      variant: 'info',
      icon: 'bx-down-arrow-alt',
    }
  if (status === 'Draft')
    return {
      variant: 'primary',
      icon: 'bx-folder',
    }
  if (status === 'Sent')
    return {
      variant: 'secondary',
      icon: 'bx-envelope',
    }
  if (status === 'Past Due')
    return {
      variant: 'error',
      icon: 'bx-error-circle',
    }
  
  return {
    variant: 'secondary',
    icon: 'bx-x',
  }
}

const deleteInvoice = async id => {
  await $api(`/apps/invoice/${ id }`, { method: 'DELETE' })
  fetchInvoices()
}
</script>

<template>
  <VCard
    v-if="invoices"
    id="invoice-list"
  >
    <VCardItem>
      <VCardTitle>
        Billing History
      </VCardTitle>
    </VCardItem>

    <VCardText class="d-flex align-center flex-wrap gap-4 pt-6">
      <!-- 👉 Create invoice -->

      <div class="d-flex gap-2">
        <VLabel>Show</VLabel>
        <AppSelect
          v-model="itemsPerPage"
          :items="[5, 10, 20, 25, 50]"
        />
      </div>

      <VBtn
        prepend-icon="bx-plus"
        :to="{ name: 'apps-invoice-add' }"
      >
        Create invoice
      </VBtn>

      <VSpacer />

      <div class="d-flex align-end flex-wrap gap-4">
        <!-- 👉 Search  -->
        <div class="invoice-list-search">
          <AppTextField
            v-model="searchQuery"
            placeholder="Search Invoice"
          />
        </div>
        <div>
          <AppSelect
            v-model="selectedStatus"
            placeholder="Invoice Status"
            clearable
            clear-icon="bx-x"
            :items="['Downloaded', 'Draft', 'Sent', 'Paid', 'Partial Payment', 'Past Due']"
            style="inline-size: 10rem;"
          />
        </div>
      </div>
    </VCardText>

    <VDivider />

    <!-- SECTION DataTable -->
    <VDataTableServer
      v-model="selectedRows"
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      show-select
      :items-length="totalInvoices"
      :headers="headers"
      :items="invoices"
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <!-- id -->
      <template #item.id="{ item }">
        <div class="text-body-1">
          <RouterLink :to="{ name: 'apps-invoice-preview-id', params: { id: item.id } }">
            #{{ item.id }}
          </RouterLink>
        </div>
      </template>

      <!-- status -->
      <template #item.status="{ item }">
        <VTooltip>
          <template #activator="{ props }">
            <VAvatar
              :size="28"
              v-bind="props"
              :color="resolveInvoiceStatusVariantAndIcon(item.invoiceStatus).variant"
              variant="tonal"
            >
              <VIcon
                size="16"
                :icon="resolveInvoiceStatusVariantAndIcon(item.invoiceStatus).icon"
              />
            </VAvatar>
          </template>
          <p class="mb-0">
            {{ item.invoiceStatus }}
          </p>
          <p class="mb-0">
            Balance: {{ item.balance }}
          </p>
          <p class="mb-0">
            Due date: {{ item.dueDate }}
          </p>
        </VTooltip>
      </template>

      <!-- client -->
      <template #item.client="{ item }">
        <div class="d-flex align-center">
          <VAvatar
            size="34"
            :color="!item.avatar.length ? resolveInvoiceStatusVariantAndIcon(item.invoiceStatus).variant : undefined"
            :variant="!item.avatar.length ? 'tonal' : undefined"
            class="me-3"
          >
            <VImg
              v-if="item.avatar.length"
              :src="item.avatar"
            />
            <span v-else>{{ avatarText(item.client.name) }}</span>
          </VAvatar>
          <div class="d-flex flex-column">
            <RouterLink
              class="font-weight-medium mb-0 text-link"
              :to="{ name: 'pages-user-profile-tab', params: { tab: 'profile' } }"
              style="line-height: 1.375rem;"
            >
              {{ item.client.name }}
            </RouterLink>
            <span class="text-body-2">{{ item.client.companyEmail }}</span>
          </div>
        </div>
      </template>

      <!-- Total -->
      <template #item.total="{ item }">
        ${{ item.total }}
      </template>

      <!-- Issued Date -->
      <template #item.date="{ item }">
        {{ item.issuedDate }}
      </template>

      <!-- Balance -->
      <template #item.balance="{ item }">
        <VChip
          v-if="typeof ((resolveInvoiceBalanceVariant(item.balance, item.total)).status) === 'string'"
          :color="resolveInvoiceBalanceVariant(item.balance, item.total).chip.color"
          size="small"
          label
        >
          {{ (resolveInvoiceBalanceVariant(item.balance, item.total)).status }}
        </VChip>
        <div
          v-else
          class="text-body-1 text-high-emphasis"
        >
          {{ Number((resolveInvoiceBalanceVariant(item.balance, item.total)).status) > 0 ? `$${(resolveInvoiceBalanceVariant(item.balance, item.total)).status}` : `-$${Math.abs(Number((resolveInvoiceBalanceVariant(item.balance, item.total)).status))}` }}
        </div>
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <IconBtn @click="deleteInvoice(item.id)">
          <VIcon icon="bx-trash" />
        </IconBtn>

        <IconBtn :to="{ name: 'apps-invoice-preview-id', params: { id: item.id } }">
          <VIcon icon="bx-show" />
        </IconBtn>
        <IconBtn>
          <VIcon icon="bx-dots-vertical-rounded" />
        </IconBtn>
      </template>

      <template #bottom>
        <TablePagination
          v-model:page="page"
          :items-per-page="itemsPerPage"
          :total-items="totalInvoices"
        />
      </template>
    </VDataTableServer>
    <!-- !SECTION -->
  </VCard>
</template>

<style lang="scss">
#invoice-list {
  .invoice-list-actions {
    inline-size: 8rem;
  }

  .invoice-list-search {
    inline-size: 15.625rem;
  }
}
</style>

<style lang="scss" scoped>
.client-title {
  &:not(:hover) {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  }
}
</style>
