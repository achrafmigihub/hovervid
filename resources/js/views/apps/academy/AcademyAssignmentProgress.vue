<script setup>
const assignmentData = [
  {
    title: 'User Experience Design',
    tasks: 120,
    progress: 72,
    color: 'primary',
  },
  {
    title: 'Basic fundamentals',
    tasks: 32,
    progress: 48,
    color: 'success',
  },
  {
    title: 'React Native components',
    tasks: 182,
    progress: 15,
    color: 'error',
  },
  {
    title: 'Basic of music theory',
    tasks: 56,
    progress: 24,
    color: 'info',
  },
]

const moreList = [
  {
    title: 'Refresh',
    value: 'refresh',
  },
  {
    title: 'Update',
    value: 'update',
  },
  {
    title: 'Share',
    value: 'share',
  },
]
</script>

<template>
  <VCard>
    <VCardItem title="Assignment progress">
      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>
    <VCardText>
      <VList class="card-list">
        <VListItem
          v-for="assignment in assignmentData"
          :key="assignment.title"
        >
          <template #prepend>
            <VProgressCircular
              v-model="assignment.progress"
              :size="54"
              class="me-4"
              :color="assignment.color"
            >
              <span class="text-body-1 text-high-emphasis font-weight-medium">
                {{ assignment.progress }}%
              </span>
            </VProgressCircular>
          </template>
          <VListItemTitle class="font-weight-medium mb-1 me-2">
            {{ assignment.title }}
          </VListItemTitle>

          <VListItemSubtitle class="me-2 text-body-1">
            {{ assignment.tasks }} Tasks
          </VListItemSubtitle>
          <template #append>
            <VBtn
              icon
              variant="tonal"
              color="secondary"
              rounded="sm"
              size="30"
            >
              <VIcon
                icon="bx-chevron-right"
                size="20"
                class="flip-in-rtl"
              />
            </VBtn>
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 2rem;
}
</style>
