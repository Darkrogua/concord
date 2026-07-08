<template>
  <div class="tg-block-card__title-wrap">
    <div class="tg-block-card__title-row">
      <p class="tg-block-card__type">{{ typeLabel }}</p>
      <div v-if="nameEditing" class="tg-block-title-edit" @click.stop>
        <input
          ref="nameInputRef"
          v-model="nameDraft"
          class="tg-block-title-edit__input"
          type="text"
          maxlength="255"
          :aria-label="`Название: ${typeLabel}`"
          :disabled="savingName"
          @keydown.enter.prevent="saveName"
          @keydown.escape.prevent="cancelNameEdit"
        >
        <button
          type="button"
          class="tg-block-title-edit__save"
          aria-label="Сохранить название"
          :disabled="savingName"
          @click.stop="saveName"
        >
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M20 6L9 17l-5-5"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>
      </div>
      <h2
        v-else
        :class="['tg-block-card__title', { 'tg-block-card__title--editable': editable }]"
        @click="startNameEdit"
      >
        {{ displayName }}
      </h2>
    </div>
    <p v-if="nameError" class="tg-error tg-error--inline">{{ nameError }}</p>
  </div>
</template>

<script>
import { computed, nextTick, ref, watch } from 'vue'

export default {
  name: 'BlockTitleEdit',
  props: {
    name: {
      type: String,
      default: '',
    },
    typeLabel: {
      type: String,
      required: true,
    },
    fallback: {
      type: String,
      required: true,
    },
    editable: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['save-name'],
  setup(props, { emit }) {
    const nameInputRef = ref(null)
    const nameEditing = ref(false)
    const nameDraft = ref('')
    const savingName = ref(false)
    const nameError = ref('')

    const displayName = computed(() => props.name?.trim() || props.fallback)

    const cancelNameEdit = () => {
      nameEditing.value = false
      nameDraft.value = displayName.value
      nameError.value = ''
    }

    const startNameEdit = () => {
      if (!props.editable || nameEditing.value || savingName.value) {
        return
      }

      nameDraft.value = displayName.value
      nameError.value = ''
      nameEditing.value = true
      nextTick(() => {
        nameInputRef.value?.focus()
        nameInputRef.value?.select()
      })
    }

    const saveName = () => {
      if (!props.editable || savingName.value) {
        return
      }

      const nextName = nameDraft.value.trim()
      if (!nextName) {
        nameError.value = 'Введите название'
        return
      }

      if (nextName === displayName.value) {
        cancelNameEdit()
        return
      }

      savingName.value = true
      nameError.value = ''
      emit('save-name', {
        name: nextName,
        done: () => {
          savingName.value = false
          nameEditing.value = false
        },
        fail: (message) => {
          savingName.value = false
          nameError.value = message || 'Не удалось сохранить название'
        },
      })
    }

    watch(
      () => props.name,
      () => {
        if (!nameEditing.value) {
          nameDraft.value = displayName.value
        }
      }
    )

    return {
      nameInputRef,
      nameEditing,
      nameDraft,
      savingName,
      nameError,
      displayName,
      startNameEdit,
      cancelNameEdit,
      saveName,
    }
  },
}
</script>
