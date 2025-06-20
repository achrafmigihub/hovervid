// ❗ WARNING please use your access token from mapbox.com
<script setup>
import mapboxgl from 'mapbox-gl'
import {
  onMounted,
  ref,
} from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'
import fleetImg from '@images/misc/fleet-car.png'

const { isLeftSidebarOpen } = useResponsiveLeftSidebar()
const accessToken = import.meta.env.VITE_MAPBOX_ACCESS_TOKEN
const map = ref()
const vuetifyDisplay = useDisplay()

definePage({ meta: { layoutWrapperClasses: 'layout-content-height-fixed' } })

const carImgs = ref([
  fleetImg,
  fleetImg,
  fleetImg,
  fleetImg,
])

const refCars = ref([])

const showPanel = ref([
  true,
  false,
  false,
  false,
])

const geojson = {
  type: 'FeatureCollection',
  features: [
    {
      type: 'Feature',
      geometry: {
        type: 'Point',
        coordinates: [
          -73.999024,
          40.75249842,
        ],
      },
    },
    {
      type: 'Feature',
      geometry: {
        type: 'Point',
        coordinates: [
          -74.03,
          40.75699842,
        ],
      },
    },
    {
      type: 'Feature',
      geometry: {
        type: 'Point',
        coordinates: [
          -73.967524,
          40.7599842,
        ],
      },
    },
    {
      type: 'Feature',
      geometry: {
        type: 'Point',
        coordinates: [
          -74.0325,
          40.742992,
        ],
      },
    },
  ],
}

const activeIndex = ref(0)

onMounted(async () => {
  await new Promise(resolve => setTimeout(resolve, 100))
  mapboxgl.accessToken = accessToken
  map.value = new mapboxgl.Map({
    container: 'mapContainer',
    style: 'mapbox://styles/mapbox/light-v9',
    center: [
      -73.999024,
      40.75249842,
    ],
    zoom: 12.25,
  })
  for (let index = 0; index < geojson.features.length; index++) {
    new mapboxgl.Marker({ element: refCars.value[index] }).setLngLat(geojson.features[index].geometry.coordinates).addTo(map.value)
  }
  refCars.value[activeIndex.value].classList.add('marker-focus')
})

const vehicleTrackingData = [
  {
    name: 'VOL-342808',
    location: 'Chelsea, NY, USA',
    progress: 88,
    driverName: 'Veronica Herman',
  },
  {
    name: 'VOL-954784',
    location: 'Lincoln Harbor, NY, USA',
    progress: 100,
    driverName: 'Myrtle Ullrich',
  },
  {
    name: 'VOL-342808',
    location: 'Midtown East, NY, USA',
    progress: 60,
    driverName: 'Barry Schowalter',
  },
  {
    name: 'VOL-343908',
    location: 'Hoboken, NY, USA',
    progress: 28,
    driverName: 'Helen Jacobs',
  },
]

const flyToLocation = (geolocation, index) => {
  activeIndex.value = index
  showPanel.value.fill(false)
  showPanel.value[index] = !showPanel.value[index]
  if (vuetifyDisplay.mdAndDown.value)
    isLeftSidebarOpen.value = false
  map.value.flyTo({
    center: geolocation,
    zoom: 16,
  })
}

watch(activeIndex, () => {
  refCars.value.forEach((car, index) => {
    if (index === activeIndex.value)
      car.classList.add('marker-focus')
    else
      car.classList.remove('marker-focus')
  })
})
</script>

