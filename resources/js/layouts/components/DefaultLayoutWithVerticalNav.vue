<script setup>
import navItems from '@/navigation/vertical'
import { themeConfig } from '@themeConfig'

// Components
import DomainSetupPopup from '@/components/DomainSetupPopup.vue'
import NavRefresher from '@/components/NavRefresher.vue'
import SuspendedUserModal from '@/components/SuspendedUserModal.vue'
import Footer from '@/layouts/components/Footer.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavSearchBar from '@/layouts/components/NavSearchBar.vue'
import NavbarShortcuts from '@/layouts/components/NavbarShortcuts.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'

// @layouts plugin
import { useConfigStore } from '@/@core/stores/config'
import { useAuthStore } from '@/stores/useAuthStore'
import { VerticalNavLayout } from '@layouts'
import { computed, nextTick, onMounted, ref, watch } from 'vue'

const configStore = useConfigStore()
const authStore = useAuthStore()

// Navigation state management
const navRefreshKey = ref(0)
const isNavLoading = ref(true)
const navError = ref(null)
const authInitialized = ref(false)

// Computed navigation items that wait for auth initialization
const computedNavItems = computed(() => {
  // Don't render navigation items until auth is initialized
  if (!authStore.sessionInitialized || isNavLoading.value) {
    return []
  }

  try {
    // Filter navigation items based on current auth state
    return navItems.filter(item => {
      // If item has conditionalVisible function, evaluate it
      if (typeof item.conditionalVisible === 'function') {
        return item.conditionalVisible()
      }
      // Default to showing the item if no condition specified
      return true
    })
  } catch (error) {
    console.error('Error filtering navigation items:', error)
    navError.value = error
    return []
  }
})

// Initialize navigation after auth is ready
const initializeNavigation = async () => {
  try {
    isNavLoading.value = true
    navError.value = null
    
    // Wait for auth store to be fully initialized
    if (!authStore.sessionInitialized) {
      console.log('Waiting for auth initialization before rendering navigation...')
      return
    }

    console.log('Auth initialized, setting up navigation for user role:', authStore.user?.role)
    
    // Allow Vue to process auth state changes
    await nextTick()
    
    // Mark auth as initialized for navigation
    authInitialized.value = true
    
    // Refresh navigation key to trigger re-render
    navRefreshKey.value++
    
    console.log('Navigation initialized successfully')
  } catch (error) {
    console.error('Error initializing navigation:', error)
    navError.value = error
  } finally {
    isNavLoading.value = false
  }
}

// Watch for auth store initialization
watch(() => authStore.sessionInitialized, async (newVal) => {
  if (newVal) {
    console.log('Auth session initialized, initializing navigation...')
    await initializeNavigation()
  }
}, { immediate: true })

// Watch for user role changes
watch(() => authStore.user?.role, async (newRole, oldRole) => {
  if (newRole !== oldRole && authStore.sessionInitialized) {
    console.log('User role changed from', oldRole, 'to', newRole, '- refreshing navigation')
    await initializeNavigation()
  }
})

// Watch for authentication state changes
watch(() => authStore.isAuthenticated, async (newVal, oldVal) => {
  if (newVal !== oldVal && authStore.sessionInitialized) {
    console.log('Authentication state changed to', newVal, '- refreshing navigation')
    await initializeNavigation()
  }
})

// Initialize on mount
onMounted(async () => {
  console.log('DefaultLayoutWithVerticalNav mounted')
  
  // If auth is already initialized, set up navigation
  if (authStore.sessionInitialized) {
    await initializeNavigation()
  } else {
    console.log('Auth not yet initialized, waiting...')
  }
})

// ℹ️ Provide animation name for vertical nav collapse icon.
const verticalNavHeaderActionAnimationName = ref(null)

watch([
  () => configStore.isVerticalNavCollapsed,
  () => configStore.isAppRTL,
], val => {
  if (configStore.isAppRTL)
    verticalNavHeaderActionAnimationName.value = val[0] ? 'rotate-back-180' : 'rotate-180'
  else
    verticalNavHeaderActionAnimationName.value = val[0] ? 'rotate-180' : 'rotate-back-180'
}, { immediate: true })

const actionArrowInitialRotation = configStore.isVerticalNavCollapsed ? '180deg' : '0deg'
</script>

