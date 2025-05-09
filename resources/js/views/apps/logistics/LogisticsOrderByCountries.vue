<script setup>
const currentTab = ref('New')

const tabsData = [
  'New',
  'Preparing',
  'Shipping',
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
  <VCard class="country-order-card">
    <VCardItem
      title="Orders by countries"
      subtitle="62 deliveries in progress"
    >
      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>

    <VTabs
      v-model="currentTab"
      grow
      class="disable-tab-transition"
    >
      <VTab
        v-for="(tab, index) in tabsData"
        :key="index"
      >
        {{ tab }}
      </VTab>
    </VTabs>

    <VCardText>
      <VWindow v-model="currentTab">
        <VWindowItem>
          <div>
            <VTimeline
              align="start"
              truncate-line="both"
              side="end"
              density="compact"
              line-thickness="1"
              class="v-timeline--variant-outlined order-timeline"
            >
              <VTimelineItem
                icon="bx-check-circle"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="success"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-uppercase text-success">
                  Sender
                </div>
                <div class="app-timeline-title">
                  Myrtle Ullrich
                </div>
                <div class="app-timeline-text">
                  101 Boulder, California(CA), 95959
                </div>
              </VTimelineItem>
              <VTimelineItem
                icon="bx-map"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="primary"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-primary text-uppercase">
                  Receiver
                </div>
                <div class="app-timeline-title">
                  Barry Schowalter
                </div>
                <div class="app-timeline-text">
                  939 Orange, California(CA), 92118
                </div>
              </VTimelineItem>
            </VTimeline>
            <VDivider
              class="my-4"
              style="border-style: dashed;"
            />
            <VTimeline
              align="start"
              truncate-line="both"
              side="end"
              density="compact"
              line-thickness="1"
              class="v-timeline--variant-outlined order-timeline"
            >
              <VTimelineItem
                icon="bx-check-circle"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="success"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-uppercase text-success">
                  Sender
                </div>
                <div class="app-timeline-title">
                  Veronica Herman
                </div>
                <div class="app-timeline-text">
                  162  Windsor, California(CA), 95492
                </div>
              </VTimelineItem>

              <VTimelineItem
                icon="bx-map"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="primary"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-primary text-uppercase">
                  Receiver
                </div>
                <div class="app-timeline-title">
                  Helen Jacobs
                </div>
                <div class="app-timeline-text">
                  487 Sunset, California(CA), 94043
                </div>
              </VTimelineItem>
            </VTimeline>
          </div>
        </VWindowItem>

        <VWindowItem>
          <div>
            <VTimeline
              align="start"
              truncate-line="both"
              side="end"
              density="compact"
              line-thickness="1"
              class="v-timeline--variant-outlined order-timeline"
            >
              <VTimelineItem
                icon="bx-check-circle"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="success"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-uppercase text-success">
                  Sender
                </div>
                <div class="app-timeline-title">
                  Myrtle Ullrich
                </div>
                <div class="app-timeline-text">
                  101 Boulder, California(CA), 95959
                </div>
              </VTimelineItem>
              <VTimelineItem
                icon="bx-map"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="primary"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-primary text-uppercase">
                  Receiver
                </div>
                <div class="app-timeline-title">
                  Barry Schowalter
                </div>
                <div class="app-timeline-text">
                  939 Orange, California(CA), 92118
                </div>
              </VTimelineItem>
            </VTimeline>
            <VDivider
              class="my-4"
              style="border-style: dashed;"
            />
            <VTimeline
              align="start"
              truncate-line="both"
              side="end"
              density="compact"
              line-thickness="1"
              class="v-timeline--variant-outlined order-timeline"
            >
              <VTimelineItem
                icon="bx-check-circle"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="success"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-uppercase text-success">
                  Sender
                </div>
                <div class="app-timeline-title">
                  Veronica Herman
                </div>
                <div class="app-timeline-text">
                  162  Windsor, California(CA), 95492
                </div>
              </VTimelineItem>

              <VTimelineItem
                icon="bx-map"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="primary"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-primary text-uppercase">
                  Receiver
                </div>
                <div class="app-timeline-title">
                  Helen Jacobs
                </div>
                <div class="app-timeline-text">
                  487 Sunset, California(CA), 94043
                </div>
              </VTimelineItem>
            </VTimeline>
          </div>
        </VWindowItem>

        <VWindowItem>
          <div>
            <VTimeline
              align="start"
              truncate-line="both"
              side="end"
              density="compact"
              line-thickness="1"
              class="v-timeline--variant-outlined order-timeline"
            >
              <VTimelineItem
                icon="bx-check-circle"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="success"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-uppercase text-success">
                  Sender
                </div>
                <div class="app-timeline-title">
                  Myrtle Ullrich
                </div>
                <div class="app-timeline-text">
                  101 Boulder, California(CA), 95959
                </div>
              </VTimelineItem>
              <VTimelineItem
                icon="bx-map"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="primary"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-primary text-uppercase">
                  Receiver
                </div>
                <div class="app-timeline-title">
                  Barry Schowalter
                </div>
                <div class="app-timeline-text">
                  939 Orange, California(CA), 92118
                </div>
              </VTimelineItem>
            </VTimeline>
            <VDivider
              class="my-4"
              style="border-style: dashed;"
            />
            <VTimeline
              align="start"
              truncate-line="both"
              side="end"
              density="compact"
              line-thickness="1"
              class="v-timeline--variant-outlined order-timeline"
            >
              <VTimelineItem
                icon="bx-check-circle"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="success"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-uppercase text-success">
                  Sender
                </div>
                <div class="app-timeline-title">
                  Veronica Herman
                </div>
                <div class="app-timeline-text">
                  162  Windsor, California(CA), 95492
                </div>
              </VTimelineItem>

              <VTimelineItem
                icon="bx-map"
                dot-color="rgba(var(--v-theme-surface))"
                icon-color="primary"
                fill-dot
                size="20"
                :elevation="0"
              >
                <div class="text-body-2 text-primary text-uppercase">
                  Receiver
                </div>
                <div class="app-timeline-title">
                  Helen Jacobs
                </div>
                <div class="app-timeline-text">
                  487 Sunset, California(CA), 94043
                </div>
              </VTimelineItem>
            </VTimeline>
          </div>
        </VWindowItem>
      </VWindow>
    </VCardText>
  </VCard>
</template>

<style lang="scss">
.country-order-card {
  .v-timeline .v-timeline-divider__dot .v-timeline-divider__inner-dot {
    box-shadow: none !important;
  }
}

.order-timeline {
  &.v-timeline .v-timeline-item:not(:last-child) {
    .v-timeline-item__body {
      margin-block-end: 0.25rem;
    }
  }
}
</style>
