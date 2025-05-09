export const accept = { ts: `<template>
  <AppFileInput
    accept="image/*"
    label="File input"
  />
</template>
`, js: `<template>
  <AppFileInput
    accept="image/*"
    label="File input"
  />
</template>
` }

export const basic = { ts: `<template>
  <AppFileInput label="File input" />
</template>
`, js: `<template>
  <AppFileInput label="File input" />
</template>
` }

export const chips = { ts: `<template>
  <AppFileInput
    chips
    label="File input w/ chips"
  />
</template>
`, js: `<template>
  <AppFileInput
    chips
    label="File input w/ chips"
  />
</template>
` }

export const counter = { ts: `<template>
  <AppFileInput
    show-size
    counter
    multiple
    label="File input"
  />
</template>
`, js: `<template>
  <AppFileInput
    show-size
    counter
    multiple
    label="File input"
  />
</template>
` }

export const density = { ts: `<template>
  <AppFileInput
    label="File input"
    density="compact"
  />
</template>
`, js: `<template>
  <AppFileInput
    label="File input"
    density="compact"
  />
</template>
` }

export const loading = { ts: `<script setup lang="ts">
const file = ref()
const loading = ref(true)

watch(file, () => {
  loading.value = !file.value[0]
})
</script>

<template>
  <AppFileInput
    v-model="file"
    :loading="loading"
    color="primary"
    label="File input"
    variant="outlined"
  />
</template>
`, js: `<script setup>
const file = ref()
const loading = ref(true)

watch(file, () => {
  loading.value = !file.value[0]
})
</script>

<template>
  <AppFileInput
    v-model="file"
    :loading="loading"
    color="primary"
    label="File input"
    variant="outlined"
  />
</template>
` }

export const multiple = { ts: `<template>
  <AppFileInput
    multiple
    label="File input"
  />
</template>
`, js: `<template>
  <AppFileInput
    multiple
    label="File input"
  />
</template>
` }

export const prependIcon = { ts: `<template>
  <AppFileInput
    label="File input"
    prepend-icon="bx-camera"
  />
</template>
`, js: `<template>
  <AppFileInput
    label="File input"
    prepend-icon="bx-camera"
  />
</template>
` }

export const selectionSlot = { ts: `<script lang="ts" setup>
const files = ref<File[]>([])
</script>

<template>
  <AppFileInput
    v-model="files"
    multiple
    placeholder="Upload your documents"
    label="File input"
    prepend-inner-icon="bx-paperclip"
  >
    <template #selection="{ fileNames }">
      <template
        v-for="fileName in fileNames"
        :key="fileName"
      >
        <VChip
          label
          size="small"
          color="primary"
          class="me-2"
        >
          {{ fileName }}
        </VChip>
      </template>
    </template>
  </AppFileInput>
</template>
`, js: `<script setup>
const files = ref([])
</script>

<template>
  <AppFileInput
    v-model="files"
    multiple
    placeholder="Upload your documents"
    label="File input"
    prepend-inner-icon="bx-paperclip"
  >
    <template #selection="{ fileNames }">
      <template
        v-for="fileName in fileNames"
        :key="fileName"
      >
        <VChip
          label
          size="small"
          color="primary"
          class="me-2"
        >
          {{ fileName }}
        </VChip>
      </template>
    </template>
  </AppFileInput>
</template>
` }

export const showSize = { ts: `<template>
  <AppFileInput
    show-size
    label="File input"
  />
</template>
`, js: `<template>
  <AppFileInput
    show-size
    label="File input"
  />
</template>
` }

export const validation = { ts: `<script lang="ts" setup>
const rules = [
  (fileList: FileList) => !fileList || !fileList.length || fileList[0].size < 1000000 || 'Avatar size should be less than 1 MB!',
]
</script>

<template>
  <AppFileInput
    :rules="rules"
    label="Avatar"
    accept="image/png, image/jpeg, image/bmp"
    placeholder="Pick an avatar"
    prepend-inner-icon="bx-camera"
    variant="outlined"
  />
</template>
`, js: `<script setup>
const rules = [fileList => !fileList || !fileList.length || fileList[0].size < 1000000 || 'Avatar size should be less than 1 MB!']
</script>

<template>
  <AppFileInput
    :rules="rules"
    label="Avatar"
    accept="image/png, image/jpeg, image/bmp"
    placeholder="Pick an avatar"
    prepend-inner-icon="bx-camera"
    variant="outlined"
  />
</template>
` }

export const variant = { ts: `<template>
  <VRow>
    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput label="Outlined" />
    </VCol>

    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput
        label="Filled"
        variant="filled"
      />
    </VCol>

    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput
        label="Solo"
        variant="solo"
      />
    </VCol>

    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput
        label="Plain"
        variant="plain"
      />
    </VCol>
    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput
        label="Underlined"
        variant="underlined"
        density="default"
      />
    </VCol>
  </VRow>
</template>
`, js: `<template>
  <VRow>
    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput label="Outlined" />
    </VCol>

    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput
        label="Filled"
        variant="filled"
      />
    </VCol>

    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput
        label="Solo"
        variant="solo"
      />
    </VCol>

    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput
        label="Plain"
        variant="plain"
      />
    </VCol>
    <VCol
      cols="12"
      sm="6"
    >
      <VFileInput
        label="Underlined"
        variant="underlined"
        density="default"
      />
    </VCol>
  </VRow>
</template>
` }

