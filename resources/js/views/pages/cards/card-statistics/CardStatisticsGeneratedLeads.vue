<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'

const vuetifyTheme = useTheme()

const series = [
  23,
  27,
  22,
  28,
]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const secondaryTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['medium-emphasis-opacity'] })`
  const primaryTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['high-emphasis-opacity'] })`
  
  return {
    stroke: { width: 0 },
    colors: [
      '#66C732',
      '#E3F8D7',
      '#AAEB87',
      '#8DE45F',
    ],
    legend: { show: false },
    tooltip: { enabled: false },
    dataLabels: { enabled: false },
    labels: [
      '1st Week',
      '2nd Week',
      '3rd Week',
      '4th Week',
    ],
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    grid: { padding: { top: 10 } },
    plotOptions: {
      pie: {
        customScale: 0.8,
        expandOnClick: false,
        donut: {
          size: '73%',
          labels: {
            show: true,
            name: {
              offsetY: 22,
              color: secondaryTextColor,
              fontFamily: 'Public Sans',
            },
            value: {
              offsetY: -17,
              fontWeight: 500,
              fontSize: '24px',
              formatter: val => `${ val }%`,
              color: primaryTextColor,
              fontFamily: 'Public Sans',
            },
            total: {
              show: true,
              label: 'Average',
              fontSize: '15px',
              color: secondaryTextColor,
              fontFamily: 'Public Sans',
              formatter: () => `${ series.reduce((a, b) => a + b, 0) / series.length }%`,
            },
          },
        },
      },
    },
  }
})
</script>

<template>
  <VCard>
    <VCardText class="d-flex justify-space-between">
      <div class="d-flex flex-column justify-space-between">
        <div>
          <h5 class="text-h5">
            Generated Leads
          </h5>
          <span class="text-base">Monthly Report</span>
        </div>

        <div>
          <h4 class="text-h4">
            4,234
          </h4>
          <div class="d-flex gap-1 align-center text-success text-sm">
            <VIcon
              size="24"
              icon="bx-chevron-up"
            />
            <span class="text-base d-inline-block">22.3%</span>
          </div>
        </div>
      </div>

      <div>
        <VueApexCharts
          type="donut"
          :height="179"
          :width="150"
          :options="chartOptions"
          :series="series"
        />
      </div>
    </VCardText>
  </VCard>
</template>
