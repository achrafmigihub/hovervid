<script setup>
import appleImg from '@images/front-pages/landing-page/apple-icon.png'
import googlePlayImg from '@images/front-pages/landing-page/google-play-icon.png'
import footerDarkBg from '@images/front-pages/backgrounds/footer-bg-dark.png'
import footerLightBg from '@images/front-pages/backgrounds/footer-bg-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

const footerBg = useGenerateImageVariant(footerLightBg, footerDarkBg)

const pagesList = [
  {
    name: 'Pricing',
    to: { name: 'front-pages-pricing' },
  },
  {
    name: 'Payment',
    to: { name: 'front-pages-payment' },
    isNew: true,
  },
  {
    name: 'Maintenance',
    to: { name: 'pages-misc-under-maintenance' },
  },
  {
    name: 'Coming Soon',
    to: { name: 'pages-misc-coming-soon' },
  },
]

const productList = [
  {
    title: 'Page Builder',
    to: 'https://themeselection.com/',
  },
  {
    title: 'Admin Dashboards',
    to: 'https://themeselection.com/item/category/admin-templates/',
  },
  {
    title: 'UI Kits',
    to: 'https://themeselection.com/item/category/ui-kits/',
  },
  {
    title: 'Illustration',
    to: 'https://themeselection.com/',
  },
]
</script>

<template>
  <div class="footer">
    <div
      class="footer-top pt-11 pb-6"
      theme="dark"
      :style="{ 'background-image': `url(${footerBg})`, 'background-color': '#14181C' }"
    >
      <VContainer>
        <VRow>
          <!-- 👉 Footer  -->
          <VCol
            cols="12"
            md="5"
          >
            <div
              class="mb-4"
              :class="$vuetify.display.smAndDown ? 'w-100' : 'w-75'"
            >
              <div class="app-logo mb-6">
                <VNodeRenderer :nodes="themeConfig.app.logo" />
                <h1 class="app-logo-title text-white">
                  {{ themeConfig.app.title }}
                </h1>
              </div>

              <div class="mb-6 text-white-variant">
                Most developer friendly & highly customisable Admin Dashboard Template.
              </div>
              <VForm class="subscribe-form d-flex align-center">
                <AppTextField
                  label="Subscribe to newsletter"
                  placeholder="john@email.com"
                />
                <VBtn class="align-self-end rounded-s-0">
                  Subscribe
                </VBtn>
              </VForm>
            </div>
          </VCol>

          <!-- 👉 Pages  -->
          <VCol
            md="2"
            sm="4"
            xs="6"
          >
            <div class="footer-links">
              <h6 class="footer-title text-h6 mb-6">
                Pages
              </h6>
              <ul style="list-style: none;">
                <li
                  v-for="(item, index) in pagesList"
                  :key="index"
                  class="mb-4"
                >
                  <RouterLink
                    class="me-2 text-white-variant"
                    :to="item.to"
                  >
                    {{ item.name }}
                  </RouterLink>
                  <template v-if="item.isNew">
                    <VChip
                      color="primary"
                      variant="elevated"
                      label
                      size="small"
                    >
                      New
                    </VChip>
                  </template>
                </li>
              </ul>
            </div>
          </VCol>

          <!-- 👉 Products -->
          <VCol
            md="2"
            sm="4"
            xs="6"
          >
            <div class="footer-links">
              <h6 class="footer-title text-h6 mb-6">
                Products
              </h6>
              <ul style="list-style: none;">
                <li
                  v-for="(item, index) in productList"
                  :key="index"
                  class="mb-4 text-body-1"
                >
                  <a
                    :href="item.to"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-white-variant"
                  >
                    {{ item.title }}
                  </a>
                </li>
              </ul>
            </div>
          </VCol>

          <!-- 👉 Download App -->
          <VCol
            cols="12"
            md="3"
            sm="4"
          >
            <div>
              <h6 class="footer-title text-h6 mb-6">
                Download our app
              </h6>

              <div>
                <VBtn
                  v-for="(item, index) in [
                    { image: appleImg, store: 'App Store' },
                    { image: googlePlayImg, store: 'Google Play' },
                  ]"
                  :key="index"
                  color="#282C3E"
                  height="56"
                  class="mb-4 d-block"
                >
                  <template #default>
                    <div class="d-flex align-center gap-x-8 footer-logo-buttons">
                      <VImg
                        :src="item.image"
                        height="34"
                        width="34"
                      />
                      <div class="d-flex flex-column justify-content-start">
                        <div class="text-white-variant text-body-2">
                          Download on the
                        </div>
                        <h6 class="text-h6 text-start footer-title">
                          {{ item.store }}
                        </h6>
                      </div>
                    </div>
                  </template>
                </VBtn>
              </div>
            </div>
          </VCol>
        </VRow>
      </VContainer>
    </div>

    <!-- 👉 Footer Line -->
    <div class="footer-line w-100">
      <VContainer>
        <div class="d-flex justify-space-between flex-wrap gap-y-5 align-center">
          <div
            class="text-wrap me-4 text-body-1"
            style="color: #d3d4dc;"
          >
            &copy;

            {{ new Date().getFullYear() }}
            <a
              href="https://themeselection.com/"
              target="_blank"
              rel="noopener noreferrer"
              class="text-white"
            >Themeselection</a>,
            Made With ❤️ for a better web.
          </div>

          <div class="d-flex gap-x-4 text-body-1">
            <template
              v-for="(item, index) in [
                { title: 'github', icon: 'bx-bxl-github', href: 'https://github.com/themeselection' },
                { title: 'facebook', icon: 'bx-bxl-facebook', href: 'https://www.facebook.com/ThemeSelections/' },
                { title: 'twitter', icon: 'bx-bxl-twitter', href: 'https://x.com/Theme_Selection' },
                { title: 'google', icon: 'bx-bxl-google', href: 'https://www.themeselection.com' },
              ]"
              :key="index"
            >
              <IconBtn
                :href="item.href"
                size="28"
                target="_blank"
                color="#fff"
                rel="noopener noreferrer"
              >
                <VIcon
                  :icon="item.icon"
                  size="20"
                  color="white"
                />
              </IconBtn>
            </template>
          </div>
        </div>
      </VContainer>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.footer-title {
  color: rgba(255, 255, 255, 92%);
}

.footer-top {
  border-radius: 60px 60px 0 0;
  background-size: cover;
}

.footer-line {
  background: #282c3e;
}

.footer-links {
  .text-white-variant:hover {
    color: #fff;
  }
}
</style>

<style lang="scss">
.subscribe-form {
  .v-label {
    color: rgba(230, 230, 241, 90%) !important;
  }

  .v-field {
    border-end-end-radius: 0;
    border-end-start-radius: 6px;
    border-start-end-radius: 0;
    border-start-start-radius: 6px;
    color: rgb(230, 230, 241);

    input.v-field__input::placeholder {
      color: rgba(230, 230, 241, var(--v-disabled-opacity)) !important;
    }

    input.v-field__input {
      color: rgba(230, 230, 241, 78%);
    }
  }
}

.footer {
  border-radius: 50%;

  @media (min-width: 600px) and (max-width: 960px) {
    .v-container {
      padding-inline: 2rem !important;
    }

    .footer-logo-buttons {
      gap: 0.75rem;
    }
  }
}
</style>
