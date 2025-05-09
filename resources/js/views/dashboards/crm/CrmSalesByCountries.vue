<script setup>
import asFlag from '@images/icons/countries/au.png'
import brFlag from '@images/icons/countries/br.png'
import cnFlag from '@images/icons/countries/cn.png'
import bgFlag from '@images/icons/countries/fr.png'
import inFlag from '@images/icons/countries/in.png'
import usFlag from '@images/icons/countries/us.png'

const salesByCountries = [
  {
    flag: usFlag,
    amount: '$8,656k',
    country: 'United states of america',
    change: 25.8,
    sales: '894k',
  },
  {
    flag: brFlag,
    amount: '$2,415k',
    country: 'Brazil',
    change: -6.2,
    sales: '645k',
  },
  {
    flag: inFlag,
    amount: '$865k',
    country: 'India',
    change: 12.4,
    sales: '148k',
  },
  {
    flag: asFlag,
    amount: '$745k',
    country: 'Australia',
    change: -11.9,
    sales: '86k',
  },
  {
    flag: bgFlag,
    amount: '$45k',
    country: 'Belgium',
    change: 16.2,
    sales: '42k',
  },
  {
    flag: cnFlag,
    amount: '$12k',
    country: 'China',
    change: 14.8,
    sales: '8k',
  },
]

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
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>
        Sales by Countries
      </VCardTitle>
      <VCardSubtitle>Monthly Sales Overview</VCardSubtitle>

      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>

    <VCardText>
      <VList class="card-list">
        <VListItem
          v-for="data in salesByCountries"
          :key="data.country"
        >
          <template #prepend>
            <VAvatar
              variant="tonal"
              :image="data.flag"
            />
          </template>

          <VListItemTitle class="d-flex align-center">
            <span class="text-h6">{{ data.amount }}</span>
            <div
              class="d-flex align-center"
              :class="data.change > 0 ? 'text-success' : 'text-error'"
            >
              <VIcon
                size="22"
                :icon="data.change > 0 ? 'bx-chevron-up' : 'bx-chevron-down'"
                class="ms-2"
              />
              <span class="text-sm font-weight-medium">
                {{ data.change }}%
              </span>
            </div>
          </VListItemTitle>

          <VListItemSubtitle>
            {{ data.country }}
          </VListItemSubtitle>

          <template #append>
            <h4 class="font-weight-regular">
              {{ data.sales }}
            </h4>
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

  <style lang="scss" scoped>
  .card-list {
    --v-card-list-gap: 1rem;
  }
  </style>
