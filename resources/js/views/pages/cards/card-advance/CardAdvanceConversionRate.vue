<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'

const vuetifyTheme = useTheme()

const series = [{
  data: [
    30,
    58,
    45,
    68,
  ],
}]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  
  return {
    chart: {
      parentHeightOffset: 0,
      toolbar: { show: false },
      dropShadow: {
        top: 8,
        blur: 3,
        left: 3,
        enabled: true,
        opacity: 0.14,
        color: currentTheme.primary,
      },
    },
    grid: {
      show: false,
      padding: {
        top: -21,
        left: -5,
        bottom: -8,
      },
    },
    tooltip: { enabled: false },
    colors: [`rgba(${ hexToRgb(String(currentTheme.primary)) },1)`],
    markers: {
      size: 6,
      offsetX: -2,
      offsetY: -1,
      strokeWidth: 5,
      strokeOpacity: 1,
      colors: ['transparent'],
      strokeColors: 'transparent',
      discrete: [{
        size: 7,
        seriesIndex: 0,
        strokeColor: currentTheme.primary,
        fillColor: currentTheme.surface,
        dataPointIndex: series[0].data.length - 1,
      }],
    },
    stroke: {
      width: 5,
      curve: 'smooth',
      lineCap: 'round',
    },
    xaxis: {
      labels: { show: false },
      axisTicks: { show: false },
      axisBorder: { show: false },
    },
    yaxis: { labels: { show: false } },
  }
})

const ConversionRates = [
  {
    changePercent: 12.8,
    title: 'Impressions',
    subtitle: '12.4k Visits',
  },
  {
    changePercent: -8.3,
    title: 'Added To Cart',
    subtitle: '32 Product in cart',
  },
  {
    title: 'Checkout',
    changePercent: 9.12,
    subtitle: '21 Product checkout',
  },
  {
    title: 'Purchased',
    changePercent: 2.24,
    subtitle: '12 Orders',
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
      <VCardTitle class="mb-1">
        Conversion Rate
      </VCardTitle>
      <VCardSubtitle>Compared To Last Month</VCardSubtitle>

      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>

    <VCardText class="pt-4">
      <div class="d-flex align-center justify-space-between gap-2 flex-wrap mb-6">
        <div class="d-flex align-center gap-2">
          <h3 class="text-h3">
            8.72%
          </h3>
          <div class="text-success">
            <VIcon
              icon="bx-chevron-up"
              size="24"
            />
            <span class="text-sm d-inline-block">4.8%</span>
          </div>
        </div>

        <div>
          <VueApexCharts
            type="line"
            :height="60"
            :width="100"
            :options="chartOptions"
            :series="series"
          />
        </div>
      </div>

      <VList class="card-list card-conversion-rate">
        <VListItem
          v-for="rate in ConversionRates"
          :key="rate.title"
        >
          <VListItemTitle>
            {{ rate.title }}
          </VListItemTitle>
          <VListItemSubtitle class="text-body-2">
            {{ rate.subtitle }}
          </VListItemSubtitle>

          <template #append>
            <VIcon
              size="24"
              :icon="rate.changePercent > 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt'"
              :color="rate.changePercent > 0 ? 'success' : 'error'"
              class="me-2"
            />
            <span class="text-md text-medium-emphasis">{{ rate.changePercent }}%</span>
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss">
.card-conversion-rate.v-list {
  .v-list-item .v-list-item__append {
    align-self: start;
  }
}
</style>
