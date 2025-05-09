<script setup>
import girlWithLaptop from '@images/illustrations/laptop-girl.png'
</script>

<template>
  <VCard>
    <VCardText>
      <div class="d-flex justify-center align-start pb-0 px-3 pt-4 mb-4 bg-light-primary rounded">
        <VImg
          :src="girlWithLaptop"
          width="125"
          height="148"
        />
      </div>
      <div>
        <h5 class="text-h5 mb-2">
          Upcoming Webinar
        </h5>
        <div class="text-body-1">
          Next Generation Frontend Architecture Using Layout Engine And Vue.
        </div>
        <div class="d-flex justify-space-between my-4 flex-wrap gap-4">
          <div
            v-for="{ icon, title, value } in [{ icon: 'bx-calendar', title: '17 Nov 23', value: 'Date' }, { icon: 'bx-time-five', title: '32 Minutes', value: 'Duration' }]"
            :key="title"
            class="d-flex gap-x-3 align-center"
          >
            <VAvatar
              color="primary"
              size="40"
              variant="tonal"
              rounded
            >
              <VIcon
                :icon="icon"
                size="24"
              />
            </VAvatar>
            <div>
              <h6 class="text-h6">
                {{ title }}
              </h6>
              <div class="text-sm">
                {{ value }}
              </div>
            </div>
          </div>
        </div>
        <VBtn block>
          Join the event
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>
