<script setup>
import trophy from '@images/cards/trophy.png'
</script>

<template>
  <VCard>
    <VCardItem class="pb-3">
      <VCardTitle>Congratulations Katie! 🎉</VCardTitle>
      <VCardSubtitle>Best seller of the month</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <h5 class="text-h5 text-primary">
        $42.8k
      </h5>
      <p class="text-base mb-3">
        78% of target<span class="text-high-emphasis">🚀</span>
      </p>
      <VBtn size="small">
        View Sales
      </VBtn>
    </VCardText>

    <!-- Trophy -->
    <VImg
      :src="trophy"
      :width="92"
      class="trophy"
    />
  </VCard>
</template>

<style lang="scss" scoped>
.trophy {
  position: absolute;
  inline-size: 5.188rem;
  inset-block-end: 0;
  inset-inline-end: 2rem;
}
</style>
