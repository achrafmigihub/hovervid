<script setup>
import ShoppingGirl from '@images/illustrations/shopping-girl.png'

const props = defineProps({
  formData: {
    type: null,
    required: true,
  },
})

const emit = defineEmits(['update:formData'])

const discountOffers = [
  {
    icon: {
      icon: 'bx-purchase-tag',
      size: '28',
    },
    title: 'Percentage',
    desc: 'Create a deal which offer uses some % off (i.e 5% OFF) on total.',
    value: 'percentage',
  },
  {
    icon: {
      icon: 'bx-dollar',
      size: '28',
    },
    title: 'Flat Amount',
    desc: 'Create a deal which offer uses flat $5 OFF on total.',
    value: 'flat',
  },
  {
    icon: {
      icon: 'bx-user',
      size: '28',
    },
    title: 'Prime member',
    desc: 'Create prime member only deal to encourage the prime members.',
    value: 'prime',
  },
]

const formData = ref(props.formData)

watch(formData, () => {
  emit('update:formData', formData.value)
})
</script>

<template>
  <VForm>
    <VRow>
      <!-- 👉 Shopping girl image -->
      <VCol cols="12">
        <VImg
          :src="ShoppingGirl"
          max-height="240"
          class="border rounded "
        />
      </VCol>

      <VCol cols="12">
        <CustomRadiosWithIcon
          v-model:selected-radio="formData.Offer"
          :radio-content="discountOffers"
          :grid-column="{ cols: '12', sm: '4' }"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <AppTextField
          v-model="formData.discount"
          type="number"
          label="Discount"
          placeholder="25"
          hint="Enter the discount percentage. 10 = 10%"
          persistent-hint
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <AppSelect
          v-model="formData.region"
          label="Region"
          :items="['Asia', 'Europe', 'Africa', 'Australia', 'North America', 'South America']"
          placeholder="Select Region"
          hint="Select applicable regions for the deal."
          persistent-hint
        />
      </VCol>
    </VRow>
  </VForm>
</template>
