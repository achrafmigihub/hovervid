<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'

const vuetifyTheme = useTheme()
const series = [78]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  return {
    chart: { sparkline: { enabled: true } },
    stroke: { lineCap: 'round' },
    colors: [`rgba(${ hexToRgb(String(currentTheme.primary)) }, 1)`],
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    plotOptions: {
      radialBar: {
        endAngle: 90,
        startAngle: -88,
        hollow: { size: '64%' },
        track: { background: `rgba(${ hexToRgb(String(currentTheme['on-surface'])) }, 0.08)` },
        dataLabels: {
          name: { show: false },
          value: {
            offsetY: -4,
            fontWeight: 500,
            fontSize: '18px',
            color: `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['high-emphasis-opacity'] })`,
          },
        },
      },
    },
  }
})
</script>

<template>
  <VCard>
    <VCardText class="pb-2">
      <div class="text-base">
        Expenses
      </div>
    </VCardText>

    <VueApexCharts
      type="radialBar"
      :height="140"
      :options="chartOptions"
      :series="series"
    />

    <p class="text-center text-sm mt-4 mx-4 mb-3">
      $21k Expenses more than last month
    </p>
  </VCard>
</template>
