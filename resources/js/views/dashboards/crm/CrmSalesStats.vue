<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@core/utils/colorConverter'
import arrowStar from '@images/cards/arrow-star.png'

const vuetifyTheme = useTheme()
const series = [75]

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const secondaryTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['medium-emphasis-opacity'] })`
  const primaryTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['high-emphasis-opacity'] })`
  
  return {
    chart: { sparkline: { enabled: true } },
    labels: ['Sales'],
    stroke: { lineCap: 'round' },
    colors: [`rgba(${ hexToRgb(String(currentTheme.success)) }, 1)`],
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    plotOptions: {
      radialBar: {
        hollow: {
          size: '73%',
          imageWidth: 72,
          imageHeight: 53,
          imageOffsetY: -40,
          imageClipped: false,
          image: arrowStar,
        },
        track: {
          strokeWidth: '45px',
          background: `rgba(${ hexToRgb(String(currentTheme['on-surface'])) }, 0.08)`,
        },
        dataLabels: {
          name: {
            offsetY: 50,
            color: secondaryTextColor,
            fontSize: '15px',
            fontWeight: 400,
          },
          value: {
            offsetY: 10,
            fontWeight: 500,
            fontSize: '28px',
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
</script>

<template>
  <VCard title="Sales Stats">
    <template #append>
      <MoreBtn :menu-list="moreList" />
    </template>
    <VCardText>
      <VueApexCharts
        type="radialBar"
        class="pt-2"
        :height="270"
        :min-height="258"
        :options="chartOptions"
        :series="series"
      />

      <div class="d-flex justify-center gap-x-6 gap-y-2 flex-wrap">
        <div>
          <VIcon
            size="10"
            icon="bx-bxs-circle"
            color="success"
            class="me-2"
          />
          <span class="text-caption d-inline-block text-medium-emphasis">Conversion Ratio</span>
        </div>

        <div>
          <VIcon
            size="10"
            icon="bx-bxs-circle"
            color="disabled"
            class="me-2"
          />
          <span class="text-caption d-inline-block text-medium-emphasis">Total requirements</span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
