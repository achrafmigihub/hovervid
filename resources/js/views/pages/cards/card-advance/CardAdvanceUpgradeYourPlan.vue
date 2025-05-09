<script setup>
import briefcase from '@images/cards/briefcase.png'
import logoCreditCard2 from '@images/cards/logo-credit-card-2.png'
import logoMastercardSmall from '@images/cards/logo-mastercard-small.png'

const savedCards = [
  {
    logo: logoMastercardSmall,
    type: 'Credit card',
    number: '2566 xxxx xxxx 8908',
  },
  {
    logo: logoCreditCard2,
    type: 'Credit card',
    number: '8990 xxxx xxxx 6852',
  },
]

const moreList = [
  {
    title: 'Share',
    value: 'Share',
  },
  {
    title: 'Refresh',
    value: 'Refresh',
  },
  {
    title: 'Update',
    value: 'Update',
  },
]
</script>

<template>
  <VCard>
    <!-- SECTION Card Header and Menu -->
    <VCardItem>
      <!-- 👉 Title -->
      <VCardTitle>Upgrade Your Plan</VCardTitle>

      <!-- 👉 menu -->
      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>
    <!-- !SECTION -->

    <VCardText>
      <p class="mb-5">
        Please make the payment to start enjoying all the features of our premium plan as soon as possible.
      </p>

      <!-- SECTION Upgrade plan banner -->
      <div class="plan-upgrade-banner py-3 px-4 d-flex rounded align-center mb-5">
        <VAvatar
          rounded
          size="40"
          class="plan-details me-2 border-opacity-100 border-primary me-3"
        >
          <VImg
            :src="briefcase"
            alt="briefcase"
          />
        </VAvatar>

        <div class="d-flex flex-column">
          <p class="text-body-1 mb-0 text-high-emphasis">
            Business
          </p>
          <span class="text-primary text-body-2">Upgrade Plan</span>
        </div>
        <VSpacer />
        <div class="d-flex align-center">
          <div class="text-body-2 text-high-emphasis me-1">
            $
          </div>
          <div class="text-h5">
            2,124
          </div>
          <div class="text-align-end">
            <h6 class="text-body-1 text-high-emphasis">
              /Year
            </h6>
          </div>
        </div>
      </div>
      <!-- !SECTION -->

      <!-- SECTION Payment Details -->
      <h6 class="text-h6 mb-4">
        Payment details
      </h6>
      <VList class="card-list pt-1">
        <VListItem
          v-for="card in savedCards"
          :key="card.logo"
        >
          <!-- 👉 Avatar -->
          <template #prepend>
            <VImg
              rounded
              start
              height="32"
              width="45"
              class="me-3"
              :src="card.logo"
            />
          </template>

          <!-- 👉 Title and Subtitle -->

          <VListItemTitle class="text-h6">
            {{ card.type }}
          </VListItemTitle>
          <VListItemSubtitle class="text-body-2 text-disabled font-weight-medium">
            {{ card.number }}
          </VListItemSubtitle>

          <!-- 👉 Action -->
          <template #append>
            <VListItemAction>
              <AppTextField
                placeholder="CVV"
                style="inline-size: 5rem;"
              />
            </VListItemAction>
          </template>
        </VListItem>

        <!-- 👉 Add Payment  -->
        <VListItem class="mb-5">
          <a
            href="javascript:void(0)"
            class="text-h6 text-primary"
          >Add Payment Method</a>
        </VListItem>
      </VList>
      <!-- !SECTION -->

      <!-- 👉 Email -->
      <VForm>
        <VRow>
          <VCol cols="12">
            <VTextField
              placeholder="Email Address"
              class="mb-5"
            />

            <VBtn block>
              Process To Payment
            </VBtn>
          </VCol>
        </VRow>
      </VForm>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.plan-upgrade-banner {
  border: 1px solid rgb(var(--v-theme-primary));
}

.card-list {
  --v-card-list-gap: 1rem;
}
</style>
