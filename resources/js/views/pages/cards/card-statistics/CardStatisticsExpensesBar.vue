<script setup>
import {
  useDisplay,
  useTheme,
} from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'

const vuetifyTheme = useTheme()
const display = useDisplay()

const series = [
  {
    name: `${ new Date().getFullYear() - 1 }`,
    data: [
      12,
      32,
      12,
      27,
      39,
      27,
      17,
      9,
      12,
      20,
    ],
  },
  {
    name: `${ new Date().getFullYear() - 2 }`,
    data: [
      -28,
      -20,
      -27,
      -15,
      -21,
      -17,
      -19,
      -12,
      -30,
      -18,
    ],
  },
]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  
  return {
    chart: {
      stacked: true,
      parentHeightOffset: 0,
      toolbar: { show: false },
    },
    legend: { show: false },
    tooltip: { enabled: false },
    dataLabels: { enabled: false },
    stroke: {
      width: 2,
      lineCap: 'round',
      colors: [currentTheme.surface],
    },
    colors: [
      `rgba(${ hexToRgb(String(currentTheme.primary)) }, 1)`,
      `rgba(${ hexToRgb(String(currentTheme.warning)) }, 1)`,
    ],
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        columnWidth: '40%',
        borderRadiusApplication: 'around',
        borderRadiusWhenStacked: 'all',
      },
    },
    grid: {
      show: false,
      padding: {
        top: -10,
        right: 2,
        bottom: 0,
      },
    },
    xaxis: {
      labels: { show: false },
      axisTicks: { show: false },
      crosshairs: { opacity: 0 },
      axisBorder: { show: false },
    },
    yaxis: { show: false },
    responsive: [
      {
        breakpoint: 1400,
        options: { plotOptions: { bar: { columnWidth: '60%' } } },
      },
      {
        breakpoint: 1250,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '65%',
              borderRadius: 6,
            },
          },
        },
      },
      {
        breakpoint: display.thresholds.value.lg,
        options: {
          chart: { height: 146 },
          plotOptions: {
            bar: {
              columnWidth: '40%',
              borderRadius: 4,
            },
          },
        },
      },
      {
        breakpoint: display.thresholds.value.md,
        options: { chart: { height: 191 } },
      },
      {
        breakpoint: 420,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '55%',
              borderRadius: 4,
            },
          },
        },
      },
    ],
  }
})
</script>

<template>
  <VCard>
    <VCardText class="d-flex justify-space-between">
      <div class="d-flex flex-column gap-3 justify-space-between">
        <h5 class="text-h5">
          Expenses
        </h5>

        <div class="">
          <h4 class="text-h4">
            4,234
          </h4>
          <div class="text-error text-base">
            <VIcon
              size="20"
              icon="bx-down-arrow-alt"
              class="me-1"
            />
            <span>8.2%</span>
          </div>
        </div>

        <div>
          <VChip
            label
            size="small"
            color="secondary"
          >
            2023 Year
          </VChip>
        </div>
      </div>

      <div>
        <VueApexCharts
          type="bar"
          :height="170"
          :options="chartOptions"
          :series="series"
        />
      </div>
    </VCardText>
  </VCard>
</template>
