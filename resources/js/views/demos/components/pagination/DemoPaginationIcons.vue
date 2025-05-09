<script setup>
const currentPage = ref(1)
</script>

<template>
  <VPagination
    v-model="currentPage"
    :length="5"
    prev-icon="bx-caret-left"
    next-icon="bx-caret-right"
  />
</template>
