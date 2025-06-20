<script setup>
const emit = defineEmits(['close'])

const content = ref('')
const to = ref('')
const subject = ref('')
const message = ref('')
const cc = ref('')
const bcc = ref('')
const isEmailCc = ref(false)
const isEmailBcc = ref(false)

const resetValues = () => {
  to.value = subject.value = message.value = ''
}
</script>

<template>
  <VCard
    class="email-compose-dialog"
    max-width="30vw"
  >
    <VCardText
      class="py-3 px-6"
      style="
        background-color: rgba(
          var(--v-theme-on-surface),
          var(--v-hover-opacity)
        );
      "
    >
      <div class="d-flex align-center">
        <h5 class="text-h5 text-medium-emphasis">
          Compose Mail
        </h5>
        <VSpacer />

        <div class="d-flex align-center gap-x-2">
          <IconBtn
            size="small"
            icon="bx-minus"
            @click="$emit('close')"
          />
          <IconBtn
            size="small"
            icon="bx-x"
            @click="
              $emit('close');
              resetValues();
            "
          />
        </div>
      </div>
    </VCardText>

    <div class="px-1 pe-6 py-1">
      <VTextField
        v-model="to"
        density="compact"
      >
        <template #prepend-inner>
          <div class="text-base font-weight-medium text-disabled">
            To:
          </div>
        </template>
        <template #append>
          <span class="cursor-pointer text-body-1">
            <span @click="isEmailCc = !isEmailCc">Cc</span>
            <span> | </span>
            <span @click="isEmailBcc = !isEmailBcc">Bcc</span>
          </span>
        </template>
      </VTextField>
    </div>

    <VExpandTransition>
      <div v-if="isEmailCc">
        <VDivider />

        <div class="px-1 pe-6 py-1">
          <VTextField
            v-model="cc"
            density="compact"
          >
            <template #prepend-inner>
              <div class="text-disabled font-weight-medium">
                Cc:
              </div>
            </template>
          </VTextField>
        </div>
      </div>
    </VExpandTransition>

    <VExpandTransition>
      <div v-if="isEmailBcc">
        <VDivider />

        <div class="px-1 pe-6 py-1">
          <VTextField
            v-model="bcc"
            density="compact"
          >
            <template #prepend-inner>
              <div class="text-disabled font-weight-medium">
                Bcc:
              </div>
            </template>
          </VTextField>
        </div>
      </div>
    </VExpandTransition>

    <VDivider />
    <div class="px-1 pe-6 py-1">
      <VTextField
        v-model="subject"
        density="compact"
      >
        <template #prepend-inner>
          <div class="text-base font-weight-medium text-disabled">
            Subject:
          </div>
        </template>
      </VTextField>
    </div>

    <VDivider />

    <!-- 👉 Tiptap Editor  -->
    <TiptapEditor
      v-model="content"
      placeholder="Message"
    />

    <div class="d-flex align-center px-6 py-4">
      <VBtn
        color="primary"
        class="me-4"
        append-icon="bx-paper-plane"
        :disabled="to === '' ? true : false"
        @click="
          $emit('close');
          content = '';
          resetValues();
        "
      >
        send
      </VBtn>

      <IconBtn size="small">
        <VIcon icon="bx-paperclip" />
      </IconBtn>

      <VSpacer />

      <IconBtn
        size="small"
        class="me-2"
      >
        <VIcon icon="bx-dots-vertical-rounded" />
      </IconBtn>

      <IconBtn
        size="small"
        @click="
          $emit('close');
          resetValues();
          content = '';
        "
      >
        <VIcon icon="bx-trash" />
      </IconBtn>
    </div>
  </VCard>
</template>

<style lang="scss">
@use "@core-scss/base/mixins";

.v-card.email-compose-dialog {
  z-index: 910 !important;

  @include mixins.elevation(10);

  .v-field--prepended {
    padding-inline-start: 20px;
  }

  .v-field__prepend-inner {
    align-items: center;
    padding: 0;
  }

  .v-textarea .v-field {
    --v-field-padding-start: 20px;
  }

  .v-field__outline {
    display: none;
  }

  .v-input {
    .v-field__prepend-inner {
      display: flex;
      align-items: center;
      padding-block-start: 0;
    }
  }

  .app-text-field {
    .v-field__input {
      padding-block-start: 6px;
    }

    .v-field--focused {
      box-shadow: none !important;
    }
  }
}

.email-compose-dialog {
  .ProseMirror {
    p {
      margin-block-end: 0;
    }

    padding: 1.5rem;
    block-size: 100px;
    overflow-y: auto;
    padding-block: 0.5rem;

    &:focus-visible {
      outline: none;
    }

    p.is-editor-empty:first-child::before {
      block-size: 0;
      color: #adb5bd;
      content: attr(data-placeholder);
      float: inline-start;
      pointer-events: none;
    }

    ul,
    ol {
      padding-inline: 1.125rem;
    }

    &-focused {
      outline: none;
    }
  }
}
</style>
