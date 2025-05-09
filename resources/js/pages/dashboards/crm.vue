<script setup>
import CrmCustomerRatings from '@/views/dashboards/crm/CrmCustomerRatings.vue'
import CrmCustomerTable from '@/views/dashboards/crm/CrmCustomerTable.vue'
import CrmEarningReport from '@/views/dashboards/crm/CrmEarningReport.vue'
import CrmGeneratedLeads from '@/views/dashboards/crm/CrmGeneratedLeads.vue'
import CrmSalesActivity from '@/views/dashboards/crm/CrmSalesActivity.vue'
import CrmSalesAnalytics from '@/views/dashboards/crm/CrmSalesAnalytics.vue'
import CrmSalesStats from '@/views/dashboards/crm/CrmSalesStats.vue'
import CrmSession from '@/views/dashboards/crm/CrmSessions.vue'
import CrmTeamMembers from '@/views/dashboards/crm/CrmTeamMembers.vue'
import CrmTopProducts from '@/views/dashboards/crm/CrmTopProducts.vue'
import CardAdvanceSalesByCountries from '@/views/pages/cards/card-advance/CardAdvanceSalesByCountries.vue'
import cube from '@images/cards/cube-secondary.png'
</script>

<template>
  <VRow class="match-height">
    <!-- 👉 Customer Ratings -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <CrmCustomerRatings />
    </VCol>

    <!-- 👉 Sales Activity -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <CrmSalesActivity />
    </VCol>

    <VCol
      cols="12"
      lg="4"
    >
      <VRow class="match-height">
        <!-- 👉 Session chart -->
        <VCol
          cols="12"
          sm="6"
          md="3"
          lg="6"
        >
          <CrmSession />
        </VCol>

        <!-- 👉 Statistics Vertical card -->
        <VCol
          cols="12"
          sm="6"
          md="3"
          lg="6"
        >
          <CardStatisticsVertical
            title="Order"
            color="secondary"
            :image="cube"
            stats="$1,286"
            :change="-13.24"
          />
        </VCol>

        <!-- 👉 Generated Leads card -->
        <VCol
          cols="12"
          md="6"
          lg="12"
        >
          <CrmGeneratedLeads />
        </VCol>
      </VRow>
    </VCol>

    <!-- 👉 Top Products -->
    <VCol
      cols="12"
      lg="8"
    >
      <CrmTopProducts />
    </VCol>

    <!-- 👉 Earning Report -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <CrmEarningReport />
    </VCol>

    <!-- 👉 Sales Analytics -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <CrmSalesAnalytics />
    </VCol>

    <!-- 👉 Sales By Countries -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <CardAdvanceSalesByCountries />
    </VCol>

    <!-- 👉 Sales Stats -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <CrmSalesStats />
    </VCol>

    <!-- 👉 Team Members -->
    <VCol
      cols="12"
      md="5"
    >
      <CrmTeamMembers />
    </VCol>

    <!-- 👉 Customer Table -->
    <VCol
      cols="12"
      md="7"
    >
      <CrmCustomerTable />
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/libs/apex-chart.scss";
</style>
