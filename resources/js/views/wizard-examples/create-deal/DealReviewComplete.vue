<script setup>
import sittingGirlCheckout from '@images/illustrations/sitting-girl-checkout.png'

const props = defineProps({
  formData: {
    type: null,
    required: true,
  },
})

const emit = defineEmits(['update:formData'])

const formData = ref(props.formData)

watch(formData, () => {
  emit('update:formData', formData.value)
})
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="6"
      class="pb-4 pb-sm-0"
    >
      <h4 class="text-h4 mb-4">
        Almost done! 🚀
      </h4>

      <p>Confirm your deal details information and submit to create it.</p>

      <table class="text-base">
        <tbody>
          <tr>
            <td style="inline-size: 150px">
              <p class="font-weight-medium mb-2">
                Deal Type
              </p>
            </td>
            <td>
              <p class="mb-2">
                Percentage
              </p>
            </td>
          </tr>

          <tr>
            <td>
              <p class="font-weight-medium mb-2">
                Amount
              </p>
            </td>
            <td>
              <p class="mb-2">
                25%
              </p>
            </td>
          </tr>

          <tr>
            <td>
              <p class="font-weight-medium mb-2">
                Deal Code
              </p>
            </td>
            <td>
              <p class="mb-2">
                <VChip
                  size="small"
                  color="warning"
                  label
                >
                  25PEROFF
                </VChip>
              </p>
            </td>
          </tr>

          <tr>
            <td>
              <p class="font-weight-medium mb-2">
                Deal Title
              </p>
            </td>
            <td>
              <p class="mb-2">
                Black friday sale, 25% OFF
              </p>
            </td>
          </tr>

          <tr>
            <td>
              <p class="font-weight-medium mb-2">
                Deal Duration
              </p>
            </td>
            <td>
              <p class="mb-2">
                2021-07-14 to 2021-07-30
              </p>
            </td>
          </tr>
        </tbody>
      </table>

      <VSwitch
        v-model="formData.isDealDetailsConfirmed"
        label="I have confirmed the deal details."
        class="mb-3"
      />
    </VCol>

    <VCol
      cols="12"
      md="6"
    >
      <VImg
        width="334"
        :src="sittingGirlCheckout"
        class="mx-auto flip-in-rtl"
      />
    </VCol>
  </VRow>
</template>
