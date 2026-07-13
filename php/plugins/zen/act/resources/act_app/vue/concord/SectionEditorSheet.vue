<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <div class="concord-sheet concord-section-sheet" role="dialog" aria-label="Настройки раздела">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 class="concord-sheet__title">{{ isEdit ? 'Контейнер' : 'Новый контейнер' }}</h2>

      <label class="concord-section-sheet__field">
        <span class="concord-section-sheet__label">название контейнера</span>
        <input
          v-model="title"
          type="text"
          class="concord-section-sheet__input"
          placeholder="Введите название"
        >
      </label>

      <div class="concord-section-sheet__field">
        <span class="concord-section-sheet__label">участники</span>
        <div class="concord-section-sheet__chips">
          <button
            v-for="contact in contacts"
            :key="contact.id"
            type="button"
            class="concord-section-sheet__chip"
            :class="{ 'concord-section-sheet__chip--active': selectedParticipantIds.includes(contact.id) }"
            @click="toggleParticipant(contact.id)"
          >
            {{ contact.shortName }}
          </button>
        </div>
      </div>

      <div class="concord-section-sheet__field">
        <span class="concord-section-sheet__label">группы</span>
        <div class="concord-section-sheet__chips">
          <button
            v-for="group in groups"
            :key="group.id"
            type="button"
            class="concord-section-sheet__chip"
            :class="{ 'concord-section-sheet__chip--active': selectedGroupIds.includes(group.id) }"
            @click="toggleGroup(group.id)"
          >
            {{ group.title }}
          </button>
        </div>
      </div>

      <div class="concord-create-sheet__actions">
        <button type="button" class="concord-create-sheet__btn concord-create-sheet__btn--cancel" @click="$emit('close')">
          Отмена
        </button>
        <button
          type="button"
          class="concord-create-sheet__btn concord-create-sheet__btn--save"
          :disabled="!title.trim()"
          @click="save"
        >
          {{ isEdit ? 'Сохранить' : 'Создать' }}
        </button>
      </div>
    </div>
  </template>
</template>

<script>
import { ref, watch } from 'vue'

export default {
  name: 'SectionEditorSheet',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    isEdit: {
      type: Boolean,
      default: false,
    },
    initialTitle: {
      type: String,
      default: '',
    },
    initialParticipantIds: {
      type: Array,
      default: () => [],
    },
    initialGroupIds: {
      type: Array,
      default: () => [],
    },
    contacts: {
      type: Array,
      default: () => [],
    },
    groups: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const title = ref('')
    const selectedParticipantIds = ref([])
    const selectedGroupIds = ref([])

    watch(
      () => props.open,
      (isOpen) => {
        if (!isOpen) {
          return
        }
        title.value = props.initialTitle
        selectedParticipantIds.value = [...props.initialParticipantIds]
        selectedGroupIds.value = [...props.initialGroupIds]
      }
    )

    function toggleParticipant(id) {
      if (selectedParticipantIds.value.includes(id)) {
        selectedParticipantIds.value = selectedParticipantIds.value.filter((item) => item !== id)
        return
      }
      selectedParticipantIds.value = [...selectedParticipantIds.value, id]
    }

    function toggleGroup(id) {
      if (selectedGroupIds.value.includes(id)) {
        selectedGroupIds.value = selectedGroupIds.value.filter((item) => item !== id)
        return
      }
      selectedGroupIds.value = [...selectedGroupIds.value, id]
    }

    function save() {
      if (!title.value.trim()) {
        return
      }
      emit('save', {
        title: title.value.trim(),
        participantIds: [...selectedParticipantIds.value],
        groupIds: [...selectedGroupIds.value],
      })
      emit('close')
    }

    return {
      title,
      selectedParticipantIds,
      selectedGroupIds,
      toggleParticipant,
      toggleGroup,
      save,
    }
  },
}
</script>
