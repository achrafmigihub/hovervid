<script setup>
import laptopGirl from '@images/illustrations/laptop-girl.png'
</script>

<template>
  <VCard>
    <VRow no-gutters>
      <VCol
        cols="12"
        sm="4"
        class="text-center"
      >
        <img
          :src="laptopGirl"
          :class="$vuetify.display.xs ? 'mt-4 mb-n2' : 'position-absolute'"
          alt="sitting girl with laptop"
          height="173"
          width="244"
          class="sitting-girl-illustration flip-in-rtl"
        >
      </VCol>
      <VCol
        cols="12"
        sm="8"
        class="d-flex justify-center"
      >
        <div>
          <VCardItem class="pb-3">
            <VCardTitle class="flex-wrap text-primary">
              Welcome back Anna!
            </VCardTitle>
          </VCardItem>

          <VCardText>
            <p class="mb-6">
              You have 12 task to finish today,
              <br>
              Your already completed 189 task good job.
            </p>
            <VChip
              color="primary"
              label
              size="small"
              class="mb-6"
            >
              78%  OF TARGET
            </VChip>
          </VCardText>
        </div>
      </VCol>
    </VRow>
  </VCard>
</template>

<style lang="scss" scoped>
.sitting-girl-illustration {
  inset-block-end: 0;
  inset-inline-start: 2.5rem;
}
</style>
