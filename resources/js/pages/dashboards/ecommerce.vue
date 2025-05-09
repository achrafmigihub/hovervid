<script setup>
import ECommerceAward from '@/views/dashboards/ecommerce/EcommerceAward.vue'
import EcommerceConversionRate from '@/views/dashboards/ecommerce/EcommerceConversionRate.vue'
import EcommerceExpenses from '@/views/dashboards/ecommerce/EcommerceExpenses.vue'
import EcommerceExpensesBar from '@/views/dashboards/ecommerce/EcommerceExpensesBar.vue'
import EcommerceNewVisitors from '@/views/dashboards/ecommerce/EcommerceNewVisitors.vue'
import EcommercePerformance from '@/views/dashboards/ecommerce/EcommercePerformance.vue'
import EcommerceProfit from '@/views/dashboards/ecommerce/EcommerceProfit.vue'
import EcommerceSales from '@/views/dashboards/ecommerce/EcommerceSales.vue'
import EcommerceTable from '@/views/dashboards/ecommerce/EcommerceTable.vue'
import EcommerceTotalBalance from '@/views/dashboards/ecommerce/EcommerceTotalBalance.vue'
import EcommerceTotalIncome from '@/views/dashboards/ecommerce/EcommerceTotalIncome.vue'
import card from '@images/cards/credit-card-primary.png'
import desktop from '@images/cards/desktop-warning.png'
import wallet from '@images/cards/wallet-info.png'
</script>

<template>
  <VRow class="match-height">
    <!-- 👉 Award card -->
    <VCol
      cols="12"

      md="4"
    >
      <ECommerceAward />
    </VCol>

    <!-- 👉 New Visitors card -->
    <VCol
      cols="12"
      md="8"
    >
      <EcommerceNewVisitors />
    </VCol>

    <VCol
      cols="12"
      lg="4"
    >
      <VRow>
        <!-- 👉 Statistics Vertical  -->
        <VCol
          cols="12"
          sm="6"
          md="3"
          lg="6"
        >
          <CardStatisticsVertical
            title="Sales"
            :image="wallet"
            color="primary"
            stats="$4,679"
            :change="28.14"
          />
        </VCol>

        <!-- 👉 Profit -->
        <VCol
          cols="12"
          sm="6"
          md="3"
          lg="6"
        >
          <EcommerceProfit />
        </VCol>

        <!-- 👉 Expenses -->
        <VCol
          cols="12"
          sm="6"
          md="3"
          lg="6"
        >
          <EcommerceExpenses />
        </VCol>

        <!-- 👉 Transactions -->
        <VCol
          cols="12"
          sm="6"
          md="3"
          lg="6"
        >
          <CardStatisticsVertical
            title="Transactions"
            color="warning"
            :image="card"
            stats="$14,854"
            :change="62"
          />
        </VCol>
      </VRow>
    </VCol>

    <!-- 👉 Total Income -->
    <VCol
      cols="12"
      lg="8"
    >
      <EcommerceTotalIncome />
    </VCol>

    <!-- 👉 Performance -->
    <VCol
      cols="12"
      sm="6"
      md="4"
    >
      <EcommercePerformance />
    </VCol>

    <!-- 👉 Conversion Rate -->
    <VCol
      cols="12"
      md="4"
      sm="6"
    >
      <EcommerceConversionRate />
    </VCol>

    <VCol
      cols="12"
      md="4"
    >
      <VRow>
        <!-- 👉 Revenue card -->
        <VCol
          cols="12"
          sm="6"
        >
          <CardStatisticsVertical
            v-bind="{
              title: 'Revenue',
              color: 'info',
              image: desktop,
              stats: '$42,389',
              change: 52.18,
            }"
          />
        </VCol>

        <!-- 👉 Sales card -->
        <VCol
          cols="12"
          sm="6"
        >
          <EcommerceSales />
        </VCol>

        <!-- 👉 Expenses Bar -->
        <VCol cols="12">
          <EcommerceExpensesBar />
        </VCol>
      </VRow>
    </VCol>

    <!-- 👉 Table -->
    <VCol
      cols="12"
      md="8"
    >
      <EcommerceTable />
    </VCol>

    <!-- 👉 Total Balance -->
    <VCol
      cols="12"
      md="4"
    >
      <EcommerceTotalBalance />
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/libs/apex-chart.scss";
</style>
