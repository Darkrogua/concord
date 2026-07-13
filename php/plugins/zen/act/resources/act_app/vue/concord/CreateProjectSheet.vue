<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <div class="concord-sheet concord-create-sheet" role="dialog" aria-label="Создать">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 class="concord-sheet__title">Создать</h2>

      <ul class="concord-create-sheet__list" role="radiogroup" aria-label="Тип проекта">
        <li v-for="option in options" :key="option.id">
          <button
            type="button"
            class="concord-create-sheet__item"
            role="radio"
            :aria-checked="selectedId === option.id"
            @click="selectedId = option.id"
          >
            <span class="concord-create-sheet__avatar" aria-hidden="true">{{ avatarInitial }}</span>
            <span class="concord-create-sheet__label">{{ option.label }}</span>
            <span
              class="concord-sheet__radio"
              :class="{ 'concord-sheet__radio--active': selectedId === option.id }"
              aria-hidden="true"
            >
              <span v-if="selectedId === option.id" class="concord-sheet__radio-dot" />
            </span>
          </button>
        </li>
      </ul>

      <div class="concord-create-sheet__actions">
        <button type="button" class="concord-create-sheet__btn concord-create-sheet__btn--cancel" @click="$emit('close')">
          Отмена
        </button>
        <button type="button" class="concord-create-sheet__btn concord-create-sheet__btn--save" @click="onSave">
          Создать
        </button>
      </div>
    </div>
  </template>
</template>

<script>
import { ref, watch } from 'vue'

export const CREATE_PROJECT_OPTIONS = [
  { id: 'approval', label: 'Согласование' },
  { id: 'voting', label: 'Голосование' },
]

export default {
  name: 'CreateProjectSheet',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    avatarInitial: {
      type: String,
      default: 'А',
    },
  },
  emits: ['close', 'select'],
  setup(props, { emit }) {
    const options = CREATE_PROJECT_OPTIONS
    const selectedId = ref('approval')

    watch(
      () => props.open,
      (isOpen) => {
        if (isOpen) {
          selectedId.value = 'approval'
        }
      }
    )

    function onSave() {
      emit('select', selectedId.value)
      emit('close')
    }

    return { options, selectedId, onSave }
  },
}
</script>
