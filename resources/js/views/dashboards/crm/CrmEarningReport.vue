<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'

const vuetifyTheme = useTheme()

const series = [{
  data: [
    32,
    98,
    61,
    41,
    88,
    47,
    71,
  ],
}]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const disabledTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['disabled-opacity'] })`
  
  return {
    chart: {
      parentHeightOffset: 0,
      toolbar: { show: false },
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        distributed: true,
        columnWidth: '52%',
        endingShape: 'rounded',
        startingShape: 'rounded',
      },
    },
    legend: { show: false },
    tooltip: { enabled: false },
    dataLabels: { enabled: false },
    colors: [
      `rgba(${ hexToRgb(String(currentTheme.primary)) }, 0.16)`,
      `rgba(${ hexToRgb(String(currentTheme.primary)) }, 0.16)`,
      `rgba(${ hexToRgb(String(currentTheme.primary)) }, 0.16)`,
      `rgba(${ hexToRgb(String(currentTheme.primary)) }, 0.16)`,
      `rgba(${ hexToRgb(String(currentTheme.primary)) }, 16)`,
      `rgba(${ hexToRgb(String(currentTheme.primary)) }, 0.16)`,
      `rgba(${ hexToRgb(String(currentTheme.primary)) }, 0.16)`,
    ],
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    xaxis: {
      categories: [
        'Mo',
        'Tu',
        'We',
        'Th',
        'Fr',
        'Sa',
        'Su',
      ],
      axisTicks: { show: false },
      axisBorder: { show: false },
      tickPlacement: 'on',
      labels: {
        style: {
          fontSize: '13px',
          colors: disabledTextColor,
          fontFamily: 'Public Sans',
        },
      },
    },
    yaxis: { show: false },
    grid: {
      show: false,
      padding: {
        top: -5,
        left: -14,
        right: -16,
        bottom: -12,
      },
    },
  }
})

const reports = [
  {
    amount: '$1,619',
    percentage: 18.6,
    title: 'Net Profit',
    avatarColor: 'primary',
    subtitle: '12.4k Sales',
    avatarIcon: 'bx-trending-up',
  },
  {
    amount: '$3,571',
    percentage: 39.6,
    title: 'Total Income',
    avatarColor: 'success',
    subtitle: 'Sales, Affiliation',
    avatarIcon: 'bx-dollar',
  },
  {
    amount: '$430',
    percentage: 52.8,
    title: 'Total Expenses',
    avatarColor: 'secondary',
    subtitle: 'ADVT, Marketing',
    avatarIcon: 'bx-credit-card',
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
        Earning Report
      </VCardTitle>
      <VCardSubtitle>Weekly Earnings Overview</VCardSubtitle>

      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>

    <VCardText>
      <VList class="card-list mb-4">
        <VListItem
          v-for="report in reports"
          :key="report.title"
        >
          <template #prepend>
            <VAvatar
              rounded
              variant="tonal"
              size="34"
              :color="report.avatarColor"
            >
              <VIcon
                size="22"
                :icon="report.avatarIcon"
              />
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-medium">
            {{ report.title }}
          </VListItemTitle>
          <VListItemSubtitle class="text-disabled">
            {{ report.subtitle }}
          </VListItemSubtitle>

          <template #append>
            <div class="text-body-1 text-medium-emphasis">
              <span class="d-inline-block">{{ report.amount }}</span>
              <VIcon
                color="success"
                icon="bx-chevron-up"
                size="20"
                class="ms-3 me-1"
              />
              <span class="d-inline-block">{{ report.percentage }}%</span>
            </div>
          </template>
        </VListItem>
      </VList>

      <VueApexCharts
        type="bar"
        :height="160"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
  .card-list {
    --v-card-list-gap: 1rem;
  }
</style>
