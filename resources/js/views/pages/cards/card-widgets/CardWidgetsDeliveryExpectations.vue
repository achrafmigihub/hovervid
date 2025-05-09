<script setup>
const chartColors = {
  donut: {
    series1: '#5AB12C',
    series2: '#66C732',
    series3: '#8DE45F',
    series4: '#C6F1AF',
  },
}

const headingColor = 'rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity))'
const labelColor = 'rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity))'

const deliveryExceptionsChartSeries = [
  13,
  25,
  22,
  40,
]

const deliveryExceptionsChartConfig = {
  labels: [
    'Incorrect address',
    'Weather conditions',
    'Federal Holidays',
    'Damage during transit',
  ],
  colors: [
    chartColors.donut.series1,
    chartColors.donut.series2,
    chartColors.donut.series3,
    chartColors.donut.series4,
  ],
  stroke: { width: 0 },
  dataLabels: {
    enabled: false,
    formatter(val) {
      return `${ Number.parseInt(val) }%`
    },
  },
  legend: {
    show: true,
    position: 'bottom',
    offsetY: 10,
    markers: {
      width: 8,
      height: 8,
      offsetX: -1,
      offsetY: 1,
    },
    itemMargin: {
      horizontal: 15,
      vertical: 5,
    },
    fontSize: '13px',
    fontWeight: 400,
    labels: {
      colors: headingColor,
      useSeriesColors: false,
    },
  },
  tooltip: { theme: false },
  grid: { padding: { top: 15 } },
  plotOptions: {
    pie: {
      donut: {
        size: '75%',
        labels: {
          show: true,
          value: {
            fontSize: '24px',
            color: headingColor,
            fontWeight: 500,
            offsetY: -20,
            formatter(val) {
              return `${ Number.parseInt(val) }%`
            },
          },
          name: { offsetY: 20 },
          total: {
            show: true,
            fontSize: '0.9375rem',
            fontWeight: 400,
            label: 'AVG. Exceptions',
            color: labelColor,
            formatter() {
              return '30%'
            },
          },
        },
      },
    },
  },
  responsive: [{
    breakpoint: 420,
    options: { chart: { height: 400 } },
  }],
}

const moreList = [
  {
    title: 'Refresh',
    value: 'refresh',
  },
  {
    title: 'Update',
    value: 'update',
  },
  {
    title: 'Share',
    value: 'share',
  },
]
</script>

<template>
  <VCard>
    <VCardItem title="Delivery exceptions">
      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>
    <VCardText>
      <VueApexCharts
        type="donut"
        height="400"
        :options="deliveryExceptionsChartConfig"
        :series="deliveryExceptionsChartSeries"
      />
    </VCardText>
  </VCard>
</template>
