<script setup>
import avatar1 from '@images/avatars/avatar-1.png'
import avatar2 from '@images/avatars/avatar-2.png'
import avatar3 from '@images/avatars/avatar-3.png'
import avatar4 from '@images/avatars/avatar-4.png'
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>
        Finance Summary
      </VCardTitle>
      <VCardSubtitle>Check out each Column for more details</VCardSubtitle>

      <template #append>
        <VAvatar
          size="44"
          color="primary"
          variant="tonal"
        >
          <VIcon
            size="24"
            icon="bx-dollar"
          />
        </VAvatar>
      </template>
    </VCardItem>

    <VCardText>
      <VRow>
        <VCol
          cols="12"
          sm="6"
        >
          <p class="text-sm mb-0">
            Annual Companies Taxes
          </p>
          <h6 class="text-h6">
            $50,000
          </h6>
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <p class="text-sm mb-0">
            Next Tax Review Date
          </p>
          <h6 class="text-h6">
            July 24, 2022
          </h6>
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <p class="text-sm mb-0">
            Average Product Price
          </p>
          <h6 class="text-h6">
            $85.50
          </h6>
        </VCol>

        <VCol>
          <p class="text-sm mb-1">
            Next Tax Review Date
          </p>
          <div
            class="d-flex align-center gap-4 w-100"
            style="inline-size: 130px;"
          >
            <div class="flex-grow-1">
              <VProgressLinear
                color="primary"
                :model-value="78"
                :height="8"
                rounded
                rounded-bar
              />
            </div>
            <span class="text-sm font-weight-medium">78%</span>
          </div>
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <div class="v-avatar-group">
            <VAvatar :image="avatar1" />
            <VAvatar :image="avatar2" />
            <VAvatar :image="avatar3" />
            <VAvatar :image="avatar4" />
          </div>
        </VCol>

        <VCol>
          <VChip
            label
            color="primary"
            size="small"
            class="mt-2 text-uppercase"
          >
            5 days Ago
          </VChip>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
