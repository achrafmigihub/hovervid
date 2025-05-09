<script setup>
const moreList = [
  {
    title: 'Share',
    value: 'Share',
  },
  {
    title: 'Refresh',
    value: 'Refresh',
  },
  {
    title: 'Update',
    value: 'Update',
  },
]

const businesses = ref([
  {
    name: 'Branding',
    amount: '+$30',
    color: 'success',
    selected: false,
  },
  {
    name: 'Marketing',
    amount: '+$75',
    color: 'primary',
    selected: true,
  },
  {
    name: 'App Building',
    amount: '+$125',
    color: 'success',
    selected: false,
  },
  {
    name: 'SEO',
    amount: '+$48',
    color: 'primary',
    selected: false,
  },
])

const addRemoveSelected = value => {
  businesses.value = businesses.value.map(item => {
    if (item.name === value)
      item.selected = !item.selected
    
    return item
  })
}
</script>

<template>
  <VCard title="For Business Sharks">
    <template #append>
      <MoreBtn :menu-list="moreList" />
    </template>

    <VCardText>
      <p>Here, I focus on a range of items and features that we use in life without them.</p>
      <h6 class="text-base font-weight-regular mb-1">
        Basic price is $30
      </h6>

      <VList density="default">
        <VListItem
          v-for="(item, index) in businesses"
          :key="item.name"
          :class="`text-medium-emphasis border rounded cursor-pointer ${index > 0 ? 'mt-3' : ''}`"
          :style="item.selected ? 'border-color: rgb(var(--v-theme-primary)) !important' : ''"
          @click="addRemoveSelected(item.name)"
        >
          <template #prepend>
            <VCheckbox
              v-model="item.selected"
              class="me-1"
              :label="item.name"
            />
          </template>

          <template #append>
            <VChip
              label
              size="small"
              :color="item.color"
            >
              {{ item.amount }}
            </VChip>
          </template>
        </VListItem>
      </VList>

      <div class="mt-1 mb-4">
        <div class="d-flex justify-space-between font-weight-medium mb-1">
          <span>Vat Taxes</span>
          <span>$24</span>
        </div>
        <div class="d-flex justify-space-between align-center font-weight-medium">
          <span>Total Amount</span>
          <span class="text-primary text-h5">$99</span>
        </div>
      </div>

      <VBtn block>
        Purchase
      </VBtn>
    </VCardText>
  </VCard>
</template>
