<script setup>
import CardAward from '@/views/pages/cards/card-gamifications/CardGamificationsAward.vue'
import CardCongratulations from '@/views/pages/cards/card-gamifications/CardGamificationsCongratulations.vue'
import CardUpgrade from '@/views/pages/cards/card-gamifications/CardGamificationsUpgrade.vue'
import CardWelcome from '@/views/pages/cards/card-gamifications/CardGamificationsWelcome.vue'
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="4"
    >
      <CardAward />
    </VCol>

    <VCol
      cols="12"
      md="8"
      class="align-self-end"
    >
      <CardCongratulations />
    </VCol>

    <VCol
      cols="12"
      md="8"
      class="align-self-end"
    >
      <CardWelcome />
    </VCol>

    <VCol
      cols="12"
      md="4"
    >
      <CardUpgrade />
    </VCol>
  </VRow>
</template>
