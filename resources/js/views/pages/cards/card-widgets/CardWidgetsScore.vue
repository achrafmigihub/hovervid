<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'

const vuetifyTheme = useTheme()
const series = [78]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const HighEmphasisTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['high-emphasis-opacity'] })`
  const MediumEmphasisTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['medium-emphasis-opacity'] })`
  
  return {
    chart: { sparkline: { enabled: true } },
    labels: ['Out of 100'],
    stroke: { dashArray: 5 },
    colors: [`rgba(${ hexToRgb(String(currentTheme.primary)) }, 1)`],
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    grid: {
      padding: {
        top: -15,
        bottom: -13,
      },
    },
    fill: {
      type: 'gradient',
      gradient: {
        shade: 'dark',
        opacityTo: 0.6,
        opacityFrom: 1,
        shadeIntensity: 0.5,
        stops: [
          30,
          70,
          100,
        ],
        inverseColors: false,
        gradientToColors: [currentTheme.primary],
      },
    },
    plotOptions: {
      radialBar: {
        endAngle: 150,
        startAngle: -140,
        hollow: { size: '55%' },
        track: { background: 'transparent' },
        dataLabels: {
          name: {
            offsetY: 15,
            fontSize: '14px',
            color: MediumEmphasisTextColor,
            fontWeight: 400,
            fontFamily: 'Public Sans',
          },
          value: {
            offsetY: -25,
            fontWeight: 500,
            fontSize: '24px',
            formatter: value => `${ value }`,
            color: HighEmphasisTextColor,
            fontFamily: 'Public Sans',
          },
        },
      },
    },
  }
})
</script>

<template>
  <VCard>
    <VCardText class="text-center">
      <p class="text-base mb-0">
        Your score is
      </p>
      <h5 class="text-h5">
        Awesome
      </h5>

      <VueApexCharts
        type="radialBar"
        :height="200"
        :options="chartOptions"
        :series="series"
      />
      <p class="text-base mb-0">
        Your score is based on the last
      </p>
      <h6 class="text-h6 mb-4">
        287 Transactions
      </h6>

      <VBtn>View My Account</VBtn>
    </VCardText>
  </VCard>
</template>
