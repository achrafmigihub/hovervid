<template>
  <VCard>
    <VCardText>
      <div class="text-base mb-1">
        Sales
      </div>
      <h4 class="text-h4 mb-3">
        482k
      </h4>
      <VChip
        label
        size="small"
        color="info"
      >
        +34%
      </VChip>
      <p class="text-sm mb-0 mt-3">
        Sales Target
      </p>
      <div class="d-flex align-center gap-2">
        <div class="flex-grow-1">
          <VProgressLinear
            height="8"
            rounded
            rounded-bar
            color="info"
            model-value="78"
          />
        </div>
        <span class="text-sm">78%</span>
      </div>
    </VCardText>
  </VCard>
</template>
