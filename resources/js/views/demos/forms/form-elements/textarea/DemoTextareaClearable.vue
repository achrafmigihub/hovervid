<script setup>
const textareaValue = ref('This is clearable text.')
</script>

<template>
  <AppTextarea
    v-model="textareaValue"
    clearable
    clear-icon="bx-x-circle"
    label="Text"
    placeholder="Placeholder Text"
  />
</template>
