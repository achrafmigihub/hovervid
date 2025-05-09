<script setup>
import { computed } from 'vue'

const props = defineProps({
  page: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    required: true,
  },
  totalItems: {
    type: Number,
    required: true,
    default: 0,
    validator: (value) => !isNaN(value) && value >= 0,
  },
})

const emit = defineEmits(['update:page'])

const updatePage = value => {
  emit('update:page', value)
}

// Computed property to ensure we never have NaN in the pagination length
const paginationLength = computed(() => {
  const total = props.totalItems || 0
  return Math.max(1, Math.ceil(total / props.itemsPerPage))
})
</script>

<template>
  <div>
    <VDivider />

    <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 px-6 py-3">
      <p class="text-disabled mb-0">
        {{ paginationMeta({ page, itemsPerPage }, totalItems || 0) }}
      </p>

      <VPagination
        :model-value="page"
        active-color="primary"
        :length="paginationLength"
        :total-visible="$vuetify.display.xs ? 1 : Math.min(paginationLength, 5)"
        @update:model-value="updatePage"
      />
    </div>
  </div>
</template>
