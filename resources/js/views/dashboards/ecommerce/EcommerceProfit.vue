<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'

const vuetifyTheme = useTheme()

const series = [
  {
    data: [
      11,
      7,
      11,
      20,
    ],
  },
  {
    data: [
      9,
      5,
      15,
      18,
    ],
  },
]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const disabledText = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['disabled-opacity'] })`
  
  return {
    chart: {
      parentHeightOffset: 0,
      offsetY: 0,
      toolbar: { show: false },
    },
    grid: {
      padding: {
        top: -22,
        left: -1,
        right: 10,
        bottom: -3,
      },
      yaxis: { lines: { show: false } },
    },
    legend: { show: false },
    tooltip: { enabled: false },
    dataLabels: { enabled: false },
    colors: [
      `rgba(${ hexToRgb(String(currentTheme.success)) }, 1)`,
      `rgba(${ hexToRgb(String(currentTheme.success)) }, 0.16)`,
    ],
    plotOptions: {
      bar: {
        borderRadius: 3,
        columnWidth: '52%',
        endingShape: 'rounded',
        startingShape: 'rounded',
      },
    },
    stroke: {
      width: 2,
      colors: [currentTheme.surface],
    },
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    xaxis: {
      axisTicks: { show: false },
      axisBorder: { show: false },
      categories: [
        'Jan',
        'Apr',
        'Jul',
        'Oct',
      ],
      offsetY: -10,
      labels: {
        style: {
          fontSize: '11px',
          lineHeight: '14px',
          colors: disabledText,
          fontFamily: 'Public Sans',
        },
      },
    },
    yaxis: { labels: { show: false } },
  }
})
</script>

<template>
  <VCard>
    <VCardText class="pb-2">
      <div class="text-base">
        Profit
      </div>
      <h4 class="text-h4">
        624k
      </h4>
    </VCardText>

    <VueApexCharts
      type="bar"
      :height="105"
      :options="chartOptions"
      :series="series"
    />
  </VCard>
</template>
