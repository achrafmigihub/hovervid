<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'
import shoppingBag from '@images/cards/credit-card.png'
import paypalPrimary from '@images/cards/paypal.png'
import walletInfo from '@images/cards/wallet.png'

const vuetifyTheme = useTheme()

const series = [{
  name: 'Income',
  data: [
    3350,
    3350,
    4800,
    4800,
    2950,
    2950,
    1800,
    1800,
    3750,
    3750,
    5700,
    5700,
  ],
}]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const disabledTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['disabled-opacity'] })`
  const borderColor = `rgba(${ hexToRgb(String(variableTheme['border-color'])) },${ variableTheme['border-opacity'] })`
  
  return {
    chart: {
      parentHeightOffset: 0,
      toolbar: { show: false },
      offsetY: 10,
      offsetX: -14,
      dropShadow: {
        top: 14,
        left: 0,
        blur: 4,
        opacity: 0.15,
        enabled: true,
        color: currentTheme.primary,
      },
    },
    dataLabels: { enabled: false },
    stroke: {
      width: 4,
      curve: 'straight',
    },
    grid: {
      borderColor,
      padding: {
        top: 5,
        right: 6,
        bottom: 7,
      },
    },
    fill: {
      type: 'gradient',
      gradient: {
        opacityTo: 0.25,
        opacityFrom: 0.5,
        stops: [
          0,
          95,
          100,
        ],
        shadeIntensity: 0.6,
        colorStops: [[
          {
            offset: 0,
            opacity: 0.2,
            color: currentTheme.primary,
          },
          {
            opacity: 0,
            offset: 100,
            color: currentTheme.surface,
          },
        ]],
      },
    },
    theme: {
      monochrome: {
        enabled: true,
        shadeTo: 'light',
        shadeIntensity: 1,
        color: currentTheme.primary,
      },
    },
    xaxis: {
      axisTicks: { show: false },
      axisBorder: { show: false },
      categories: [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
      ],
      labels: {
        style: {
          fontSize: '13px',
          colors: disabledTextColor,
          fontFamily: 'Public Sans',
        },
      },
    },
    yaxis: {
      labels: {
        formatter: value => `${ value / 1000 }k`,
        style: {
          fontSize: '13px',
          colors: disabledTextColor,
          fontFamily: 'Public Sans',
        },
      },
    },
  }
})

const reportData = [
  {
    title: 'Income',
    avatarWidth: 20,
    stats: '$42,845',
    avatarHeight: 24,
    trendNumber: +2.34,
    avatarSrc: paypalPrimary,
  },
  {
    avatarWidth: 24,
    title: 'Expense',
    stats: '$38,658',
    avatarHeight: 17,
    trendNumber: -1.15,
    avatarSrc: shoppingBag,
  },
  {
    title: 'Profit',
    avatarWidth: 24,
    stats: '$18,220',
    avatarHeight: 21,
    trendNumber: +1.34,
    avatarSrc: walletInfo,
  },
]

const moreList = [
  {
    title: 'Last Week',
    value: 'Last Week',
  },
  {
    title: 'Last Month',
    value: 'Last Month',
  },
  {
    title: 'Last Year',
    value: 'Last Year',
  },
]
</script>

<template>
  <VCard>
    <VRow no-gutters>
      <VCol
        cols="12"
        md="8"
        :class="$vuetify.display.mdAndUp ? 'border-e' : 'border-b'"
      >
        <VCardItem>
          <VCardTitle>Total Income</VCardTitle>
          <VCardSubtitle class="text-base">
            Yearly report overview
          </VCardSubtitle>

          <template #append>
            <MoreBtn :menu-list="moreList" />
          </template>
        </VCardItem>

        <VCardText class="pe-0">
          <VueApexCharts
            type="area"
            :height="291"
            :options="chartOptions"
            :series="series"
          />
        </VCardText>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCardItem>
          <VCardTitle>Report</VCardTitle>
          <VCardSubtitle class="text-base">
            Monthly Avg. $45.578k
          </VCardSubtitle>

          <template #append>
            <MoreBtn :menu-list="moreList" />
          </template>
        </VCardItem>

        <VCardText class="pt-6">
          <div
            v-for="(report, index) in reportData"
            :key="report.title"
            class="bg-var-theme-background d-flex align-center gap-4 px-4 py-3 rounded"
            :class="index !== 0 ? 'mt-4' : '' "
          >
            <VAvatar
              rounded
              size="40"
              color="rgb(var(--v-theme-surface))"
              variant="elevated"
            >
              <img
                :src="report.avatarSrc"
                :height="report.avatarHeight"
                :width="report.avatarWidth"
              >
            </VAvatar>

            <div class="d-flex flex-wrap align-center justify-space-between gap-2 flex-grow-1">
              <div>
                <span class="text-base d-inline-block text-disabled">{{ report.title }}</span>
                <h5 class="text-h5">
                  {{ report.stats }}
                </h5>
              </div>

              <span
                class="text-sm"
                :class=" report.trendNumber > 0 ? 'text-success' : 'text-error'"
              >{{ report.trendNumber > 0 ? `+${report.trendNumber}` : report.trendNumber }}k</span>
            </div>
          </div>
        </VCardText>
      </VCol>
    </VRow>
  </VCard>
</template>
