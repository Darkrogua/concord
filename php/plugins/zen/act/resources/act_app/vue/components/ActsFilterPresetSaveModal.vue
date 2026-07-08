<template>
  <EditFieldModal
    :open="open"
    title="Сохранить фильтры"
    title-id="act-filter-preset-save-title"
    @close="$emit('close')"
  >
    <label class="tg-field">
      <span class="tg-field__label">Название</span>
      <input
        ref="nameInputRef"
        v-model="name"
        class="tg-input"
        type="text"
        maxlength="40"
        placeholder="Например, Срочное"
        @keydown.enter.prevent="save"
      >
    </label>

    <div class="tg-field">
      <span class="tg-field__label">Цвет</span>
      <div class="tg-preset-colors" role="radiogroup" aria-label="Цвет пресета">
        <button
          v-for="item in colors"
          :key="item.slug"
          type="button"
          class="tg-preset-colors__item"
          :class="{ 'tg-preset-colors__item--selected': color === item.slug }"
          :style="{ '--preset-color': item.hex }"
          :aria-label="item.label"
          :aria-pressed="color === item.slug"
          @click="color = item.slug"
        >
          <span class="tg-preset-colors__dot" aria-hidden="true" />
        </button>
      </div>
    </div>

    <p v-if="error" class="tg-error tg-error--inline">{{ error }}</p>

    <button class="tg-btn tg-btn--block" type="button" @click="save">
      Сохранить
    </button>
  </EditFieldModal>
</template>

<script>
import { ref, watch } from 'vue'
import { DEFAULT_PRESET_COLOR, PRESET_COLORS } from '../composables/filter-preset-colors.js'
import EditFieldModal from './EditFieldModal.vue'

export default {
  name: 'ActsFilterPresetSaveModal',
  components: { EditFieldModal },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    externalError: {
      type: String,
      default: '',
    },
  },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const name = ref('')
    const color = ref(DEFAULT_PRESET_COLOR)
    const error = ref('')
    const nameInputRef = ref(null)
    const colors = PRESET_COLORS

    const reset = () => {
      name.value = ''
      color.value = DEFAULT_PRESET_COLOR
      error.value = ''
    }

    watch(() => props.open, (isOpen) => {
      if (isOpen) {
        reset()
        window.requestAnimationFrame(() => {
          nameInputRef.value?.focus()
        })
      }
    })

    watch(() => props.externalError, (value) => {
      if (value) {
        error.value = value
      }
    })

    const save = () => {
      error.value = ''
      const trimmed = name.value.trim()
      if (!trimmed) {
        error.value = 'Введите название'
        return
      }
      emit('save', { name: trimmed, color: color.value })
    }

    return {
      name,
      color,
      error,
      nameInputRef,
      colors,
      save,
    }
  },
}
</script>
