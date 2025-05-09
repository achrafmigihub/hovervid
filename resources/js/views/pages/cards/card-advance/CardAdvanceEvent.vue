<script setup>
import avatar1 from '@images/avatars/avatar-1.png'
import avatar2 from '@images/avatars/avatar-2.png'
import avatar3 from '@images/avatars/avatar-3.png'
import avatar4 from '@images/avatars/avatar-4.png'
import event from '@images/cards/event.png'

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

const tags = [
  {
    title: 'TECHNICAL',
    color: 'success',
  },
  {
    title: 'EXCEL',
    color: 'primary',
  },
  {
    title: 'ACCOUNT',
    color: 'info',
  },
]

const avatarGroups = [
  {
    img: avatar1,
    name: 'John Doe',
  },
  {
    img: avatar2,
    name: 'Jennie o\'brien',
  },
  {
    img: avatar3,
    name: 'Peter Harper',
  },
  {
    img: avatar4,
    name: 'Vivian Padilla',
  },
]
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>
        Olivia Shared Event
      </VCardTitle>
      <VCardSubtitle class="text-body-2 text-medium-emphasis">
        07 Sep 2020 at 10:30 AM
      </VCardSubtitle>
      <template #prepend>
        <VAvatar
          :image="avatar4"
          size="40"
          class="me-1"
        />
      </template>

      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>

    <VImg
      cover
      height="160"
      :src="event"
    />
    <VCardText class="position-relative">
      <div class="event-date rounded elevation-8">
        <h4 class="text-h4">
          12
        </h4>
        <span class="d-inline-block text-body-1 text-primary">Mar</span>
      </div>

      <h5 class="text-h5 mt-7 mb-2">
        How To Excel In A Technical…
      </h5>
      <div class="d-flex gap-2 flex-wrap">
        <VChip
          v-for=" tag in tags"
          :key="tag.title"
          label
          :color="tag.color"
          size="small"
        >
          {{ tag.title }}
        </VChip>
      </div>

      <div class="d-flex align-center justify-space-between mt-6">
        <div class="v-avatar-group">
          <VAvatar
            v-for="avatar in avatarGroups"
            :key="avatar.name"
            :size="38"
          >
            <VImg :src="avatar.img" />
            <VTooltip
              activator="parent"
              location="top"
            >
              {{ avatar.name }}
            </VTooltip>
          </VAvatar>
        </div>

        <VBtn>Join Now</VBtn>
      </div>
    </VCardText>

    <VCardText class="d-flex align-center gap-4">
      <div class="d-flex ">
        <VIcon
          size="22"
          icon="bx-heart"
          class="me-1"
        />
        <span>
          236
        </span>
      </div>
      <div>
        <VIcon
          size="22"
          icon="bx-chat"
          class="me-1"
        />
        <span class="d-inline-block">
          12
        </span>
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss">
.event-date {
  position: absolute;
  background-color: rgb(var(--v-theme-surface));
  block-size: 62px;
  inline-size: 60px;
  inset-block-start: -2rem;
  padding-block: 0.25rem;
  padding-inline: 1rem;

  h4 {
    line-height: 2rem;
  }
}
</style>
