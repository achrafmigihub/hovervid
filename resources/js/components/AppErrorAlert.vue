<script setup>
/**
 * Component for displaying error messages with field-specific details
 */

const props = defineProps({
  // The error object from the API response
  error: {
    type: [String, Object],
    required: true
  },
  // Show detailed validation errors in list form
  showDetails: {
    type: Boolean,
    default: true
  },
  // Color of the alert
  color: {
    type: String,
    default: 'error'
  },
  // Alert variant
  variant: {
    type: String,
    default: 'tonal'
  },
  // Whether to show the close button
  closable: {
    type: Boolean,
    default: true
  }
})

// Computed property to get the error message
const errorMessage = computed(() => {
  if (typeof props.error === 'string') {
    return props.error
  }
  
  return props.error.message || 'An unexpected error occurred.'
})

// Computed property to check if there are validation errors
const hasValidationErrors = computed(() => {
  return props.error && 
         typeof props.error === 'object' && 
         props.error.validationErrors && 
         Object.keys(props.error.validationErrors).length > 0
})

// Format field name for display (convert camelCase or snake_case to words)
const formatFieldName = (field) => {
  return field
    // Insert a space before all uppercase letters
    .replace(/([A-Z])/g, ' $1')
    // Replace underscores with spaces
    .replace(/_/g, ' ')
    // Capitalize the first letter
    .replace(/^./, str => str.toUpperCase())
    .trim()
}
</script>

<template>
  <VAlert
    :color="color"
    :variant="variant"
    :closable="closable"
    class="mb-4"
    v-if="error"
  >
    <!-- Main error message -->
    <div class="text-body-1 font-weight-medium mb-1">
      {{ errorMessage }}
    </div>
    
    <!-- Validation error details -->
    <div v-if="hasValidationErrors && showDetails" class="mt-2">
      <ul class="validation-error-list pl-4 mb-0">
        <li v-for="(message, field) in error.validationErrors" :key="field">
          <span class="font-weight-medium">{{ formatFieldName(field) }}:</span> {{ message }}
        </li>
      </ul>
    </div>
  </VAlert>
</template>

<style scoped>
.validation-error-list {
  margin-top: 8px;
  list-style-type: disc;
}
</style> 