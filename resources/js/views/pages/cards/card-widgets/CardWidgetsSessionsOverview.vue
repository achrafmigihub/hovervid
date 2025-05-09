<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'

const vuetifyTheme = useTheme()
const series = [78]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const disabledTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['disabled-opacity'] })`
  const primaryTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['high-emphasis-opacity'] })`
  
  return {
    chart: {
      sparkline: { enabled: true },
      offsetX: -10,
    },
    labels: ['Loss Rate'],
    stroke: { lineCap: 'round' },
    colors: [`rgba(${ hexToRgb(String(currentTheme.warning)) }, 1)`],
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    grid: {
      padding: {
        top: -15,
        bottom: -10,
      },
    },
    plotOptions: {
      radialBar: {
        endAngle: 140,
        startAngle: -140,
        hollow: { size: '66%' },
        track: {
          strokeWidth: '60%',
          background: `rgba(${ hexToRgb(String(currentTheme['on-surface'])) }, 0.08)`,
        },
        dataLabels: {
          name: {
            offsetY: 60,
            fontWeight: 500,
            fontSize: '13px',
            color: disabledTextColor,
            fontFamily: 'Public Sans',
          },
          value: {
            offsetY: -5,
            fontWeight: 500,
            fontSize: '24px',
            color: primaryTextColor,
            fontFamily: 'Public Sans',
          },
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

const sessionData = [
  {
    title: 'Today',
    stat: '+ $340',
  },
  {
    title: 'Last Week',
    stat: '+ $680',
  },
  {
    title: 'Last Month',
    stat: '+ $3,540',
  },
]
</script>

<template>
  <VCard title="Sessions Overview">
    <template #append>
      <MoreBtn :menu-list="moreList" />
    </template>

    <VCardText>
      <VRow>
        <VCol
          sm="5"
          cols="12"
        >
          <div class="mb-6">
            <h3 class="text-h3">
              32,754
            </h3>
            <span class="text-success text-base d-inline-block">+0.7645%</span>
          </div>

          <VueApexCharts
            type="radialBar"
            :height="180"
            :options="chartOptions"
            :series="series"
          />
        </VCol>

        <VCol
          cols="12"
          sm="7"
          class="d-flex flex-column justify-space-between"
        >
          <div class="d-flex justify-space-around text-center mb-5">
            <div
              v-for="data in sessionData"
              :key="data.title"
            >
              <p class="text-sm text-disabled mb-0">
                {{ data.title }}
              </p>
              <p class="font-weight-medium mb-0">
                {{ data.stat }}
              </p>
            </div>
          </div>

          <div class="mb-sm-7">
            <div>Effective Return</div>
            <div class="d-flex align-center gap-4 mb-6">
              <div class="flex-grow-1">
                <VProgressLinear
                  color="primary"
                  height="8"
                  rounded
                  rounded-bar
                  model-value="74"
                />
              </div>
              <span class="text-sm font-weight-medium text-medium-emphasis">74%</span>
            </div>

            <div>Invalid Session</div>
            <div class="d-flex align-center gap-4">
              <div class="flex-grow-1">
                <VProgressLinear
                  color="primary"
                  height="8"
                  rounded
                  rounded-bar
                  model-value="40"
                />
              </div>
              <span class="text-sm font-weight-medium text-medium-emphasis">40%</span>
            </div>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
