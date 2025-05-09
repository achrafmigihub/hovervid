<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'

const vuetifyTheme = useTheme()

const series = [
  {
    name: 'Income',
    data: [
      26,
      29,
      31,
      40,
      29,
      24,
    ],
  },
  {
    name: 'Earning',
    data: [
      30,
      26,
      24,
      26,
      24,
      38,
    ],
  },
]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const disabledTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['disabled-opacity'] })`
  const secondaryTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['medium-emphasis-opacity'] })`
  const borderColor = `rgba(${ hexToRgb(String(variableTheme['border-color'])) },${ variableTheme['border-opacity'] })`
  
  return {
    chart: {
      parentHeightOffset: 0,
      toolbar: { show: false },
      dropShadow: {
        top: 0,
        blur: 6,
        left: 0,
        opacity: 0.14,
        enabled: true,
        color: '#000',
      },
    },
    markers: { size: 0 },
    stroke: { show: false },
    fill: {
      opacity: [
        1,
        0.85,
      ],
    },
    colors: [
      currentTheme.primary,
      currentTheme.info,
    ],
    legend: {
      show: true,
      fontSize: '13px',
      markers: {
        width: 10,
        height: 10,
        offsetX: -2,
      },
      itemMargin: { horizontal: 15 },
      fontFamily: 'Public Sans',
      labels: { colors: secondaryTextColor },
    },
    plotOptions: {
      radar: {
        polygons: {
          strokeColors: borderColor,
          connectorColors: borderColor,
        },
      },
    },
    yaxis: { show: false },
    grid: {
      show: false,
      padding: { bottom: -10 },
    },
    xaxis: {
      categories: [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
      ],
      labels: {
        style: {
          fontSize: '13px',
          fontFamily: 'Public Sans',
          colors: [
            disabledTextColor,
            disabledTextColor,
            disabledTextColor,
            disabledTextColor,
            disabledTextColor,
            disabledTextColor,
          ],
        },
      },
    },
  }
})

const moreList = [
  {
    title: 'Share',
    value: 'share',
  },
  {
    title: 'Refresh',
    value: 'refresh',
  },
  {
    title: 'Delete',
    value: 'delete',
  },
]
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="mb-1">
        Performance
      </VCardTitle>

      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>

    <VCardText>
      <div class="d-flex justify-space-between align-center mt-2 mb-4">
        <span>Earning: $846.17</span>
        <span>Sales: 25.7M</span>
      </div>
      <VueApexCharts
        type="radar"
        :height="300"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>