<template>
  <VerticalNavLayout :nav-items="computedNavItems" :key="navRefreshKey">
    <!-- Add NavRefresher to update navigation when auth state changes -->
    <NavRefresher />
    
    <!-- Navigation Loading State -->
    <template v-if="isNavLoading && !authStore.sessionInitialized" #nav-header>
      <div class="nav-loading-container d-flex align-center justify-center pa-4">
        <VProgressCircular
          indeterminate
          color="primary"
          size="24"
          width="3"
        />
        <span class="text-sm ml-2">Loading navigation...</span>
      </div>
    </template>

    <!-- Navigation Error State -->
    <template v-else-if="navError" #nav-header>
      <div class="nav-error-container pa-4">
        <VAlert
          type="error"
          variant="tonal"
          density="compact"
          closable
          @click:close="navError = null"
        >
          <VAlertTitle>Navigation Error</VAlertTitle>
          <div class="text-caption">
            Failed to load navigation. Please refresh the page.
          </div>
        </VAlert>
      </div>
    </template>
    
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="bx-menu"
          />
        </IconBtn>

        <NavSearchBar class="ms-lg-n3" />

        <VSpacer />

        <NavBarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />
        <NavbarThemeSwitcher />
        <NavbarShortcuts />
        <NavBarNotifications class="me-1" />
        
        <!-- Show loading indicator in navbar if navigation is still loading -->
        <div v-if="isNavLoading" class="me-2">
          <VProgressCircular
            indeterminate
            color="primary"
            size="16"
            width="2"
          />
        </div>
        
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <TheCustomizer />
    
    <!-- 👉 Suspended User Modal -->
    <SuspendedUserModal />

    <!-- 👉 Domain Setup Popup -->
    <DomainSetupPopup />
  </VerticalNavLayout>
</template>

<style lang="scss">
@use "@layouts/styles/mixins" as layoutsMixins;

// Navigation loading and error styles
.nav-loading-container {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-surface), 1);
}

.nav-error-container {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.layout-vertical-nav {
  // ℹ️ Nav header circle on the right edge
  .nav-header {
    position: relative;
    overflow: visible !important;

    &::after {
      --diameter: 36px;

      position: absolute;
      z-index: -1;
      border: 7px solid rgba(var(--v-theme-background), 1);
      border-radius: 100%;
      aspect-ratio: 1;
      background: rgba(var(--v-theme-surface), 1);
      content: "";
      inline-size: var(--diameter);
      inset-block-start: calc(50% - var(--diameter) / 2);
      inset-inline-end: -18px;

      @at-root {
        // Change background color of nav header circle when vertical nav is in overlay mode
        .layout-overlay-nav {
          --app-header-container-bg: rgb(var(--v-theme-surface));

          // ℹ️ Only transition in overlay mode
          .nav-header::after {
            transition: opacity 0.2s ease-in-out;
          }
        }

        .layout-vertical-nav-collapsed .layout-vertical-nav:not(.hovered) {
          .nav-header::after,
          .nav-header .header-action {
            opacity: 0;
          }
        }
      }
    }
  }

  // Don't show nav header circle when vertical nav is in overlay mode and not visible
  &.overlay-nav:not(.visible) .nav-header::after {
    opacity: 0;
  }
}

// ℹ️ Nav header action buttons styles
@keyframes rotate-180 {
  from {
    transform: rotate(0deg) scaleX(var(--app-header-actions-scale-x));
  }

  to {
    transform: rotate(180deg) scaleX(var(--app-header-actions-scale-x));
  }
}

@keyframes rotate-back-180 {
  from {
    transform: rotate(180deg) scaleX(var(--app-header-actions-scale-x));
  }

  to {
    transform: rotate(0deg) scaleX(var(--app-header-actions-scale-x));
  }
}

/* stylelint-disable-next-line no-duplicate-selectors */
.layout-vertical-nav {
  /* stylelint-disable-next-line no-duplicate-selectors */
  .nav-header {
    .header-action {
      // ℹ️ We need to create this CSS variable for reusing value in animation
      --app-header-actions-scale-x: 1;

      position: absolute;
      border-radius: 100%;
      animation-duration: 0.35s;
      animation-fill-mode: forwards;
      animation-name: v-bind(verticalNavHeaderActionAnimationName);
      color: white;
      inset-inline-end: 0;
      inset-inline-end: -11px;
      /* stylelint-disable-next-line value-keyword-case */
      transform: rotate(v-bind(actionArrowInitialRotation)) scaleX(var(--app-header-actions-scale-x));
      transition: opacity 0.2s ease-in-out;

      @include layoutsMixins.rtl {
        --app-header-actions-scale-x: -1;
      }

      @at-root {
        .layout-nav-type-vertical.layout-overlay-nav .layout-vertical-nav:not(.visible) .nav-header .header-action {
          opacity: 0;
        }
      }
    }
  }
}
</style>
