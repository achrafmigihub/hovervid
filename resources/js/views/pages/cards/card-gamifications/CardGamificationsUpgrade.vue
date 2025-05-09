<script setup>
import upgradeAccountLaunch from '@images/cards/upgrade-account-launch.png'
</script>

<template>
  <VCard class="position-relative">
    <VRow>
      <VCol
        cols="12"
        xs="6"
        sm="4"
      >
        <VImg
          :src="upgradeAccountLaunch"
          :width="115"
          class="launch-illustration flip-in-rtl"
        />
      </VCol>
      <VCol
        cols="12"
        xs="6"
        sm="8"
      >
        <VCardItem class="text-end text-sm-center pb-3">
          <VCardTitle> Upgrade Account</VCardTitle>
          <VCardSubtitle>Add 15 team Members</VCardSubtitle>
        </VCardItem>

        <VCardText class="text-end text-sm-center">
          <h5 class="text-h5 text-info">
            $199
          </h5>
          <p class="text-base mb-3">
            20% OFF
          </p>
          <VBtn
            size="small"
            color="info"
          >
            Upgrade
          </VBtn>
        </VCardText>
      </VCol>
    </VRow>
  </VCard>
</template>

<style lang="scss">
.launch-illustration {
  position: absolute;
  inline-size: 3rem;
  inset-block-end: 0;
  inset-inline-start: 1.5rem;
}
</style>
