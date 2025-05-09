<script setup>
/**
 * Form input component with built-in error handling
 * Integrates with our API error handling system
 */

const props = defineProps({
  // Input type
  type: {
    type: String,
    default: 'text',
    validator: value => ['text', 'email', 'password', 'number', 'tel', 'url', 'textarea'].includes(value)
  },
  // Input label
  label: {
    type: String,
    required: true
  },
  // Model value
  modelValue: {
    type: [String, Number, null],
    default: null
  },
  // Field name (used for error detection)
  name: {
    type: String,
    required: true
  },
  // Error object from API response
  error: {
    type: Object,
    default: null
  },
  // Input placeholder
  placeholder: {
    type: String,
    default: null
  },
  // Input hint text
  hint: {
    type: String,
    default: null
  },
  // Whether the input is required
  required: {
    type: Boolean,
    default: false
  },
  // Whether the input is disabled
  disabled: {
    type: Boolean,
    default: false
  },
  // Input density
  density: {
    type: String,
    default: 'default',
    validator: value => ['default', 'compact', 'comfortable'].includes(value)
  },
  // Input variant
  variant: {
    type: String,
    default: 'outlined',
    validator: value => ['outlined', 'filled', 'plain', 'underlined', 'solo'].includes(value)
  },
  // Autocomplete value
  autocomplete: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:modelValue'])

// Get field-specific error if exists
const fieldError = computed(() => {
  if (!props.error || !props.error.validationErrors) return null

  // Check if there's an error for this specific field
  if (props.error.validationErrors[props.name]) {
    return props.error.validationErrors[props.name]
  }

  // Support nested fields with dot notation (e.g., user.email)
  if (props.name.includes('.')) {
    const parts = props.name.split('.')
    const lastPart = parts[parts.length - 1]
    
    if (props.error.validationErrors[lastPart]) {
      return props.error.validationErrors[lastPart]
    }
  }

  return null
})

// Update the model value
const onInput = (event) => {
  emit('update:modelValue', event.target.value)
}
</script>

<template>
  <div class="form-input-wrapper mb-3">
    <VTextField
      v-if="type !== 'textarea'"
      v-model="modelValue"
      :type="type"
      :label="label"
      :placeholder="placeholder"
      :hint="hint"
      :error="!!fieldError"
      :error-messages="fieldError"
      :required="required"
      :disabled="disabled"
      :density="density"
      :variant="variant"
      :autocomplete="autocomplete"
      @update:model-value="value => $emit('update:modelValue', value)"
      class="form-input"
    />
    
    <VTextarea
      v-else
      v-model="modelValue"
      :label="label"
      :placeholder="placeholder"
      :hint="hint"
      :error="!!fieldError"
      :error-messages="fieldError"
      :required="required"
      :disabled="disabled"
      :density="density"
      :variant="variant"
      @update:model-value="value => $emit('update:modelValue', value)"
      class="form-input form-textarea"
    />
  </div>
</template>

<style scoped>
.form-input-wrapper {
  width: 100%;
}
</style> 