<template>
  <VLayout class="fleet-app-layout">
    <VNavigationDrawer
      v-model="isLeftSidebarOpen"
      data-allow-mismatch
      width="360"
      absolute
      touchless
      location="start"
      border="none"
    >
      <VCard
        class="h-100 fleet-navigation-drawer"
        flat
      >
        <VCardItem>
          <VCardTitle> Fleet </VCardTitle>
          <template #append>
            <IconBtn
              class="d-lg-none navigation-close-btn"
              @click="isLeftSidebarOpen = !isLeftSidebarOpen"
            >
              <VIcon icon="bx-x" />
            </IconBtn>
          </template>
        </VCardItem>
        <!-- 👉 Perfect Scrollbar -->
        <PerfectScrollbar
          :options="{ wheelPropagation: false, suppressScrollX: true }"
          style="block-size: calc(100% - 60px)"
        >
          <VCardText class="pt-0">
            <div
              v-for="(vehicle, index) in vehicleTrackingData"
              :key="index"
              class="mb-6"
            >
              <div
                class="d-flex align-center justify-space-between cursor-pointer"
                @click="
                  flyToLocation(
                    geojson.features[index].geometry.coordinates,
                    index,
                  )
                "
              >
                <div class="d-flex gap-x-4 align-center">
                  <VAvatar
                    icon="bx-car"
                    size="40"
                    variant="tonal"
                    color="secondary"
                  />
                  <div>
                    <div class="text-body-1 text-high-emphasis mb-1">
                      {{ vehicle.name }}
                    </div>
                    <div class="text-body-1">
                      {{ vehicle.location }}
                    </div>
                  </div>
                </div>
                <IconBtn size="small">
                  <VIcon
                    :icon="
                      showPanel[index]
                        ? 'bx-chevron-down'
                        : $vuetify.locale.isRtl
                          ? 'bx-chevron-left'
                          : 'bx-chevron-right'
                    "
                    class="text-high-emphasis"
                  />
                </IconBtn>
              </div>
              <VExpandTransition mode="out-in">
                <div v-show="showPanel[index]">
                  <div class="py-8">
                    <div class="d-flex justify-space-between mb-1">
                      <span class="text-body-1 text-high-emphasis">Delivery Process</span>
                      <span class="text-body-1">{{ vehicle.progress }}%</span>
                    </div>
                    <VProgressLinear
                      :model-value="vehicle.progress"
                      color="primary"
                      rounded
                      height="6"
                    />
                  </div>
                  <div>
                    <VTimeline
                      align="start"
                      truncate-line="both"
                      side="end"
                      density="compact"
                      line-thickness="1"
                      line-inset="6"
                      class="ps-2 v-timeline--variant-outlined fleet-timeline"
                    >
                      <VTimelineItem
                        icon="bx-check-circle"
                        dot-color="rgb(var(--v-theme-surface))"
                        icon-color="success"
                        fill-dot
                        size="20"
                        :elevation="0"
                      >
                        <div class="ps-1">
                          <div class="text-caption text-success">
                            TRACKING NUMBER CREATED
                          </div>
                          <div class="app-timeline-title">
                            {{ vehicle.driverName }}
                          </div>
                          <div class="text-body-2">
                            Sep 01, 7:53 AM
                          </div>
                        </div>
                      </VTimelineItem>
                      <VTimelineItem
                        icon="bx-check-circle"
                        dot-color="rgb(var(--v-theme-surface))"
                        icon-color="success"
                        fill-dot
                        size="20"
                        :elevation="0"
                      >
                        <div class="text-caption text-uppercase text-success">
                          OUT FOR DELIVERY
                        </div>
                        <div class="app-timeline-title">
                          Veronica Herman
                        </div>
                        <div class="text-body-2">
                          Sep 03, 8:02 AM
                        </div>
                      </VTimelineItem>
                      <VTimelineItem
                        icon="bx-map"
                        dot-color="rgb(var(--v-theme-surface))"
                        icon-color="primary"
                        fill-dot
                        size="20"
                        :elevation="0"
                      >
                        <div class="text-caption text-uppercase text-success">
                          ARRIVED
                        </div>
                        <div class="app-timeline-title">
                          Veronica Herman
                        </div>
                        <div class="text-body-2">
                          Sep 04, 8:18 AM
                        </div>
                      </VTimelineItem>
                    </VTimeline>
                  </div>
                </div>
              </VExpandTransition>
            </div>
          </VCardText>
        </PerfectScrollbar>
      </VCard>
    </VNavigationDrawer>
    <VMain>
      <div class="h-100">
        <IconBtn
          class="d-lg-none navigation-toggle-btn rounded-sm"
          variant="elevated"
          @click="isLeftSidebarOpen = true"
        >
          <VIcon icon="bx-menu" />
        </IconBtn>

        <!-- 👉 Fleet map  -->
        <div
          id="mapContainer"
          class="basemap"
        />

        <img
          v-for="(car, index) in carImgs"
          :key="index"
          ref="refCars"
          :src="car"
          alt="car Img marker"
          height="42"
          width="20"
        >
      </div>
    </VMain>
  </VLayout>
</template>

<style lang="scss">
@use "@styles/variables/vuetify";
@use "@core-scss/base/mixins";
@import "mapbox-gl/dist/mapbox-gl.css";

.fleet-app-layout {
  border-radius: vuetify.$card-border-radius;

  @include mixins.elevation(vuetify.$card-elevation);

  $sel-fleet-app-layout: &;

  @at-root {
    .skin--bordered {
      @include mixins.bordered-skin($sel-fleet-app-layout);
    }
  }
}

.navigation-toggle-btn {
  position: absolute;
  z-index: 1;
  inset-block-start: 1rem;
  inset-inline-start: 1rem;
}

.navigation-close-btn {
  position: absolute;
  z-index: 1;
  inset-block-start: 1rem;
  inset-inline-end: 1rem;
}

.basemap {
  block-size: 100%;
  inline-size: 100%;
}

.marker-focus {
  filter: drop-shadow(0 0 7px rgb(var(--v-theme-primary)));
}

.mapboxgl-ctrl-bottom-left,
.mapboxgl-ctrl-bottom-right {
  display: none;
}

.fleet-navigation-drawer {
  .v-timeline .v-timeline-divider__dot .v-timeline-divider__inner-dot {
    box-shadow: none;
  }
}

.fleet-timeline {
  &.v-timeline .v-timeline-item:not(:last-child) {
    .v-timeline-item__body {
      margin-block-end: 0.25rem;
    }
  }
}

/* stylelint-disable-next-line selector-id-pattern */
#mapContainer {
  block-size: 100vh !important;
}
</style>
